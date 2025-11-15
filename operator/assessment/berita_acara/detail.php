<?php
/**
 * Detail Berita Acara - Operator Assessment
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

global $pdo;

$id = intval($_GET['id'] ?? 0);
$id_sesi = intval($_GET['id_sesi'] ?? 0);

// Get berita acara
if ($id) {
    $stmt = $pdo->prepare("SELECT ba.*, u.judul, u.tipe_asesmen, u.tahun_ajaran, u.semester, 
                           m.nama_mapel, k.nama_kelas, s.nama_sesi, u2.nama as creator_name
                           FROM berita_acara ba
                           INNER JOIN ujian u ON ba.id_ujian = u.id
                           INNER JOIN mapel m ON u.id_mapel = m.id
                           LEFT JOIN kelas k ON ba.id_kelas = k.id
                           LEFT JOIN sesi_ujian s ON ba.id_sesi = s.id
                           LEFT JOIN users u2 ON ba.created_by = u2.id
                           WHERE ba.id = ?");
    $stmt->execute([$id]);
    $berita_acara = $stmt->fetch();
} elseif ($id_sesi) {
    $stmt = $pdo->prepare("SELECT ba.*, u.judul, u.tipe_asesmen, u.tahun_ajaran, u.semester, 
                           m.nama_mapel, k.nama_kelas, s.nama_sesi, u2.nama as creator_name
                           FROM berita_acara ba
                           INNER JOIN ujian u ON ba.id_ujian = u.id
                           INNER JOIN mapel m ON u.id_mapel = m.id
                           LEFT JOIN kelas k ON ba.id_kelas = k.id
                           LEFT JOIN sesi_ujian s ON ba.id_sesi = s.id
                           LEFT JOIN users u2 ON ba.created_by = u2.id
                           WHERE ba.id_sesi = ?");
    $stmt->execute([$id_sesi]);
    $berita_acara = $stmt->fetch();
} else {
    redirect('operator-assessment-berita-acara-generate');
}

if (!$berita_acara) {
    redirect('operator-assessment-berita-acara-generate');
}

// Get absensi detail
$stmt = $pdo->prepare("SELECT a.*, u.nama as nama_siswa, u.username, k.nama_kelas
                       FROM absensi_ujian a
                       INNER JOIN users u ON a.id_siswa = u.id
                       LEFT JOIN user_kelas uk ON u.id = uk.id_user
                       LEFT JOIN kelas k ON uk.id_kelas = k.id
                       WHERE a.id_sesi = ?
                       ORDER BY u.nama ASC");
$stmt->execute([$berita_acara['id_sesi']]);
$absensi_detail = $stmt->fetchAll();

// Parse pengawas
$pengawas = json_decode($berita_acara['pengawas'], true) ?? [];

$page_title = 'Detail Berita Acara';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Berita Acara</h2>
            <div class="d-flex gap-2">
                <a href="<?php echo base_url('operator-assessment-berita-acara-print?id=' . $berita_acara['id']); ?>" 
                   class="btn btn-primary" target="_blank">
                    <i class="fas fa-print"></i> Print
                </a>
                <a href="<?php echo base_url('operator-assessment-berita-acara-generate'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Ujian</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Judul Ujian</th>
                        <td><?php echo escape($berita_acara['judul']); ?></td>
                    </tr>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <td><?php echo escape($berita_acara['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Kelas</th>
                        <td><?php echo escape($berita_acara['nama_kelas'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Tipe Assessment</th>
                        <td>
                            <span class="badge <?php echo get_sumatip_badge_class($berita_acara['tipe_asesmen']); ?>">
                                <?php echo get_sumatip_badge_label($berita_acara['tipe_asesmen']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Tanggal</th>
                        <td><?php echo format_date($berita_acara['tanggal'], 'd/m/Y'); ?></td>
                    </tr>
                    <tr>
                        <th>Waktu</th>
                        <td><?php echo date('H:i', strtotime($berita_acara['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($berita_acara['waktu_selesai'])); ?></td>
                    </tr>
                    <tr>
                        <th>Pengawas</th>
                        <td>
                            <?php if (!empty($pengawas)): ?>
                                <?php foreach ($pengawas as $p): ?>
                                    <span class="badge bg-info"><?php echo escape($p); ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($berita_acara['catatan']): ?>
                    <tr>
                        <th>Catatan</th>
                        <td><?php echo nl2br(escape($berita_acara['catatan'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Statistik Absensi</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h3 class="mb-0 text-primary"><?php echo $berita_acara['total_peserta']; ?></h3>
                            <small class="text-muted">Total Peserta</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h3 class="mb-0 text-success"><?php echo $berita_acara['total_hadir']; ?></h3>
                            <small class="text-muted">Hadir</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h3 class="mb-0 text-danger"><?php echo $berita_acara['total_tidak_hadir']; ?></h3>
                            <small class="text-muted">Tidak Hadir</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3 bg-light rounded">
                            <h3 class="mb-0 text-warning"><?php echo $berita_acara['total_izin'] + $berita_acara['total_sakit']; ?></h3>
                            <small class="text-muted">Izin/Sakit</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Informasi Berita Acara</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless table-sm">
                    <tr>
                        <th>Dibuat Oleh</th>
                        <td><?php echo escape($berita_acara['creator_name']); ?></td>
                    </tr>
                    <tr>
                        <th>Tanggal Dibuat</th>
                        <td><?php echo format_date($berita_acara['created_at'], 'd/m/Y H:i'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Absensi Detail -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0">Detail Absensi</h5>
    </div>
    <div class="card-body">
        <?php if (empty($absensi_detail)): ?>
            <p class="text-muted text-center">Tidak ada data absensi</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa</th>
                            <th>Username</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>Waktu Absen</th>
                            <th>Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absensi_detail as $index => $absensi): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($absensi['nama_siswa']); ?></td>
                            <td><?php echo escape($absensi['username']); ?></td>
                            <td><?php echo escape($absensi['nama_kelas'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $absensi['status_absen'] === 'hadir' ? 'success' : 
                                        ($absensi['status_absen'] === 'tidak_hadir' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $absensi['status_absen'])); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($absensi['waktu_absen'], 'd/m/Y H:i'); ?></td>
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






