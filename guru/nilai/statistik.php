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
$stmt = $pdo->prepare("SELECT n.nilai, n.id_siswa, u.nama as nama_siswa, u.kelas 
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      WHERE n.id_ujian = ? AND n.status = 'selesai' AND n.nilai IS NOT NULL");
$stmt->execute([$ujian_id]);
$nilai_data = $stmt->fetchAll();
$nilai_list = array_column($nilai_data, 'nilai');

// Get comparison data - other ujian with same mapel and tingkat
$stmt = $pdo->prepare("SELECT u2.id, u2.judul, AVG(n2.nilai) as avg_nilai, COUNT(n2.id) as total_peserta
                      FROM ujian u2
                      INNER JOIN nilai n2 ON u2.id = n2.id_ujian
                      WHERE u2.id_mapel = ? AND u2.tingkat_kelas = ? AND u2.id != ?
                      AND n2.status = 'selesai' AND n2.nilai IS NOT NULL
                      GROUP BY u2.id, u2.judul
                      ORDER BY u2.created_at DESC
                      LIMIT 5");
$stmt->execute([$ujian['id_mapel'], $ujian['tingkat_kelas'], $ujian_id]);
$comparison_ujian = $stmt->fetchAll();

// Get trend data per siswa (students who took multiple ujian)
$stmt = $pdo->prepare("SELECT u.nama, n.id_siswa, n.nilai, uj.judul as ujian_judul, uj.created_at
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      INNER JOIN ujian uj ON n.id_ujian = uj.id
                      WHERE uj.id_mapel = ? AND uj.tingkat_kelas = ?
                      AND n.status = 'selesai' AND n.nilai IS NOT NULL
                      AND n.id_siswa IN (
                          SELECT id_siswa FROM nilai 
                          WHERE id_ujian IN (
                              SELECT id FROM ujian 
                              WHERE id_mapel = ? AND tingkat_kelas = ?
                              ORDER BY created_at DESC LIMIT 5
                          )
                          GROUP BY id_siswa
                          HAVING COUNT(DISTINCT id_ujian) >= 2
                      )
                      ORDER BY u.nama, uj.created_at ASC");
$stmt->execute([$ujian['id_mapel'], $ujian['tingkat_kelas'], $ujian['id_mapel'], $ujian['tingkat_kelas']]);
$trend_data = $stmt->fetchAll();

// Group trend data by siswa
$trend_by_siswa = [];
foreach ($trend_data as $row) {
    if (!isset($trend_by_siswa[$row['id_siswa']])) {
        $trend_by_siswa[$row['id_siswa']] = [
            'nama' => $row['nama'],
            'data' => []
        ];
    }
    $trend_by_siswa[$row['id_siswa']]['data'][] = [
        'ujian' => $row['ujian_judul'],
        'nilai' => floatval($row['nilai']),
        'tanggal' => $row['created_at']
    ];
}

// Get kelas distribution if available
$kelas_distribution = [];
if (!empty($nilai_data)) {
    foreach ($nilai_data as $row) {
        $kelas = $row['kelas'] ?? 'Tidak Ada';
        if (!isset($kelas_distribution[$kelas])) {
            $kelas_distribution[$kelas] = [
                'total' => 0,
                'sum' => 0,
                'rata_rata' => 0
            ];
        }
        $kelas_distribution[$kelas]['total']++;
        $kelas_distribution[$kelas]['sum'] += floatval($row['nilai']);
    }
    foreach ($kelas_distribution as $kelas => &$data) {
        $data['rata_rata'] = $data['total'] > 0 ? $data['sum'] / $data['total'] : 0;
    }
}

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

<?php if (!empty($comparison_ujian)): ?>
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Perbandingan dengan Ujian Lain (Mapel & Kelas Sama)</h5>
            </div>
            <div class="card-body">
                <canvas id="comparisonChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($kelas_distribution) && count($kelas_distribution) > 1): ?>
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Perbandingan Rata-rata per Kelas</h5>
            </div>
            <div class="card-body">
                <canvas id="kelasComparisonChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($trend_by_siswa) && count($trend_by_siswa) > 0): ?>
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-area"></i> Trend Nilai per Siswa (Top 10 Siswa dengan Multiple Ujian)</h5>
            </div>
            <div class="card-body">
                <canvas id="trendChart" height="100"></canvas>
                <div class="mt-3">
                    <small class="text-muted">Menampilkan siswa yang mengikuti minimal 2 ujian dengan mapel dan kelas yang sama</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

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

<?php if (!empty($comparison_ujian)): ?>
// Comparison Chart
const comparisonLabels = <?php echo json_encode(array_merge([$ujian['judul']], array_column($comparison_ujian, 'judul'))); ?>;
const comparisonData = [<?php echo number_format($stats['rata_rata'], 2); ?>, <?php echo implode(',', array_map(function($u) { return number_format($u['avg_nilai'], 2); }, $comparison_ujian)); ?>];
const comparisonCounts = [<?php echo $stats['total']; ?>, <?php echo implode(',', array_column($comparison_ujian, 'total_peserta')); ?>];

const ctx3 = document.getElementById('comparisonChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: comparisonLabels,
        datasets: [{
            label: 'Rata-rata Nilai',
            data: comparisonData,
            backgroundColor: 'rgba(59, 130, 246, 0.6)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: 'Jumlah Peserta',
            data: comparisonCounts,
            type: 'line',
            borderColor: 'rgba(245, 158, 11, 1)',
            backgroundColor: 'rgba(245, 158, 11, 0.1)',
            borderWidth: 2,
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                position: 'left',
                title: {
                    display: true,
                    text: 'Rata-rata Nilai'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Jumlah Peserta'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                callbacks: {
                    afterLabel: function(context) {
                        if (context.datasetIndex === 0) {
                            const index = context.dataIndex;
                            return 'Peserta: ' + comparisonCounts[index];
                        }
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($kelas_distribution) && count($kelas_distribution) > 1): ?>
// Kelas Comparison Chart
const kelasLabels = <?php echo json_encode(array_keys($kelas_distribution)); ?>;
const kelasData = [<?php echo implode(',', array_map(function($d) { return number_format($d['rata_rata'], 2); }, $kelas_distribution)); ?>];

const ctx4 = document.getElementById('kelasComparisonChart').getContext('2d');
new Chart(ctx4, {
    type: 'bar',
    data: {
        labels: kelasLabels,
        datasets: [{
            label: 'Rata-rata Nilai per Kelas',
            data: kelasData,
            backgroundColor: kelasData.map(function(val) {
                if (val >= 80) return 'rgba(16, 185, 129, 0.6)'; // Green
                if (val >= 70) return 'rgba(245, 158, 11, 0.6)'; // Yellow
                return 'rgba(239, 68, 68, 0.6)'; // Red
            }),
            borderColor: kelasData.map(function(val) {
                if (val >= 80) return 'rgba(16, 185, 129, 1)';
                if (val >= 70) return 'rgba(245, 158, 11, 1)';
                return 'rgba(239, 68, 68, 1)';
            }),
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Rata-rata Nilai'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    afterLabel: function(context) {
                        const index = context.dataIndex;
                        const kelas = kelasLabels[index];
                        const total = <?php echo json_encode(array_column($kelas_distribution, 'total')); ?>[index];
                        return 'Total Siswa: ' + total;
                    }
                }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($trend_by_siswa) && count($trend_by_siswa) > 0): ?>
// Trend Chart - Show top 10 students
const trendStudents = <?php echo json_encode(array_slice($trend_by_siswa, 0, 10, true)); ?>;
const trendLabels = [];
const trendDatasets = [];

// Get all unique ujian dates
const allUjianDates = new Set();
Object.values(trendStudents).forEach(function(student) {
    student.data.forEach(function(point) {
        allUjianDates.add(point.ujian);
    });
});
const sortedUjianLabels = Array.from(allUjianDates);

// Create datasets for each student
let colorIndex = 0;
const colors = [
    'rgba(59, 130, 246, 1)', 'rgba(16, 185, 129, 1)', 'rgba(245, 158, 11, 1)',
    'rgba(239, 68, 68, 1)', 'rgba(139, 92, 246, 1)', 'rgba(236, 72, 153, 1)',
    'rgba(14, 165, 233, 1)', 'rgba(34, 197, 94, 1)', 'rgba(251, 146, 60, 1)', 'rgba(168, 85, 247, 1)'
];

Object.entries(trendStudents).slice(0, 10).forEach(function([siswaId, student]) {
    const data = sortedUjianLabels.map(function(ujian) {
        const point = student.data.find(function(p) { return p.ujian === ujian; });
        return point ? point.nilai : null;
    });
    
    trendDatasets.push({
        label: student.nama,
        data: data,
        borderColor: colors[colorIndex % colors.length],
        backgroundColor: colors[colorIndex % colors.length].replace('1)', '0.1)'),
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        pointRadius: 4,
        pointHoverRadius: 6
    });
    colorIndex++;
});

const ctx5 = document.getElementById('trendChart').getContext('2d');
new Chart(ctx5, {
    type: 'line',
    data: {
        labels: sortedUjianLabels.map(function(label) {
            return label.length > 30 ? label.substring(0, 30) + '...' : label;
        }),
        datasets: trendDatasets
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Nilai'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Ujian'
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'right',
                labels: {
                    boxWidth: 12,
                    font: {
                        size: 10
                    }
                }
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        }
    }
});
<?php endif; ?>
</script>

<div class="text-center mt-4">
    <a href="<?php echo base_url('guru/nilai/list.php?ujian_id=' . $ujian_id); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Nilai
    </a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



