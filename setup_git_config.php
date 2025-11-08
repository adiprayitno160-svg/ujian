<?php
/**
 * Setup Git Configuration
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Script ini membantu setup Git configuration
 * Akses: http://localhost/UJAN/setup_git_config.php
 */

require_once __DIR__ . '/config/config.php';

// Only allow admin or localhost
$is_admin = (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN);
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if (!$is_admin && !$is_localhost) {
    die('Access denied. Please login as admin or access from localhost.');
}

$success = '';
$error = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = trim($_POST['user_name'] ?? '');
    $user_email = trim($_POST['user_email'] ?? '');
    
    if (empty($user_name) || empty($user_email)) {
        $error = 'Username dan email harus diisi';
    } else {
        $output = [];
        $return_var = 0;
        
        // Set user name
        $name_escaped = escapeshellarg($user_name);
        exec('git config --global user.name ' . $name_escaped . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0) {
            // Set user email
            $email_escaped = escapeshellarg($user_email);
            exec('git config --global user.email ' . $email_escaped . ' 2>&1', $return_var);
            
            if ($return_var === 0) {
                $success = 'Git configuration berhasil disetup!';
            } else {
                $error = 'Gagal set email: ' . implode("\n", $output);
            }
        } else {
            $error = 'Gagal set username: ' . implode("\n", $output);
        }
    }
}

// Get current config
$current_name = '';
$current_email = '';

$output = [];
exec('git config --global user.name 2>&1', $output, $return_var);
if ($return_var === 0 && !empty($output)) {
    $current_name = trim($output[0]);
}

$output = [];
exec('git config --global user.email 2>&1', $output, $return_var);
if ($return_var === 0 && !empty($output)) {
    $current_email = trim($output[0]);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Setup Git Configuration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-cog"></i> Setup Git Configuration</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                                <br><br>
                                <a href="<?php echo base_url('push_to_github.php'); ?>" class="btn btn-success">
                                    <i class="fas fa-arrow-right"></i> Lanjutkan Push ke GitHub
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($current_name && $current_email): ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Current Configuration</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($current_name); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($current_email); ?></p>
                                <p class="mb-0">Jika ingin mengubah, isi form di bawah:</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="user_name" class="form-label">Git Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="user_name" name="user_name" 
                                       value="<?php echo htmlspecialchars($current_name); ?>" 
                                       placeholder="Your Name" required>
                                <small class="text-muted">Nama yang akan muncul di commit history</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="user_email" class="form-label">Git Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="user_email" name="user_email" 
                                       value="<?php echo htmlspecialchars($current_email); ?>" 
                                       placeholder="your.email@example.com" required>
                                <small class="text-muted">Email yang terhubung dengan GitHub account</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Configuration
                                </button>
                                <a href="<?php echo base_url('push_to_github.php'); ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Kembali ke Push Helper
                                </a>
                            </div>
                        </form>
                        
                        <hr>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-lightbulb"></i> Tips:</h6>
                            <ul class="mb-0">
                                <li>Gunakan nama dan email yang sama dengan GitHub account</li>
                                <li>Email harus terverifikasi di GitHub</li>
                                <li>Konfigurasi ini bersifat global (untuk semua repository)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

