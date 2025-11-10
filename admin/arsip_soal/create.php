<?php
/**
 * Create Arsip Soal - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

global $pdo;

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pool = sanitize($_POST['nama_pool'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $tingkat_kelas = sanitize($_POST['tingkat_kelas'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $status = sanitize($_POST['status'] ?? 'draft');
    
    if (empty($nama_pool) || !$id_mapel) {
        $error = 'Nama arsip dan mata pelajaran harus diisi';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO arsip_soal 
                                  (nama_pool, id_mapel, tingkat_kelas, deskripsi, status, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nama_pool, $id_mapel, $tingkat_kelas ?: null, $deskripsi, $status, $_SESSION['user_id']]);
            $pool_id = $pdo->lastInsertId();
            
            log_activity('create_arsip_soal', 'arsip_soal', $pool_id);
            
            redirect('admin/arsip_soal/detail.php?id=' . $pool_id);
        } catch (PDOException $e) {
            error_log("Create arsip soal error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat arsip soal';
        }
    }
}

$page_title = 'Buat Arsip Soal Baru';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

// Get mapel list
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Buat Arsip Soal Baru</h2>
            <a href="<?php echo base_url('admin/arsip_soal/list.php'); ?>" class="btn btn-secondary">
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

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Pool <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nama_pool" 
                           value="<?php echo escape($_POST['nama_pool'] ?? ''); ?>" 
                           placeholder="Contoh: Ujian A, Ujian B, dll" required>
                    <small class="text-muted">Nama untuk mengidentifikasi arsip soal</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select class="form-select" name="id_mapel" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id']; ?>" 
                                    <?php echo (isset($_POST['id_mapel']) && $_POST['id_mapel'] == $mapel['id']) ? 'selected' : ''; ?>>
                                <?php echo escape($mapel['nama_mapel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tingkat Kelas</label>
                    <select class="form-select" name="tingkat_kelas">
                        <option value="">Semua Tingkat</option>
                        <option value="7" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] == '7') ? 'selected' : ''; ?>>Kelas 7</option>
                        <option value="8" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] == '8') ? 'selected' : ''; ?>>Kelas 8</option>
                        <option value="9" <?php echo (isset($_POST['tingkat_kelas']) && $_POST['tingkat_kelas'] == '9') ? 'selected' : ''; ?>>Kelas 9</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="aktif" <?php echo (isset($_POST['status']) && $_POST['status'] == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                        <option value="arsip" <?php echo (isset($_POST['status']) && $_POST['status'] == 'arsip') ? 'selected' : ''; ?>>Arsip</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" rows="3" 
                              placeholder="Deskripsi arsip soal (opsional)"><?php echo escape($_POST['deskripsi'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Arsip Soal
                    </button>
                    <a href="<?php echo base_url('admin/arsip_soal/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

