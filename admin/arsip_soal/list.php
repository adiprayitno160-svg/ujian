<?php
/**
 * List Arsip Soal - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

$page_title = 'Arsip Soal';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$filter_mapel = intval($_GET['mapel'] ?? 0);
$filter_status = $_GET['status'] ?? '';
$filter_tingkat = $_GET['tingkat'] ?? '';

// Build query
$sql = "SELECT ps.*, m.nama_mapel, u.nama as created_by_name
        FROM arsip_soal ps
        INNER JOIN mapel m ON ps.id_mapel = m.id
        INNER JOIN users u ON ps.created_by = u.id
        WHERE 1=1";
$params = [];

if ($filter_mapel) {
    $sql .= " AND ps.id_mapel = ?";
    $params[] = $filter_mapel;
}

if ($filter_status) {
    $sql .= " AND ps.status = ?";
    $params[] = $filter_status;
}

if ($filter_tingkat) {
    $sql .= " AND ps.tingkat_kelas = ?";
    $params[] = $filter_tingkat;
}

$sql .= " ORDER BY ps.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pool_list = $stmt->fetchAll();

// Get mapel list for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Arsip Soal</h2>
            <a href="<?php echo base_url('admin/arsip_soal/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Arsip Baru
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Mata Pelajaran</label>
                <select class="form-select" name="mapel">
                    <option value="">Semua Mapel</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $filter_status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="aktif" <?php echo $filter_status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="arsip" <?php echo $filter_status == 'arsip' ? 'selected' : ''; ?>>Arsip</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tingkat Kelas</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua Tingkat</option>
                    <option value="7" <?php echo $filter_tingkat == '7' ? 'selected' : ''; ?>>Kelas 7</option>
                    <option value="8" <?php echo $filter_tingkat == '8' ? 'selected' : ''; ?>>Kelas 8</option>
                    <option value="9" <?php echo $filter_tingkat == '9' ? 'selected' : ''; ?>>Kelas 9</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Pool List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($pool_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada arsip soal. 
                <a href="<?php echo base_url('admin/arsip_soal/create.php'); ?>">Buat arsip baru</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pool</th>
                            <th>Mata Pelajaran</th>
                            <th>Tingkat</th>
                            <th>Total Soal</th>
                            <th>Status</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pool_list as $index => $pool): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($pool['nama_pool']); ?></strong></td>
                                <td><?php echo escape($pool['nama_mapel']); ?></td>
                                <td>
                                    <?php if ($pool['tingkat_kelas']): ?>
                                        <span class="badge bg-info">Kelas <?php echo escape($pool['tingkat_kelas']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo $pool['total_soal']; ?> soal</strong></td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'draft' => 'secondary',
                                        'aktif' => 'success',
                                        'arsip' => 'dark'
                                    ];
                                    $status_label = [
                                        'draft' => 'Draft',
                                        'aktif' => 'Aktif',
                                        'arsip' => 'Arsip'
                                    ];
                                    $badge_class = $status_badge[$pool['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo $status_label[$pool['status']] ?? ucfirst($pool['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo escape($pool['created_by_name']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($pool['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo base_url('admin/arsip_soal/detail.php?id=' . $pool['id']); ?>" 
                                           class="btn btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo base_url('admin/arsip_soal/import.php?id=' . $pool['id']); ?>" 
                                           class="btn btn-warning" title="Import Soal">
                                            <i class="fas fa-upload"></i>
                                        </a>
                                        <a href="<?php echo base_url('admin/arsip_soal/edit.php?id=' . $pool['id']); ?>" 
                                           class="btn btn-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo base_url('admin/arsip_soal/delete.php?id=' . $pool['id']); ?>" 
                                           class="btn btn-danger" title="Hapus"
                                           onclick="return confirm('Apakah Anda yakin ingin menghapus pool ini?');">
                                            <i class="fas fa-trash"></i>
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

