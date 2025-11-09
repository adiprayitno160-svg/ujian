<?php
/**
 * List Ujian - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

// Allow guru and operator (with operator access)
if ($_SESSION['role'] !== 'guru' && !has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Daftar Ujian';
$role_css = $_SESSION['role'] === 'guru' ? 'guru' : 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get ujian
$search = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? '');

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

// For guru: only show their own ujian
// For operator: show all ujian
if ($_SESSION['role'] === 'guru') {
    $where = "id_guru = ?";
    $params = [$_SESSION['user_id']];
} else {
    // Operator can see all ujian
    $where = "1=1";
    $params = [];
}

if ($search) {
    $where .= " AND (judul LIKE ? OR deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
}

$stmt = $pdo->prepare("SELECT u.*, m.nama_mapel, 
                      (SELECT COUNT(*) FROM soal WHERE id_ujian = u.id) as total_soal,
                      (SELECT COUNT(*) FROM sesi_ujian WHERE id_ujian = u.id) as total_sesi
                      FROM ujian u
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE $where
                      ORDER BY u.created_at DESC");
$stmt->execute($params);
$ujian_list = $stmt->fetchAll();
?>

<?php if (isset($_GET['success']) && $_GET['success'] === 'ujian_deleted'): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> Ujian berhasil dihapus!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'delete_failed'): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> Gagal menghapus ujian. Silakan coba lagi.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar Ujian</h2>
            <?php if ($_SESSION['role'] === 'guru'): ?>
                <a href="<?php echo base_url('guru/ujian/create.php'); ?>" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Buat Ujian Baru
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Cari ujian..." value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $status_filter === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo base_url('guru/ujian/list.php'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($ujian_list)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada ujian. <a href="<?php echo base_url('guru/ujian/create.php'); ?>">Buat ujian baru</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($ujian_list as $ujian): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo escape($ujian['judul']); ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-book"></i> <?php echo escape($ujian['nama_mapel']); ?>
                    </p>
                    <p class="text-muted small mb-3">
                        <?php echo escape($ujian['deskripsi'] ? substr($ujian['deskripsi'], 0, 100) . '...' : '-'); ?>
                    </p>
                    <div class="d-flex justify-content-between mb-3">
                        <span class="badge bg-<?php 
                            echo $ujian['status'] === 'published' ? 'success' : 
                                ($ujian['status'] === 'completed' ? 'info' : 'secondary'); 
                        ?>">
                            <?php echo ucfirst($ujian['status']); ?>
                        </span>
                        <span class="text-muted">
                            <i class="fas fa-clock"></i> <?php echo $ujian['durasi']; ?> menit
                        </span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-file-alt"></i> <?php echo $ujian['total_soal']; ?> soal | 
                            <i class="fas fa-calendar"></i> <?php echo $ujian['total_sesi']; ?> sesi
                        </small>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $ujian['id']); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> Detail
                        </a>
                        <a href="<?php echo base_url('guru/ujian/settings.php?id=' . $ujian['id']); ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="<?php echo base_url('guru/ujian/delete.php?id=' . $ujian['id']); ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Yakin hapus ujian ini? Tindakan ini tidak dapat dibatalkan!');">
                            <i class="fas fa-trash"></i> Hapus
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
