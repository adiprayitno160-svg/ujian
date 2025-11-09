<?php
/**
 * Delete Ujian - Guru/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

// Allow guru and operator (with operator access)
if ($_SESSION['role'] !== 'guru' && !has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$ujian_id = intval($_GET['id'] ?? 0);

// Get ujian
$ujian = get_ujian($ujian_id);
if (!$ujian) {
    header("Location: " . base_url('guru/ujian/list.php'));
    exit;
}

// Verify ownership for guru (operator can delete any ujian)
if ($_SESSION['role'] === 'guru' && $ujian['id_guru'] != $_SESSION['user_id']) {
    header("Location: " . base_url('guru/ujian/list.php'));
    exit;
}

// Check if ujian has active sesi
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM sesi_ujian WHERE id_ujian = ? AND status = 'aktif'");
$stmt->execute([$ujian_id]);
$active_sesi = $stmt->fetch()['total'];

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();
        
        // Get all soal with images to delete files
        $stmt = $pdo->prepare("SELECT gambar FROM soal WHERE id_ujian = ? AND gambar IS NOT NULL AND gambar != ''");
        $stmt->execute([$ujian_id]);
        $soal_images = $stmt->fetchAll();
        
        // Delete soal images
        foreach ($soal_images as $soal) {
            if ($soal['gambar']) {
                $file_path = UPLOAD_SOAL . '/' . $soal['gambar'];
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }
        
        // Delete ujian (cascade will delete soal, sesi_ujian, dan semua data terkait)
        $stmt = $pdo->prepare("DELETE FROM ujian WHERE id = ?");
        $stmt->execute([$ujian_id]);
        
        $pdo->commit();
        log_activity('delete_ujian', 'ujian', $ujian_id);
        
        // Redirect to list
        header("Location: " . base_url('guru/ujian/list.php?success=ujian_deleted'));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Delete ujian error: " . $e->getMessage());
        header("Location: " . base_url('guru/ujian/list.php?error=delete_failed'));
        exit;
    }
} else {
    // Show confirmation page
    $page_title = 'Hapus Ujian';
    $role_css = $_SESSION['role'] === 'guru' ? 'guru' : 'admin';
    include __DIR__ . '/../../includes/header.php';
    
    // Get statistics
    $stmt = $pdo->prepare("SELECT 
                          (SELECT COUNT(*) FROM soal WHERE id_ujian = ?) as total_soal,
                          (SELECT COUNT(*) FROM sesi_ujian WHERE id_ujian = ?) as total_sesi,
                          (SELECT COUNT(*) FROM sesi_peserta sp 
                           INNER JOIN sesi_ujian su ON sp.id_sesi = su.id 
                           WHERE su.id_ujian = ?) as total_peserta");
    $stmt->execute([$ujian_id, $ujian_id, $ujian_id]);
    $stats = $stmt->fetch();
    ?>
    
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">Hapus Ujian</h2>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Peringatan!</strong> Apakah Anda yakin ingin menghapus ujian ini?
            </div>
            
            <div class="mb-3">
                <h5><?php echo escape($ujian['judul']); ?></h5>
                <p class="text-muted mb-2">
                    <strong>Mata Pelajaran:</strong> <?php echo escape($ujian['nama_mapel']); ?><br>
                    <strong>Guru:</strong> <?php echo escape($ujian['nama_guru']); ?><br>
                    <strong>Durasi:</strong> <?php echo $ujian['durasi']; ?> menit<br>
                    <strong>Status:</strong> 
                    <span class="badge bg-<?php 
                        echo $ujian['status'] === 'published' ? 'success' : 
                            ($ujian['status'] === 'completed' ? 'info' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst($ujian['status']); ?>
                    </span>
                </p>
            </div>
            
            <div class="alert alert-info">
                <strong>Statistik Ujian:</strong>
                <ul class="mb-0">
                    <li>Total Soal: <strong><?php echo $stats['total_soal']; ?></strong></li>
                    <li>Total Sesi: <strong><?php echo $stats['total_sesi']; ?></strong> 
                        <?php if ($active_sesi > 0): ?>
                            <span class="badge bg-danger"><?php echo $active_sesi; ?> aktif</span>
                        <?php endif; ?>
                    </li>
                    <li>Total Peserta: <strong><?php echo $stats['total_peserta']; ?></strong></li>
                </ul>
            </div>
            
            <?php if ($active_sesi > 0): ?>
            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> 
                <strong>Peringatan:</strong> Ujian ini memiliki <strong><?php echo $active_sesi; ?></strong> sesi yang masih aktif. 
                Menghapus ujian ini akan menghapus semua sesi aktif dan data terkait.
            </div>
            <?php endif; ?>
            
            <div class="alert alert-danger">
                <i class="fas fa-info-circle"></i> 
                <strong>Tindakan ini tidak dapat dibatalkan!</strong> Semua data ujian akan dihapus termasuk:
                <ul class="mb-0 mt-2">
                    <li>Semua soal dan gambar</li>
                    <li>Semua sesi ujian</li>
                    <li>Semua hasil ujian dan jawaban siswa</li>
                    <li>Semua token ujian</li>
                    <li>Semua data peserta</li>
                </ul>
            </div>
            
            <form method="POST">
                <input type="hidden" name="confirm_delete" value="1">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger" onclick="return confirm('Apakah Anda YAKIN ingin menghapus ujian ini? Tindakan ini TIDAK DAPAT DIBATALKAN!');">
                        <i class="fas fa-trash"></i> Ya, Hapus Ujian
                    </button>
                    <a href="<?php echo base_url('guru/ujian/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php } ?>

