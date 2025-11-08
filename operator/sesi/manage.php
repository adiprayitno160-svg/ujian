<?php
/**
 * Manage Sesi - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('operator');
check_session_timeout();

$sesi_id = intval($_GET['id'] ?? 0);
if (!$sesi_id) {
    redirect('operator/sesi/list.php');
}

$sesi = get_sesi($sesi_id);
if (!$sesi) {
    redirect('operator/sesi/list.php');
}

$page_title = 'Manage Sesi';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get peserta
$stmt = $pdo->prepare("SELECT sp.*, 
                      CASE 
                          WHEN sp.tipe_assign = 'individual' THEN u.nama
                          WHEN sp.tipe_assign = 'kelas' THEN k.nama_kelas
                      END as nama_peserta
                      FROM sesi_peserta sp
                      LEFT JOIN users u ON sp.id_user = u.id AND sp.tipe_assign = 'individual'
                      LEFT JOIN kelas k ON sp.id_kelas = k.id AND sp.tipe_assign = 'kelas'
                      WHERE sp.id_sesi = ?");
$stmt->execute([$sesi_id]);
$peserta_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Manage Sesi</h2>
        <p class="text-muted"><?php echo escape($sesi['nama_sesi']); ?> - <?php echo escape($sesi['judul_ujian']); ?></p>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sesi</h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-4">Nama Sesi:</dt>
                    <dd class="col-sm-8"><?php echo escape($sesi['nama_sesi']); ?></dd>
                    
                    <dt class="col-sm-4">Ujian:</dt>
                    <dd class="col-sm-8"><?php echo escape($sesi['judul_ujian']); ?></dd>
                    
                    <dt class="col-sm-4">Waktu Mulai:</dt>
                    <dd class="col-sm-8"><?php echo format_date($sesi['waktu_mulai']); ?></dd>
                    
                    <dt class="col-sm-4">Waktu Selesai:</dt>
                    <dd class="col-sm-8"><?php echo format_date($sesi['waktu_selesai']); ?></dd>
                    
                    <dt class="col-sm-4">Durasi:</dt>
                    <dd class="col-sm-8"><?php echo $sesi['durasi']; ?> menit</dd>
                    
                    <dt class="col-sm-4">Status:</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo $sesi['status'] === 'aktif' ? 'success' : 'secondary'; ?>">
                            <?php echo escape($sesi['status']); ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Peserta (<?php echo count($peserta_list); ?>)</h5>
                    <a href="<?php echo base_url('operator/sesi/assign_peserta.php?sesi_id=' . $sesi_id); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-user-plus"></i> Assign Peserta
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($peserta_list)): ?>
                <p class="text-muted text-center py-3">Belum ada peserta</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Peserta</th>
                                <th>Tipe</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peserta_list as $peserta): ?>
                            <tr>
                                <td><?php echo escape($peserta['nama_peserta']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $peserta['tipe_assign'] === 'individual' ? 'primary' : 'info'; ?>">
                                        <?php echo escape($peserta['tipe_assign']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo base_url('operator/sesi/assign_peserta.php?sesi_id=' . $sesi_id); ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Assign Peserta
                    </a>
                    <a href="<?php echo base_url('operator/sesi/manage_token.php?sesi_id=' . $sesi_id); ?>" class="btn btn-warning">
                        <i class="fas fa-key"></i> Kelola Token
                    </a>
                    <a href="<?php echo base_url('operator/monitoring/realtime.php?sesi_id=' . $sesi_id); ?>" class="btn btn-success">
                        <i class="fas fa-chart-line"></i> Monitoring
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

