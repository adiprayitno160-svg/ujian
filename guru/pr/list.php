<?php
/**
 * List PR - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Daftar PR';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get PR
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel,
                      (SELECT COUNT(*) FROM pr_submission WHERE id_pr = p.id) as total_submission,
                      (SELECT COUNT(*) FROM pr_submission WHERE id_pr = p.id AND status = 'sudah_dikumpulkan') as sudah_dikumpulkan,
                      (SELECT COUNT(*) FROM pr_submission WHERE id_pr = p.id AND status = 'dinilai') as sudah_dinilai
                      FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id_guru = ?
                      ORDER BY p.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pr_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar PR</h2>
            <a href="<?php echo base_url('guru/pr/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat PR Baru
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($pr_list)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada PR. <a href="<?php echo base_url('guru/pr/create.php'); ?>">Buat PR baru</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pr_list as $pr): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title"><?php echo escape($pr['judul']); ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-book"></i> <?php echo escape($pr['nama_mapel']); ?>
                    </p>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-calendar"></i> Deadline: <?php echo format_date($pr['deadline']); ?>
                    </p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-users"></i> <?php echo $pr['total_submission']; ?> submission | 
                            <i class="fas fa-check"></i> <?php echo $pr['sudah_dikumpulkan']; ?> dikumpulkan |
                            <i class="fas fa-star"></i> <?php echo $pr['sudah_dinilai']; ?> dinilai
                        </small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo base_url('guru/pr/review.php?id=' . $pr['id']); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> Review
                        </a>
                        <a href="<?php echo base_url('guru/pr/edit.php?id=' . $pr['id']); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="<?php echo base_url('guru/pr/delete.php?id=' . $pr['id']); ?>" class="btn btn-sm btn-outline-danger" 
                           onclick="return confirm('Apakah Anda yakin ingin menghapus PR ini?');">
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
