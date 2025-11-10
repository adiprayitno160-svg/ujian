<?php
/**
 * Login Page - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in - prevent redirect loop
// Only redirect if user is logged in AND we're on a login page
if (is_logged_in() && isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $current_path = parse_url($current_url, PHP_URL_PATH);
    
    // Only redirect if we're on a login page (prevent loop)
    if (strpos($current_path, 'login') !== false || strpos($current_path, 'siswa-login') !== false) {
        // Determine target route based on role and redirect
        if ($role === 'admin') {
            redirect('admin');
        } elseif ($role === 'guru') {
            redirect('guru');
        } elseif ($role === 'operator') {
            redirect('operator');
        } else {
            redirect('siswa');
        }
        exit;
    }
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

<style>
    body {
        background: linear-gradient(135deg, #e6f2ff 0%, #cce5ff 100%) !important;
        min-height: 100vh;
    }
    
    main.container {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 100vh;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
    
    .login-container {
        width: 100%;
        max-width: 450px;
        padding: 20px;
        margin: 0 auto;
    }
    
    .login-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 102, 204, 0.2);
        overflow: hidden;
    }
    
    .login-header {
        background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .login-header h2 {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: white;
    }
    
    .login-header p {
        margin: 10px 0 0 0;
        opacity: 0.95;
        font-size: 0.95rem;
    }
    
    .login-body {
        padding: 35px;
    }
    
    .form-label {
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 8px;
        font-size: 0.9rem;
    }
    
    .input-group-text {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-right: none;
        color: #0066cc;
        font-size: 1rem;
    }
    
    .form-control {
        border: 1px solid #dee2e6;
        border-left: none;
        padding: 12px 15px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
    }
    
    .input-group:focus-within .input-group-text {
        border-color: #0066cc;
        background: #e6f2ff;
    }
    
    .form-text {
        font-size: 0.85rem;
        color: #6b7280;
        margin-top: 5px;
    }
    
    .btn-login {
        background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
        border: none;
        color: white;
        padding: 12px 24px;
        font-weight: 600;
        font-size: 1rem;
        border-radius: 8px;
        width: 100%;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 102, 204, 0.3);
    }
    
    .btn-login:hover {
        background: linear-gradient(135deg, #0052a3 0%, #004080 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0, 102, 204, 0.4);
        color: white;
    }
    
    .btn-login:active {
        transform: translateY(0);
    }
    
    .alert {
        border-radius: 8px;
        border: none;
        padding: 12px 16px;
        font-size: 0.9rem;
    }
    
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .alert-success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .form-check-input:checked {
        background-color: #0066cc;
        border-color: #0066cc;
    }
    
    .form-check-label {
        font-size: 0.9rem;
        color: #4b5563;
    }
    
    @media (max-width: 576px) {
        .login-container {
            padding: 15px;
        }
        
        .login-body {
            padding: 25px;
        }
        
        .login-header {
            padding: 25px;
        }
        
        .login-header h2 {
            font-size: 1.5rem;
        }
    }
</style>

<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h2><i class="fas fa-graduation-cap me-2"></i>Login Siswa</h2>
            <p>Masuk ke akun Anda</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo escape($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo escape($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="nis" class="form-label">
                        <i class="fas fa-id-card me-1"></i> NIS (Nomor Induk Siswa)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               class="form-control" 
                               id="nis" 
                               name="nis" 
                               placeholder="Masukkan NIS Anda" 
                               required 
                               autofocus>
                    </div>
                    <small class="form-text">Masukkan Nomor Induk Siswa Anda</small>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-1"></i> Password
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-key"></i>
                        </span>
                        <input type="password" 
                               class="form-control" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password" 
                               required>
                    </div>
                </div>
                
                <div class="mb-4 form-check">
                    <input type="checkbox" 
                           class="form-check-input" 
                           id="remember" 
                           name="remember">
                    <label class="form-check-label" for="remember">
                        Ingat saya
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Masuk
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

