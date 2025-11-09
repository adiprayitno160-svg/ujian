<?php
/**
 * Create Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

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
            
            // Insert soal
            $stmt = $pdo->prepare("INSERT INTO soal 
                                  (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_ujian, $pertanyaan, $tipe_soal, $opsi_json, $kunci_jawaban, $bobot, $urutan]);
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
        <form method="POST" id="soalForm">
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

