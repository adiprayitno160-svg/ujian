<?php
/**
 * Dashboard - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Dashboard Guru';
$role_css = 'guru';
include __DIR__ . '/../includes/header.php';

// Get statistics
global $pdo;

$stats = [
    'total_ujian' => 0,
    'active_sesi' => 0,
    'total_pr' => 0,
    'pending_review' => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ujian WHERE id_guru = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_ujian'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sesi_ujian s
                          INNER JOIN ujian u ON s.id_ujian = u.id
                          WHERE u.id_guru = ? AND s.status = 'aktif'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['active_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr WHERE id_guru = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_pr'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr_submission ps
                          INNER JOIN pr p ON ps.id_pr = p.id
                          WHERE p.id_guru = ? AND ps.status = 'sudah_dikumpulkan'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_review'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <p class="text-muted mb-0">Selamat datang, <strong><?php echo escape($_SESSION['nama']); ?></strong>!</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-file-alt fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Ujian</h6>
                        <h3 class="mb-0"><?php echo $stats['total_ujian']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-calendar-check fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Sesi Aktif</h6>
                        <h3 class="mb-0"><?php echo $stats['active_sesi']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-tasks fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total PR</h6>
                        <h3 class="mb-0"><?php echo $stats['total_pr']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-clipboard-check fa-2x text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Menunggu Review</h6>
                        <h3 class="mb-0"><?php echo $stats['pending_review']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

