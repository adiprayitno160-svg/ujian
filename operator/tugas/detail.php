<?php
/**
 * Detail Tugas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tugas_functions.php';

require_role('operator');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas) {
    redirect('operator-tugas-list');
}

// Get statistics
$stats = get_tugas_statistics($tugas_id);

// Get attachments
$attachments = get_tugas_attachments($tugas_id);

// Get kelas
$kelas_list = get_tugas_kelas($tugas_id);

$page_title = 'Detail Tugas - Operator';
$role_css = 'operator';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold"><?php echo escape($tugas['judul']); ?></h2>
                <p class="text-muted mb-0"><?php echo escape($tugas['nama_mapel']); ?></p>
            </div>
            <div>
                <a href="<?php echo base_url('operator-tugas-review?id=' . $tugas_id); ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Review
                </a>
                <a href="<?php echo base_url('operator-tugas-edit?id=' . $tugas_id); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="<?php echo base_url('operator-tugas-list'); ?>" class="btn btn-secondary">
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
                <h5 class="mb-0">Informasi Tugas</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Judul</th>
                        <td><?php echo escape($tugas['judul']); ?></td>
                    </tr>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <td><?php echo escape($tugas['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Guru</th>
                        <td><?php echo escape($tugas['nama_guru'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo nl2br(escape($tugas['deskripsi'] ?? '-')); ?></td>
                    </tr>
                    <tr>
                        <th>Deadline</th>
                        <td><?php echo format_date($tugas['deadline']); ?></td>
                    </tr>
                    <tr>
                        <th>Poin Maksimal</th>
                        <td><?php echo number_format($tugas['poin_maksimal'], 2); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $tugas['status'] === 'published' ? 'success' : 
                                    ($tugas['status'] === 'draft' ? 'warning' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($tugas['status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if (!empty($attachments)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">File Lampiran</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <?php foreach ($attachments as $attachment): ?>
                    <li class="list-group-item">
                        <a href="<?php echo base_url('uploads/pr/' . $attachment['file_path']); ?>" target="_blank">
                            <i class="fas fa-file"></i> <?php echo escape($attachment['nama_file']); ?>
                        </a>
                        <small class="text-muted">(<?php echo format_file_size($attachment['file_size']); ?>)</small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Statistik</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Total Siswa</th>
                        <td><?php echo $stats['total_siswa'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Sudah Dikumpulkan</th>
                        <td><?php echo $stats['sudah_dikumpulkan'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Belum Dikumpulkan</th>
                        <td><?php echo $stats['belum_dikumpulkan'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Completion Rate</th>
                        <td><?php echo number_format($stats['completion_rate'] ?? 0, 2); ?>%</td>
                    </tr>
                    <tr>
                        <th>Rata-rata Nilai</th>
                        <td><?php echo $stats['rata_rata_nilai'] ? number_format($stats['rata_rata_nilai'], 2) : '-'; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Kelas yang Ditetapkan</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach ($kelas_list as $kelas): ?>
                    <li class="list-group-item px-0">
                        <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

