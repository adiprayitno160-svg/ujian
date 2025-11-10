<?php
/**
 * Login Page - Admin, Guru & Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in - prevent redirect loop
if (is_logged_in() && isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    $current_url = $_SERVER['REQUEST_URI'] ?? '';
    $current_path = parse_url($current_url, PHP_URL_PATH);
    
    // Determine target path based on role
    $target_route = '';
    if ($role === 'admin') {
        $target_route = 'admin';
    } elseif ($role === 'guru') {
        $target_route = 'guru';
    } elseif ($role === 'operator') {
        $target_route = 'operator';
    } else {
        $target_route = 'siswa';
    }
    
    // Only redirect if we're on a login page (prevent loop)
    if (strpos($current_path, 'login') !== false) {
        redirect($target_route);
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $result = login($username, $password);
        
        if ($result['success']) {
            // Check if user is admin, guru, or operator
            $role = $result['user']['role'];
            if ($role !== 'admin' && $role !== 'guru' && $role !== 'operator') {
                $error = 'Halaman ini hanya untuk admin, guru, dan operator';
                logout();
            } else {
                // Redirect based on role - use explicit routes to avoid redirect loop
                if ($role === 'admin') {
                    redirect('admin');
                } elseif ($role === 'guru') {
                    redirect('guru');
                } elseif ($role === 'operator') {
                    redirect('operator');
                } else {
                    redirect('siswa');
                }
            }
        } else {
            $error = $result['message'];
        }
    }
}

$page_title = 'Login Admin / Guru / Operator';
$hide_navbar = true;
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center align-items-center min-vh-100">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-lg border-0">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-primary">Login Admin / Guru / Operator</h2>
                    <p class="text-muted">Akses Administrator, Guru & Operator</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Masukkan username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        <a href="<?php echo base_url('login'); ?>" class="text-decoration-none">
                            Login sebagai Siswa?
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

