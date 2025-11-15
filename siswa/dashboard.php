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

// Get new exams that can be started (active and within time range, not completed)
$new_exams = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT s.*, u.judul as judul_ujian, u.id_mapel, m.nama_mapel, u.durasi
                          FROM sesi_ujian s 
                          INNER JOIN ujian u ON s.id_ujian = u.id 
                          LEFT JOIN mapel m ON u.id_mapel = m.id 
                          WHERE s.status = 'aktif'
                          AND s.waktu_mulai <= NOW()
                          AND s.waktu_selesai >= NOW()
                          AND EXISTS (
                              SELECT 1 FROM sesi_peserta sp
                              WHERE sp.id_sesi = s.id
                              AND (
                                  (sp.id_user = ? AND sp.tipe_assign = 'individual')
                                  OR
                                  (sp.tipe_assign = 'kelas' AND sp.id_kelas IN (
                                      SELECT id_kelas FROM user_kelas 
                                      WHERE id_user = ? AND tahun_ajaran = ?
                                  ))
                              )
                          )
                          AND NOT EXISTS (
                              SELECT 1 FROM nilai n 
                              WHERE n.id_sesi = s.id 
                              AND n.id_siswa = ? 
                              AND n.status = 'selesai'
                          )
                          ORDER BY s.waktu_mulai ASC 
                          LIMIT 1");
    $stmt->execute([$student_id, $student_id, $tahun_ajaran, $student_id]);
    $new_exams = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get new exams error: " . $e->getMessage());
}

// Get new PR (not submitted yet, created within last 3 days)
$new_pr = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT p.*, m.nama_mapel
                          FROM pr p
                          INNER JOIN mapel m ON p.id_mapel = m.id
                          INNER JOIN pr_kelas pk ON p.id = pk.id_pr
                          INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                          WHERE uk.id_user = ?
                          AND p.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                          AND p.deadline >= NOW()
                          AND NOT EXISTS (
                              SELECT 1 FROM pr_submission ps
                              WHERE ps.id_pr = p.id
                              AND ps.id_siswa = ?
                              AND ps.status IN ('sudah_dikumpulkan', 'dinilai', 'terlambat')
                          )
                          ORDER BY p.created_at DESC
                          LIMIT 1");
    $stmt->execute([$student_id, $student_id]);
    $new_pr = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get new PR error: " . $e->getMessage());
}

// Get new Tugas (not submitted yet, created within last 3 days)
$new_tugas = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT t.*, m.nama_mapel
                          FROM tugas t
                          INNER JOIN mapel m ON t.id_mapel = m.id
                          INNER JOIN tugas_kelas tk ON t.id = tk.id_tugas
                          INNER JOIN user_kelas uk ON tk.id_kelas = uk.id_kelas
                          WHERE uk.id_user = ?
                          AND t.status = 'published'
                          AND t.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
                          AND t.deadline >= NOW()
                          AND NOT EXISTS (
                              SELECT 1 FROM tugas_submission ts
                              WHERE ts.id_tugas = t.id
                              AND ts.id_siswa = ?
                              AND ts.status IN ('sudah_dikumpulkan', 'dinilai', 'terlambat')
                          )
                          ORDER BY t.created_at DESC
                          LIMIT 1");
    $stmt->execute([$student_id, $student_id]);
    $new_tugas = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get new Tugas error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">Dashboard</h2>
        <p class="text-muted mb-0">Selamat datang, <strong><?php echo escape($_SESSION['nama']); ?></strong>!</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-primary mb-2">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3 class="mb-1"><?php echo $stats['total_ujian']; ?></h3>
                <p class="text-muted mb-0">Total Ujian</p>
                <small class="text-muted d-block mt-1">Jumlah ujian yang telah diselesaikan</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-success mb-2">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['rata_rata'], 1); ?></h3>
                <p class="text-muted mb-0">Rata-rata Nilai</p>
                <small class="text-muted d-block mt-1">Rata-rata nilai dari semua ujian</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="display-4 text-info mb-2">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="mb-1"><?php echo $unread_count; ?></h3>
                <p class="text-muted mb-0">Notifikasi Baru</p>
                <small class="text-muted d-block mt-1">Notifikasi yang belum dibaca</small>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-bolt"></i> Menu Cepat
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa-ujian-list'); ?>" class="btn btn-outline-primary w-100" title="Lihat daftar ujian yang tersedia dan riwayat ujian yang telah dikerjakan">
                            <i class="fas fa-clipboard-list"></i> Daftar Ujian
                        </a>
                        <small class="text-muted d-block mt-1 text-center">Lihat dan kerjakan ujian</small>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa/pr/list.php'); ?>" class="btn btn-outline-success w-100" title="Lihat daftar pekerjaan rumah yang diberikan dan kumpulkan tugas">
                            <i class="fas fa-tasks"></i> Daftar PR
                        </a>
                        <small class="text-muted d-block mt-1 text-center">Kerjakan dan kumpulkan PR</small>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa/tugas/list.php'); ?>" class="btn btn-outline-warning w-100" title="Lihat daftar tugas yang diberikan dan kumpulkan tugas">
                            <i class="fas fa-clipboard-list"></i> Daftar Tugas
                        </a>
                        <small class="text-muted d-block mt-1 text-center">Kerjakan dan kumpulkan tugas</small>
                    </div>
                    <?php
                    // Get raport menu visibility setting
                    $siswa_raport_menu_visible = 1; // Default visible
                    try {
                        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'siswa_raport_menu_visible'");
                        $stmt->execute();
                        $setting = $stmt->fetch();
                        if ($setting) {
                            $siswa_raport_menu_visible = intval($setting['setting_value']);
                        }
                    } catch (PDOException $e) {
                        error_log("Error getting raport menu setting: " . $e->getMessage());
                    }
                    ?>
                    <?php if ($siswa_raport_menu_visible): ?>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('siswa/raport/list.php'); ?>" class="btn btn-outline-info w-100" title="Lihat raport nilai dan hasil belajar">
                            <i class="fas fa-file-alt"></i> Raport
                        </a>
                        <small class="text-muted d-block mt-1 text-center">Lihat raport nilai</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Popup Notifikasi Ujian Baru -->
