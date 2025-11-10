<?php
/**
 * Delete Arsip Soal - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

global $pdo;

$pool_id = intval($_GET['id'] ?? 0);

if (!$pool_id) {
    redirect('admin/arsip_soal/list.php');
}

// Check if pool exists
$stmt = $pdo->prepare("SELECT * FROM arsip_soal WHERE id = ?");
$stmt->execute([$pool_id]);
$pool = $stmt->fetch();

if (!$pool) {
    redirect('admin/arsip_soal/list.php');
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        // Delete pool (cascade will delete all items)
        $stmt = $pdo->prepare("DELETE FROM arsip_soal WHERE id = ?");
        $stmt->execute([$pool_id]);
        
        log_activity('delete_arsip_soal', 'arsip_soal', $pool_id);
        
        redirect('admin/arsip_soal/list.php?success=deleted');
    } catch (PDOException $e) {
        error_log("Delete arsip soal error: " . $e->getMessage());
        redirect('admin/arsip_soal/detail.php?id=' . $pool_id . '&error=delete_failed');
    }
} else {
    // Show confirmation
    $page_title = 'Hapus Arsip Soal';
    $role_css = 'admin';
    include __DIR__ . '/../../includes/header.php';
    ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">Hapus Arsip Soal</h2>
        </div>
    </div>
    
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Peringatan!</strong> Apakah Anda yakin ingin menghapus arsip soal "<strong><?php echo escape($pool['nama_pool']); ?></strong>"?
        <br><br>
        Tindakan ini akan menghapus semua soal dalam pool ini dan tidak dapat dibatalkan!
    </div>
    
    <form method="POST">
        <input type="hidden" name="confirm" value="1">
        <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash"></i> Ya, Hapus Arsip
        </button>
        <a href="<?php echo base_url('admin/arsip_soal/detail.php?id=' . $pool_id); ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Batal
        </a>
    </form>
    
    <?php include __DIR__ . '/../../includes/footer.php';
}

