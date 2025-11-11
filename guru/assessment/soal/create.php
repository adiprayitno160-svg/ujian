<?php
/**
 * Create Assessment Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk guru membuat soal assessment (tengah semester, semester, tahunan)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

// Check if guru has permission to create assessment soal
if (!can_create_assessment_soal()) {
    redirect('guru/index.php');
}

global $pdo;

$error = '';
$success = '';

// Get tahun ajaran aktif
$tahun_ajaran = get_tahun_ajaran_aktif();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe_asesmen = sanitize($_POST['tipe_asesmen'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $semester = sanitize($_POST['semester'] ?? '');
    $tingkat_kelas = sanitize($_POST['tingkat_kelas'] ?? '');
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $tipe_soal = sanitize($_POST['tipe_soal'] ?? '');
    $bobot = floatval($_POST['bobot'] ?? 1.0);
    $kunci_jawaban_raw = $_POST['kunci_jawaban'] ?? '';
    $media_path = sanitize($_POST['media_path'] ?? '');
    $media_type = sanitize($_POST['media_type'] ?? '');
    
    // Validate
    if (empty($pertanyaan) || !$id_mapel || !$tipe_soal || !$tipe_asesmen || !$semester || !$tingkat_kelas) {
        $error = 'Semua field wajib harus diisi';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if SUMATIP exists for this combination
            $stmt = $pdo->prepare("SELECT id FROM ujian 
                                  WHERE tipe_asesmen = ? 
                                  AND tahun_ajaran = ? 
                                  AND semester = ? 
                                  AND id_mapel = ?
                                  AND tingkat_kelas = ?");
            $stmt->execute([$tipe_asesmen, $tahun_ajaran, $semester, $id_mapel, $tingkat_kelas]);
            $sumatip = $stmt->fetch();
            
            $ujian_id = null;
            
            if (!$sumatip) {
                // Create SUMATIP if not exists
                $jenis_label = [
                    'sumatip_tengah_semester' => 'SUMATIP Tengah Semester',
                    'sumatip_akhir_semester' => 'SUMATIP Akhir Semester',
                    'sumatip_akhir_tahun' => 'SUMATIP Akhir Tahun'
                ];
                $jenis = $jenis_label[$tipe_asesmen] ?? 'SUMATIP';
                $semester_label = ucfirst($semester);
                $periode = "$jenis - Semester $semester_label $tahun_ajaran";
                
                $judul = "$jenis - " . get_mapel($id_mapel)['nama_mapel'] . " - Kelas $tingkat_kelas";
                
                $stmt = $pdo->prepare("INSERT INTO ujian 
                                      (judul, deskripsi, id_mapel, id_guru, durasi, tipe_asesmen, tahun_ajaran, semester, 
                                       periode_sumatip, tingkat_kelas, ai_correction_enabled, status) 
                                      VALUES (?, ?, ?, ?, 120, ?, ?, ?, ?, ?, 1, 'draft')");
                $stmt->execute([
                    $judul,
                    "Assessment $jenis untuk mata pelajaran " . get_mapel($id_mapel)['nama_mapel'] . " kelas $tingkat_kelas",
                    $id_mapel,
                    $_SESSION['user_id'],
                    $tipe_asesmen,
                    $tahun_ajaran,
                    $semester,
                    $periode,
                    $tingkat_kelas
                ]);
                $ujian_id = $pdo->lastInsertId();
            } else {
                $ujian_id = $sumatip['id'];
            }
            
            // Get max urutan
            $stmt = $pdo->prepare("SELECT MAX(urutan) as max_urutan FROM soal WHERE id_ujian = ?");
            $stmt->execute([$ujian_id]);
            $max = $stmt->fetch();
            $urutan = ($max['max_urutan'] ?? 0) + 1;
            
            // Prepare opsi_json and kunci_jawaban based on tipe
            $opsi_json = null;
            $kunci_jawaban = '';
            
            if ($tipe_soal === 'pilihan_ganda') {
                $opsi = [
                    'A' => sanitize($_POST['opsi_a'] ?? ''),
                    'B' => sanitize($_POST['opsi_b'] ?? ''),
                    'C' => sanitize($_POST['opsi_c'] ?? ''),
                    'D' => sanitize($_POST['opsi_d'] ?? '')
                ];
                $opsi = array_filter($opsi, function($value) {
                    return !empty($value);
                });
                
                // Validate: minimal 2 opsi harus diisi
                if (count($opsi) < 2) {
                    throw new Exception('Pilihan ganda minimal harus memiliki 2 opsi yang diisi');
                }
                
                // Validate: kunci_jawaban harus ada di opsi yang diisi
                $kunci_jawaban = sanitize($kunci_jawaban_raw);
                if (empty($kunci_jawaban)) {
                    throw new Exception('Kunci jawaban untuk pilihan ganda harus dipilih');
                }
                if (!isset($opsi[$kunci_jawaban])) {
                    throw new Exception('Kunci jawaban harus sesuai dengan opsi yang diisi');
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
                $kunci_jawaban = sanitize($kunci_jawaban_raw);
                // Validate kunci_jawaban for benar_salah
                if (empty($kunci_jawaban) || !in_array($kunci_jawaban, ['Benar', 'Salah'])) {
                    throw new Exception('Kunci jawaban untuk benar/salah harus dipilih');
                }
            } elseif ($tipe_soal === 'isian_singkat') {
                // For isian_singkat, kunci_jawaban can be multiple values separated by comma
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
            // Helper function for string length (compatible with servers without mbstring)
            $strlen_func = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
            
            // Trim pertanyaan and validate length
            $pertanyaan = trim($pertanyaan);
            if (empty($pertanyaan)) {
                throw new Exception('Pertanyaan tidak boleh kosong');
            }
            if ($strlen_func($pertanyaan) > 5000) {
                throw new Exception('Pertanyaan terlalu panjang. Maksimal 5000 karakter.');
            }
            
            // Validate kunci_jawaban length
            if (!empty($kunci_jawaban) && $strlen_func($kunci_jawaban) > 500) {
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
            if (!empty($media_path) && $strlen_func($media_path) > 255) {
                throw new Exception('Path media terlalu panjang');
            }
            
            // Prepare values for database (handle NULL properly)
            $db_opsi_json = !empty($opsi_json) ? $opsi_json : null;
            $db_kunci_jawaban = !empty($kunci_jawaban) ? $kunci_jawaban : '';
            $db_media_path = !empty($media_path) ? $media_path : null;
            $db_media_type = !empty($media_type) ? $media_type : null;
            
            // Log data before insert for debugging (sanitized)
            error_log("Create assessment soal - Data: ujian_id=$ujian_id, tipe=$tipe_soal, pertanyaan_len=" . $strlen_func($pertanyaan) . ", opsi_json_len=" . ($opsi_json ? $strlen_func($opsi_json) : 0) . ", kunci_len=" . $strlen_func($db_kunci_jawaban));
            
            // Insert soal with error handling
            try {
                $stmt = $pdo->prepare("INSERT INTO soal 
                                      (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $ujian_id, 
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
                error_log("PDO Error creating assessment soal: " . $pdoe->getMessage());
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
            
            $pdo->commit();
            
            // Auto-add soal to bank_soal (non-blocking, log error if fails)
            // Do this after commit to avoid transaction issues
            try {
                add_soal_to_bank($soal_id, $id_mapel, $tingkat_kelas);
            } catch (Exception $e) {
                // Log error but don't fail the transaction
                error_log("Failed to add soal to bank: " . $e->getMessage());
            }
            
            log_activity('create_assessment_soal', 'soal', $soal_id);
            
            $success = 'Soal assessment berhasil dibuat';
            
            // Reset form
            $_POST = [];
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
                    'tipe_asesmen' => $_POST['tipe_asesmen'] ?? null,
                    'id_mapel' => $_POST['id_mapel'] ?? null,
                    'tipe_soal' => $_POST['tipe_soal'] ?? null,
                    'pertanyaan_length' => isset($_POST['pertanyaan']) ? (function_exists('mb_strlen') ? mb_strlen($_POST['pertanyaan']) : strlen($_POST['pertanyaan'])) : 0,
                ]
            ];
            
            error_log("Create assessment soal error: " . json_encode($error_details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            
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

$page_title = 'Buat Soal Assessment';
$role_css = 'guru';
include __DIR__ . '/../../../includes/header.php';

// Get mapel untuk guru ini
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Soal Assessment</h2>
        <p class="text-muted">Buat soal untuk assessment tengah semester, semester, dan tahunan</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" id="soalForm" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="tipe_asesmen" class="form-label">Tipe Assessment <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipe_asesmen" name="tipe_asesmen" required>
                        <option value="">Pilih Tipe Assessment</option>
                        <option value="sumatip_tengah_semester" <?php echo (isset($_POST['tipe_asesmen']) && $_POST['tipe_asesmen'] === 'sumatip_tengah_semester') ? 'selected' : ''; ?>>Tengah Semester</option>
                        <option value="sumatip_akhir_semester" <?php echo (isset($_POST['tipe_asesmen']) && $_POST['tipe_asesmen'] === 'sumatip_akhir_semester') ? 'selected' : ''; ?>>Akhir Semester</option>
                        <option value="sumatip_akhir_tahun" <?php echo (isset($_POST['tipe_asesmen']) && $_POST['tipe_asesmen'] === 'sumatip_akhir_tahun') ? 'selected' : ''; ?>>Akhir Tahun</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                    <select class="form-select" id="semester" name="semester" required>
                        <option value="">Pilih Semester</option>
                        <option value="ganjil" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="genap" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'genap') ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    
                    <?php if (count($mapel_list) == 1): ?>
                        <?php $single_mapel = $mapel_list[0]; ?>
                        <!-- Jika hanya 1 mata pelajaran, auto-select dan tampilkan sebagai info -->
                        <div class="alert alert-info d-flex align-items-center mb-2">
                            <i class="fas fa-book me-2"></i>
                            <strong>Mata Pelajaran:</strong> 
                            <span class="ms-2 badge bg-primary"><?php echo escape($single_mapel['nama_mapel']); ?></span>
                        </div>
                        <input type="hidden" name="id_mapel" value="<?php echo $single_mapel['id']; ?>">
                    <?php else: ?>
                        <!-- Jika lebih dari 1 mata pelajaran, tampilkan info semua mata pelajaran yang diampu -->
                        <div class="mb-2">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Mata pelajaran yang Anda ampu:</strong>
                            </small>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($mapel_list as $mapel): ?>
                                    <span class="badge bg-info"><?php echo escape($mapel['nama_mapel']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <select class="form-select" id="id_mapel" name="id_mapel" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo (isset($_POST['id_mapel']) && $_POST['id_mapel'] == $mapel['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="tingkat_kelas" class="form-label">Tingkat Kelas <span class="text-danger">*</span></label>
                    <select class="form-select" id="tingkat_kelas" name="tingkat_kelas" required>
                        <option value="">Pilih Tingkat Kelas</option>
                        <option value="VII" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] === 'VII') ? 'selected' : ''; ?>>VII</option>
                        <option value="VIII" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] === 'VIII') ? 'selected' : ''; ?>>VIII</option>
                        <option value="IX" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] === 'IX') ? 'selected' : ''; ?>>IX</option>
                    </select>
                </div>
            </div>
            
            <hr>
            
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
                <textarea class="form-control" id="pertanyaan" name="pertanyaan" rows="4" required><?php echo isset($_POST['pertanyaan']) ? escape($_POST['pertanyaan']) : ''; ?></textarea>
            </div>
            
            <!-- Media Upload Section -->
            <div class="mb-3">
                <label for="soal_media" class="form-label">
                    <i class="fas fa-image me-1"></i> Media Soal (Gambar) <span class="text-muted fw-normal">(Opsional)</span>
                </label>
                <input type="file" 
                       class="form-control" 
                       id="soal_media" 
                       name="soal_media" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                       onchange="handleMediaUpload(this)">
                <div class="alert alert-info mt-2 mb-0">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>Format yang Didukung:</strong>
                            <ul class="mb-1 mt-1">
                                <li><strong>JPG/JPEG</strong> - Format gambar standar</li>
                                <li><strong>PNG</strong> - Format dengan transparansi</li>
                                <li><strong>GIF</strong> - Format animasi (statis)</li>
                                <li><strong>WebP</strong> - Format modern dengan kompresi tinggi</li>
                            </ul>
                            <strong>Ukuran Maksimal: 500KB (512.000 bytes)</strong><br>
                            <small class="text-muted">Ukuran file yang lebih kecil akan mempercepat loading saat ujian.</small>
                        </div>
                    </div>
                </div>
                <div id="media_upload_error" class="alert alert-danger mt-2" style="display:none;">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <span id="media_error_message"></span>
                </div>
                <div id="media_preview" class="mt-2" style="display:none;">
                    <div class="alert alert-success d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-check-circle me-2"></i>
                            <span id="media_filename"></span>
                            <span class="badge bg-primary ms-2" id="media_type_badge"></span>
                            <span class="badge bg-info ms-2" id="media_size_badge"></span>
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
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi A</label>
                        <input type="text" class="form-control" name="opsi_a" value="<?php echo isset($_POST['opsi_a']) ? escape($_POST['opsi_a']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi B</label>
                        <input type="text" class="form-control" name="opsi_b" value="<?php echo isset($_POST['opsi_b']) ? escape($_POST['opsi_b']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi C</label>
                        <input type="text" class="form-control" name="opsi_c" value="<?php echo isset($_POST['opsi_c']) ? escape($_POST['opsi_c']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi D</label>
                        <input type="text" class="form-control" name="opsi_d" value="<?php echo isset($_POST['opsi_d']) ? escape($_POST['opsi_d']) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kunci Jawaban <span class="text-danger">*</span></label>
                        <select class="form-select" name="kunci_jawaban" required>
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
                    <label class="form-label">Kunci Jawaban <span class="text-danger">*</span></label>
                    <select class="form-select" name="kunci_jawaban" required>
                        <option value="">Pilih</option>
                        <option value="Benar">Benar</option>
                        <option value="Salah">Salah</option>
                    </select>
                </div>
            </div>
            
            <!-- Isian Singkat Fields -->
            <div id="isian_singkat_fields" style="display:none;">
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban (bisa multiple, pisahkan dengan koma)</label>
                    <input type="text" class="form-control" name="kunci_jawaban" placeholder="Contoh: Jakarta, DKI Jakarta" value="<?php echo isset($_POST['kunci_jawaban']) ? escape($_POST['kunci_jawaban']) : ''; ?>">
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
                    <textarea class="form-control" name="kunci_jawaban" rows="3"><?php echo isset($_POST['kunci_jawaban']) ? escape($_POST['kunci_jawaban']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="bobot" class="form-label">Bobot</label>
                <input type="number" class="form-control" id="bobot" name="bobot" value="1.00" step="0.01" min="0.01">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Soal
                </button>
                <a href="<?php echo base_url('guru-assessment-soal-list'); ?>" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Lihat Daftar Soal
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
    document.querySelectorAll('input[name="kunci_jawaban"], select[name="kunci_jawaban"], textarea[name="kunci_jawaban"]').forEach(el => {
        el.removeAttribute('required');
    });
    
    // Show selected and set required if needed
    if (tipe === 'pilihan_ganda') {
        document.getElementById('pilihan_ganda_fields').style.display = 'block';
        const kunciSelect = document.querySelector('#pilihan_ganda_fields select[name="kunci_jawaban"]');
        if (kunciSelect) kunciSelect.setAttribute('required', 'required');
    } else if (tipe === 'benar_salah') {
        document.getElementById('benar_salah_fields').style.display = 'block';
        const kunciSelect = document.querySelector('#benar_salah_fields select[name="kunci_jawaban"]');
        if (kunciSelect) kunciSelect.setAttribute('required', 'required');
    } else if (tipe === 'isian_singkat') {
        document.getElementById('isian_singkat_fields').style.display = 'block';
    } else if (tipe === 'matching') {
        document.getElementById('matching_fields').style.display = 'block';
    } else if (tipe === 'esai') {
        document.getElementById('esai_fields').style.display = 'block';
    }
}

// Form validation before submit
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('soalForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const tipe = document.getElementById('tipe_soal').value;
            let isValid = true;
            let errorMsg = '';
            
            // Validate based on tipe soal
            if (tipe === 'pilihan_ganda') {
                const opsi_a = document.querySelector('input[name="opsi_a"]').value.trim();
                const opsi_b = document.querySelector('input[name="opsi_b"]').value.trim();
                const opsi_c = document.querySelector('input[name="opsi_c"]').value.trim();
                const opsi_d = document.querySelector('input[name="opsi_d"]').value.trim();
                const kunciSelect = document.querySelector('#pilihan_ganda_fields select[name="kunci_jawaban"]');
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
                const kunciSelect = document.querySelector('#benar_salah_fields select[name="kunci_jawaban"]');
                const kunci = kunciSelect ? kunciSelect.value : '';
                if (!kunci) {
                    isValid = false;
                    errorMsg = 'Kunci jawaban untuk benar/salah harus dipilih';
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
    const errorDiv = document.getElementById('media_upload_error');
    const errorMessage = document.getElementById('media_error_message');
    
    // Hide error message initially
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
    
    if (!file) {
        removeMedia();
        return;
    }
    
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        const errorMsg = 'Format file tidak didukung. Hanya JPG, PNG, GIF, dan WebP yang diizinkan.';
        if (errorDiv && errorMessage) {
            errorMessage.textContent = errorMsg;
            errorDiv.style.display = 'block';
        } else {
            alert(errorMsg);
        }
        input.value = '';
        removeMedia();
        return;
    }
    
    // Validate file size (max 500KB for images)
    const maxSize = 512000; // 500KB
    if (file.size > maxSize) {
        const fileSizeKB = (file.size / 1024).toFixed(2);
        const maxSizeKB = (maxSize / 1024).toFixed(0);
        const errorMsg = `Ukuran file terlalu besar: ${fileSizeKB} KB. Maksimal: ${maxSizeKB} KB (500 KB).`;
        if (errorDiv && errorMessage) {
            errorMessage.textContent = errorMsg;
            errorDiv.style.display = 'block';
        } else {
            alert(errorMsg);
        }
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
            // Hide error if shown
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
            
            document.getElementById('media_path').value = data.path;
            document.getElementById('media_type').value = data.media_type;
            document.getElementById('media_type_badge').textContent = data.media_type === 'gambar' ? 'Gambar' : 'Video';
            
            // Show file size
            const fileSizeKB = data.size ? (data.size / 1024).toFixed(2) : (file.size / 1024).toFixed(2);
            document.getElementById('media_size_badge').textContent = fileSizeKB + ' KB';
            
            // Show preview (only images)
            document.getElementById('media_preview_content').innerHTML = 
                '<img src="' + data.url + '" class="img-thumbnail" style="max-width: 400px; max-height: 300px;">';
        } else {
            const errorMsg = 'Error: ' + (data.message || 'Gagal mengupload file');
            if (errorDiv && errorMessage) {
                errorMessage.textContent = errorMsg;
                errorDiv.style.display = 'block';
            } else {
                alert(errorMsg);
            }
            input.value = '';
            removeMedia();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        const errorMsg = 'Terjadi kesalahan saat mengupload file. Pastikan koneksi internet stabil dan coba lagi.';
        if (errorDiv && errorMessage) {
            errorMessage.textContent = errorMsg;
            errorDiv.style.display = 'block';
        } else {
            alert(errorMsg);
        }
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
    
    // Hide error message
    const errorDiv = document.getElementById('media_upload_error');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

