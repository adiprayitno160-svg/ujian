<?php
/**
 * Dashboard - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/functions_sumatip.php';

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
    'total_peserta' => 0,
    'total_sumatip' => 0,
    'sumatip_aktif' => 0,
    'bank_soal_pending' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_ujian WHERE status = 'aktif'");
    $stats['active_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_ujian");
    $stats['total_sesi'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM sesi_peserta");
    $stats['total_peserta'] = $stmt->fetch()['total'];
    
    // SUMATIP stats
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ujian WHERE tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun')");
    $stats['total_sumatip'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ujian WHERE tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun') AND status = 'published'");
    $stats['sumatip_aktif'] = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bank_soal WHERE status = 'pending'");
    $stats['bank_soal_pending'] = $stmt->fetch()['total'];
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

<!-- SUMATIP Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
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
    
    <div class="col-md-4">
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
    
    <div class="col-md-4">
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
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Menu Operator</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <a href="<?php echo base_url('operator-manage-siswa'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-user-graduate fa-2x text-info me-3"></i>
                            <div>
                                <h6 class="mb-0">Kelola Siswa</h6>
                                <small class="text-muted">Tambah, edit, hapus siswa dan import dari Excel</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-manage-kelas'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chalkboard fa-2x text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0">Kelola Kelas</h6>
                                <small class="text-muted">Tambah, edit, hapus kelas dan import siswa ke kelas</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-template-raport'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-alt fa-2x text-info me-3"></i>
                            <div>
                                <h6 class="mb-0">Template Raport</h6>
                                <small class="text-muted">Lihat dan kelola template raport untuk mencetak raport siswa</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-ledger-nilai-manual'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-book fa-2x text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">Ledger Nilai Manual</h6>
                                <small class="text-muted">Lihat nilai dari input nilai manual guru mata pelajaran</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator/raport/list.php'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-print fa-2x text-danger me-3"></i>
                            <div>
                                <h6 class="mb-0">Print Raport</h6>
                                <small class="text-muted">Lihat dan cetak raport siswa berdasarkan nilai yang sudah disetujui</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator/sesi/list.php'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-calendar fa-2x text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">Kelola Sesi</h6>
                                <small class="text-muted">Lihat dan kelola semua sesi ujian</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator/monitoring/realtime.php'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-chart-line fa-2x text-success me-3"></i>
                            <div>
                                <h6 class="mb-0">Monitoring Real-time</h6>
                                <small class="text-muted">Pantau progress ujian secara real-time</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-assessment-index'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clipboard-check fa-2x text-primary me-3"></i>
                            <div>
                                <h6 class="mb-0">Assessment</h6>
                                <small class="text-muted">Kelola SUMATIP, bank soal, jadwal, dan absensi</small>
                            </div>
                        </div>
                    </a>
                    <a href="<?php echo base_url('operator-verifikasi-dokumen-index'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-shield fa-2x text-warning me-3"></i>
                            <div>
                                <h6 class="mb-0">Check Verifikasi Dokumen</h6>
                                <small class="text-muted">Check berkas file verifikasi dengan filter bermasalah/residu</small>
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
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h5>
            </div>
            <div class="card-body">
                <p class="mb-3">Sebagai operator, Anda memiliki akses penuh untuk:</p>
                <ul class="list-unstyled">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Mengelola siswa (tambah, edit, hapus)</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Import siswa dari Excel</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Assign siswa ke kelas</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Mengelola semua sesi ujian</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Assign peserta ke sesi</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Mengelola token ujian</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Monitoring real-time</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Menghapus sesi ujian</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i> <strong>Assessment (SUMATIP, Bank Soal, Jadwal, Absensi, Nilai)</strong></li>
                </ul>
                <hr>
                <p class="mb-0">
                    <a href="<?php echo base_url('operator-about'); ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-book me-1"></i> Lihat Detail Fitur & Fungsi
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>

