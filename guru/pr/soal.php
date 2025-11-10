<?php
/**
 * Manage PR Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ? AND p.id_guru = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('guru/pr/list.php');
}

// Check if PR is online or hybrid
if (!in_array($pr['tipe_pr'], ['online', 'hybrid'])) {
    redirect('guru/pr/list.php');
}

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $tipe_soal = sanitize($_POST['tipe_soal'] ?? '');
    $bobot = floatval($_POST['bobot'] ?? 1.0);
    $kunci_jawaban_raw = $_POST['kunci_jawaban'] ?? '';
    $media_path = sanitize($_POST['media_path'] ?? '');
    $media_type = sanitize($_POST['media_type'] ?? '');
    
    if (empty($pertanyaan) || !$tipe_soal) {
        $error = 'Pertanyaan dan tipe soal harus diisi';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get max urutan
            $stmt = $pdo->prepare("SELECT MAX(urutan) as max_urutan FROM pr_soal WHERE id_pr = ?");
            $stmt->execute([$pr_id]);
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
                // Remove empty options
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
            $stmt = $pdo->prepare("INSERT INTO pr_soal 
                                  (id_pr, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$pr_id, $pertanyaan, $tipe_soal, $opsi_json, $kunci_jawaban, $bobot, $urutan, $media_path, $media_type]);
            $soal_id = $pdo->lastInsertId();
            
            // Handle matching items
            if ($tipe_soal === 'matching') {
                $items_kiri = $_POST['item_kiri'] ?? [];
                $items_kanan = $_POST['item_kanan'] ?? [];
                
                foreach ($items_kiri as $idx => $kiri) {
                    if (!empty($kiri) && !empty($items_kanan[$idx])) {
                        $stmt = $pdo->prepare("INSERT INTO pr_soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$soal_id, sanitize($kiri), sanitize($items_kanan[$idx]), $idx + 1]);
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Soal berhasil ditambahkan';
            log_activity('create_pr_soal', 'pr_soal', $soal_id);
            
            // Refresh page
            header("Location: " . base_url('guru/pr/soal.php?id=' . $pr_id));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Create PR soal error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat menambahkan soal';
        }
    }
}

// Get existing soal
$stmt = $pdo->prepare("SELECT * FROM pr_soal WHERE id_pr = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$pr_id]);
$soal_list = $stmt->fetchAll();

$page_title = 'Kelola Soal PR';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Kelola Soal PR</h2>
                <p class="text-muted mb-0"><?php echo escape($pr['judul']); ?> - <?php echo escape($pr['nama_mapel']); ?></p>
            </div>
            <a href="<?php echo base_url('guru/pr/list.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Soal (<?php echo count($soal_list); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($soal_list)): ?>
                    <p class="text-muted text-center">Belum ada soal. Tambahkan soal pertama Anda.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($soal_list as $index => $soal): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong>Soal #<?php echo $index + 1; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></span>
                                    <p class="mb-0 mt-1"><?php echo escape(substr(strip_tags($soal['pertanyaan']), 0, 100)); ?>...</p>
                                </div>
                                <div>
                                    <a href="<?php echo base_url('guru/pr/soal_delete.php?id=' . $soal['id'] . '&pr_id=' . $pr_id); ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Hapus soal ini?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-plus"></i> Tambah Soal</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="soalForm" enctype="multipart/form-data">
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
                            <i class="fas fa-image me-1"></i> Media Soal (Gambar/Video)
                        </label>
                        <input type="file" 
                               class="form-control form-control-sm" 
                               id="soal_media" 
                               name="soal_media" 
                               accept="image/*,video/*"
                               onchange="handleMediaUpload(this)">
                        <small class="text-muted d-block">
                            Format: Gambar (JPG, PNG, GIF, WebP - maks. 10MB), Video (MP4, WebM, OGG - maks. 50MB)
                        </small>
                        <div id="media_preview" class="mt-2" style="display:none;">
                            <div class="alert alert-info alert-sm d-flex justify-content-between align-items-center p-2">
                                <div>
                                    <i class="fas fa-check-circle me-1"></i>
                                    <small id="media_filename"></small>
                                    <span class="badge bg-primary ms-1" id="media_type_badge"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger btn-sm" onclick="removeMedia()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="media_preview_content" class="mt-1"></div>
                        </div>
                        <input type="hidden" id="media_path" name="media_path" value="">
                        <input type="hidden" id="media_type" name="media_type" value="">
                    </div>
                    
                    <!-- Pilihan Ganda Fields -->
                    <div id="pilihan_ganda_fields" style="display:none;">
                        <div class="mb-2">
                            <input type="text" class="form-control mb-2" name="opsi_a" placeholder="Opsi A">
                            <input type="text" class="form-control mb-2" name="opsi_b" placeholder="Opsi B">
                            <input type="text" class="form-control mb-2" name="opsi_c" placeholder="Opsi C">
                            <input type="text" class="form-control mb-2" name="opsi_d" placeholder="Opsi D">
                        </div>
                        <div class="mb-3">
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
                            <input type="text" class="form-control" name="kunci_jawaban" placeholder="Contoh: jawaban1, jawaban2">
                        </div>
                    </div>
                    
                    <!-- Matching Fields -->
                    <div id="matching_fields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">Item Matching</label>
                            <div id="matching_items">
                                <div class="matching-item mb-2 p-2 border rounded">
                                    <div class="row">
                                        <div class="col-5">
                                            <input type="text" class="form-control form-control-sm" name="item_kiri[]" placeholder="Item Kiri">
                                        </div>
                                        <div class="col-5">
                                            <input type="text" class="form-control form-control-sm" name="item_kanan[]" placeholder="Item Kanan">
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-sm btn-danger w-100" onclick="removeMatchingItem(this)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addMatchingItem()">
                                <i class="fas fa-plus"></i> Tambah Item
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bobot" class="form-label">Bobot</label>
                        <input type="number" class="form-control" id="bobot" name="bobot" value="1.0" step="0.1" min="0">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Tambah Soal
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showTipeFields() {
    const tipe = document.getElementById('tipe_soal').value;
    document.getElementById('pilihan_ganda_fields').style.display = tipe === 'pilihan_ganda' ? 'block' : 'none';
    document.getElementById('benar_salah_fields').style.display = tipe === 'benar_salah' ? 'block' : 'none';
    document.getElementById('isian_singkat_fields').style.display = tipe === 'isian_singkat' ? 'block' : 'none';
    document.getElementById('matching_fields').style.display = tipe === 'matching' ? 'block' : 'none';
}

function addMatchingItem() {
    const container = document.getElementById('matching_items');
    const newItem = document.createElement('div');
    newItem.className = 'matching-item mb-2 p-2 border rounded';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-5">
                <input type="text" class="form-control form-control-sm" name="item_kiri[]" placeholder="Item Kiri">
            </div>
            <div class="col-5">
                <input type="text" class="form-control form-control-sm" name="item_kanan[]" placeholder="Item Kanan">
            </div>
            <div class="col-2">
                <button type="button" class="btn btn-sm btn-danger w-100" onclick="removeMatchingItem(this)">
                    <i class="fas fa-times"></i>
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
    const maxSize = file.type.startsWith('video/') ? 52428800 : 10485760;
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
                    '<img src="' + data.url + '" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">';
            } else {
                document.getElementById('media_preview_content').innerHTML = 
                    '<video controls class="img-thumbnail" style="max-width: 200px; max-height: 150px;">' +
                    '<source src="' + data.url + '" type="' + data.mime_type + '">' +
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

