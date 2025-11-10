<?php
/**
 * Delete Tugas - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);

// Verify Tugas exists and belongs to this guru
$tugas = get_tugas($tugas_id);

if (!$tugas || $tugas['id_guru'] != $_SESSION['user_id']) {
    header("Location: " . base_url('guru/tugas/list.php'));
    exit;
}

// Check if there are submissions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tugas_submission WHERE id_tugas = ?");
$stmt->execute([$tugas_id]);
$submission_count = $stmt->fetch()['total'];
$has_submissions = $submission_count > 0;

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete attachments
        $attachments = get_tugas_attachments($tugas_id);
        foreach ($attachments as $att) {
            $file_path = UPLOAD_PR . '/' . $att['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete submission files
        $stmt = $pdo->prepare("SELECT tsf.* FROM tugas_submission_file tsf
                              INNER JOIN tugas_submission ts ON tsf.id_submission = ts.id
                              WHERE ts.id_tugas = ?");
        $stmt->execute([$tugas_id]);
        $submission_files = $stmt->fetchAll();
        foreach ($submission_files as $file) {
            $file_path = UPLOAD_PR . '/' . $file['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete Tugas (cascade will delete tugas_kelas, tugas_submission, etc.)
        $stmt = $pdo->prepare("DELETE FROM tugas WHERE id = ? AND id_guru = ?");
        $stmt->execute([$tugas_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        log_activity('delete_tugas', 'tugas', $tugas_id);
        
        header("Location: " . base_url('guru/tugas/list.php'));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete Tugas error: " . $e->getMessage());
        header("Location: " . base_url('guru/tugas/list.php?error=delete_failed'));
        exit;
    }
}

$page_title = 'Hapus Tugas';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Hapus Tugas</h2>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Peringatan!</strong> Tindakan ini tidak dapat dibatalkan.
        </div>
        
        <h5><?php echo escape($tugas['judul']); ?></h5>
        <p class="text-muted">
            <i class="fas fa-book"></i> <?php echo escape($tugas['nama_mapel']); ?><br>
            <i class="fas fa-calendar"></i> Deadline: <?php echo format_date($tugas['deadline']); ?>
        </p>
        
        <?php if ($has_submissions): ?>
            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> 
                Tugas ini sudah memiliki <?php echo $submission_count; ?> submission. 
                Semua data submission akan dihapus juga.
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="confirm_delete" value="1">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Ya, Hapus Tugas
                </button>
                <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

