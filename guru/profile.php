<?php
/**
 * Profile Guru - Edit Profile
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Profile Saya';
$role_css = 'guru';
include __DIR__ . '/../includes/header.php';

global $pdo;

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    redirect('guru/login.php');
}

$error = '';
$success = '';

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('guru/index.php');
}

// Handle form submission (only for no_hp)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_hp = sanitize($_POST['no_hp'] ?? '');
    
    try {
        // Update no_hp only
        $stmt = $pdo->prepare("UPDATE users SET no_hp = ? WHERE id = ?");
        $stmt->execute([$no_hp ?: null, $user_id]);
        
        $success = 'Profile berhasil diperbarui';
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Update session
        $_SESSION['nama'] = $user['nama'];
    } catch (PDOException $e) {
        error_log("Update profile error: " . $e->getMessage());
        $error = 'Terjadi kesalahan saat memperbarui profile';
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Profile Saya</h2>
        <p class="text-muted">Kelola informasi profile Anda</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Informasi Profile</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" 
                                   value="<?php echo escape($user['nama'] ?? ''); ?>" 
                                   disabled>
                            <small class="text-muted">Nama tidak dapat diubah. Hubungi administrator untuk perubahan.</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" 
                                   value="<?php echo escape($user['username'] ?? ''); ?>" 
                                   disabled>
                            <small class="text-muted">Username tidak dapat diubah.</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                            <input type="text" class="form-control" id="tanggal_lahir" 
                                   value="<?php echo $user['tanggal_lahir'] ? format_date($user['tanggal_lahir'], 'd/m/Y') : 'Belum diisi'; ?>" 
                                   disabled>
                            <small class="text-muted">Hubungi administrator untuk mengubah tanggal lahir</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="no_hp" class="form-label">Nomor Handphone</label>
                            <input type="text" class="form-control" id="no_hp" 
                                   name="no_hp" 
                                   value="<?php echo escape($user['no_hp'] ?? ''); ?>" 
                                   placeholder="08xxxxxxxxxx">
                            <small class="text-muted">Nomor handphone (opsional)</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <input type="text" class="form-control" id="role" 
                               value="<?php echo ucfirst($user['role'] ?? ''); ?>" 
                               disabled>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                        <a href="<?php echo base_url('guru'); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Status:</strong>
                    <span class="badge bg-<?php echo ($user['status'] ?? '') === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($user['status'] ?? 'inactive'); ?>
                    </span>
                </div>
                
                <?php if (!empty($user['tanggal_lahir'])): ?>
                    <div class="mb-3">
                        <strong>Tanggal Lahir:</strong><br>
                        <span><?php echo format_date($user['tanggal_lahir'], 'd/m/Y'); ?></span>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Tanggal lahir belum diisi. Hubungi administrator untuk mengisi tanggal lahir.
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($user['created_at'])): ?>
                    <div class="mb-3">
                        <strong>Bergabung:</strong><br>
                        <span><?php echo format_date($user['created_at'], 'd/m/Y'); ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($user['last_login'])): ?>
                    <div class="mb-3">
                        <strong>Login Terakhir:</strong><br>
                        <span><?php echo format_date($user['last_login'], 'd/m/Y H:i'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Catatan Penting</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        Tanggal lahir hanya dapat diubah oleh administrator
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Nomor handphone dapat diubah kapan saja
                    </li>
                    <li>
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Hubungi administrator untuk perubahan data lainnya
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

