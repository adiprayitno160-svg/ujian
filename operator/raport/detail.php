<?php
/**
 * Detail Raport - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman detail raport siswa
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id_siswa = intval($_GET['id_siswa'] ?? 0);
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

if (!$id_siswa) {
    redirect('operator/raport/list.php');
}

// Get siswa data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? 
                      AND uk.tahun_ajaran = ? 
                      AND uk.semester = ?");
$stmt->execute([$id_siswa, $tahun_ajaran, $semester]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('operator/raport/list.php');
}

// Get penilaian - hanya yang sudah aktif (diterbitkan)
// Check if aktif column exists
$aktif_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $aktif_column_exists = false;
}

// Urutan mapel sesuai template raport
$mapel_order = [
    'PA&PBP' => 1,
    'P.PANQ' => 2,
    'B.INDO' => 3,
    'MAT' => 4,
    'IPA' => 5,
    'IPS' => 6,
    'B.INGG' => 7,
    'PRAK' => 8,
    'PJOK' => 9,
    'INFOR' => 10,
    'B.JAWA' => 11
];

if ($aktif_column_exists) {
    // Hanya ambil nilai yang sudah aktif (diterbitkan)
    $stmt = $pdo->prepare("SELECT pm.*, m.nama_mapel, m.kode_mapel, g.nama as nama_guru
                          FROM penilaian_manual pm
                          INNER JOIN mapel m ON pm.id_mapel = m.id
                          INNER JOIN users g ON pm.id_guru = g.id
                          WHERE pm.id_siswa = ?
                          AND pm.tahun_ajaran = ?
                          AND pm.semester = ?
                          AND pm.status = 'approved'
                          AND pm.aktif = 1");
} else {
    // Fallback: ambil semua yang approved jika kolom aktif belum ada
    $stmt = $pdo->prepare("SELECT pm.*, m.nama_mapel, m.kode_mapel, g.nama as nama_guru
                          FROM penilaian_manual pm
                          INNER JOIN mapel m ON pm.id_mapel = m.id
                          INNER JOIN users g ON pm.id_guru = g.id
                          WHERE pm.id_siswa = ?
                          AND pm.tahun_ajaran = ?
                          AND pm.semester = ?
                          AND pm.status = 'approved'");
}
$stmt->execute([$id_siswa, $tahun_ajaran, $semester]);
$penilaian_list_all = $stmt->fetchAll();

// Sort berdasarkan urutan template
usort($penilaian_list_all, function($a, $b) use ($mapel_order) {
    $order_a = $mapel_order[$a['kode_mapel']] ?? 999;
    $order_b = $mapel_order[$b['kode_mapel']] ?? 999;
    if ($order_a == $order_b) {
        return strcmp($a['nama_mapel'], $b['nama_mapel']);
    }
    return $order_a - $order_b;
});
$penilaian_list = $penilaian_list_all;

// Calculate statistics
$total_nilai = 0;
$count_nilai = 0;
foreach ($penilaian_list as $p) {
    if ($p['nilai_akhir'] !== null) {
        $total_nilai += $p['nilai_akhir'];
        $count_nilai++;
    }
}
$rata_rata = $count_nilai > 0 ? $total_nilai / $count_nilai : 0;

$page_title = 'Detail Raport - ' . escape($siswa['nama']);
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Detail Raport</h2>
                <p class="text-muted mb-0"><?php echo escape($siswa['nama']); ?> - <?php echo escape($siswa['nama_kelas']); ?></p>
            </div>
            <div>
                <a href="<?php echo base_url('operator/raport/list.php?id_kelas=' . ($_GET['id_kelas'] ?? '') . '&tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
                   class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="<?php echo base_url('operator/raport/print.php?id_siswa=' . $id_siswa . '&tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
                   class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Cetak Raport
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Student Info -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td width="30%"><strong>Nama Siswa</strong></td>
                        <td>: <?php echo escape($siswa['nama']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>NIS</strong></td>
                        <td>: <?php echo escape($siswa['username']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Kelas</strong></td>
                        <td>: <?php echo escape($siswa['nama_kelas']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tingkat</strong></td>
                        <td>: <?php echo escape($siswa['tingkat'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td width="30%"><strong>Tahun Ajaran</strong></td>
                        <td>: <?php echo escape($tahun_ajaran); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Semester</strong></td>
                        <td>: <?php echo ucfirst($semester); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Mapel</strong></td>
                        <td>: <?php echo count($penilaian_list); ?> Mapel</td>
                    </tr>
                    <tr>
                        <td><strong>Rata-rata</strong></td>
                        <td>: <strong><?php echo number_format($rata_rata, 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Penilaian Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Daftar Nilai</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Mata Pelajaran</th>
                        <th>Kode</th>
                        <th>Nilai Tugas</th>
                        <th>Nilai UTS</th>
                        <th>Nilai UAS</th>
                        <th>Nilai Akhir</th>
                        <th>Predikat</th>
                        <th>Guru</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($penilaian_list)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Belum ada data penilaian</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($penilaian_list as $index => $penilaian): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($penilaian['nama_mapel']); ?></strong></td>
                                <td><?php echo escape($penilaian['kode_mapel']); ?></td>
                                <td><?php echo $penilaian['nilai_tugas'] !== null ? number_format($penilaian['nilai_tugas'], 2) : '-'; ?></td>
                                <td><?php echo $penilaian['nilai_uts'] !== null ? number_format($penilaian['nilai_uts'], 2) : '-'; ?></td>
                                <td><?php echo $penilaian['nilai_uas'] !== null ? number_format($penilaian['nilai_uas'], 2) : '-'; ?></td>
                                <td><strong><?php echo $penilaian['nilai_akhir'] !== null ? number_format($penilaian['nilai_akhir'], 2) : '-'; ?></strong></td>
                                <td>
                                    <?php if ($penilaian['predikat']): ?>
                                        <span class="badge bg-info"><?php echo escape($penilaian['predikat']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape($penilaian['nama_guru']); ?></td>
                                <td>
                                    <?php if ($penilaian['keterangan']): ?>
                                        <small><?php echo escape($penilaian['keterangan']); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f0f0f0; font-weight: bold;">
                            <td colspan="6" class="text-right">Rata-rata:</td>
                            <td><strong><?php echo number_format($rata_rata, 2); ?></strong></td>
                            <td>
                                <?php
                                $predikat_akhir = '';
                                if ($rata_rata >= 85) {
                                    $predikat_akhir = 'A';
                                } elseif ($rata_rata >= 70) {
                                    $predikat_akhir = 'B';
                                } elseif ($rata_rata >= 55) {
                                    $predikat_akhir = 'C';
                                } elseif ($rata_rata >= 0) {
                                    $predikat_akhir = 'D';
                                }
                                ?>
                                <span class="badge bg-success"><?php echo $predikat_akhir; ?></span>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>






