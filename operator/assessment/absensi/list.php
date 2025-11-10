<?php
/**
 * Absensi List - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Absensi';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'id_sesi' => intval($_GET['id_sesi'] ?? 0),
    'id_kelas' => intval($_GET['id_kelas'] ?? 0),
    'status_absen' => $_GET['status_absen'] ?? '',
    'tanggal_mulai' => $_GET['tanggal_mulai'] ?? '',
    'tanggal_selesai' => $_GET['tanggal_selesai'] ?? '',
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'filter_assessment' => true // Filter untuk assessment saja
];

// Get absensi report
$absensi_list = get_absensi_report($filters);

// Get sesi for filter (hanya assessment, bukan ujian harian)
$stmt = $pdo->query("SELECT s.*, u.judul as judul_ujian 
                      FROM sesi_ujian s 
                      INNER JOIN ujian u ON s.id_ujian = u.id 
                      WHERE (u.tipe_asesmen IS NOT NULL AND u.tipe_asesmen != '')
                      ORDER BY s.waktu_mulai DESC 
                      LIMIT 50");
$sesi_list = $stmt->fetchAll();

// Get kelas
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? ORDER BY nama_kelas ASC");
$stmt->execute([$filters['tahun_ajaran']]);
$kelas_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div>
            <h2 class="fw-bold">Absensi</h2>
            <p class="text-muted mb-0">Sistem otomatis mencatat siswa yang mengikuti asesment.</p>
        </div>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle"></i> <strong>Informasi:</strong> Absensi untuk asesment otomatis tercatat ketika siswa mulai mengerjakan asesment. 
    Sistem akan menampilkan siapa saja siswa yang mengikuti asesment dan siapa yang tidak mengikuti (tidak hadir) untuk setiap kelas dan setiap siswa keseluruhan.
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Sesi</label>
                <select class="form-select" name="id_sesi">
                    <option value="">Semua</option>
                    <?php foreach ($sesi_list as $sesi): ?>
                        <option value="<?php echo $sesi['id']; ?>" <?php echo $filters['id_sesi'] == $sesi['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($sesi['judul_ujian']); ?> - <?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas">
                    <option value="">Semua</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $filters['id_kelas'] == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status_absen">
                    <option value="">Semua</option>
                    <option value="hadir" <?php echo $filters['status_absen'] === 'hadir' ? 'selected' : ''; ?>>Hadir</option>
                    <option value="tidak_hadir" <?php echo $filters['status_absen'] === 'tidak_hadir' ? 'selected' : ''; ?>>Tidak Hadir</option>
                    <option value="izin" <?php echo $filters['status_absen'] === 'izin' ? 'selected' : ''; ?>>Izin</option>
                    <option value="sakit" <?php echo $filters['status_absen'] === 'sakit' ? 'selected' : ''; ?>>Sakit</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" name="tanggal_mulai" value="<?php echo $filters['tanggal_mulai']; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Selesai</label>
                <input type="date" class="form-control" name="tanggal_selesai" value="<?php echo $filters['tanggal_selesai']; ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Absensi List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($absensi_list)): ?>
            <p class="text-muted text-center">Tidak ada data absensi ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Sesi</th>
                            <th>Status</th>
                            <th>Waktu Absen</th>
                            <th>Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absensi_list as $absensi): ?>
                        <tr>
                            <td><?php echo escape($absensi['nama_siswa']); ?></td>
                            <td><?php echo escape($absensi['nama_kelas'] ?? '-'); ?></td>
                            <td><?php echo escape($absensi['nama_mapel'] ?? '-'); ?></td>
                            <td><?php echo escape($absensi['nama_sesi'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $absensi['status_absen'] === 'hadir' ? 'success' : 
                                        ($absensi['status_absen'] === 'izin' ? 'info' : 
                                        ($absensi['status_absen'] === 'sakit' ? 'warning' : 'danger')); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $absensi['status_absen'])); ?>
                                </span>
                            </td>
                            <td><?php echo $absensi['waktu_absen'] ? format_date($absensi['waktu_absen'], 'd/m/Y H:i') : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $absensi['metode_absen'] === 'auto' ? 'primary' : 'secondary'; ?>">
                                    <?php echo ucfirst($absensi['metode_absen']); ?>
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

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



