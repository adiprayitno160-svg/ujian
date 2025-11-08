<?php
/**
 * Dashboard - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Check if user has operator access (admin or guru with is_operator = 1)
if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Dashboard Operator';
include __DIR__ . '/../includes/header.php';

// Get statistics
global $pdo;

$stats = [
    'active_sesi' => 0,
    'total_sesi' => 0,
    'total_peserta' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_ujian WHERE status = 'aktif'");
    $stats['active_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_ujian");
    $stats['total_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_peserta");
    $stats['total_peserta'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Dashboard Operator</h2>
        <p class="text-muted">Selamat datang, <?php echo escape($_SESSION['nama']); ?>!</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
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
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-calendar fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Sesi</h6>
                        <h3 class="mb-0"><?php echo $stats['total_sesi']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-users fa-2x text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Peserta</h6>
                        <h3 class="mb-0"><?php echo $stats['total_peserta']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>

