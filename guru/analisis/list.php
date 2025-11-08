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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

