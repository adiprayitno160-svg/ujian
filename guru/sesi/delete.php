<?php
/**
 * Delete Sesi - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$sesi_id = intval($_GET['id'] ?? 0);

// Get sesi and verify ownership
$sesi = get_sesi($sesi_id);
if (!$sesi) {
    header("Location: " . base_url('guru/sesi/list.php'));
    exit;
}

// Verify that the sesi belongs to this guru's ujian
$ujian = get_ujian($sesi['id_ujian']);
if ($ujian['id_guru'] != $_SESSION['user_id']) {
    header("Location: " . base_url('guru/sesi/list.php'));
    exit;
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Delete sesi (cascade will delete sesi_peserta, token_ujian, hasil_ujian, etc.)
        $stmt = $pdo->prepare("DELETE FROM sesi_ujian WHERE id = ?");
        $stmt->execute([$sesi_id]);
        
        $pdo->commit();
        log_activity('delete_sesi', 'sesi_ujian', $sesi_id);
        
        // Redirect to list
        header("Location: " . base_url('guru/sesi/list.php'));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete sesi error: " . $e->getMessage());
        header("Location: " . base_url('guru/sesi/list.php?error=delete_failed'));
        exit;
    }
} else {
    // Show confirmation page
    $page_title = 'Hapus Sesi';
    $role_css = 'guru';
    include __DIR__ . '/../../includes/header.php';
    
    // Get ujian info
    $ujian = get_ujian($sesi['id_ujian']);
    ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">Hapus Sesi</h2>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Peringatan!</strong> Apakah Anda yakin ingin menghapus sesi ini?
            </div>
            
            <div class="mb-3">
                <strong>Nama Sesi:</strong> <?php echo escape($sesi['nama_sesi']); ?><br>
                <strong>Ujian:</strong> <?php echo escape($ujian['judul']); ?><br>
                <strong>Waktu Mulai:</strong> <?php echo format_date($sesi['waktu_mulai']); ?><br>
                <strong>Waktu Selesai:</strong> <?php echo format_date($sesi['waktu_selesai']); ?><br>
                <strong>Status:</strong> <span class="badge bg-<?php 
                    echo $sesi['status'] === 'aktif' ? 'success' : 
                        ($sesi['status'] === 'selesai' ? 'info' : 'secondary'); 
                ?>"><?php echo ucfirst($sesi['status']); ?></span>
            </div>
            
            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> 
                Tindakan ini tidak dapat dibatalkan. Semua data sesi, peserta, token, dan hasil ujian akan dihapus.
            </div>
            
            <form method="POST">
                <input type="hidden" name="confirm_delete" value="1">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Ya, Hapus Sesi
                    </button>
                    <a href="<?php echo base_url('guru/sesi/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php } ?>









