<?php
/**
 * Real-time Monitoring - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('operator');
check_session_timeout();

$sesi_id = intval($_GET['sesi_id'] ?? 0);

$page_title = 'Real-time Monitoring';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get sesi list if no specific sesi
if (!$sesi_id) {
    $stmt = $pdo->query("SELECT s.*, u.judul FROM sesi_ujian s
                        INNER JOIN ujian u ON s.id_ujian = u.id
                        WHERE s.status = 'aktif'
                        ORDER BY s.waktu_mulai DESC");
    $sesi_list = $stmt->fetchAll();
} else {
    $sesi = get_sesi($sesi_id);
}

// Get participants status if sesi selected
$participants = [];
if ($sesi_id) {
    $stmt = $pdo->prepare("SELECT n.*, u.nama, u.username,
                          (SELECT COUNT(*) FROM jawaban_siswa WHERE id_sesi = n.id_sesi AND id_siswa = n.id_siswa) as total_jawaban
                          FROM nilai n
                          INNER JOIN users u ON n.id_siswa = u.id
                          WHERE n.id_sesi = ?
                          ORDER BY u.nama ASC");
    $stmt->execute([$sesi_id]);
    $participants = $stmt->fetchAll();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Real-time Monitoring</h2>
    </div>
</div>

<?php if (!$sesi_id): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar"></i> Pilih Sesi</h5>
    </div>
    <div class="card-body">
        <div class="list-group">
            <?php foreach ($sesi_list as $s): ?>
            <a href="<?php echo base_url('operator/monitoring/realtime.php?sesi_id=' . $s['id']); ?>" 
               class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="mb-1"><?php echo escape($s['nama_sesi']); ?></h6>
                        <small><?php echo escape($s['judul']); ?></small>
                    </div>
                    <span class="badge bg-success">Aktif</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-chart-line"></i> Monitoring: <?php echo escape($sesi['nama_sesi']); ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="text-center">
                    <h3 class="text-primary"><?php echo count($participants); ?></h3>
                    <p class="text-muted mb-0">Total Peserta</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3 class="text-success">
                        <?php echo count(array_filter($participants, function($p) { return $p['status'] === 'sedang_mengerjakan'; })); ?>
                    </h3>
                    <p class="text-muted mb-0">Sedang Mengerjakan</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3 class="text-info">
                        <?php echo count(array_filter($participants, function($p) { return $p['status'] === 'selesai'; })); ?>
                    </h3>
                    <p class="text-muted mb-0">Selesai</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3 class="text-warning">
                        <?php echo count(array_filter($participants, function($p) { return $p['warning_count'] > 0; })); ?>
                    </h3>
                    <p class="text-muted mb-0">Warning</p>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Jawaban</th>
                        <th>Warning</th>
                        <th>Waktu Mulai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $p): ?>
                    <tr>
                        <td><?php echo escape($p['nama']); ?></td>
                        <td><?php echo escape($p['username']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $p['status'] === 'selesai' ? 'success' : 
                                    ($p['status'] === 'sedang_mengerjakan' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo escape($p['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $p['total_jawaban']; ?></td>
                        <td>
                            <?php if ($p['warning_count'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $p['warning_count']; ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $p['waktu_mulai'] ? format_date($p['waktu_mulai']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-refresh every 10 seconds
setTimeout(function() {
    location.reload();
}, 10000);
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

