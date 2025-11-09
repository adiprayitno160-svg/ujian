<?php
/**
 * Edit Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Edit Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.id_guru FROM soal s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      WHERE s.id = ?");
$stmt->execute([$id]);
$soal = $stmt->fetch();

if (!$soal || $soal['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/ujian/list.php');
}

// Get matching items if matching type
$matching_items = [];
if ($soal['tipe_soal'] === 'matching') {
    $stmt = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan");
    $stmt->execute([$id]);
    $matching_items = $stmt->fetchAll();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pertanyaan = $_POST['pertanyaan'] ?? '';
    $bobot = floatval($_POST['bobot'] ?? 1.0);
    $kunci_jawaban = $_POST['kunci_jawaban'] ?? '';
    
    if (empty($pertanyaan)) {
        $error = 'Pertanyaan harus diisi';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Prepare opsi_json
            $opsi_json = null;
            if ($soal['tipe_soal'] === 'pilihan_ganda') {
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
            } elseif ($soal['tipe_soal'] === 'benar_salah') {
                $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
            }
            
            // Update soal
            $stmt = $pdo->prepare("UPDATE soal SET pertanyaan = ?, opsi_json = ?, kunci_jawaban = ?, bobot = ? WHERE id = ?");
            $stmt->execute([$pertanyaan, $opsi_json, $kunci_jawaban, $bobot, $id]);
            
            // Handle matching items
            if ($soal['tipe_soal'] === 'matching') {
                // Delete old items
                $stmt = $pdo->prepare("DELETE FROM soal_matching WHERE id_soal = ?");
                $stmt->execute([$id]);
                
                // Insert new items
                $items_kiri = $_POST['item_kiri'] ?? [];
                $items_kanan = $_POST['item_kanan'] ?? [];
                
                foreach ($items_kiri as $idx => $kiri) {
                    if (!empty($kiri) && !empty($items_kanan[$idx])) {
                        $stmt = $pdo->prepare("INSERT INTO soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$id, sanitize($kiri), sanitize($items_kanan[$idx]), $idx + 1]);
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Soal berhasil diupdate';
            log_activity('update_soal', 'soal', $id);
            
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ?");
            $stmt->execute([$id]);
            $soal = $stmt->fetch();
            
            if ($soal['tipe_soal'] === 'matching') {
                $stmt = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan");
                $stmt->execute([$id]);
                $matching_items = $stmt->fetchAll();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update soal error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat mengupdate soal';
        }
    }
}

$opsi = $soal['opsi_json'] ? json_decode($soal['opsi_json'], true) : [];
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Edit Soal</h2>
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

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" id="soalForm">
            <div class="mb-3">
                <label class="form-label">Tipe Soal</label>
                <input type="text" class="form-control" value="<?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?>" disabled>
            </div>
            
            <div class="mb-3">
                <label for="pertanyaan" class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                <textarea class="form-control" id="pertanyaan" name="pertanyaan" rows="4" required><?php echo escape($soal['pertanyaan']); ?></textarea>
            </div>
            
            <?php if ($soal['tipe_soal'] === 'pilihan_ganda'): ?>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi A</label>
                        <input type="text" class="form-control" name="opsi_a" value="<?php echo escape($opsi['A'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi B</label>
                        <input type="text" class="form-control" name="opsi_b" value="<?php echo escape($opsi['B'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi C</label>
                        <input type="text" class="form-control" name="opsi_c" value="<?php echo escape($opsi['C'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Opsi D</label>
                        <input type="text" class="form-control" name="opsi_d" value="<?php echo escape($opsi['D'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kunci Jawaban <span class="text-danger">*</span></label>
                        <select class="form-select" name="kunci_jawaban" required>
                            <option value="">Pilih</option>
                            <option value="A" <?php echo $soal['kunci_jawaban'] === 'A' ? 'selected' : ''; ?>>A</option>
                            <option value="B" <?php echo $soal['kunci_jawaban'] === 'B' ? 'selected' : ''; ?>>B</option>
                            <option value="C" <?php echo $soal['kunci_jawaban'] === 'C' ? 'selected' : ''; ?>>C</option>
                            <option value="D" <?php echo $soal['kunci_jawaban'] === 'D' ? 'selected' : ''; ?>>D</option>
                        </select>
                    </div>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'benar_salah'): ?>
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban</label>
                    <select class="form-select" name="kunci_jawaban">
                        <option value="">Pilih</option>
                        <option value="Benar" <?php echo $soal['kunci_jawaban'] === 'Benar' ? 'selected' : ''; ?>>Benar</option>
                        <option value="Salah" <?php echo $soal['kunci_jawaban'] === 'Salah' ? 'selected' : ''; ?>>Salah</option>
                    </select>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'isian_singkat'): ?>
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban (bisa multiple, pisahkan dengan koma)</label>
                    <input type="text" class="form-control" name="kunci_jawaban" value="<?php echo escape($soal['kunci_jawaban'] ?? ''); ?>">
                </div>
            <?php elseif ($soal['tipe_soal'] === 'matching'): ?>
                <div id="matching_items">
                    <?php foreach ($matching_items as $item): ?>
                    <div class="matching-item mb-3 p-3 border rounded">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Item Kiri</label>
                                <input type="text" class="form-control" name="item_kiri[]" value="<?php echo escape($item['item_kiri']); ?>">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Item Kanan</label>
                                <input type="text" class="form-control" name="item_kanan[]" value="<?php echo escape($item['item_kanan']); ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-danger w-100" onclick="removeMatchingItem(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($matching_items)): ?>
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
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-outline-primary" onclick="addMatchingItem()">
                    <i class="fas fa-plus"></i> Tambah Item
                </button>
            <?php elseif ($soal['tipe_soal'] === 'esai'): ?>
                <div class="mb-3">
                    <label class="form-label">Kunci Jawaban (opsional, sebagai referensi)</label>
                    <textarea class="form-control" name="kunci_jawaban" rows="3"><?php echo escape($soal['kunci_jawaban'] ?? ''); ?></textarea>
                </div>
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="bobot" class="form-label">Bobot</label>
                <input type="number" class="form-control" id="bobot" name="bobot" value="<?php echo $soal['bobot']; ?>" step="0.01" min="0.01">
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Soal
                </button>
                <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $soal['id_ujian']); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
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

