<?php
/**
 * Create Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$ujian = $ujian_id ? get_ujian($ujian_id) : null;

if ($ujian && $ujian['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/ujian/list.php');
}

$error = '';
$success = '';

// Handle POST first (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ujian = intval($_POST['id_ujian'] ?? 0);
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $tipe_soal = sanitize($_POST['tipe_soal'] ?? '');
    $bobot = floatval($_POST['bobot'] ?? 1.0);
    $media_path = sanitize($_POST['media_path'] ?? '');
    $media_type = sanitize($_POST['media_type'] ?? '');
    
    if (empty($pertanyaan) || !$id_ujian || !$tipe_soal) {
        $error = 'Pertanyaan, ujian, dan tipe soal harus diisi';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get max urutan
            $stmt = $pdo->prepare("SELECT MAX(urutan) as max_urutan FROM soal WHERE id_ujian = ?");
            $stmt->execute([$id_ujian]);
            $max = $stmt->fetch();
            $urutan = ($max['max_urutan'] ?? 0) + 1;
            
            // Prepare opsi_json and kunci_jawaban based on tipe
            // IMPORTANT: Get kunci_jawaban based on tipe_soal to avoid conflict with multiple fields with same name
            $opsi_json = null;
            $kunci_jawaban = '';
            
            if ($tipe_soal === 'pilihan_ganda') {
                // Build options with support for images
                $opsi = [];
                $option_keys = ['A', 'B', 'C', 'D'];
                
                foreach ($option_keys as $key) {
                    $text = sanitize($_POST['opsi_' . strtolower($key)] ?? '');
                    $image_path = sanitize($_POST['opsi_' . strtolower($key) . '_image_path'] ?? '');
                    
                    // Only add option if text or image is provided
                    if (!empty($text) || !empty($image_path)) {
                        if (!empty($image_path)) {
                            // New format: object with text and image
                            $opsi[$key] = [
                                'text' => $text,
                                'image' => $image_path
                            ];
                        } else {
                            // Old format: just text (backward compatible)
                            $opsi[$key] = $text;
                        }
                    }
                }
                
                // Remove empty options
                $opsi = array_filter($opsi, function($value) {
                    if (is_array($value)) {
                        return !empty($value['text']) || !empty($value['image']);
                    }
                    return !empty($value);
                });
                
                // Validate: minimal 2 opsi harus diisi
                if (count($opsi) < 2) {
                    throw new Exception('Pilihan ganda minimal harus memiliki 2 opsi yang diisi');
                }
                
                // Get kunci_jawaban from specific field name for pilihan_ganda
                $kunci_jawaban = sanitize($_POST['kunci_jawaban_pg'] ?? $_POST['kunci_jawaban'] ?? '');
                
                // Validate: kunci_jawaban harus ada di opsi yang diisi
                if (empty($kunci_jawaban)) {
                    throw new Exception('Kunci jawaban untuk pilihan ganda harus dipilih');
                }
                if (!isset($opsi[$kunci_jawaban])) {
                    throw new Exception('Kunci jawaban "' . $kunci_jawaban . '" harus sesuai dengan opsi yang diisi. Opsi yang tersedia: ' . implode(', ', array_keys($opsi)));
                }
                
                // Safe JSON encoding with error handling
                $opsi_json = json_encode($opsi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($opsi_json === false) {
                    throw new Exception('Gagal mengencode opsi jawaban. Pastikan tidak ada karakter khusus yang tidak valid.');
                }
            } elseif ($tipe_soal === 'benar_salah') {
                $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($opsi_json === false) {
                    throw new Exception('Gagal mengencode opsi benar/salah.');
                }
                // Get kunci_jawaban from specific field name for benar_salah
                // Check both the specific field and the hidden field (for backward compatibility)
                $kunci_jawaban_bs = isset($_POST['kunci_jawaban_bs']) ? trim($_POST['kunci_jawaban_bs']) : '';
                $kunci_jawaban_hidden = isset($_POST['kunci_jawaban']) ? trim($_POST['kunci_jawaban']) : '';
                $kunci_jawaban_raw = !empty($kunci_jawaban_bs) ? $kunci_jawaban_bs : $kunci_jawaban_hidden;
                $kunci_jawaban = sanitize($kunci_jawaban_raw);
                $kunci_jawaban = trim($kunci_jawaban);
                
                // Debug: Log received values
                error_log("Create soal benar_salah - POST data: " . json_encode($_POST));
                error_log("Create soal benar_salah - kunci_jawaban_bs raw: " . var_export($kunci_jawaban_bs, true));
                error_log("Create soal benar_salah - kunci_jawaban hidden: " . var_export($kunci_jawaban_hidden, true));
                error_log("Create soal benar_salah - kunci_jawaban (final): " . var_export($kunci_jawaban, true));
                
                // Validate kunci_jawaban for benar_salah - must be exactly 'Benar' or 'Salah'
                if ($kunci_jawaban === '' || $kunci_jawaban === false || $kunci_jawaban === null) {
                    throw new Exception('Kunci jawaban untuk benar/salah harus dipilih. Field yang diterima: kunci_jawaban_bs=' . var_export($kunci_jawaban_bs, true) . ', kunci_jawaban=' . var_export($kunci_jawaban_hidden, true));
                }
                if (!in_array($kunci_jawaban, ['Benar', 'Salah'])) {
                    throw new Exception('Kunci jawaban untuk benar/salah harus Benar atau Salah. Nilai yang diterima: ' . var_export($kunci_jawaban, true));
                }
            } elseif ($tipe_soal === 'isian_singkat') {
                // For isian_singkat, kunci_jawaban can be multiple values separated by comma
                // Get kunci_jawaban from specific field name for isian_singkat
                $kunci_jawaban_raw = $_POST['kunci_jawaban_is'] ?? $_POST['kunci_jawaban'] ?? '';
                // Clean and trim each value
                if (!empty($kunci_jawaban_raw)) {
                    $answers = array_map('trim', explode(',', $kunci_jawaban_raw));
                    $answers = array_filter($answers, function($value) {
                        return !empty($value);
                    });
                    $kunci_jawaban = implode(', ', array_map('sanitize', $answers));
                }
            } elseif ($tipe_soal === 'esai') {
                // For esai, kunci_jawaban is optional reference answer
                // Get kunci_jawaban from specific field name for esai
                $kunci_jawaban_raw = $_POST['kunci_jawaban_esai'] ?? $_POST['kunci_jawaban'] ?? '';
                $kunci_jawaban = !empty($kunci_jawaban_raw) ? sanitize($kunci_jawaban_raw) : '';
            } elseif ($tipe_soal === 'matching') {
                // For matching, kunci_jawaban is not used (stored in soal_matching table)
                $kunci_jawaban = '';
            }
            
            // Validate media_type if media_path is provided
            if (!empty($media_path) && !in_array($media_type, ['gambar', 'video'])) {
                $media_type = null;
                $media_path = null;
            }
            
            // Validate and sanitize data before insert
            // Trim pertanyaan and validate length
            $pertanyaan = trim($pertanyaan);
            if (empty($pertanyaan)) {
                throw new Exception('Pertanyaan tidak boleh kosong');
            }
            if (mb_strlen($pertanyaan) > 5000) {
                throw new Exception('Pertanyaan terlalu panjang. Maksimal 5000 karakter.');
            }
            
            // Validate kunci_jawaban length
            if (!empty($kunci_jawaban) && mb_strlen($kunci_jawaban) > 500) {
                throw new Exception('Kunci jawaban terlalu panjang. Maksimal 500 karakter.');
            }
            
            // Validate bobot
            if ($bobot <= 0) {
                $bobot = 1.0;
            }
            if ($bobot > 100) {
                throw new Exception('Bobot tidak boleh lebih dari 100');
            }
            
            // Validate media_path length if provided
            if (!empty($media_path) && mb_strlen($media_path) > 255) {
                throw new Exception('Path media terlalu panjang');
            }
            
            // Prepare values for database (handle NULL properly)
            $db_opsi_json = !empty($opsi_json) ? $opsi_json : null;
            $db_kunci_jawaban = !empty($kunci_jawaban) ? $kunci_jawaban : '';
            $db_media_path = !empty($media_path) ? $media_path : null;
            $db_media_type = !empty($media_type) ? $media_type : null;
            
            // Log data before insert for debugging (sanitized)
            error_log("Create soal - Data: ujian_id=$id_ujian, tipe=$tipe_soal, pertanyaan_len=" . mb_strlen($pertanyaan) . ", opsi_json_len=" . ($opsi_json ? mb_strlen($opsi_json) : 0) . ", kunci_len=" . mb_strlen($db_kunci_jawaban));
            
            // Insert soal with error handling
            try {
                $stmt = $pdo->prepare("INSERT INTO soal 
                                      (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $id_ujian, 
                    $pertanyaan, 
                    $tipe_soal, 
                    $db_opsi_json, 
                    $db_kunci_jawaban, 
                    $bobot, 
                    $urutan, 
                    $db_media_path, 
                    $db_media_type
                ]);
                $soal_id = $pdo->lastInsertId();
                
                if (!$soal_id) {
                    throw new Exception('Gagal mendapatkan ID soal yang baru dibuat');
                }
            } catch (PDOException $pdoe) {
                // Log detailed error for debugging
                error_log("PDO Error creating soal: " . $pdoe->getMessage());
                error_log("PDO Error Code: " . $pdoe->getCode());
                error_log("SQL State: " . $pdoe->errorInfo()[0] ?? 'unknown');
                error_log("SQL Error Info: " . print_r($pdoe->errorInfo(), true));
                
                // Provide user-friendly error based on error code
                $error_code = $pdoe->getCode();
                if ($error_code == 23000) { // Integrity constraint violation
                    throw new Exception('Data tidak valid atau sudah ada. Silakan periksa kembali data yang diinput.');
                } elseif ($error_code == 22001) { // Data too long
                    throw new Exception('Data terlalu panjang. Silakan kurangi panjang teks.');
                } elseif ($error_code == 23019) { // Check constraint violation
                    throw new Exception('Data tidak memenuhi syarat. Silakan periksa kembali.');
                } else {
                    // Re-throw with more context
                    throw new Exception('Gagal menyimpan soal ke database: ' . $pdoe->getMessage());
                }
            }
            
            // Handle matching items
            if ($tipe_soal === 'matching') {
                $items_kiri = $_POST['item_kiri'] ?? [];
                $items_kanan = $_POST['item_kanan'] ?? [];
                
                $matching_count = 0;
                foreach ($items_kiri as $idx => $kiri) {
                    if (!empty($kiri) && !empty($items_kanan[$idx])) {
                        $stmt = $pdo->prepare("INSERT INTO soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$soal_id, sanitize($kiri), sanitize($items_kanan[$idx]), $idx + 1]);
                        $matching_count++;
                    }
                }
                
                // Validate: matching minimal harus memiliki 1 item
                if ($matching_count === 0) {
                    throw new Exception('Soal matching minimal harus memiliki 1 pasangan item (kiri-kanan)');
                }
            }
            
            // Get ujian info untuk tingkat_kelas
            $stmt_ujian = $pdo->prepare("SELECT id_mapel, tingkat_kelas FROM ujian WHERE id = ?");
            $stmt_ujian->execute([$id_ujian]);
            $ujian_info = $stmt_ujian->fetch();
            
            $pdo->commit();
            
            // Auto-add soal to bank_soal (non-blocking, log error if fails)
            // Do this after commit to avoid transaction issues
            if ($ujian_info) {
                try {
                    add_soal_to_bank($soal_id, $ujian_info['id_mapel'], $ujian_info['tingkat_kelas']);
                } catch (Exception $e) {
                    // Log error but don't fail the transaction
                    error_log("Failed to add soal to bank: " . $e->getMessage());
                }
            }
            log_activity('create_soal', 'soal', $soal_id);
            
            // Redirect back (before any output)
            redirect('guru/ujian/detail.php?id=' . $id_ujian);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Enhanced error logging
            $error_details = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'post_data' => [
                    'id_ujian' => $_POST['id_ujian'] ?? null,
                    'tipe_soal' => $_POST['tipe_soal'] ?? null,
                    'pertanyaan_length' => isset($_POST['pertanyaan']) ? mb_strlen($_POST['pertanyaan']) : 0,
                ]
            ];
            
            error_log("Create soal error: " . json_encode($error_details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
            // Show user-friendly error message
            // For PDOException, show generic message but include error code if available
            if ($e instanceof PDOException) {
                $error_code = $e->getCode();
                $error = 'Terjadi kesalahan saat membuat soal. ';
                
                // Add specific guidance based on error
                if ($error_code == 23000) {
                    $error .= 'Data tidak valid atau duplikat.';
                } elseif ($error_code == 22001) {
                    $error .= 'Data terlalu panjang.';
                } elseif ($error_code == 42000) {
                    $error .= 'Kesalahan sintaks database.';
                } elseif ($error_code == '42S02') {
                    $error .= 'Tabel database tidak ditemukan.';
                } elseif ($error_code == '42S22') {
                    $error .= 'Kolom database tidak ditemukan.';
                } else {
                    $error .= 'Silakan coba lagi atau hubungi administrator. (Error Code: ' . $error_code . ')';
                }
            } else {
                // For other exceptions, show the actual error message (user-friendly)
                $error = $e->getMessage();
            }
        }
    }
}

$page_title = 'Tambah Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

// Get ujian list
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id_guru = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$ujian_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Tambah Soal</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" id="soalForm" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="id_ujian" class="form-label">Ujian <span class="text-danger">*</span></label>
                <select class="form-select" id="id_ujian" name="id_ujian" required>
                    <option value="">Pilih Ujian</option>
                    <?php foreach ($ujian_list as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $ujian_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($u['judul']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="tipe_soal" class="form-label">Tipe Soal <span class="text-danger">*</span></label>
                <select class="form-select" id="tipe_soal" name="tipe_soal" required onchange="showTipeFields()">
                    <option value="">Pilih Tipe</option>
                    <option value="pilihan_ganda">Pilihan Ganda</option>
                    <option value="isian_singkat">Isian Singkat</option>
                    <option value="benar_salah">Benar/Salah</option>
                    <option value="matching">Matching</option>
                    <option value="esai">Esai/Uraian</option>
                </select>
            </div>
            
            <div class="mb-3">
                <label for="pertanyaan" class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                <textarea class="form-control" id="pertanyaan" name="pertanyaan" rows="4" required></textarea>
            </div>
            
            <!-- Media Upload Section -->
            <div class="mb-3">
                <label for="soal_media" class="form-label">
                    <i class="fas fa-image me-1"></i> Media Soal (Gambar)
                </label>
                <input type="file" 
                       class="form-control" 
                       id="soal_media" 
                       name="soal_media" 
                       accept="image/*"
                       onchange="handleMediaUpload(this)">
                <small class="text-muted">
                    Format yang didukung: Gambar (JPG, PNG, GIF, WebP - maks. 500KB)
                </small>
                <div id="media_preview" class="mt-2" style="display:none;">
                    <div class="alert alert-info d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-check-circle me-2"></i>
                            <span id="media_filename"></span>
                            <span class="badge bg-primary ms-2" id="media_type_badge"></span>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeMedia()">
                            <i class="fas fa-times"></i> Hapus
                        </button>
                    </div>
                    <div id="media_preview_content" class="mt-2"></div>
                </div>
                <input type="hidden" id="media_path" name="media_path" value="">
                <input type="hidden" id="media_type" name="media_type" value="">
            </div>
            
            <!-- Pilihan Ganda Fields -->
            <div id="pilihan_ganda_fields" style="display:none;">
                <div class="row">
                    <!-- Opsi A -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Opsi A</label>
                        <input type="text" class="form-control mb-2" name="opsi_a" id="opsi_a" placeholder="Teks Opsi A">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="file" class="form-control form-control-sm" 
                                   id="opsi_a_image" name="opsi_a_image" 
                                   accept="image/*" 
                                   onchange="handleOptionImageUpload(this, 'opsi_a')">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    id="remove_opsi_a_image" 
                                    onclick="removeOptionImage('opsi_a')" 
                                    style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="opsi_a_preview" class="mt-2" style="display:none;">
                            <img id="opsi_a_preview_img" src="" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                        </div>
                        <input type="hidden" id="opsi_a_image_path" name="opsi_a_image_path" value="">
                    </div>
                    
                    <!-- Opsi B -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Opsi B</label>
                        <input type="text" class="form-control mb-2" name="opsi_b" id="opsi_b" placeholder="Teks Opsi B">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="file" class="form-control form-control-sm" 
                                   id="opsi_b_image" name="opsi_b_image" 
                                   accept="image/*" 
                                   onchange="handleOptionImageUpload(this, 'opsi_b')">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    id="remove_opsi_b_image" 
                                    onclick="removeOptionImage('opsi_b')" 
                                    style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="opsi_b_preview" class="mt-2" style="display:none;">
                            <img id="opsi_b_preview_img" src="" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                        </div>
                        <input type="hidden" id="opsi_b_image_path" name="opsi_b_image_path" value="">
                    </div>
                    
                    <!-- Opsi C -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Opsi C</label>
                        <input type="text" class="form-control mb-2" name="opsi_c" id="opsi_c" placeholder="Teks Opsi C">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="file" class="form-control form-control-sm" 
                                   id="opsi_c_image" name="opsi_c_image" 
                                   accept="image/*" 
                                   onchange="handleOptionImageUpload(this, 'opsi_c')">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    id="remove_opsi_c_image" 
                                    onclick="removeOptionImage('opsi_c')" 
                                    style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="opsi_c_preview" class="mt-2" style="display:none;">
                            <img id="opsi_c_preview_img" src="" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                        </div>
                        <input type="hidden" id="opsi_c_image_path" name="opsi_c_image_path" value="">
                    </div>
                    
                    <!-- Opsi D -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Opsi D</label>
                        <input type="text" class="form-control mb-2" name="opsi_d" id="opsi_d" placeholder="Teks Opsi D">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="file" class="form-control form-control-sm" 
                                   id="opsi_d_image" name="opsi_d_image" 
                                   accept="image/*" 
                                   onchange="handleOptionImageUpload(this, 'opsi_d')">
                            <button type="button" class="btn btn-sm btn-danger" 
                                    id="remove_opsi_d_image" 
                                    onclick="removeOptionImage('opsi_d')" 
                                    style="display:none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div id="opsi_d_preview" class="mt-2" style="display:none;">
                            <img id="opsi_d_preview_img" src="" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                        </div>
                        <input type="hidden" id="opsi_d_image_path" name="opsi_d_image_path" value="">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kunci Jawaban <span class="text-danger">*</span></label>
                        <select class="form-select" name="kunci_jawaban_pg" id="kunci_jawaban_pg" required>
                            <option value="">Pilih</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Benar/Salah Fields -->
            <div id="benar_salah_fields" style="display:none;">
                <div class="mb-3">
                    <label for="kunci_jawaban_bs" class="form-label">Kunci Jawaban <span class="text-danger">*</span></label>
                    <select class="form-select" name="kunci_jawaban_bs" id="kunci_jawaban_bs" required>
                        <option value="">-- Pilih Kunci Jawaban --</option>
                        <option value="Benar">Benar</option>
                        <option value="Salah">Salah</option>
                    </select>
                    <small class="text-muted">Pilih salah satu: Benar atau Salah</small>
                </div>
            </div>
            
            <!-- Isian Singkat Fields -->
            <div id="isian_singkat_fields" style="display:none;">
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban (bisa multiple, pisahkan dengan koma)</label>
                    <input type="text" class="form-control" name="kunci_jawaban_is" id="kunci_jawaban_is" placeholder="Contoh: Jakarta, DKI Jakarta">
                </div>
            </div>
            
            <!-- Matching Fields -->
            <div id="matching_fields" style="display:none;">
                <div id="matching_items">
                    <div class="matching-item mb-3 p-3 border rounded">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Item Kiri</label>
                                <input type="text" class="form-control" name="item_kiri[]">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Item Kanan</label>
                                <input type="text" class="form-control" name="item_kanan[]">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger w-100" onclick="removeMatchingItem(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-primary" onclick="addMatchingItem()">
                    <i class="fas fa-plus"></i> Tambah Item
                </button>
            </div>
            
            <!-- Esai Fields -->
            <div id="esai_fields" style="display:none;">
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban (opsional, sebagai referensi)</label>
                    <textarea class="form-control" name="kunci_jawaban_esai" id="kunci_jawaban_esai" rows="3"></textarea>
                </div>
            </div>
            
            <!-- Hidden field to store kunci_jawaban based on tipe (for backward compatibility) -->
            <input type="hidden" name="kunci_jawaban" id="kunci_jawaban_hidden" value="">
            
            <div class="mb-3">
                <label for="bobot" class="form-label">Bobot</label>
                <input type="number" class="form-control" id="bobot" name="bobot" value="1.00" step="0.01" min="0.01">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i> Simpan Soal
                </button>
                <a href="<?php echo base_url('guru/ujian/detail.php?id=' . ($ujian_id ?: '')); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function showTipeFields() {
    const tipe = document.getElementById('tipe_soal').value;
    
    // Hide all
    document.getElementById('pilihan_ganda_fields').style.display = 'none';
    document.getElementById('benar_salah_fields').style.display = 'none';
    document.getElementById('isian_singkat_fields').style.display = 'none';
    document.getElementById('matching_fields').style.display = 'none';
    document.getElementById('esai_fields').style.display = 'none';
    
    // Remove required attributes from all kunci_jawaban fields first
    document.querySelectorAll('select[name="kunci_jawaban_pg"], select[name="kunci_jawaban_bs"]').forEach(el => {
        el.removeAttribute('required');
    });
    
    // Clear hidden field
    document.getElementById('kunci_jawaban_hidden').value = '';
    
    // Show selected and set required if needed
    if (tipe === 'pilihan_ganda') {
        document.getElementById('pilihan_ganda_fields').style.display = 'block';
        const kunciSelect = document.getElementById('kunci_jawaban_pg');
        if (kunciSelect) {
            kunciSelect.setAttribute('required', 'required');
            kunciSelect.removeAttribute('disabled');
        }
    } else if (tipe === 'benar_salah') {
        document.getElementById('benar_salah_fields').style.display = 'block';
        const kunciSelect = document.getElementById('kunci_jawaban_bs');
        if (kunciSelect) {
            kunciSelect.setAttribute('required', 'required');
            kunciSelect.removeAttribute('disabled');
            kunciSelect.style.display = 'block';
            // Reset value to ensure clean state
            if (kunciSelect.value === '') {
                kunciSelect.selectedIndex = 0;
            }
        }
    } else if (tipe === 'isian_singkat') {
        document.getElementById('isian_singkat_fields').style.display = 'block';
    } else if (tipe === 'matching') {
        document.getElementById('matching_fields').style.display = 'block';
    } else if (tipe === 'esai') {
        document.getElementById('esai_fields').style.display = 'block';
    }
}

// Form validation before submit and sync kunci_jawaban to hidden field
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('soalForm');
    if (form) {
        // Sync kunci_jawaban to hidden field when tipe changes or before submit
        function syncKunciJawaban() {
            const tipe = document.getElementById('tipe_soal').value;
            const hiddenField = document.getElementById('kunci_jawaban_hidden');
            let kunciValue = '';
            
            if (tipe === 'pilihan_ganda') {
                const kunciSelect = document.getElementById('kunci_jawaban_pg');
                kunciValue = kunciSelect ? kunciSelect.value : '';
            } else if (tipe === 'benar_salah') {
                const kunciSelect = document.getElementById('kunci_jawaban_bs');
                kunciValue = kunciSelect ? kunciSelect.value.trim() : '';
                console.log('Sync benar_salah - kunci_jawaban_bs element:', kunciSelect);
                console.log('Sync benar_salah - kunci_jawaban_bs value:', kunciValue);
                // Also update hidden field for backward compatibility
                if (hiddenField && kunciValue) {
                    hiddenField.value = kunciValue;
                }
            } else if (tipe === 'isian_singkat') {
                const kunciInput = document.getElementById('kunci_jawaban_is');
                kunciValue = kunciInput ? kunciInput.value : '';
            } else if (tipe === 'esai') {
                const kunciTextarea = document.getElementById('kunci_jawaban_esai');
                kunciValue = kunciTextarea ? kunciTextarea.value : '';
            }
            
            if (hiddenField) {
                hiddenField.value = kunciValue;
            }
        }
        
        // Sync on change
        const tipeSelect = document.getElementById('tipe_soal');
        if (tipeSelect) {
            tipeSelect.addEventListener('change', syncKunciJawaban);
        }
        
        // Add change listeners to all kunci_jawaban fields
        ['kunci_jawaban_pg', 'kunci_jawaban_bs', 'kunci_jawaban_is', 'kunci_jawaban_esai'].forEach(id => {
            const field = document.getElementById(id);
            if (field) {
                field.addEventListener('change', syncKunciJawaban);
                field.addEventListener('input', syncKunciJawaban);
            }
        });
        
        form.addEventListener('submit', function(e) {
            const tipe = document.getElementById('tipe_soal').value;
            let isValid = true;
            let errorMsg = '';
            
            // Sync kunci_jawaban before validation
            syncKunciJawaban();
            
            // Validate based on tipe soal
            if (tipe === 'pilihan_ganda') {
                const opsi_a = document.getElementById('opsi_a').value.trim();
                const opsi_b = document.getElementById('opsi_b').value.trim();
                const opsi_c = document.getElementById('opsi_c').value.trim();
                const opsi_d = document.getElementById('opsi_d').value.trim();
                const kunciSelect = document.getElementById('kunci_jawaban_pg');
                const kunci = kunciSelect ? kunciSelect.value : '';
                
                const opsiFilled = [opsi_a, opsi_b, opsi_c, opsi_d].filter(o => o !== '').length;
                
                if (opsiFilled < 2) {
                    isValid = false;
                    errorMsg = 'Pilihan ganda minimal harus memiliki 2 opsi yang diisi';
                } else if (!kunci) {
                    isValid = false;
                    errorMsg = 'Kunci jawaban harus dipilih';
                } else {
                    // Check if selected kunci has filled option
                    const opsiMap = {'A': opsi_a, 'B': opsi_b, 'C': opsi_c, 'D': opsi_d};
                    if (!opsiMap[kunci] || opsiMap[kunci].trim() === '') {
                        isValid = false;
                        errorMsg = 'Kunci jawaban yang dipilih harus memiliki opsi yang diisi';
                    }
                }
            } else if (tipe === 'benar_salah') {
                const kunciSelect = document.getElementById('kunci_jawaban_bs');
                if (!kunciSelect) {
                    isValid = false;
                    errorMsg = 'Field kunci jawaban tidak ditemukan. Silakan refresh halaman.';
                    console.error('kunci_jawaban_bs element not found!');
                } else {
                    const kunci = kunciSelect.value.trim();
                    console.log('Validation benar_salah - kunci value:', kunci, 'element:', kunciSelect);
                    if (!kunci || kunci === '' || kunci === '-- Pilih Kunci Jawaban --') {
                        isValid = false;
                        errorMsg = 'Kunci jawaban untuk benar/salah harus dipilih (pilih Benar atau Salah)';
                    } else if (kunci !== 'Benar' && kunci !== 'Salah') {
                        isValid = false;
                        errorMsg = 'Kunci jawaban untuk benar/salah harus Benar atau Salah (nilai tidak valid: ' + kunci + ')';
                    }
                }
            } else if (tipe === 'matching') {
                const itemsKiri = document.querySelectorAll('#matching_fields input[name="item_kiri[]"]');
                const itemsKanan = document.querySelectorAll('#matching_fields input[name="item_kanan[]"]');
                let validPairs = 0;
                
                itemsKiri.forEach((kiri, idx) => {
                    if (itemsKanan[idx] && kiri.value.trim() !== '' && itemsKanan[idx].value.trim() !== '') {
                        validPairs++;
                    }
                });
                
                if (validPairs === 0) {
                    isValid = false;
                    errorMsg = 'Soal matching minimal harus memiliki 1 pasangan item (kiri-kanan) yang diisi';
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert(errorMsg);
                return false;
            }
            
            return true;
        });
    }
});

function addMatchingItem() {
    const container = document.getElementById('matching_items');
    const newItem = document.createElement('div');
    newItem.className = 'matching-item mb-3 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <label class="form-label">Item Kiri</label>
                <input type="text" class="form-control" name="item_kiri[]">
            </div>
            <div class="col-md-5">
                <label class="form-label">Item Kanan</label>
                <input type="text" class="form-control" name="item_kanan[]">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-danger w-100" onclick="removeMatchingItem(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newItem);
}

function removeMatchingItem(btn) {
    btn.closest('.matching-item').remove();
}

// Media upload handling
function handleMediaUpload(input) {
    const file = input.files[0];
    if (!file) {
        removeMedia();
        return;
    }
    
    // Validate file size (max 500KB for images)
    const maxSize = 512000; // 500KB
    if (file.size > maxSize) {
        alert('Ukuran file terlalu besar. Maksimal: 500KB');
        input.value = '';
        removeMedia();
        return;
    }
    
    // Show loading
    document.getElementById('media_preview').style.display = 'block';
    document.getElementById('media_filename').textContent = file.name;
    document.getElementById('media_preview_content').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Mengupload...</div>';
    
    // Create FormData
    const formData = new FormData();
    formData.append('media', file);
    
    // Upload file
    fetch('<?php echo base_url("api/upload_soal_media.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('media_path').value = data.path;
            document.getElementById('media_type').value = data.media_type;
            document.getElementById('media_type_badge').textContent = data.media_type === 'gambar' ? 'Gambar' : 'Video';
            
            // Show preview (only images)
            document.getElementById('media_preview_content').innerHTML = 
                '<img src="' + data.url + '" class="img-thumbnail" style="max-width: 400px; max-height: 300px;">';
        } else {
            alert('Error: ' + data.message);
            input.value = '';
            removeMedia();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengupload file');
        input.value = '';
        removeMedia();
    });
}

function removeMedia() {
    document.getElementById('soal_media').value = '';
    document.getElementById('media_path').value = '';
    document.getElementById('media_type').value = '';
    document.getElementById('media_preview').style.display = 'none';
    document.getElementById('media_preview_content').innerHTML = '';
}

// Handle option image upload
function handleOptionImageUpload(input, optionKey) {
    const file = input.files[0];
    if (!file) {
        removeOptionImage(optionKey);
        return;
    }
    
    // Validate file size (max 100KB for images)
    const maxSize = 102400; // 100KB
    if (file.size > maxSize) {
        alert('Ukuran file terlalu besar. Maksimal: 100KB');
        input.value = '';
        removeOptionImage(optionKey);
        return;
    }
    
    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Hanya file gambar yang diizinkan');
        input.value = '';
        removeOptionImage(optionKey);
        return;
    }
    
    // Show loading
    const previewDiv = document.getElementById(optionKey + '_preview');
    const previewImg = document.getElementById(optionKey + '_preview_img');
    const removeBtn = document.getElementById('remove_' + optionKey + '_image');
    
    previewDiv.style.display = 'block';
    previewImg.src = '';
    previewImg.alt = 'Loading...';
    
    // Create FormData
    const formData = new FormData();
    formData.append('media', file);
    formData.append('is_option_image', '1'); // Mark as option image for 100KB limit
    
    // Upload file
    fetch('<?php echo base_url("api/upload_soal_media.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById(optionKey + '_image_path').value = data.path;
            previewImg.src = data.url;
            previewImg.alt = file.name;
            removeBtn.style.display = 'block';
        } else {
            alert('Error: ' + data.message);
            input.value = '';
            removeOptionImage(optionKey);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengupload file');
        input.value = '';
        removeOptionImage(optionKey);
    });
}

function removeOptionImage(optionKey) {
    document.getElementById(optionKey + '_image').value = '';
    document.getElementById(optionKey + '_image_path').value = '';
    document.getElementById(optionKey + '_preview').style.display = 'none';
    document.getElementById('remove_' + optionKey + '_image').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

