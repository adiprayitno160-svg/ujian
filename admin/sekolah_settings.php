<?php
/**
 * Sekolah Settings - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Pengaturan Sekolah';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';
$sekolah = get_sekolah_info();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = sanitize($_POST['nama_sekolah'] ?? '');
    $alamat = sanitize($_POST['alamat'] ?? '');
    $no_telp = sanitize($_POST['no_telp'] ?? '');
    $website = sanitize($_POST['website'] ?? '');
    
    if (empty($nama_sekolah)) {
        $error = 'Nama sekolah harus diisi';
    } else {
        try {
            // Handle logo upload
            $logo = $sekolah['logo'] ?? null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_file($_FILES['logo'], UPLOAD_PROFILE, ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old logo if exists
                    if ($logo && file_exists(UPLOAD_PROFILE . '/' . $logo)) {
                        delete_file(UPLOAD_PROFILE . '/' . $logo);
                    }
                    $logo = $upload_result['filename'];
                }
            }
            
            if ($sekolah) {
                // Update
                $stmt = $pdo->prepare("UPDATE sekolah SET nama_sekolah = ?, alamat = ?, no_telp = ?, website = ?, logo = ? WHERE id = ?");
                $stmt->execute([$nama_sekolah, $alamat, $no_telp, $website, $logo, $sekolah['id']]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO sekolah (nama_sekolah, alamat, no_telp, website, logo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama_sekolah, $alamat, $no_telp, $website, $logo]);
            }
            
            $success = 'Pengaturan sekolah berhasil disimpan';
            log_activity('update_sekolah', 'sekolah', $sekolah['id'] ?? null);
            $sekolah = get_sekolah_info(); // Refresh data
        } catch (PDOException $e) {
            error_log("Update sekolah error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat menyimpan';
        }
    }
}
?>


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

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-school"></i> Informasi Sekolah</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" 
                               value="<?php echo escape($sekolah['nama_sekolah'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo escape($sekolah['alamat'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="no_telp" class="form-label">No. Telepon</label>
                        <input type="text" class="form-control" id="no_telp" name="no_telp" 
                               value="<?php echo escape($sekolah['no_telp'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="website" class="form-label">Website</label>
                        <input type="url" class="form-control" id="website" name="website" 
                               value="<?php echo escape($sekolah['website'] ?? ''); ?>" placeholder="https://example.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo Sekolah</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP. Max: 2MB</small>
                        <?php if ($sekolah && !empty($sekolah['logo'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo asset_url('uploads/profile/' . $sekolah['logo']); ?>" alt="Logo" height="100" class="img-thumbnail">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
