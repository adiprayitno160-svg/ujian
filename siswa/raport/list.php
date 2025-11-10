<?php
/**
 * List Raport - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk siswa melihat daftar raport mereka
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Raport Siswa';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';

// Get siswa data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas, k.tingkat
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? 
                      AND uk.tahun_ajaran = ? 
                      AND uk.semester = ?");
$stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
$siswa = $stmt->fetch();

// Get penilaian
$penilaian_list = [];
if ($siswa) {
    $stmt = $pdo->prepare("SELECT pm.*, m.nama_mapel, m.kode_mapel, g.nama as nama_guru
                          FROM penilaian_manual pm
                          INNER JOIN mapel m ON pm.id_mapel = m.id
                          LEFT JOIN users g ON pm.id_guru = g.id
                          WHERE pm.id_siswa = ?
                          AND pm.tahun_ajaran = ?
                          AND pm.semester = ?
                          AND pm.status = 'approved'
                          ORDER BY m.nama_mapel ASC");
    $stmt->execute([$_SESSION['user_id'], $tahun_ajaran, $semester]);
    $penilaian_list = $stmt->fetchAll();
}

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

// Get all tahun ajaran available
$stmt = $pdo->query("SELECT DISTINCT tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
$tahun_ajaran_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Raport Siswa</h2>
            <?php if ($siswa && !empty($penilaian_list)): ?>
                <a href="<?php echo base_url('siswa/raport/print.php?tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
                   class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Cetak Raport
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran">
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo escape($ta['tahun_ajaran']); ?>" 
                                <?php echo $tahun_ajaran == $ta['tahun_ajaran'] ? 'selected' : ''; ?>>
                            <?php echo escape($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="ganjil" <?php echo $semester == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester == 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!$siswa): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        Data siswa tidak ditemukan untuk tahun ajaran dan semester yang dipilih.
    </div>
<?php elseif (empty($penilaian_list)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        Belum ada data penilaian untuk tahun ajaran <?php echo escape($tahun_ajaran); ?> semester <?php echo ucfirst($semester); ?>.
    </div>
<?php else: ?>
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
                            <td>: <strong class="text-primary" style="font-size: 1.2em;"><?php echo number_format($rata_rata, 2); ?></strong></td>
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
                        </tr>
                    </thead>
                    <tbody>
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
                                <td><?php echo escape($penilaian['nama_guru'] ?? '-'); ?></td>
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
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

