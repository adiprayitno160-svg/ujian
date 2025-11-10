<?php
/**
 * List Tugas - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Daftar Tugas';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$filter_status = $_GET['status'] ?? '';
$filter_mapel = $_GET['mapel_id'] ?? '';

// Get Tugas
$filters = [];
if ($filter_status) $filters['status'] = $filter_status;
if ($filter_mapel) $filters['mapel_id'] = $filter_mapel;

$tugas_list = get_tugas_by_guru($_SESSION['user_id'], $filters);

// Get mapel for filter
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar Tugas</h2>
            <a href="<?php echo base_url('guru/tugas/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Tugas Baru
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="published" <?php echo $filter_status === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="mapel_id" class="form-label">Mata Pelajaran</label>
                <select class="form-select" id="mapel_id" name="mapel_id">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($tugas_list)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada Tugas. <a href="<?php echo base_url('guru/tugas/create.php'); ?>">Buat Tugas baru</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($tugas_list as $tugas): 
            $now = new DateTime();
            $deadline = new DateTime($tugas['deadline']);
            $is_overdue = $now > $deadline;
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 <?php echo $is_overdue ? 'border-warning' : ''; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0"><?php echo escape($tugas['judul']); ?></h5>
                        <span class="badge bg-<?php echo $tugas['status'] === 'published' ? 'success' : ($tugas['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($tugas['status']); ?>
                        </span>
                    </div>
                    <p class="text-muted mb-2">
                        <i class="fas fa-book"></i> <?php echo escape($tugas['nama_mapel']); ?>
                    </p>
                    <p class="text-muted small mb-2">
                        <i class="fas fa-calendar"></i> Deadline: 
                        <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo format_date($tugas['deadline']); ?>
                        </span>
                    </p>
                    <p class="text-muted small mb-2">
                        <i class="fas fa-star"></i> Poin: <?php echo number_format($tugas['poin_maksimal'], 0); ?>
                    </p>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-users"></i> <?php echo $tugas['total_submission']; ?> submission | 
                            <i class="fas fa-check"></i> <?php echo $tugas['sudah_dikumpulkan']; ?> dikumpulkan
                        </small>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?php echo base_url('guru/tugas/review.php?id=' . $tugas['id']); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> Review
                        </a>
                        <a href="<?php echo base_url('guru/tugas/detail.php?id=' . $tugas['id']); ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-info-circle"></i> Detail
                        </a>
                        <a href="<?php echo base_url('guru/tugas/edit.php?id=' . $tugas['id']); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="<?php echo base_url('guru/tugas/delete.php?id=' . $tugas['id']); ?>" class="btn btn-sm btn-outline-danger" 
                           onclick="return confirm('Apakah Anda yakin ingin menghapus Tugas ini?');">
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



