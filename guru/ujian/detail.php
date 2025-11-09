<?php
/**
 * Detail Ujian - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Detail Ujian';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$ujian = get_ujian($id);

if (!$ujian || $ujian['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/ujian/list.php');
}

// Get soal
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$id]);
$soal_list = $stmt->fetchAll();

// Get sesi
$stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id_ujian = ? ORDER BY waktu_mulai ASC");
$stmt->execute([$id]);
$sesi_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold"><?php echo escape($ujian['judul']); ?></h2>
            <div class="btn-group">
                <a href="<?php echo base_url('guru/ujian/settings.php?id=' . $id); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="<?php echo base_url('guru/sesi/create.php?ujian_id=' . $id); ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Buat Sesi
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Ujian</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Mata Pelajaran</th>
                        <td><?php echo escape($ujian['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Durasi</th>
                        <td><?php echo $ujian['durasi']; ?> menit</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $ujian['status'] === 'published' ? 'success' : 
                                    ($ujian['status'] === 'completed' ? 'info' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($ujian['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo escape($ujian['deskripsi'] ?? '-'); ?></td>
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
                    <strong>Acak Soal:</strong> 
                    <span class="badge bg-<?php echo $ujian['acak_soal'] ? 'success' : 'secondary'; ?>">
                        <?php echo $ujian['acak_soal'] ? 'Ya' : 'Tidak'; ?>
                    </span>
                </div>
                <div>
                    <strong>Acak Opsi:</strong> 
                    <span class="badge bg-<?php echo $ujian['acak_opsi'] ? 'success' : 'secondary'; ?>">
                        <?php echo $ujian['acak_opsi'] ? 'Ya' : 'Tidak'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Soal</h5>
                    <a href="<?php echo base_url('guru/soal/create.php?ujian_id=' . $id); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> Tambah
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($soal_list)): ?>
                    <p class="text-muted text-center">Belum ada soal</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($soal_list as $index => $soal): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong>Soal #<?php echo $index + 1; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></span>
                                    <p class="mb-0 mt-1"><?php echo escape(substr(strip_tags($soal['pertanyaan']), 0, 100)); ?>...</p>
                                </div>
                                <div>
                                    <a href="<?php echo base_url('guru/soal/edit.php?id=' . $soal['id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar"></i> Sesi</h5>
                    <a href="<?php echo base_url('guru/sesi/create.php?ujian_id=' . $id); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> Tambah
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($sesi_list)): ?>
                    <p class="text-muted text-center">Belum ada sesi</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($sesi_list as $sesi): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong><?php echo escape($sesi['nama_sesi']); ?></strong>
                                    <span class="badge bg-<?php 
                                        echo $sesi['status'] === 'aktif' ? 'success' : 
                                            ($sesi['status'] === 'selesai' ? 'info' : 'secondary'); 
                                    ?> ms-2">
                                        <?php echo ucfirst($sesi['status']); ?>
                                    </span>
                                    <p class="mb-0 mt-1 small">
                                        <i class="fas fa-calendar"></i> <?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?> - 
                                        <?php echo format_date($sesi['waktu_selesai'], 'H:i'); ?>
                                    </p>
                                </div>
                                <div>
                                    <a href="<?php echo base_url('guru/sesi/manage.php?id=' . $sesi['id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-cog"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Tools & Analisis</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <a href="<?php echo base_url('guru/analisis/list.php?ujian_id=' . $id); ?>" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chart-bar"></i> Analisis Butir Soal
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('guru/nilai/list.php?ujian_id=' . $id); ?>" class="btn btn-outline-success w-100">
                            <i class="fas fa-list"></i> Daftar Nilai
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('guru/nilai/statistik.php?ujian_id=' . $id); ?>" class="btn btn-outline-info w-100">
                            <i class="fas fa-chart-pie"></i> Statistik Nilai
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('guru/soal/import.php?ujian_id=' . $id); ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-file-import"></i> Import Soal
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('guru/soal/export.php?ujian_id=' . $id); ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-file-export"></i> Export Soal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
