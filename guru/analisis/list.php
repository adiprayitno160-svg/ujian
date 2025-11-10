<?php
/**
 * Analisis Butir Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/analisis_butir.php';

require_role('guru');
check_session_timeout();

$page_title = 'Analisis Butir Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get ujian
$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

// Get soal
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$ujian_id]);
$soal_list = $stmt->fetchAll();

// Calculate analysis for each soal
$analisis_list = [];
foreach ($soal_list as $soal) {
    $analisis = analyze_soal($ujian_id, $soal['id']);
    if ($analisis) {
        $analisis_list[] = array_merge($soal, [
            'total_responden' => $analisis['stats']['total_peserta'] ?? 0,
            'jumlah_benar' => $analisis['stats']['benar'] ?? 0,
            'jumlah_salah' => $analisis['stats']['salah'] ?? 0,
            'tingkat_kesukaran' => $analisis['difficulty']['index'] ?? 0,
            'daya_pembeda' => $analisis['discrimination']['index'] ?? 0,
            'efektivitas_distraktor' => 0 // Simplified
        ]);
    } else {
        $analisis_list[] = array_merge($soal, [
            'total_responden' => 0,
            'jumlah_benar' => 0,
            'jumlah_salah' => 0,
            'tingkat_kesukaran' => 0,
            'daya_pembeda' => 0,
            'efektivitas_distraktor' => 0
        ]);
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Analisis Butir Soal</h2>
        <p class="text-muted">Ujian: <?php echo escape($ujian['judul']); ?></p>
    </div>
</div>

<?php if (!empty($analisis_list)): ?>
<!-- Summary Charts -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Tingkat Kesukaran per Soal</h5>
            </div>
            <div class="card-body">
                <canvas id="difficultyChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Daya Pembeda per Soal</h5>
            </div>
            <div class="card-body">
                <canvas id="discriminationChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Distribusi Kategori Soal</h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-chart-area"></i> Distribusi Jawaban (Benar/Salah/Kosong)</h5>
            </div>
            <div class="card-body">
                <canvas id="answerDistributionChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($analisis_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada soal untuk dianalisis
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Soal</th>
                            <th>Jumlah Responden</th>
                            <th>Jumlah Benar</th>
                            <th>Jumlah Salah</th>
                            <th>Tingkat Kesukaran</th>
                            <th>Daya Pembeda</th>
                            <th>Efektivitas Distraktor</th>
                            <th>Kategori</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analisis_list as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div style="max-width: 300px;">
                                    <?php echo escape(substr($item['pertanyaan'], 0, 100)); ?>
                                    <?php echo strlen($item['pertanyaan']) > 100 ? '...' : ''; ?>
                                </div>
                            </td>
                            <td><?php echo $item['total_responden'] ?? 0; ?></td>
                            <td><?php echo $item['jumlah_benar'] ?? 0; ?></td>
                            <td><?php echo $item['jumlah_salah'] ?? 0; ?></td>
                            <td>
                                <?php 
                                $tk = $item['tingkat_kesukaran'] ?? 0;
                                $color = $tk >= 0.7 ? 'text-danger' : ($tk >= 0.3 ? 'text-warning' : 'text-success');
                                ?>
                                <span class="<?php echo $color; ?>">
                                    <?php echo number_format($tk, 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $dp = $item['daya_pembeda'] ?? 0;
                                $color = $dp >= 0.4 ? 'text-success' : ($dp >= 0.2 ? 'text-warning' : 'text-danger');
                                ?>
                                <span class="<?php echo $color; ?>">
                                    <?php echo number_format($dp, 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $ed = $item['efektivitas_distraktor'] ?? 0;
                                echo number_format($ed, 2);
                                ?>
                            </td>
                            <td>
                                <?php 
                                $tk = $item['tingkat_kesukaran'] ?? 0;
                                $dp = $item['daya_pembeda'] ?? 0;
                                
                                if ($tk >= 0.3 && $tk <= 0.7 && $dp >= 0.4) {
                                    $kategori = 'Baik';
                                    $badge_color = 'success';
                                } elseif ($tk >= 0.3 && $tk <= 0.7 && $dp >= 0.2) {
                                    $kategori = 'Cukup';
                                    $badge_color = 'warning';
                                } else {
                                    $kategori = 'Perlu Revisi';
                                    $badge_color = 'danger';
                                }
                                ?>
                                <span class="badge bg-<?php echo $badge_color; ?>">
                                    <?php echo $kategori; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Keterangan</h5>
    </div>
    <div class="card-body">
        <ul>
            <li><strong>Tingkat Kesukaran:</strong> 
                <ul>
                    <li>≥ 0.7: Mudah (Merah)</li>
                    <li>0.3 - 0.7: Sedang (Kuning)</li>
                    <li>&lt; 0.3: Sukar (Hijau)</li>
                </ul>
            </li>
            <li><strong>Daya Pembeda:</strong>
                <ul>
                    <li>≥ 0.4: Baik (Hijau)</li>
                    <li>0.2 - 0.4: Cukup (Kuning)</li>
                    <li>&lt; 0.2: Kurang (Merah)</li>
                </ul>
            </li>
            <li><strong>Kategori:</strong> Berdasarkan kombinasi tingkat kesukaran dan daya pembeda</li>
        </ul>
    </div>
</div>

<?php if (!empty($analisis_list)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Prepare data for charts
const soalNumbers = <?php 
$soal_nums = [];
foreach ($analisis_list as $index => $item) {
    $soal_nums[] = 'Soal ' . ($index + 1);
}
echo json_encode($soal_nums); 
?>;
const difficultyData = <?php echo json_encode(array_column($analisis_list, 'tingkat_kesukaran')); ?>;
const discriminationData = <?php echo json_encode(array_column($analisis_list, 'daya_pembeda')); ?>;
const benarData = <?php echo json_encode(array_column($analisis_list, 'jumlah_benar')); ?>;
const salahData = <?php echo json_encode(array_column($analisis_list, 'jumlah_salah')); ?>;
const kosongData = <?php 
$kosong_data = [];
foreach ($analisis_list as $item) {
    $kosong_data[] = ($item['total_responden'] ?? 0) - ($item['jumlah_benar'] ?? 0) - ($item['jumlah_salah'] ?? 0);
}
echo json_encode($kosong_data); 
?>;

// Category counts
const categoryCounts = {
    baik: 0,
    cukup: 0,
    revisi: 0
};

<?php foreach ($analisis_list as $item): ?>
    <?php 
    $tk = $item['tingkat_kesukaran'] ?? 0;
    $dp = $item['daya_pembeda'] ?? 0;
    if ($tk >= 0.3 && $tk <= 0.7 && $dp >= 0.4) {
        echo "categoryCounts.baik++;";
    } elseif ($tk >= 0.3 && $tk <= 0.7 && $dp >= 0.2) {
        echo "categoryCounts.cukup++;";
    } else {
        echo "categoryCounts.revisi++;";
    }
    ?>
<?php endforeach; ?>

// Difficulty Chart
const ctx1 = document.getElementById('difficultyChart').getContext('2d');
new Chart(ctx1, {
    type: 'bar',
    data: {
        labels: soalNumbers,
        datasets: [{
            label: 'Tingkat Kesukaran',
            data: difficultyData,
            backgroundColor: difficultyData.map(tk => {
                if (tk >= 0.7) return 'rgba(239, 68, 68, 0.6)'; // Mudah - Red
                if (tk >= 0.3) return 'rgba(245, 158, 11, 0.6)'; // Sedang - Yellow
                return 'rgba(16, 185, 129, 0.6)'; // Sulit - Green
            }),
            borderColor: difficultyData.map(tk => {
                if (tk >= 0.7) return 'rgba(239, 68, 68, 1)';
                if (tk >= 0.3) return 'rgba(245, 158, 11, 1)';
                return 'rgba(16, 185, 129, 1)';
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
                max: 1,
                title: {
                    display: true,
                    text: 'Tingkat Kesukaran (0-1)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Nomor Soal'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const tk = context.parsed.y;
                        let category = 'Sulit';
                        if (tk >= 0.7) category = 'Mudah';
                        else if (tk >= 0.3) category = 'Sedang';
                        return `Tingkat Kesukaran: ${tk.toFixed(3)} (${category})`;
                    }
                }
            }
        }
    }
});

// Discrimination Chart
const ctx2 = document.getElementById('discriminationChart').getContext('2d');
new Chart(ctx2, {
    type: 'line',
    data: {
        labels: soalNumbers,
        datasets: [{
            label: 'Daya Pembeda',
            data: discriminationData,
            borderColor: 'rgba(59, 130, 246, 1)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: discriminationData.map(dp => {
                if (dp >= 0.4) return 'rgba(16, 185, 129, 1)'; // Baik - Green
                if (dp >= 0.2) return 'rgba(245, 158, 11, 1)'; // Cukup - Yellow
                return 'rgba(239, 68, 68, 1)'; // Kurang - Red
            }),
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                max: 1,
                title: {
                    display: true,
                    text: 'Daya Pembeda (0-1)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Nomor Soal'
                }
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const dp = context.parsed.y;
                        let category = 'Kurang';
                        if (dp >= 0.4) category = 'Baik';
                        else if (dp >= 0.2) category = 'Cukup';
                        return `Daya Pembeda: ${dp.toFixed(3)} (${category})`;
                    }
                }
            }
        }
    }
});

// Category Chart
const ctx3 = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx3, {
    type: 'doughnut',
    data: {
        labels: ['Baik', 'Cukup', 'Perlu Revisi'],
        datasets: [{
            data: [categoryCounts.baik, categoryCounts.cukup, categoryCounts.revisi],
            backgroundColor: [
                'rgba(16, 185, 129, 0.6)',
                'rgba(245, 158, 11, 0.6)',
                'rgba(239, 68, 68, 0.6)'
            ],
            borderColor: [
                'rgba(16, 185, 129, 1)',
                'rgba(245, 158, 11, 1)',
                'rgba(239, 68, 68, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = categoryCounts.baik + categoryCounts.cukup + categoryCounts.revisi;
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Answer Distribution Chart
const ctx4 = document.getElementById('answerDistributionChart').getContext('2d');
new Chart(ctx4, {
    type: 'bar',
    data: {
        labels: soalNumbers,
        datasets: [{
            label: 'Benar',
            data: benarData,
            backgroundColor: 'rgba(16, 185, 129, 0.6)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 1
        }, {
            label: 'Salah',
            data: salahData,
            backgroundColor: 'rgba(239, 68, 68, 0.6)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 1
        }, {
            label: 'Kosong',
            data: kosongData,
            backgroundColor: 'rgba(156, 163, 175, 0.6)',
            borderColor: 'rgba(156, 163, 175, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                stacked: true,
                title: {
                    display: true,
                    text: 'Nomor Soal'
                }
            },
            y: {
                stacked: true,
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Jumlah Responden'
                }
            }
        },
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

