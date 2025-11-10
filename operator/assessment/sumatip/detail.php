<?php
/**
 * Detail SUMATIP - Operator Assessment
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

$page_title = 'Detail SUMATIP';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$sumatip = get_sumatip($id);

if (!$sumatip) {
    redirect('operator-assessment-index');
}

// Get soal
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$id]);
$soal_list = $stmt->fetchAll();

// Get sesi
$stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id_ujian = ? ORDER BY waktu_mulai ASC");
$stmt->execute([$id]);
$sesi_list = $stmt->fetchAll();

// Get kelas target
$stmt = $pdo->prepare("SELECT k.* FROM sumatip_kelas_target skt
                      INNER JOIN kelas k ON skt.id_kelas = k.id
                      WHERE skt.id_ujian = ?");
$stmt->execute([$id]);
$kelas_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold"><?php echo escape($sumatip['judul']); ?></h2>
            <a href="<?php echo base_url('operator-assessment-sumatip-list'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi SUMATIP</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Jenis SUMATIP</th>
                        <td>
                            <span class="badge <?php echo get_sumatip_badge_class($sumatip['tipe_asesmen']); ?>">
                                <?php echo get_sumatip_badge_label($sumatip['tipe_asesmen']); ?>
                            </span>
                            <?php if ($sumatip['is_mandatory']): ?>
                                <span class="badge bg-danger">Wajib</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <td><?php echo escape($sumatip['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Periode</th>
                        <td><?php echo escape($sumatip['periode_sumatip'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Tahun Ajaran</th>
                        <td><?php echo escape($sumatip['tahun_ajaran'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Semester</th>
                        <td><?php echo escape(ucfirst($sumatip['semester'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th>Tingkat Kelas</th>
                        <td><?php echo escape($sumatip['tingkat_kelas'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Durasi</th>
                        <td><?php echo $sumatip['durasi']; ?> menit</td>
                    </tr>
                    <tr>
                        <th>Guru</th>
                        <td><?php echo escape($sumatip['nama_guru']); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $sumatip['status'] === 'published' ? 'success' : 
                                    ($sumatip['status'] === 'completed' ? 'info' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($sumatip['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo escape($sumatip['deskripsi'] ?? '-'); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistik</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Total Soal:</strong> <?php echo count($soal_list); ?>
                </div>
                <div class="mb-3">
                    <strong>Total Sesi:</strong> <?php echo count($sesi_list); ?>
                </div>
                <div class="mb-3">
                    <strong>Total Kelas:</strong> <?php echo count($kelas_list); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kelas Target -->
<?php if (!empty($kelas_list)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-users"></i> Kelas Target</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($kelas_list as $kelas): ?>
                    <div class="col-md-3 mb-2">
                        <span class="badge bg-primary"><?php echo escape($kelas['nama_kelas']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Sesi -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-calendar"></i> Sesi</h5>
            </div>
            <div class="card-body">
                <?php if (empty($sesi_list)): ?>
                    <p class="text-muted text-center">Belum ada sesi</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama Sesi</th>
                                    <th>Waktu Mulai</th>
                                    <th>Waktu Selesai</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sesi_list as $sesi): ?>
                                <tr>
                                    <td><?php echo escape($sesi['nama_sesi']); ?></td>
                                    <td><?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?></td>
                                    <td><?php echo format_date($sesi['waktu_selesai'], 'd/m/Y H:i'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $sesi['status'] === 'aktif' ? 'success' : 
                                                ($sesi['status'] === 'selesai' ? 'info' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($sesi['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?php echo base_url('operator/sesi/manage.php?id=' . $sesi['id']); ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



