<?php
/**
 * Dashboard Assessment - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Dashboard Assessment';
include __DIR__ . '/../../includes/header.php';

global $pdo;
$tahun_ajaran = get_tahun_ajaran_aktif();

// Get statistics
$stats = [
    'total_sumatip' => 0,
    'sumatip_aktif' => 0,
    'sumatip_completed' => 0,
    'total_absensi' => 0,
    'total_jadwal' => 0,
    'bank_soal_pending' => 0
];

try {
    // Total SUMATIP
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ujian WHERE tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun')");
    $stmt->execute();
    $stats['total_sumatip'] = $stmt->fetch()['total'];
    
    // SUMATIP Aktif
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ujian WHERE tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun') AND status = 'published'");
    $stmt->execute();
    $stats['sumatip_aktif'] = $stmt->fetch()['total'];
    
    // SUMATIP Completed
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ujian WHERE tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun') AND status = 'completed'");
    $stmt->execute();
    $stats['sumatip_completed'] = $stmt->fetch()['total'];
    
    // Total Absensi
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM absensi_ujian");
    $stats['total_absensi'] = $stmt->fetch()['total'];
    
    // Total Jadwal
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM jadwal_assessment WHERE status = 'aktif'");
    $stats['total_jadwal'] = $stmt->fetch()['total'];
    
    // Bank Soal Pending
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bank_soal WHERE status = 'pending'");
    $stats['bank_soal_pending'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Dashboard assessment stats error: " . $e->getMessage());
}

// Get upcoming SUMATIP
$upcoming_sumatip = get_sumatip_list([
    'status' => 'published',
    'limit' => 5
]);
?>

<div class="row mb-4">
    <div class="col-12">
        <p class="text-muted mb-0">Selamat datang, <strong><?php echo escape($_SESSION['nama']); ?></strong>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-clipboard-check fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total SUMATIP</h6>
                        <h3 class="mb-0"><?php echo $stats['total_sumatip']; ?></h3>
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
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">SUMATIP Aktif</h6>
                        <h3 class="mb-0"><?php echo $stats['sumatip_aktif']; ?></h3>
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
                            <i class="fas fa-calendar fa-2x text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Jadwal Aktif</h6>
                        <h3 class="mb-0"><?php echo $stats['total_jadwal']; ?></h3>
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
                            <i class="fas fa-file-alt fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Bank Soal Pending</h6>
                        <h3 class="mb-0"><?php echo $stats['bank_soal_pending']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Menu Assessment</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?php echo base_url('operator-assessment-sumatip-list'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clipboard-check fa-2x text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">SUMATIP</h6>
                                <small class="text-muted">Kelola SUMATIP Assessment</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-bank-soal-list'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-book fa-2x text-info me-3"></i>
                            <div>
                                <h6 class="mb-0">Bank Soal</h6>
                                <small class="text-muted">Kelola bank soal dari semua guru</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-bank-soal-create-assessment'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-plus-circle fa-2x text-success me-3"></i>
                            <div>
                                <h6 class="mb-0">Buat Assessment dari Bank Soal</h6>
                                <small class="text-muted">Ambil soal dari bank soal untuk membuat assessment</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-manage-guru-soal'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-check fa-2x text-secondary me-3"></i>
                            <div>
                                <h6 class="mb-0">Kelola Permission Guru Soal</h6>
                                <small class="text-muted">Atur guru yang boleh membuat soal assessment</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-berita-acara-generate'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-alt fa-2x text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0">Berita Acara</h6>
                                <small class="text-muted">Generate dan print berita acara ujian</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-nilai-form'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-table fa-2x text-success me-3"></i>
                            <div>
                                <h6 class="mb-0">Lihat Nilai</h6>
                                <small class="text-muted">Lihat nilai seluruh mata pelajaran</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-jadwal-list'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar-alt fa-2x text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0">Jadwal Assessment</h6>
                                <small class="text-muted">Kelola jadwal assessment lengkap</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-absensi-list'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-check fa-2x text-danger me-3"></i>
                            <div>
                                <h6 class="mb-0">Absensi</h6>
                                <small class="text-muted">Kelola absensi digital</small>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> SUMATIP Terdekat</h5>
            </div>
            <div class="card-body">
                <?php if (empty($upcoming_sumatip)): ?>
                    <p class="text-muted text-center">Tidak ada SUMATIP yang akan datang</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_sumatip as $sumatip): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo escape($sumatip['judul']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo escape($sumatip['nama_mapel']); ?> | 
                                        <?php echo escape($sumatip['periode_sumatip'] ?? '-'); ?>
                                    </small>
                                </div>
                                <span class="badge <?php echo get_sumatip_badge_class($sumatip['tipe_asesmen']); ?>">
                                    <?php echo get_sumatip_badge_label($sumatip['tipe_asesmen']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



