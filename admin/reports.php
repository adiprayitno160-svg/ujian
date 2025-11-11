<?php
/**
 * System Reports - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'System Reports';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

// Get statistics
$stats = [
    'total_users' => 0,
    'total_guru' => 0,
    'total_siswa' => 0,
    'total_ujian' => 0,
    'total_kelas' => 0,
    'total_mapel' => 0,
    'total_sesi' => 0,
    'total_nilai' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'guru'");
    $stats['total_guru'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'");
    $stats['total_siswa'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ujian");
    $stats['total_ujian'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas WHERE status = 'active'");
    $stats['total_kelas'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mapel");
    $stats['total_mapel'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_ujian");
    $stats['total_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM nilai WHERE status = 'selesai'");
    $stats['total_nilai'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Reports stats error: " . $e->getMessage());
}

// Get recent activities
$stmt = $pdo->query("SELECT l.*, u.nama as user_nama 
                     FROM log_aktivitas l
                     LEFT JOIN users u ON l.id_user = u.id
                     ORDER BY l.waktu DESC
                     LIMIT 50");
$recent_activities = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">System Reports</h2>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary"><?php echo $stats['total_users']; ?></div>
                <small class="text-muted">Total Users</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success"><?php echo $stats['total_guru']; ?></div>
                <small class="text-muted">Total Guru</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info"><?php echo $stats['total_siswa']; ?></div>
                <small class="text-muted">Total Siswa</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-warning"><?php echo $stats['total_ujian']; ?></div>
                <small class="text-muted">Total Ujian</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-secondary"><?php echo $stats['total_kelas']; ?></div>
                <small class="text-muted">Total Kelas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-dark"><?php echo $stats['total_mapel']; ?></div>
                <small class="text-muted">Total Mata Pelajaran</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary"><?php echo $stats['total_sesi']; ?></div>
                <small class="text-muted">Total Sesi</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success"><?php echo $stats['total_nilai']; ?></div>
                <small class="text-muted">Total Nilai</small>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Activities</h5>
    </div>
    <div class="card-body">
        <?php if (empty($recent_activities)): ?>
            <p class="text-muted text-center">Tidak ada aktivitas</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activities as $activity): ?>
                        <tr>
                            <td><?php echo format_date($activity['waktu']); ?></td>
                            <td><?php echo escape($activity['user_nama'] ?? '-'); ?></td>
                            <td><?php echo escape($activity['aksi']); ?></td>
                            <td><?php echo escape($activity['deskripsi'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>




