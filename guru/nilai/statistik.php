<?php
/**
 * Statistik Nilai - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Statistik Nilai';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

// Get nilai
$stmt = $pdo->prepare("SELECT nilai FROM nilai WHERE id_ujian = ? AND status = 'selesai' AND nilai IS NOT NULL");
$stmt->execute([$ujian_id]);
$nilai_list = array_column($stmt->fetchAll(), 'nilai');

// Calculate statistics
$stats = [
    'total' => count($nilai_list),
    'rata_rata' => 0,
    'median' => 0,
    'tertinggi' => 0,
    'terendah' => 100,
    'lulus' => 0,
    'tidak_lulus' => 0
];

if (!empty($nilai_list)) {
    $stats['rata_rata'] = array_sum($nilai_list) / count($nilai_list);
    sort($nilai_list);
    $count = count($nilai_list);
    $stats['median'] = ($count % 2 === 0) 
        ? ($nilai_list[$count/2 - 1] + $nilai_list[$count/2]) / 2 
        : $nilai_list[floor($count/2)];
    $stats['tertinggi'] = max($nilai_list);
    $stats['terendah'] = min($nilai_list);
    $stats['lulus'] = count(array_filter($nilai_list, fn($n) => $n >= 70));
    $stats['tidak_lulus'] = count(array_filter($nilai_list, fn($n) => $n < 70));
}

// Prepare data for chart
$ranges = [
    '0-20' => 0,
    '21-40' => 0,
    '41-60' => 0,
    '61-80' => 0,
    '81-100' => 0
];

foreach ($nilai_list as $nilai) {
    if ($nilai <= 20) $ranges['0-20']++;
    elseif ($nilai <= 40) $ranges['21-40']++;
    elseif ($nilai <= 60) $ranges['41-60']++;
    elseif ($nilai <= 80) $ranges['61-80']++;
    else $ranges['81-100']++;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Statistik Nilai</h2>
        <p class="text-muted">Ujian: <?php echo escape($ujian['judul']); ?></p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-primary"><?php echo $stats['total']; ?></div>
                <small class="text-muted">Total Peserta</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-success"><?php echo number_format($stats['rata_rata'], 2); ?></div>
                <small class="text-muted">Rata-rata</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-info"><?php echo number_format($stats['median'], 2); ?></div>
                <small class="text-muted">Median</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-3 fw-bold text-warning"><?php echo number_format($stats['tertinggi'], 2); ?></div>
                <small class="text-muted">Tertinggi</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Distribusi Nilai</h5>
            </div>
            <div class="card-body">
                <canvas id="distributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Status Kelulusan</h5>
            </div>
            <div class="card-body">
                <canvas id="passChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Detail Statistik</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Nilai Tertinggi</th>
                        <td><?php echo number_format($stats['tertinggi'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Nilai Terendah</th>
                        <td><?php echo number_format($stats['terendah'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Rata-rata</th>
                        <td><?php echo number_format($stats['rata_rata'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Median</th>
                        <td><?php echo number_format($stats['median'], 2); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Lulus (≥70)</th>
                        <td>
                            <span class="badge bg-success"><?php echo $stats['lulus']; ?></span>
                            (<?php echo $stats['total'] > 0 ? number_format(($stats['lulus'] / $stats['total']) * 100, 2) : 0; ?>%)
                        </td>
                    </tr>
                    <tr>
                        <th>Tidak Lulus (&lt;70)</th>
                        <td>
                            <span class="badge bg-danger"><?php echo $stats['tidak_lulus']; ?></span>
                            (<?php echo $stats['total'] > 0 ? number_format(($stats['tidak_lulus'] / $stats['total']) * 100, 2) : 0; ?>%)
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Distribution Chart
const ctx1 = document.getElementById('distributionChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: ['0-20', '21-40', '41-60', '61-80', '81-100'],
        datasets: [{
            label: 'Jumlah Siswa',
            data: [<?php echo implode(',', array_values($ranges)); ?>],
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Pass Chart
const ctx2 = document.getElementById('passChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ['Lulus', 'Tidak Lulus'],
        datasets: [{
            data: [<?php echo $stats['lulus']; ?>, <?php echo $stats['tidak_lulus']; ?>],
            backgroundColor: ['rgba(40, 167, 69, 0.5)', 'rgba(220, 53, 69, 0.5)'],
            borderColor: ['rgba(40, 167, 69, 1)', 'rgba(220, 53, 69, 1)'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<div class="text-center mt-4">
    <a href="<?php echo base_url('guru/nilai/list.php?ujian_id=' . $ujian_id); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Nilai
    </a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



