<?php
/**
 * Submit PR - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Kumpulkan PR';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ?");
$stmt->execute([$pr_id]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('siswa/pr/list.php');
}

// Check if student is in assigned class
$stmt = $pdo->prepare("SELECT * FROM pr_kelas pk
                      INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                      WHERE pk.id_pr = ? AND uk.id_user = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$is_assigned = $stmt->fetch();

if (!$is_assigned) {
    redirect('siswa/pr/list.php');
}

// Get existing submission
$stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $komentar = sanitize($_POST['komentar'] ?? '');
    
    // Check deadline
    $deadline = new DateTime($pr['deadline']);
    $now = new DateTime();
    
    if ($now > $deadline && !$submission) {
        $error = 'Deadline sudah lewat';
    } else {
        try {
            // Handle file upload
            $file_jawaban = null;
            if (isset($_FILES['file_jawaban']) && $_FILES['file_jawaban']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_file($_FILES['file_jawaban'], UPLOAD_PR, ALLOWED_DOC_TYPES);
                if ($upload_result['success']) {
                    $file_jawaban = $upload_result['filename'];
                } else {
                    $error = $upload_result['message'];
                }
            } elseif ($submission && !$submission['file_jawaban']) {
                $error = 'File jawaban harus diupload';
            }
            
            if (!$error) {
                if ($submission) {
                    // Update
                    if ($file_jawaban) {
                        // Delete old file
                        if ($submission['file_jawaban']) {
                            $old_file = UPLOAD_DIR . '/pr/' . $submission['file_jawaban'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        $stmt = $pdo->prepare("UPDATE pr_submission SET 
                                              file_jawaban = ?, komentar = ?, waktu_submit = NOW(), status = 'sudah_dikumpulkan'
                                              WHERE id = ?");
                        $stmt->execute([$file_jawaban, $komentar, $submission['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE pr_submission SET 
                                              komentar = ?, waktu_submit = NOW(), status = 'sudah_dikumpulkan'
                                              WHERE id = ?");
                        $stmt->execute([$komentar, $submission['id']]);
                    }
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO pr_submission 
                                          (id_pr, id_siswa, file_jawaban, komentar, status, waktu_submit) 
                                          VALUES (?, ?, ?, ?, 'sudah_dikumpulkan', NOW())");
                    $stmt->execute([$pr_id, $_SESSION['user_id'], $file_jawaban, $komentar]);
                }
                
                $success = 'PR berhasil dikumpulkan';
                log_activity('submit_pr', 'pr_submission', $pr_id);
                
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
                $stmt->execute([$pr_id, $_SESSION['user_id']]);
                $submission = $stmt->fetch();
            }
        } catch (PDOException $e) {
            error_log("Submit PR error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat mengumpulkan PR';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Kumpulkan PR</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h4><?php echo escape($pr['judul']); ?></h4>
        <p class="text-muted"><?php echo escape($pr['nama_mapel']); ?></p>
        <table class="table table-borderless">
            <tr>
                <th width="150">Deadline</th>
                <td>
                    <?php 
                    $deadline = new DateTime($pr['deadline']);
                    $now = new DateTime();
                    $class = $now > $deadline ? 'text-danger' : '';
                    ?>
                    <span class="<?php echo $class; ?>">
                        <?php echo format_date($pr['deadline']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Deskripsi</th>
                <td><?php echo nl2br(escape($pr['deskripsi'] ?? '-')); ?></td>
            </tr>
            <?php if ($pr['file_lampiran']): ?>
            <tr>
                <th>File Lampiran</th>
                <td>
                    <a href="<?php echo asset_url('uploads/pr/' . $pr['file_lampiran']); ?>" 
                       target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($submission): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Status Submission</h5>
    </div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="150">Status</th>
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
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="file_jawaban" class="form-label">
                    File Jawaban <?php echo $submission && $submission['file_jawaban'] ? '(Update)' : ''; ?>
                    <span class="text-danger">*</span>
                </label>
                <input type="file" class="form-control" id="file_jawaban" name="file_jawaban" 
                       accept=".pdf,.doc,.docx,.zip" <?php echo !$submission || !$submission['file_jawaban'] ? 'required' : ''; ?>>
                <small class="text-muted">Format: PDF, DOC, DOCX, ZIP. Max: 10MB</small>
                <?php if ($submission && $submission['file_jawaban']): ?>
                <div class="mt-2">
                    <small>File saat ini: 
                        <a href="<?php echo asset_url('uploads/pr/' . $submission['file_jawaban']); ?>" target="_blank">
                            <?php echo escape($submission['file_jawaban']); ?>
                        </a>
                    </small>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label for="komentar" class="form-label">Komentar (opsional)</label>
                <textarea class="form-control" id="komentar" name="komentar" rows="3"><?php echo escape($submission['komentar'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> <?php echo $submission ? 'Update' : 'Kumpulkan'; ?>
                </button>
                <a href="<?php echo base_url('siswa/pr/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
