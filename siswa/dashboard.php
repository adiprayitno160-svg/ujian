<?php
/**
 * Dashboard Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Dashboard dengan grafik performa dan progress tracking
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notification_functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Dashboard Siswa';
$role_css = 'siswa';
include __DIR__ . '/../includes/header.php';

global $pdo;

// Get current semester and tahun ajaran
$tahun_ajaran = get_tahun_ajaran_aktif();
// Determine semester based on current month (Ganjil = Jul-Dec, Genap = Jan-Jun)
$current_month = (int)date('n');
$semester = ($current_month >= 1 && $current_month <= 6) ? 'genap' : 'ganjil';

// Get student info
$student_id = $_SESSION['user_id'];

// Get statistics
$stats = [
    'total_ujian' => 0,
    'total_nilai' => 0,
    'rata_rata' => 0,
    'nilai_tertinggi' => 0,
    'nilai_terendah' => 100,
    'total_pr' => 0,
    'pr_selesai' => 0,
    'total_tugas' => 0,
    'tugas_selesai' => 0
];

try {
    // Total ujian
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM nilai WHERE id_siswa = ? AND status = 'selesai'");
    $stmt->execute([$student_id]);
    $stats['total_ujian'] = $stmt->fetch()['total'];
    
    // Get nilai statistics
    $stmt = $pdo->prepare("SELECT nilai FROM nilai WHERE id_siswa = ? AND status = 'selesai' AND nilai IS NOT NULL");
    $stmt->execute([$student_id]);
    $nilai_list = array_column($stmt->fetchAll(), 'nilai');
    
    if (!empty($nilai_list)) {
        $stats['total_nilai'] = count($nilai_list);
        $stats['rata_rata'] = array_sum($nilai_list) / count($nilai_list);
        $stats['nilai_tertinggi'] = max($nilai_list);
        $stats['nilai_terendah'] = min($nilai_list);
    }
    
    // Total PR
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr_submission WHERE id_siswa = ?");
    $stmt->execute([$student_id]);
    $stats['total_pr'] = $stmt->fetch()['total'];
    
    // PR selesai
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr_submission WHERE id_siswa = ? AND status = 'sudah_dikumpulkan'");
    $stmt->execute([$student_id]);
    $stats['pr_selesai'] = $stmt->fetch()['total'];
    
    // Total Tugas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas_submission WHERE id_siswa = ?");
    $stmt->execute([$student_id]);
    $stats['total_tugas'] = $stmt->fetch()['total'];
    
    // Tugas selesai
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas_submission WHERE id_siswa = ? AND status = 'sudah_dikumpulkan'");
    $stmt->execute([$student_id]);
    $stats['tugas_selesai'] = $stmt->fetch()['total'];
} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent ujian
$recent_ujian = [];
try {
    $stmt = $pdo->prepare("SELECT n.*, u.judul as judul_ujian, u.id_mapel, m.nama_mapel, s.nama_sesi 
                          FROM nilai n 
                          INNER JOIN ujian u ON n.id_ujian = u.id 
                          LEFT JOIN mapel m ON u.id_mapel = m.id 
                          LEFT JOIN sesi_ujian s ON n.id_sesi = s.id 
                          WHERE n.id_siswa = ? AND n.status = 'selesai' 
                          ORDER BY n.waktu_submit DESC 
                          LIMIT 5");
    $stmt->execute([$student_id]);
    $recent_ujian = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get recent ujian error: " . $e->getMessage());
}

// Get performance by mapel
$performance_by_mapel = [];
try {
    $stmt = $pdo->prepare("SELECT u.id_mapel, m.nama_mapel, 
                          COUNT(*) as total_ujian,
                          AVG(n.nilai) as rata_rata,
                          MAX(n.nilai) as nilai_tertinggi,
                          MIN(n.nilai) as nilai_terendah
                          FROM nilai n 
                          INNER JOIN ujian u ON n.id_ujian = u.id 
                          LEFT JOIN mapel m ON u.id_mapel = m.id 
                          WHERE n.id_siswa = ? AND n.status = 'selesai' AND n.nilai IS NOT NULL 
                          GROUP BY u.id_mapel, m.nama_mapel 
                          ORDER BY rata_rata DESC");
    $stmt->execute([$student_id]);
    $performance_by_mapel = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get performance by mapel error: " . $e->getMessage());
}

// Get upcoming ujian
$upcoming_ujian = [];
try {
    $stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, u.id_mapel, m.nama_mapel, u.durasi 
                          FROM sesi_ujian s 
                          INNER JOIN ujian u ON s.id_ujian = u.id 
                          LEFT JOIN mapel m ON u.id_mapel = m.id 
                          INNER JOIN nilai n ON s.id = n.id_sesi AND n.id_siswa = ? 
                          WHERE s.status = 'aktif' 
                          AND n.status = 'mulai' 
                          AND s.waktu_mulai > NOW() 
                          ORDER BY s.waktu_mulai ASC 
                          LIMIT 5");
    $stmt->execute([$student_id]);
    $upcoming_ujian = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get upcoming ujian error: " . $e->getMessage());
}

// Get unread notifications
$unread_count = get_unread_notification_count($student_id);
$recent_notifications = get_notifications($student_id, 5, true);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">Dashboard</h2>
        <p class="text-muted mb-0">Selamat datang, <strong><?php echo escape($_SESSION['nama']); ?></strong>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-primary mb-2">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3 class="mb-1"><?php echo $stats['total_ujian']; ?></h3>
                <p class="text-muted mb-0">Total Ujian</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-success mb-2">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['rata_rata'], 1); ?></h3>
                <p class="text-muted mb-0">Rata-rata Nilai</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-warning mb-2">
                    <i class="fas fa-trophy"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['nilai_tertinggi'], 1); ?></h3>
                <p class="text-muted mb-0">Nilai Tertinggi</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-info mb-2">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="mb-1"><?php echo $unread_count; ?></h3>
                <p class="text-muted mb-0">Notifikasi Baru</p>
            </div>
        </div>
    </div>
</div>

<!-- Notifications -->
<?php if (!empty($recent_notifications)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bell"></i> Notifikasi Terbaru
                    <a href="<?php echo base_url('siswa-notifications'); ?>" class="btn btn-sm btn-light float-end">
                        Lihat Semua
                    </a>
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_notifications as $notif): ?>
                    <a href="<?php echo $notif['link'] ? base_url($notif['link']) : '#'; ?>" 
                       class="list-group-item list-group-item-action <?php echo $notif['is_read'] ? '' : 'list-group-item-primary'; ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo escape($notif['title']); ?></h6>
                            <small><?php echo time_ago($notif['created_at']); ?></small>
                        </div>
                        <p class="mb-1"><?php echo escape($notif['message']); ?></p>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Performance by Mapel -->
<?php if (!empty($performance_by_mapel)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Performa per Mata Pelajaran
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mata Pelajaran</th>
                                <th>Total Ujian</th>
                                <th>Rata-rata</th>
                                <th>Tertinggi</th>
                                <th>Terendah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performance_by_mapel as $perf): ?>
                            <tr>
                                <td><?php echo escape($perf['nama_mapel']); ?></td>
                                <td><?php echo $perf['total_ujian']; ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo number_format($perf['rata_rata'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo number_format($perf['nilai_tertinggi'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?php echo number_format($perf['nilai_terendah'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('siswa-progress?mapel_id=' . $perf['id_mapel']); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-chart-line"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Ujian & Upcoming Ujian -->
<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history"></i> Ujian Terbaru
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_ujian)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recent_ujian as $ujian): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo escape($ujian['judul_ujian']); ?></h6>
                            <span class="badge bg-<?php echo $ujian['nilai'] >= 75 ? 'success' : ($ujian['nilai'] >= 60 ? 'warning' : 'danger'); ?>">
                                <?php echo number_format($ujian['nilai'], 1); ?>
                            </span>
                        </div>
                        <p class="mb-1 text-muted">
                            <small><?php echo escape($ujian['nama_mapel']); ?></small>
                        </p>
                        <small class="text-muted">
                            <?php echo format_date($ujian['waktu_submit']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-3">Belum ada ujian yang selesai</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-alt"></i> Ujian Mendatang
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($upcoming_ujian)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($upcoming_ujian as $ujian): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo escape($ujian['judul_ujian']); ?></h6>
                            <span class="badge bg-primary">
                                <?php echo format_date($ujian['waktu_mulai']); ?>
                            </span>
                        </div>
                        <p class="mb-1 text-muted">
                            <small><?php echo escape($ujian['nama_mapel']); ?> - Durasi: <?php echo $ujian['durasi']; ?> menit</small>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-3">Tidak ada ujian mendatang</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt"></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa-ujian-list'); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-clipboard-list"></i> Daftar Ujian
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa-progress'); ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-chart-line"></i> Progress Tracking
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa-notifications'); ?>" class="btn btn-outline-info w-100">
                            <i class="fas fa-bell"></i> Notifikasi
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa/raport/list.php'); ?>" class="btn btn-outline-warning w-100">
                            <i class="fas fa-file-alt"></i> Raport
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

