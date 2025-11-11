<?php
/**
 * Create Ujian from Arsip Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk membuat ujian baru dengan memilih soal dari arsip soal
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $durasi = intval($_POST['durasi'] ?? 0);
    $pool_id = intval($_POST['pool_id'] ?? 0);
    $selected_soal = $_POST['selected_soal'] ?? [];
    
    if (empty($judul) || !$id_mapel || $durasi <= 0 || !$pool_id) {
        $error = 'Judul, mata pelajaran, durasi, dan arsip soal harus diisi';
    } elseif (empty($selected_soal)) {
        $error = 'Pilih minimal satu soal dari arsip';
    } else {
        // Validate: Guru hanya bisa membuat ujian untuk mata pelajaran yang dia ajar
        if (!guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
            $error = 'Anda tidak diizinkan membuat ujian untuk mata pelajaran ini.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $min_submit_minutes = DEFAULT_MIN_SUBMIT_MINUTES;
                
                // Create ujian (with AI correction enabled by default)
                $stmt = $pdo->prepare("INSERT INTO ujian (judul, deskripsi, id_mapel, id_guru, durasi, min_submit_minutes, ai_correction_enabled, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, 1, 'draft')");
                $stmt->execute([$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $durasi, $min_submit_minutes]);
                $ujian_id = $pdo->lastInsertId();
                
                // Copy selected soal from pool to ujian
                $urutan = 1;
                foreach ($selected_soal as $soal_id) {
                    $soal_id = intval($soal_id);
                    
                    // Get soal from arsip
                    $stmt = $pdo->prepare("SELECT * FROM arsip_soal_item WHERE id = ? AND id_arsip_soal = ?");
                    $stmt->execute([$soal_id, $pool_id]);
                    $soal = $stmt->fetch();
                    
                    if ($soal) {
                        // Insert soal to ujian
                        $stmt_insert = $pdo->prepare("INSERT INTO soal 
                                                      (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_insert->execute([
                            $ujian_id,
                            $soal['pertanyaan'],
                            $soal['tipe_soal'],
                            $soal['opsi_json'],
                            $soal['kunci_jawaban'],
                            $soal['bobot'],
                            $urutan++,
                            $soal['gambar'],
                            $soal['media_type']
                        ]);
                        $new_soal_id = $pdo->lastInsertId();
                        
                        // Copy matching items if exists
                        $stmt_matching = $pdo->prepare("SELECT * FROM arsip_soal_matching WHERE id_arsip_soal_item = ?");
                        $stmt_matching->execute([$soal_id]);
                        $matching_items = $stmt_matching->fetchAll();
                        
                        if (!empty($matching_items) && table_exists('soal_matching')) {
                            foreach ($matching_items as $item) {
                                $stmt_match_insert = $pdo->prepare("INSERT INTO soal_matching 
                                                                   (id_soal, item_kiri, item_kanan, urutan) 
                                                                   VALUES (?, ?, ?, ?)");
                                $stmt_match_insert->execute([
                                    $new_soal_id,
                                    $item['item_kiri'],
                                    $item['item_kanan'],
                                    $item['urutan']
                                ]);
                            }
                        }
                    }
                }
                
                $pdo->commit();
                
                log_activity('create_ujian_from_pool', 'ujian', $ujian_id);
                
                redirect('guru/ujian/detail.php?id=' . $ujian_id);
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Create ujian from pool error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat membuat ujian: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Buat Ujian dari Arsip Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

// Get mapel for this guru
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Get arsip soal list (aktif only)
$pool_list = [];
if (!empty($mapel_list)) {
    $mapel_ids = array_column($mapel_list, 'id');
    $placeholders = implode(',', array_fill(0, count($mapel_ids), '?'));
    
    $stmt = $pdo->prepare("SELECT ps.*, m.nama_mapel 
                           FROM arsip_soal ps
                           INNER JOIN mapel m ON ps.id_mapel = m.id
                           WHERE ps.id_mapel IN ($placeholders) 
                           AND ps.status = 'aktif'
                           ORDER BY ps.nama_pool ASC");
    $stmt->execute($mapel_ids);
    $pool_list = $stmt->fetchAll();
}

// Get soal from selected arsip
$pool_id = intval($_GET['pool_id'] ?? 0);
$soal_list = [];
if ($pool_id) {
    $stmt = $pdo->prepare("SELECT * FROM arsip_soal_item 
                           WHERE id_arsip_soal = ? 
                           ORDER BY urutan ASC, id ASC");
    $stmt->execute([$pool_id]);
    $soal_list = $stmt->fetchAll();
    
    // Get arsip info
    $stmt = $pdo->prepare("SELECT ps.*, m.nama_mapel FROM arsip_soal ps
                           INNER JOIN mapel m ON ps.id_mapel = m.id
                           WHERE ps.id = ?");
    $stmt->execute([$pool_id]);
    $selected_pool = $stmt->fetch();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Ujian dari Arsip Soal</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (empty($mapel_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Anda belum di-assign ke mata pelajaran.</strong><br>
        Hubungi admin untuk assign mata pelajaran yang akan Anda ajarkan.
    </div>
<?php elseif (empty($pool_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Belum ada arsip soal aktif.</strong><br>
        Arsip soal harus dibuat terlebih dahulu oleh admin/operator.
    </div>
<?php else: ?>
    <form method="POST" id="createUjianForm">
        <div class="row g-3">
            <!-- Arsip Soal Selection -->
            <div class="col-12">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Pilih Arsip Soal</h5>
                        <select class="form-select" name="pool_id" id="poolSelect" required onchange="window.location.href='?pool_id=' + this.value">
                            <option value="">Pilih Arsip Soal</option>
                            <?php foreach ($pool_list as $pool): ?>
                                <option value="<?php echo $pool['id']; ?>" 
                                        <?php echo $pool_id == $pool['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($pool['nama_pool']); ?> - <?php echo escape($pool['nama_mapel']); ?> 
                                    (<?php echo $pool['total_soal']; ?> soal)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="pool_id" value="<?php echo $pool_id; ?>">
                    </div>
                </div>
            </div>
            
            <?php if ($pool_id && $selected_pool): ?>
                <!-- Ujian Info -->
                <div class="col-md-6">
                    <label class="form-label">Judul Ujian <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="judul" 
                           value="<?php echo escape($_POST['judul'] ?? ''); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_mapel" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id']; ?>" 
                                    <?php echo (isset($_POST['id_mapel']) && $_POST['id_mapel'] == $mapel['id']) || $selected_pool['id_mapel'] == $mapel['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($mapel['nama_mapel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="durasi" 
                           value="<?php echo escape($_POST['durasi'] ?? '60'); ?>" min="1" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" rows="2"><?php echo escape($_POST['deskripsi'] ?? ''); ?></textarea>
                </div>
                
                <!-- Soal Selection -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-list"></i> Pilih Soal dari Arsip
                                <small class="float-end">
                                    <button type="button" class="btn btn-sm btn-light" onclick="selectAll()">Pilih Semua</button>
                                    <button type="button" class="btn btn-sm btn-light" onclick="deselectAll()">Batal Pilih</button>
                                </small>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($soal_list)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> Arsip ini belum memiliki soal.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="50">
                                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll()">
                                                </th>
                                                <th width="50">No</th>
                                                <th>Pertanyaan</th>
                                                <th width="150">Tipe Soal</th>
                                                <th width="100">Bobot</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($soal_list as $index => $soal): ?>
                                                <tr>
                                                    <td>
                                                        <input type="checkbox" name="selected_soal[]" 
                                                               value="<?php echo $soal['id']; ?>" 
                                                               class="soal-checkbox">
                                                    </td>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <?php 
                                                        $pertanyaan = strip_tags($soal['pertanyaan']);
                                                        echo escape(mb_substr($pertanyaan, 0, 80)) . (mb_strlen($pertanyaan) > 80 ? '...' : ''); 
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $tipe_labels = [
                                                            'pilihan_ganda' => 'Pilihan Ganda',
                                                            'benar_salah' => 'Benar/Salah',
                                                            'essay' => 'Essay',
                                                            'matching' => 'Matching',
                                                            'isian_singkat' => 'Isian Singkat'
                                                        ];
                                                        echo $tipe_labels[$soal['tipe_soal']] ?? ucfirst($soal['tipe_soal']);
                                                        ?>
                                                    </td>
                                                    <td><?php echo number_format($soal['bobot'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <strong>Total soal dipilih: <span id="selectedCount">0</span> dari <?php echo count($soal_list); ?> soal</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat Ujian
                    </button>
                    <a href="<?php echo base_url('guru/ujian/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </form>
<?php endif; ?>

<script>
function selectAll() {
    document.querySelectorAll('.soal-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.soal-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function toggleAll() {
    const selectAll = document.getElementById('selectAllCheckbox').checked;
    document.querySelectorAll('.soal-checkbox').forEach(cb => cb.checked = selectAll);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.soal-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Update count on checkbox change
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.soal-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    updateSelectedCount();
});

// Validate form
document.getElementById('createUjianForm')?.addEventListener('submit', function(e) {
    const selectedCount = document.querySelectorAll('.soal-checkbox:checked').length;
    if (selectedCount === 0) {
        e.preventDefault();
        alert('Pilih minimal satu soal dari arsip!');
        return false;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

