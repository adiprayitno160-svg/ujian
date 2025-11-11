<?php
/**
 * List Tugas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tugas_functions.php';

require_role('operator');
check_session_timeout();

$page_title = 'Daftar Tugas - Operator';
$role_css = 'operator';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$filter_status = $_GET['status'] ?? '';
$filter_mapel = $_GET['mapel_id'] ?? '';
$filter_guru = $_GET['guru_id'] ?? '';

// Get all Tugas (operator can see all)
$sql = "SELECT t.*, m.nama_mapel, u.nama as nama_guru,
        (SELECT COUNT(*) FROM tugas_submission WHERE id_tugas = t.id) as total_submission,
        (SELECT COUNT(*) FROM tugas_submission WHERE id_tugas = t.id AND status = 'sudah_dikumpulkan') as sudah_dikumpulkan
        FROM tugas t
        INNER JOIN mapel m ON t.id_mapel = m.id
        LEFT JOIN users u ON t.id_guru = u.id
        WHERE 1=1";
        
$params = [];

if ($filter_status) {
    $sql .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_mapel) {
    $sql .= " AND t.id_mapel = ?";
    $params[] = $filter_mapel;
}

if ($filter_guru) {
    $sql .= " AND t.id_guru = ?";
    $params[] = $filter_guru;
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tugas_list = $stmt->fetchAll();

// Get mapel for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get guru for filter
$stmt = $pdo->query("SELECT DISTINCT u.id, u.nama FROM users u INNER JOIN tugas t ON u.id = t.id_guru WHERE u.role = 'guru' ORDER BY u.nama ASC");
$guru_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar Tugas</h2>
            <a href="<?php echo base_url('operator-tugas-create'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Tugas Baru
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $filter_status === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label for="guru_id" class="form-label">Guru</label>
                <select class="form-select" id="guru_id" name="guru_id">
                    <option value="">Semua Guru</option>
                    <?php foreach ($guru_list as $guru): ?>
                        <option value="<?php echo $guru['id']; ?>" <?php echo $filter_guru == $guru['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($guru['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
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

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($tugas_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada tugas yang tersedia
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Submissions</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tugas_list as $tugas): ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($tugas['judul']); ?></strong>
                                <?php if ($tugas['deskripsi']): ?>
                                    <br><small class="text-muted"><?php echo escape(substr($tugas['deskripsi'], 0, 50)); ?>...</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($tugas['nama_mapel']); ?></td>
                            <td><?php echo escape($tugas['nama_guru'] ?? '-'); ?></td>
                            <td><?php echo format_date($tugas['deadline']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $tugas['status'] === 'published' ? 'success' : 
                                        ($tugas['status'] === 'draft' ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($tugas['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-info">
                                    <?php echo $tugas['sudah_dikumpulkan']; ?> / <?php echo $tugas['total_submission']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo base_url('operator-tugas-detail?id=' . $tugas['id']); ?>" 
                                       class="btn btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo base_url('operator-tugas-edit?id=' . $tugas['id']); ?>" 
                                       class="btn btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo base_url('operator-tugas-review?id=' . $tugas['id']); ?>" 
                                       class="btn btn-success" title="Review">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

