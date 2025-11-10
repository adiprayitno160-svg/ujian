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
                                       periode_sumatip, tingkat_kelas, status) 
                                      VALUES (?, ?, ?, ?, 120, ?, ?, ?, ?, ?, 'draft')");
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
                $opsi_json = json_encode($opsi);
                $kunci_jawaban = sanitize($kunci_jawaban_raw);
            } elseif ($tipe_soal === 'benar_salah') {
                $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
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
            
            // Insert soal
            $stmt = $pdo->prepare("INSERT INTO soal 
                                  (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$ujian_id, $pertanyaan, $tipe_soal, $opsi_json, $kunci_jawaban, $bobot, $urutan, $media_path, $media_type]);
            $soal_id = $pdo->lastInsertId();
            
            // Handle matching items
            if ($tipe_soal === 'matching') {
                $items_kiri = $_POST['item_kiri'] ?? [];
                $items_kanan = $_POST['item_kanan'] ?? [];
                
                foreach ($items_kiri as $idx => $kiri) {
                    if (!empty($kiri) && !empty($items_kanan[$idx])) {
                        $stmt = $pdo->prepare("INSERT INTO soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$soal_id, sanitize($kiri), sanitize($items_kanan[$idx]), $idx + 1]);
                    }
                }
            }
            
            // Auto-add soal to bank_soal
            add_soal_to_bank($soal_id, $id_mapel, $tingkat_kelas);
            
            $pdo->commit();
            log_activity('create_assessment_soal', 'soal', $soal_id);
            
            $success = 'Soal assessment berhasil dibuat';
            
            // Reset form
            $_POST = [];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Create assessment soal error: " . $e->getMessage());
            // Show user-friendly error message
            $error = $e->getMessage();
            // If it's a PDOException, show generic message to user but log details
            if ($e instanceof PDOException) {
                $error = 'Terjadi kesalahan saat membuat soal. Silakan coba lagi atau hubungi administrator.';
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
                    <i class="fas fa-image me-1"></i> Media Soal (Gambar/Video)
                </label>
                <input type="file" 
                       class="form-control" 
                       id="soal_media" 
                       name="soal_media" 
                       accept="image/*,video/*"
                       onchange="handleMediaUpload(this)">
                <small class="text-muted">
                    Format yang didukung: Gambar (JPG, PNG, GIF, WebP - maks. 10MB), Video (MP4, WebM, OGG - maks. 50MB)
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
    
    // Show selected
    if (tipe === 'pilihan_ganda') {
        document.getElementById('pilihan_ganda_fields').style.display = 'block';
    } else if (tipe === 'benar_salah') {
        document.getElementById('benar_salah_fields').style.display = 'block';
    } else if (tipe === 'isian_singkat') {
        document.getElementById('isian_singkat_fields').style.display = 'block';
    } else if (tipe === 'matching') {
        document.getElementById('matching_fields').style.display = 'block';
    } else if (tipe === 'esai') {
        document.getElementById('esai_fields').style.display = 'block';
    }
}

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
    
    // Validate file size
    const maxSize = file.type.startsWith('video/') ? 52428800 : 10485760; // 50MB for video, 10MB for image
    if (file.size > maxSize) {
        alert('Ukuran file terlalu besar. Maksimal: ' + (maxSize / 1048576) + 'MB');
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
            
            // Show preview
            if (data.media_type === 'gambar') {
                document.getElementById('media_preview_content').innerHTML = 
                    '<img src="' + data.url + '" class="img-thumbnail" style="max-width: 400px; max-height: 300px;">';
            } else {
                document.getElementById('media_preview_content').innerHTML = 
                    '<video controls class="img-thumbnail" style="max-width: 400px; max-height: 300px;">' +
                    '<source src="' + data.url + '" type="' + data.mime_type + '">' +
                    'Browser Anda tidak mendukung video tag.' +
                    '</video>';
            }
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
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

