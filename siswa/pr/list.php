<?php
/**
 * List PR - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Daftar PR';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get PR for this student's classes using the function (includes auto-hide logic)
require_once __DIR__ . '/../../includes/pr_functions.php';
$pr_list = get_pr_by_student($_SESSION['user_id'], []);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Daftar PR</h2>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($pr_list)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada PR yang tersedia
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($pr_list as $pr): 
            $now = new DateTime();
            $deadline = new DateTime($pr['deadline']);
            $is_overdue = $now > $deadline;
            $status = $pr['status_submission'] ?? 'belum_dikumpulkan';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 <?php echo $is_overdue && $status !== 'sudah_dikumpulkan' ? 'border-danger' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo escape($pr['judul']); ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-book"></i> <?php echo escape($pr['nama_mapel']); ?>
                    </p>
                    <p class="text-muted mb-2">
                        <i class="fas fa-user-tie"></i> Guru: <?php echo escape($pr['nama_guru'] ?? 'N/A'); ?>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-calendar"></i> 
                        <strong>Deadline:</strong> 
                        <span class="<?php echo $is_overdue && $status !== 'sudah_dikumpulkan' ? 'text-danger' : ''; ?>">
                            <?php echo format_date($pr['deadline']); ?>
                        </span>
                    </p>
                    <?php if ($pr['deskripsi']): ?>
                    <p class="text-muted small mb-3"><?php echo escape(substr($pr['deskripsi'], 0, 100)); ?>...</p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?php 
                            $tipe_badge = [
                                'file_upload' => 'secondary',
                                'online' => 'primary',
                                'hybrid' => 'info'
                            ];
                            echo $tipe_badge[$pr['tipe_pr']] ?? 'secondary';
                        ?> mb-2">
                            <?php 
                            $tipe_label = [
                                'file_upload' => 'File Upload',
                                'online' => 'Online',
                                'hybrid' => 'Hybrid'
                            ];
                            echo $tipe_label[$pr['tipe_pr']] ?? 'File Upload';
                            ?>
                        </span>
                        <br>
                        <span class="badge bg-<?php 
                            echo $status === 'dinilai' ? 'success' : 
                                ($status === 'sudah_dikumpulkan' || $status === 'draft' ? 'info' : 'warning'); 
                        ?>">
                            <?php 
                            echo $status === 'dinilai' ? 'Sudah Dinilai' : 
                                ($status === 'sudah_dikumpulkan' ? 'Sudah Dikumpulkan' : 
                                ($status === 'draft' ? 'Draft' : 'Belum Dikumpulkan')); 
                            ?>
                        </span>
                        <?php if ($pr['nilai_submission'] !== null): ?>
                            <span class="badge bg-primary ms-2">Nilai: <?php echo number_format($pr['nilai_submission'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <?php 
                        $tipe_pr = $pr['tipe_pr'] ?? 'file_upload';
                        $is_online = in_array($tipe_pr, ['online', 'hybrid']);
                        $action_url = $is_online ? 'siswa/pr/take.php' : 'siswa/pr/submit.php';
                        ?>
                        <?php if ($status === 'belum_dikumpulkan' || $status === null || $status === 'draft'): ?>
                            <a href="<?php echo base_url($action_url . '?id=' . $pr['id']); ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-<?php echo $is_online ? 'edit' : 'upload'; ?>"></i> 
                                <?php echo $is_online ? 'Kerjakan PR' : 'Kumpulkan'; ?>
                            </a>
                            <?php if ($tipe_pr === 'hybrid'): ?>
                                <a href="<?php echo base_url('siswa/pr/submit.php?id=' . $pr['id']); ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-upload"></i> Upload File
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo base_url($action_url . '?id=' . $pr['id']); ?>" 
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Lihat Detail
                            </a>
                            <?php if ($pr['allow_edit_after_submit'] && !$is_overdue): ?>
                                <a href="<?php echo base_url($action_url . '?id=' . $pr['id']); ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
