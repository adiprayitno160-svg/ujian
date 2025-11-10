<?php
/**
 * Bulk Operations - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Bulk operations untuk ujian: archive, delete, publish, dll
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Bulk Operations - Ujian';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $ujian_ids = $_POST['ujian_ids'] ?? [];
    
    if (empty($ujian_ids)) {
        $_SESSION['error_message'] = 'Tidak ada ujian yang dipilih';
        redirect('admin/ujian/bulk_operations.php');
    }
    
    try {
        $pdo->beginTransaction();
        
        $placeholders = implode(',', array_fill(0, count($ujian_ids), '?'));
        
        switch ($action) {
            case 'archive':
                $stmt = $pdo->prepare("UPDATE ujian SET archived_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($ujian_ids);
                $_SESSION['success_message'] = count($ujian_ids) . ' ujian di-archive';
                break;
                
            case 'unarchive':
                $stmt = $pdo->prepare("UPDATE ujian SET archived_at = NULL WHERE id IN ($placeholders)");
                $stmt->execute($ujian_ids);
                $_SESSION['success_message'] = count($ujian_ids) . ' ujian di-unarchive';
                break;
                
            case 'publish':
                $stmt = $pdo->prepare("UPDATE ujian SET status = 'published' WHERE id IN ($placeholders)");
                $stmt->execute($ujian_ids);
                $_SESSION['success_message'] = count($ujian_ids) . ' ujian di-publish';
                break;
                
            case 'draft':
                $stmt = $pdo->prepare("UPDATE ujian SET status = 'draft' WHERE id IN ($placeholders)");
                $stmt->execute($ujian_ids);
                $_SESSION['success_message'] = count($ujian_ids) . ' ujian di-set ke draft';
                break;
                
            case 'delete':
                // Check if ujian has sesi or nilai
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sesi_ujian WHERE id_ujian IN ($placeholders)");
                $stmt->execute($ujian_ids);
                $has_sesi = $stmt->fetch()['count'] > 0;
                
                if ($has_sesi) {
                    $_SESSION['error_message'] = 'Tidak bisa menghapus ujian yang sudah memiliki sesi';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM ujian WHERE id IN ($placeholders)");
                    $stmt->execute($ujian_ids);
                    $_SESSION['success_message'] = count($ujian_ids) . ' ujian dihapus';
                }
                break;
                
            default:
                $_SESSION['error_message'] = 'Action tidak valid';
        }
        
        $pdo->commit();
        redirect('admin/ujian/bulk_operations.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bulk operations error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Terjadi kesalahan: ' . $e->getMessage();
        redirect('admin/ujian/bulk_operations.php');
    }
}

// Get filter
$filter_status = $_GET['status'] ?? 'all';
$filter_archived = $_GET['archived'] ?? 'no';
$filter_mapel = intval($_GET['mapel_id'] ?? 0);
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT u.*, m.nama_mapel, u2.nama as nama_guru, 
        (SELECT COUNT(*) FROM sesi_ujian WHERE id_ujian = u.id) as total_sesi,
        (SELECT COUNT(*) FROM nilai WHERE id_ujian = u.id) as total_nilai
        FROM ujian u 
        LEFT JOIN mapel m ON u.id_mapel = m.id 
        LEFT JOIN users u2 ON u.id_guru = u2.id 
        WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $sql .= " AND u.status = ?";
    $params[] = $filter_status;
}

if ($filter_archived === 'yes') {
    $sql .= " AND u.archived_at IS NOT NULL";
} elseif ($filter_archived === 'no') {
    $sql .= " AND u.archived_at IS NULL";
}

if ($filter_mapel > 0) {
    $sql .= " AND u.id_mapel = ?";
    $params[] = $filter_mapel;
}

if (!empty($search)) {
    $sql .= " AND (u.judul LIKE ? OR u.deskripsi LIKE ?)";
    $search_term = '%' . $search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ujian_list = $stmt->fetchAll();

// Get mapel list
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel");
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">
            <i class="fas fa-tasks"></i> Bulk Operations - Ujian
        </h2>
        <p class="text-muted mb-0">Kelola multiple ujian sekaligus</p>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="draft" <?php echo $filter_status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $filter_status === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Archived</label>
                        <select name="archived" class="form-select">
                            <option value="no" <?php echo $filter_archived === 'no' ? 'selected' : ''; ?>>Tidak Di-archive</option>
                            <option value="yes" <?php echo $filter_archived === 'yes' ? 'selected' : ''; ?>>Di-archive</option>
                            <option value="all" <?php echo $filter_archived === 'all' ? 'selected' : ''; ?>>Semua</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="mapel_id" class="form-select">
                            <option value="0">Semua Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($mapel['nama_mapel']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Cari</label>
                        <input type="text" name="search" class="form-control" value="<?php echo escape($search); ?>" placeholder="Cari ujian...">
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions -->
<form method="POST" action="" id="bulkForm" onsubmit="return confirm('Apakah Anda yakin ingin melakukan action ini?');">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                <i class="fas fa-check-square"></i> Pilih Semua
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                                <i class="fas fa-square"></i> Batal Pilih
                            </button>
                            <span class="ms-2 text-muted">
                                <span id="selectedCount">0</span> ujian dipilih
                            </span>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <select name="action" class="form-select form-select-sm" style="width: auto;" required>
                                <option value="">Pilih Action</option>
                                <option value="publish">Publish</option>
                                <option value="draft">Set Draft</option>
                                <option value="archive">Archive</option>
                                <option value="unarchive">Unarchive</option>
                                <option value="delete">Hapus</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-play"></i> Execute
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ujian List -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Daftar Ujian (<?php echo count($ujian_list); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($ujian_list)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                    </th>
                                    <th>Judul</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Guru</th>
                                    <th>Status</th>
                                    <th>Sesi</th>
                                    <th>Nilai</th>
                                    <th>Archived</th>
                                    <th>Created</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ujian_list as $ujian): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="ujian_ids[]" value="<?php echo $ujian['id']; ?>" 
                                               class="ujian-checkbox" onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <strong><?php echo escape($ujian['judul']); ?></strong>
                                        <?php if (!empty($ujian['deskripsi'])): ?>
                                        <br><small class="text-muted"><?php echo escape(substr($ujian['deskripsi'], 0, 50)); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($ujian['nama_mapel'] ?? '-'); ?></td>
                                    <td><?php echo escape($ujian['nama_guru'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $ujian['status'] === 'published' ? 'success' : 
                                                ($ujian['status'] === 'draft' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo ucfirst($ujian['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $ujian['total_sesi']; ?></td>
                                    <td><?php echo $ujian['total_nilai']; ?></td>
                                    <td>
                                        <?php if ($ujian['archived_at']): ?>
                                        <span class="badge bg-secondary">Archived</span>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo format_date($ujian['created_at']); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $ujian['id']); ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Tidak ada ujian yang ditemukan.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function selectAll() {
    document.querySelectorAll('.ujian-checkbox').forEach(cb => cb.checked = true);
    document.getElementById('selectAllCheckbox').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    document.querySelectorAll('.ujian-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateSelectedCount();
}

function toggleAll(checkbox) {
    document.querySelectorAll('.ujian-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.ujian-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = count;
}

// Update count on load
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedCount();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

