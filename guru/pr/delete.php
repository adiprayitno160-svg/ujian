<?php
/**
 * Delete PR - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);

// Verify PR exists and belongs to this guru
$stmt = $pdo->prepare("SELECT * FROM pr WHERE id = ? AND id_guru = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    header("Location: " . base_url('guru/pr/list.php'));
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete file if exists
        if ($pr['file_lampiran']) {
            $file_path = UPLOAD_PR . '/' . $pr['file_lampiran'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete PR (cascade will delete pr_kelas and pr_submission)
        $stmt = $pdo->prepare("DELETE FROM pr WHERE id = ? AND id_guru = ?");
        $stmt->execute([$pr_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        log_activity('delete_pr', 'pr', $pr_id);
        
        // Redirect to list
        header("Location: " . base_url('guru/pr/list.php'));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete PR error: " . $e->getMessage());
        header("Location: " . base_url('guru/pr/list.php?error=delete_failed'));
        exit;
    }
} else {
    // Show confirmation page
    $page_title = 'Hapus PR';
    $role_css = 'guru';
    include __DIR__ . '/../../includes/header.php';
    ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">Hapus PR</h2>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Peringatan!</strong> Apakah Anda yakin ingin menghapus PR ini?
            </div>
            
            <div class="mb-3">
                <strong>Judul:</strong> <?php echo escape($pr['judul']); ?><br>
                <strong>Deadline:</strong> <?php echo format_date($pr['deadline']); ?>
            </div>
            
            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> 
                Tindakan ini tidak dapat dibatalkan. Semua data PR, kelas assignment, dan submission siswa akan dihapus.
            </div>
            
            <form method="POST">
                <input type="hidden" name="confirm_delete" value="1">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus PR
                    </button>
                    <a href="<?php echo base_url('guru/pr/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php } ?>

