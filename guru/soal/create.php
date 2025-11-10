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
    $kunci_jawaban = $_POST['kunci_jawaban'] ?? '';
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
            
            // Prepare opsi_json based on tipe
            $opsi_json = null;
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
            } elseif ($tipe_soal === 'benar_salah') {
                $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
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
            $stmt->execute([$id_ujian, $pertanyaan, $tipe_soal, $opsi_json, $kunci_jawaban, $bobot, $urutan, $media_path, $media_type]);
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
            
            // Get ujian info untuk tingkat_kelas
            $stmt_ujian = $pdo->prepare("SELECT id_mapel, tingkat_kelas FROM ujian WHERE id = ?");
            $stmt_ujian->execute([$id_ujian]);
            $ujian_info = $stmt_ujian->fetch();
            
            // Auto-add soal to bank_soal
            if ($ujian_info) {
                add_soal_to_bank($soal_id, $ujian_info['id_mapel'], $ujian_info['tingkat_kelas']);
            }
            
            $pdo->commit();
            log_activity('create_soal', 'soal', $soal_id);
            
            // Redirect back (before any output)
            redirect('guru/ujian/detail.php?id=' . $id_ujian);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Create soal error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat soal';
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
                        <input type="text" class="form-control" name="opsi_a">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi B</label>
                        <input type="text" class="form-control" name="opsi_b">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi C</label>
                        <input type="text" class="form-control" name="opsi_c">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi D</label>
                        <input type="text" class="form-control" name="opsi_d">
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
                    <label class="form-label">Kunci Jawaban</label>
                    <select class="form-select" name="kunci_jawaban">
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
                    <input type="text" class="form-control" name="kunci_jawaban" placeholder="Contoh: Jakarta, DKI Jakarta">
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
                    <textarea class="form-control" name="kunci_jawaban" rows="3"></textarea>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

