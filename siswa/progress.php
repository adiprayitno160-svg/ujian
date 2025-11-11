<?php
/**
 * Progress Tracking - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Progress tracking per mata pelajaran
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Progress Tracking';
$role_css = 'siswa';
include __DIR__ . '/../includes/header.php';

global $pdo;

$student_id = $_SESSION['user_id'];
$tahun_ajaran = get_tahun_ajaran_aktif();
$current_month = (int)date('n');
$semester = ($current_month >= 1 && $current_month <= 6) ? '2' : '1';

// Get filter
$filter_mapel = intval($_GET['mapel_id'] ?? 0);
$filter_semester = $_GET['semester'] ?? $semester;
$filter_tahun = $_GET['tahun'] ?? $tahun_ajaran;

// Get all mapel for student
$mapel_list = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT u.id_mapel, m.nama_mapel 
                          FROM nilai n 
                          INNER JOIN ujian u ON n.id_ujian = u.id 
                          INNER JOIN mapel m ON u.id_mapel = m.id 
                          WHERE n.id_siswa = ? 
                          ORDER BY m.nama_mapel");
    $stmt->execute([$student_id]);
    $mapel_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get mapel list error: " . $e->getMessage());
}

// Get progress data
$progress_data = [];
try {
    $sql = "SELECT u.id_mapel, m.nama_mapel, 
            COUNT(*) as total_ujian,
            AVG(n.nilai) as rata_rata,
            MAX(n.nilai) as nilai_tertinggi,
            MIN(n.nilai) as nilai_terendah,
            SUM(CASE WHEN n.nilai >= 75 THEN 1 ELSE 0 END) as lulus,
            SUM(CASE WHEN n.nilai < 75 THEN 1 ELSE 0 END) as tidak_lulus
            FROM nilai n 
            INNER JOIN ujian u ON n.id_ujian = u.id 
            INNER JOIN mapel m ON u.id_mapel = m.id 
            WHERE n.id_siswa = ? AND n.status = 'selesai' AND n.nilai IS NOT NULL";
    $params = [$student_id];
    
    if ($filter_mapel > 0) {
        $sql .= " AND u.id_mapel = ?";
        $params[] = $filter_mapel;
    }
    
    $sql .= " GROUP BY u.id_mapel, m.nama_mapel ORDER BY rata_rata DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $progress_data = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get progress data error: " . $e->getMessage());
}

// Get detailed history for selected mapel
$detail_history = [];
if ($filter_mapel > 0) {
    try {
        $stmt = $pdo->prepare("SELECT n.*, u.judul as judul_ujian, u.id_mapel, m.nama_mapel, s.nama_sesi 
                              FROM nilai n 
                              INNER JOIN ujian u ON n.id_ujian = u.id 
                              LEFT JOIN mapel m ON u.id_mapel = m.id 
                              LEFT JOIN sesi_ujian s ON n.id_sesi = s.id 
                              WHERE n.id_siswa = ? AND u.id_mapel = ? AND n.status = 'selesai' 
                              ORDER BY n.waktu_submit DESC");
        $stmt->execute([$student_id, $filter_mapel]);
        $detail_history = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get detail history error: " . $e->getMessage());
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">
            <i class="fas fa-chart-line"></i> Progress Tracking
        </h2>
        <p class="text-muted mb-0">Tracking performa dan progress belajar Anda</p>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Mata Pelajaran</label>
                        <select name="mapel_id" class="form-select">
                            <option value="0">Semua Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id_mapel']; ?>" <?php echo $filter_mapel == $mapel['id_mapel'] ? 'selected' : ''; ?>>
                                <?php echo escape($mapel['nama_mapel']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tahun Ajaran</label>
                        <input type="text" name="tahun" class="form-control" value="<?php echo escape($filter_tahun); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Progress Overview -->
<?php if (!empty($progress_data)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-bar"></i> Ringkasan Progress
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
                                <th>Lulus</th>
                                <th>Tidak Lulus</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($progress_data as $progress): ?>
                            <?php 
                            $progress_percent = $progress['total_ujian'] > 0 
                                ? ($progress['lulus'] / $progress['total_ujian']) * 100 
                                : 0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo escape($progress['nama_mapel']); ?></strong>
                                </td>
                                <td><?php echo $progress['total_ujian']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $progress['rata_rata'] >= 75 ? 'success' : ($progress['rata_rata'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($progress['rata_rata'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo number_format($progress['nilai_tertinggi'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?php echo number_format($progress['nilai_terendah'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-success">
                                        <?php echo $progress['lulus']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?php echo $progress['tidak_lulus']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $progress_percent; ?>%"
                                             aria-valuenow="<?php echo $progress_percent; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                            <?php echo number_format($progress_percent, 1); ?>%
                                        </div>
                                    </div>
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

<!-- Detail History -->
<?php if ($filter_mapel > 0 && !empty($detail_history)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-history"></i> Riwayat Ujian - <?php echo escape($detail_history[0]['nama_mapel'] ?? ''); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Ujian</th>
                                <th>Sesi</th>
                                <th>Nilai</th>
                                <th>Status</th>
                                <th>Waktu Submit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detail_history as $idx => $history): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td><?php echo escape($history['judul_ujian']); ?></td>
                                <td><?php echo escape($history['nama_sesi'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $history['nilai'] >= 75 ? 'success' : ($history['nilai'] >= 60 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($history['nilai'], 1); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $history['status'] === 'selesai' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo format_date($history['waktu_submit'], 'd/m/Y H:i'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($filter_mapel > 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Belum ada riwayat ujian untuk mata pelajaran ini.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Chart Visualization -->
<?php if ($filter_mapel > 0 && !empty($detail_history)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line"></i> Grafik Progress
                </h5>
            </div>
            <div class="card-body">
                <canvas id="progressChart" height="50"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('progressChart').getContext('2d');
const progressChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php echo implode(',', array_map(function($h) { return "'" . escape($h['judul_ujian']) . "'"; }, array_reverse($detail_history))); ?>],
        datasets: [{
            label: 'Nilai',
            data: [<?php echo implode(',', array_map(function($h) { return $h['nilai']; }, array_reverse($detail_history))); ?>],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

