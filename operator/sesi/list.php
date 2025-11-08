<?php
/**
 * List Sesi - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('operator');
check_session_timeout();

$page_title = 'Daftar Sesi';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get all sesi
$status_filter = $_GET['status'] ?? '';

$sql = "SELECT s.*, u.judul as judul_ujian, m.nama_mapel, u2.nama as nama_guru,
        (SELECT COUNT(*) FROM sesi_peserta WHERE id_sesi = s.id) as total_peserta
        FROM sesi_ujian s
        INNER JOIN ujian u ON s.id_ujian = u.id
        INNER JOIN mapel m ON u.id_mapel = m.id
        INNER JOIN users u2 ON u.id_guru = u2.id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND s.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY s.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesi_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Daftar Sesi</h2>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $status_filter === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama Sesi</th>
                        <th>Ujian</th>
                        <th>Guru</th>
                        <th>Mata Pelajaran</th>
                        <th>Waktu Mulai</th>
                        <th>Peserta</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sesi_list)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Belum ada sesi</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($sesi_list as $sesi): ?>
                    <tr>
                        <td><strong><?php echo escape($sesi['nama_sesi']); ?></strong></td>
                        <td><?php echo escape($sesi['judul_ujian']); ?></td>
                        <td><?php echo escape($sesi['nama_guru']); ?></td>
                        <td><?php echo escape($sesi['nama_mapel']); ?></td>
                        <td><?php echo format_date($sesi['waktu_mulai']); ?></td>
                        <td><span class="badge bg-info"><?php echo $sesi['total_peserta']; ?></span></td>
                        <td>
                            <span class="badge bg-<?php echo $sesi['status'] === 'aktif' ? 'success' : 'secondary'; ?>">
                                <?php echo escape($sesi['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?php echo base_url('operator/sesi/manage.php?id=' . $sesi['id']); ?>" 
                                   class="btn btn-info" title="Manage">
                                    <i class="fas fa-cog"></i>
                                </a>
                                <a href="<?php echo base_url('operator/sesi/manage_token.php?sesi_id=' . $sesi['id']); ?>" 
                                   class="btn btn-warning" title="Token">
                                    <i class="fas fa-key"></i>
                                </a>
                                <a href="<?php echo base_url('operator/sesi/delete.php?id=' . $sesi['id']); ?>" 
                                   class="btn btn-danger" 
                                   onclick="return confirm('Apakah Anda yakin ingin menghapus sesi ini? Semua data terkait akan dihapus.');"
                                   title="Hapus Sesi">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

