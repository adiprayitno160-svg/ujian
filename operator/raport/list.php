<?php
/**
 * Raport - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator melihat dan mencetak raport siswa
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Raport - Operator';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';
$id_kelas = intval($_GET['id_kelas'] ?? 0);

// Get kelas list
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? AND status = 'active' ORDER BY nama_kelas ASC");
$stmt->execute([$tahun_ajaran]);
$kelas_list = $stmt->fetchAll();

// Get siswa list
$siswa_list = [];
if ($id_kelas) {
    $stmt = $pdo->prepare("SELECT u.id, u.username as nis, u.nama, k.nama_kelas
                          FROM users u
                          INNER JOIN user_kelas uk ON u.id = uk.id_user
                          INNER JOIN kelas k ON uk.id_kelas = k.id
                          WHERE u.role = 'siswa' 
                          AND u.status = 'active'
                          AND uk.id_kelas = ?
                          AND uk.tahun_ajaran = ?
                          AND uk.semester = ?
                          ORDER BY u.nama ASC");
    $stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
    $siswa_list = $stmt->fetchAll();
}

// Get all mapel
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get penilaian for siswa
$penilaian_data = [];
if (!empty($siswa_list)) {
    $siswa_ids = array_column($siswa_list, 'id');
    $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
    
    $stmt = $pdo->prepare("SELECT * FROM penilaian_manual
                          WHERE tahun_ajaran = ?
                          AND semester = ?
                          AND status = 'approved'
                          AND id_siswa IN ($placeholders)");
    $params = array_merge([$tahun_ajaran, $semester], $siswa_ids);
    $stmt->execute($params);
    $penilaian_results = $stmt->fetchAll();
    
    foreach ($penilaian_results as $p) {
        if (!isset($penilaian_data[$p['id_siswa']])) {
            $penilaian_data[$p['id_siswa']] = [];
        }
        $penilaian_data[$p['id_siswa']][$p['id_mapel']] = $p;
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Raport Siswa</h2>
        <p class="text-muted">Lihat dan cetak raport siswa berdasarkan nilai yang sudah disetujui</p>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <input type="text" class="form-control" value="<?php echo escape($tahun_ajaran); ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="ganjil" <?php echo $semester == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester == 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $id_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Tampilkan
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($id_kelas && !empty($siswa_list)): ?>
    <!-- Raport List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-file-alt"></i> Daftar Raport
                <?php 
                $kelas_nama = '';
                foreach ($kelas_list as $k) {
                    if ($k['id'] == $id_kelas) {
                        $kelas_nama = $k['nama_kelas'];
                        break;
                    }
                }
                ?>
                - <?php echo escape($kelas_nama); ?> - Semester <?php echo ucfirst($semester); ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Total Mapel</th>
                            <th>Rata-rata</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswa_list as $index => $siswa): ?>
                            <?php 
                            $penilaian_siswa = $penilaian_data[$siswa['id']] ?? [];
                            $total_mapel = count($penilaian_siswa);
                            $total_nilai = 0;
                            $count_nilai = 0;
                            
                            foreach ($penilaian_siswa as $p) {
                                if ($p['nilai_akhir'] !== null) {
                                    $total_nilai += $p['nilai_akhir'];
                                    $count_nilai++;
                                }
                            }
                            
                            $rata_rata = $count_nilai > 0 ? $total_nilai / $count_nilai : 0;
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $total_mapel; ?> Mapel</span>
                                </td>
                                <td>
                                    <strong><?php echo number_format($rata_rata, 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($total_mapel >= count($mapel_list) * 0.8): // 80% mapel sudah ada nilainya ?>
                                        <span class="badge bg-success">Lengkap</span>
                                    <?php elseif ($total_mapel > 0): ?>
                                        <span class="badge bg-warning">Belum Lengkap</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Belum Ada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('operator/raport/print.php?id_siswa=' . $siswa['id'] . '&tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="fas fa-print"></i> Cetak Raport
                                    </a>
                                    <a href="<?php echo base_url('operator/raport/detail.php?id_siswa=' . $siswa['id'] . '&tahun_ajaran=' . urlencode($tahun_ajaran) . '&semester=' . $semester); ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Lihat Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif ($id_kelas): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Tidak ada siswa di kelas yang dipilih untuk semester ini.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


