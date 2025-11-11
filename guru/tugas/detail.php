<?php
/**
 * Detail Tugas - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas || $tugas['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/tugas/list.php');
}

// Get statistics
$stats = get_tugas_statistics($tugas_id);

// Get attachments
$attachments = get_tugas_attachments($tugas_id);

// Get kelas
$kelas_list = get_tugas_kelas($tugas_id);

$page_title = 'Detail Tugas';
$role_css = 'guru';
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
                <a href="<?php echo base_url('guru/tugas/review.php?id=' . $tugas_id); ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> Review
                </a>
                <a href="<?php echo base_url('guru/tugas/edit.php?id=' . $tugas_id); ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h4>Informasi Tugas</h4>
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Mata Pelajaran</th>
                        <td><?php echo escape($tugas['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Deadline</th>
                        <td><?php echo format_date($tugas['deadline']); ?></td>
                    </tr>
                    <tr>
                        <th>Poin Maksimal</th>
                        <td><?php echo number_format($tugas['poin_maksimal'], 0); ?></td>
                    </tr>
                    <tr>
                        <th>Tipe Tugas</th>
                        <td>
                            <span class="badge bg-info"><?php echo ucfirst($tugas['tipe_tugas']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php echo $tugas['status'] === 'published' ? 'success' : ($tugas['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                <?php echo ucfirst($tugas['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Kelas</th>
                        <td>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <span class="badge bg-secondary me-1"><?php echo escape($kelas['nama_kelas']); ?></span>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo nl2br(escape($tugas['deskripsi'] ?? '-')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php if (!empty($attachments)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-paperclip"></i> File Lampiran</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($attachments as $att): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file"></i> 
                                <strong><?php echo escape($att['nama_file']); ?></strong>
                                <small class="text-muted">(<?php echo format_file_size($att['file_size']); ?>)</small>
                            </div>
                            <a href="<?php echo asset_url('uploads/pr/' . $att['file_path']); ?>" 
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Statistik</h5>
            </div>
            <div class="card-body">
                <?php if ($stats): ?>
                    <table class="table table-borderless">
                        <tr>
                            <th>Total Siswa</th>
                            <td><?php echo $stats['total_siswa']; ?></td>
                        </tr>
                        <tr>
                            <th>Total Submission</th>
                            <td><?php echo $stats['total_submission'] ?? 0; ?></td>
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
                            <th>Sudah Dinilai</th>
                            <td><?php echo $stats['sudah_dinilai'] ?? 0; ?></td>
                        </tr>
                        <tr>
                            <th>Completion Rate</th>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $stats['completion_rate']; ?>%"
                                         aria-valuenow="<?php echo $stats['completion_rate']; ?>" 
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $stats['completion_rate']; ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php if ($stats['rata_rata_nilai']): ?>
                        <tr>
                            <th>Rata-rata Nilai</th>
                            <td><strong><?php echo number_format($stats['rata_rata_nilai'], 2); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Belum ada data statistik</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>




