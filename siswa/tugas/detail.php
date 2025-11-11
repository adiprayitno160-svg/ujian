<?php
/**
 * Detail Tugas - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas) {
    redirect('siswa/tugas/list.php');
}

// Check if student is assigned
if (!is_student_assigned_to_tugas($tugas_id, $_SESSION['user_id'])) {
    redirect('siswa/tugas/list.php');
}

// Get submission
$submission = get_tugas_submission($tugas_id, $_SESSION['user_id']);

// Get attachments
$attachments = get_tugas_attachments($tugas_id);

$page_title = 'Detail Tugas';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

$now = new DateTime();
$deadline = new DateTime($tugas['deadline']);
$is_overdue = $now > $deadline;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold"><?php echo escape($tugas['judul']); ?></h2>
            <a href="<?php echo base_url('siswa/tugas/list.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
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
                        <th width="150">Mata Pelajaran</th>
                        <td><?php echo escape($tugas['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Deadline</th>
                        <td>
                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                <?php echo format_date($tugas['deadline']); ?>
                            </span>
                            <?php if ($is_overdue): ?>
                                <span class="badge bg-danger ms-2">Terlambat</span>
                            <?php else: ?>
                                <?php 
                                $diff = $now->diff($deadline);
                                echo " - Sisa: " . $diff->format('%d hari %h jam %i menit');
                                ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Poin Maksimal</th>
                        <td><?php echo number_format($tugas['poin_maksimal'], 0); ?></td>
                    </tr>
                    <tr>
                        <th>Tipe Tugas</th>
                        <td>
                            <span class="badge bg-info">
                                <?php echo ucfirst($tugas['tipe_tugas']); ?>
                            </span>
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
        <?php if ($submission): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Status Submission</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $submission['status'] === 'dinilai' ? 'success' : 
                                    ($submission['status'] === 'sudah_dikumpulkan' ? 'info' : 'warning'); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($submission['waktu_submit']): ?>
                    <tr>
                        <th>Waktu Submit</th>
                        <td><?php echo format_date($submission['waktu_submit']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission['nilai'] !== null): ?>
                    <tr>
                        <th>Nilai</th>
                        <td><strong><?php echo number_format($submission['nilai'], 2); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission['feedback']): ?>
                    <tr>
                        <th>Feedback</th>
                        <td><?php echo nl2br(escape($submission['feedback'])); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5>Aksi</h5>
                <?php if (!$submission || $submission['status'] === 'belum_dikumpulkan' || $submission['status'] === 'draft'): ?>
                    <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas_id); ?>" 
                       class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-upload"></i> Submit Tugas
                    </a>
                <?php else: ?>
                    <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas_id); ?>" 
                       class="btn btn-info w-100 mb-2">
                        <i class="fas fa-eye"></i> Lihat Submission
                    </a>
                    <?php if (can_student_edit_tugas($tugas_id, $_SESSION['user_id'])): ?>
                        <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas_id); ?>" 
                           class="btn btn-warning w-100">
                            <i class="fas fa-edit"></i> Edit Submission
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>




