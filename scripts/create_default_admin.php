<?php
/**
 * Create Default Admin User
 * Script untuk membuat user admin default setelah ganti database
 * 
 * Cara penggunaan:
 * 1. Akses melalui browser: http://localhost/UJAN/scripts/create_default_admin.php
 * 2. Atau jalankan via CLI: php scripts/create_default_admin.php
 */

// Prevent direct access in production (optional - comment out if needed)
// if (php_sapi_name() !== 'cli' && $_SERVER['HTTP_HOST'] !== 'localhost') {
//     die('Script ini hanya bisa diakses dari localhost');
// }

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Default admin credentials
$default_username = 'admin';
$default_password = 'admin123'; // GANTI PASSWORD INI SETELAH LOGIN PERTAMA KALI!
$default_nama = 'Administrator';

$message = '';
$error = '';
$success = false;

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Handle form submission (web)
if (!$is_cli && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? $default_username);
    $password = $_POST['password'] ?? '';
    $nama = trim($_POST['nama'] ?? $default_nama);
    
    if (empty($username) || empty($password) || empty($nama)) {
        $error = 'Semua field harus diisi';
    } else {
        try {
            // Check if admin user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'admin'");
            $stmt->execute([$username]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = "User admin dengan username '$username' sudah ada. Silakan gunakan username lain atau hapus user yang sudah ada terlebih dahulu.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create admin user
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'admin', ?, 'active')");
                $stmt->execute([$username, $hashed_password, $nama]);
                
                $success = true;
                $message = "User admin berhasil dibuat!\n";
                $message .= "Username: $username\n";
                $message .= "Password: (yang Anda masukkan)\n";
                $message .= "Silakan login menggunakan kredensial tersebut.";
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Auto-create with default credentials if running from CLI
if ($is_cli) {
    try {
        // Check if admin user already exists
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE role = 'admin'");
        $stmt->execute();
        $existing_admins = $stmt->fetchAll();
        
        if (!empty($existing_admins)) {
            echo "User admin sudah ada:\n";
            foreach ($existing_admins as $admin) {
                echo "  - ID: {$admin['id']}, Username: {$admin['username']}\n";
            }
            echo "\nTidak perlu membuat user admin baru.\n";
        } else {
            // Hash password
            $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
            
            // Create admin user
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'admin', ?, 'active')");
            $stmt->execute([$default_username, $hashed_password, $default_nama]);
            
            echo "========================================\n";
            echo "User admin default berhasil dibuat!\n";
            echo "========================================\n";
            echo "Username: $default_username\n";
            echo "Password: $default_password\n";
            echo "\n";
            echo "PERINGATAN: Segera ganti password setelah login pertama kali!\n";
            echo "========================================\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
    exit(0);
}

// Web interface
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat User Admin Default</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-user-shield"></i> Buat User Admin Default
                        </h3>
                        <p class="mb-0 mt-2">Setelah ganti database, buat user admin baru</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 
                                <strong>Berhasil!</strong><br>
                                <?php echo nl2br(htmlspecialchars($message)); ?>
                                <hr>
                                <a href="<?php echo base_url('admin/login.php'); ?>" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt"></i> Login Sekarang
                                </a>
                            </div>
                        <?php else: ?>
                            <?php
                            // Check if admin already exists
                            try {
                                $stmt = $pdo->prepare("SELECT id, username, nama FROM users WHERE role = 'admin'");
                                $stmt->execute();
                                $existing_admins = $stmt->fetchAll();
                                
                                if (!empty($existing_admins)) {
                                    echo '<div class="alert alert-info">';
                                    echo '<i class="fas fa-info-circle"></i> <strong>User admin sudah ada:</strong><ul class="mb-0 mt-2">';
                                    foreach ($existing_admins as $admin) {
                                        echo '<li>Username: <strong>' . htmlspecialchars($admin['username']) . '</strong> - ' . htmlspecialchars($admin['nama']) . '</li>';
                                    }
                                    echo '</ul></div>';
                                    echo '<a href="' . base_url('admin/login.php') . '" class="btn btn-primary w-100">';
                                    echo '<i class="fas fa-sign-in-alt"></i> Login</a>';
                                } else {
                            ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($default_username); ?>" required>
                                    <small class="text-muted">Username untuk login</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" name="password" required minlength="6">
                                    <small class="text-muted">Minimal 6 karakter</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nama" value="<?php echo htmlspecialchars($default_nama); ?>" required>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    <strong>Peringatan:</strong> Setelah membuat user admin, segera ganti password untuk keamanan!
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus"></i> Buat User Admin
                                </button>
                            </form>
                            <?php
                                }
                            } catch (PDOException $e) {
                                echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <small class="text-white">
                        <a href="<?php echo base_url(); ?>" class="text-white text-decoration-none">
                            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                        </a>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>

