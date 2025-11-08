<?php
/**
 * Login Page - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect($_SESSION['role'] . '/index.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nis = sanitize($_POST['nis'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($nis) || empty($password)) {
        $error = 'NIS dan password harus diisi';
    } else {
        // Login siswa menggunakan NIS sebagai username dan password
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'siswa' AND status = 'active'");
            $stmt->execute([$nis]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Password adalah NIS (verifikasi dengan password_verify atau jika belum di-hash, verifikasi langsung)
                $password_valid = false;
                
                // Cek jika password sudah di-hash
                if (password_verify($password, $user['password'])) {
                    $password_valid = true;
                } 
                // Fallback: jika password belum di-hash dan input sama dengan NIS
                elseif ($password === $nis && $user['password'] === $nis) {
                    $password_valid = true;
                    // Update password menjadi hashed untuk keamanan
                    $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user['id']]);
                }
                
                if ($password_valid) {
                    // Update last login
                    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['nama'] = $user['nama'];
                    $_SESSION['logged_in'] = true;
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    redirect('index.php');
                } else {
                    $error = 'NIS atau password salah';
                }
            } else {
                $error = 'NIS tidak ditemukan';
            }
        } catch (PDOException $e) {
            error_log("Login siswa error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat login';
        }
    }
}

$page_title = 'Login Siswa';
$hide_navbar = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-primary">Login Siswa</h2>
                    <p class="text-muted">Masuk ke akun Anda</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="nis" class="form-label">NIS</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" class="form-control" id="nis" name="nis" 
                                   placeholder="Masukkan NIS" required autofocus>
                        </div>
                        <small class="text-muted">Nomor Induk Siswa</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password (NIS)" required>
                        </div>
                        <small class="text-muted">Password default: NIS</small>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Ingat saya</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <a href="<?php echo base_url('about.php'); ?>" class="text-decoration-none">
                        <i class="fas fa-info-circle"></i> Tentang Aplikasi
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