<?php if (!empty($new_exams)): ?>
<div class="modal fade" id="newExamModal" tabindex="-1" aria-labelledby="newExamModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="newExamModalLabel">
                    <i class="fas fa-bell"></i> Ujian Baru Tersedia
                </h5>
            </div>
            <div class="modal-body">
                <?php foreach ($new_exams as $exam): ?>
                <div class="alert alert-info">
                    <h6 class="fw-bold"><?php echo escape($exam['judul_ujian']); ?></h6>
                    <p class="mb-2">
                        <i class="fas fa-book"></i> <strong>Mata Pelajaran:</strong> <?php echo escape($exam['nama_mapel']); ?><br>
                        <i class="fas fa-clock"></i> <strong>Durasi:</strong> <?php echo $exam['durasi']; ?> menit<br>
                        <i class="fas fa-calendar"></i> <strong>Waktu:</strong> <?php echo format_date($exam['waktu_mulai']); ?> - <?php echo format_date($exam['waktu_selesai']); ?>
                    </p>
                </div>
                <?php endforeach; ?>
                <p class="mb-0">Klik tombol <strong>Mulai</strong> untuk memulai ujian.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                <?php if (!empty($new_exams)): ?>
                <a href="<?php echo base_url('siswa/ujian/take.php?id=' . $new_exams[0]['id']); ?>" class="btn btn-primary">
                    <i class="fas fa-play"></i> Mulai
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Popup Notifikasi PR/Tugas Baru -->
<?php if (!empty($new_pr) || !empty($new_tugas)): ?>
<div class="modal fade" id="newAssignmentModal" tabindex="-1" aria-labelledby="newAssignmentModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="newAssignmentModalLabel">
                    <i class="fas fa-bell"></i> PR/Tugas Baru Tersedia
                </h5>
            </div>
            <div class="modal-body">
                <?php if (!empty($new_pr)): ?>
                    <?php foreach ($new_pr as $pr): ?>
                    <div class="alert alert-success mb-3">
                        <h6 class="fw-bold">PR Baru: <?php echo escape($pr['judul']); ?></h6>
                        <p class="mb-2">
                            <i class="fas fa-book"></i> <strong>Mata Pelajaran:</strong> <?php echo escape($pr['nama_mapel']); ?><br>
                            <i class="fas fa-calendar"></i> <strong>Deadline:</strong> <?php echo format_date($pr['deadline']); ?>
                        </p>
                        <a href="<?php echo base_url('siswa/pr/list.php'); ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-arrow-right"></i> Klik di sini untuk melihat PR
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($new_tugas)): ?>
                    <?php foreach ($new_tugas as $tugas): ?>
                    <div class="alert alert-warning mb-3">
                        <h6 class="fw-bold">Tugas Baru: <?php echo escape($tugas['judul']); ?></h6>
                        <p class="mb-2">
                            <i class="fas fa-book"></i> <strong>Mata Pelajaran:</strong> <?php echo escape($tugas['nama_mapel']); ?><br>
                            <i class="fas fa-calendar"></i> <strong>Deadline:</strong> <?php echo format_date($tugas['deadline']); ?>
                        </p>
                        <a href="<?php echo base_url('siswa/tugas/list.php'); ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-arrow-right"></i> Klik di sini untuk melihat Tugas
                        </a>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show new exam modal first if available
    <?php if (!empty($new_exams)): ?>
    var newExamModal = new bootstrap.Modal(document.getElementById('newExamModal'));
    newExamModal.show();
    <?php elseif (!empty($new_pr) || !empty($new_tugas)): ?>
    // Show new assignment modal if no new exam
    var newAssignmentModal = new bootstrap.Modal(document.getElementById('newAssignmentModal'));
    newAssignmentModal.show();
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

