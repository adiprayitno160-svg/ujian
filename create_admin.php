<?php
/**
 * Create Default Admin User
 * Script untuk membuat user admin default setelah ganti database
 * 
 * Cara penggunaan:
 * 1. Akses melalui browser: http://localhost/UJAN/create_admin.php?token=YOUR_SECRET_TOKEN
 * 2. Atau jalankan via CLI: php create_admin.php
 * 
 * PENTING UNTUK LIVE SERVER:
 * - Ganti SECRET_TOKEN dengan token yang kuat
 * - Setelah selesai, HAPUS file ini atau rename untuk keamanan
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// SECURITY: Ganti token ini dengan token yang kuat dan unik!
// Contoh: generate dengan: echo bin2hex(random_bytes(32));
define('SECRET_TOKEN', 'CHANGE_THIS_TOKEN_TO_SOMETHING_SECURE_' . bin2hex(random_bytes(16)));

// Default admin credentials
$default_username = 'admin';
$default_password = 'admin123'; // GANTI PASSWORD INI SETELAH LOGIN PERTAMA KALI!
$default_nama = 'Administrator';

$message = '';
$error = '';
$success = false;

// Check if running from CLI or web
$is_cli = php_sapi_name() === 'cli';

// Security check for web access (live server)
if (!$is_cli) {
    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_localhost = in_array($hostname, ['localhost', '127.0.0.1']) || 
                    strpos($hostname, '192.168.') === 0 || 
                    strpos($hostname, '10.') === 0;
    
    // For live server, require token
    if (!$is_localhost) {
        $provided_token = $_GET['token'] ?? '';
        if (empty($provided_token) || $provided_token !== SECRET_TOKEN) {
            http_response_code(403);
            die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Access Denied</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <h2>Access Denied</h2>
                    <p>Token tidak valid atau tidak disediakan.</p>
                    <p><small>Akses file ini memerlukan token keamanan. Gunakan: ?token=YOUR_SECRET_TOKEN</small></p>
                </div>
            </body>
            </html>
            ");
        }
    }
}

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
    // Get parameters from command line (optional)
    $username = $argv[1] ?? $default_username;
    $password = $argv[2] ?? $default_password;
    $nama = $argv[3] ?? $default_nama;
    
    echo "========================================\n";
    echo "Create Admin User - UJAN System\n";
    echo "========================================\n\n";
    
    try {
        // Check if admin user already exists
        $stmt = $pdo->prepare("SELECT id, username, nama FROM users WHERE role = 'admin'");
        $stmt->execute();
        $existing_admins = $stmt->fetchAll();
        
        if (!empty($existing_admins)) {
            echo "⚠️  User admin sudah ada:\n\n";
            foreach ($existing_admins as $admin) {
                echo "  - ID: {$admin['id']}\n";
                echo "    Username: {$admin['username']}\n";
                echo "    Nama: {$admin['nama']}\n\n";
            }
            echo "Tidak perlu membuat user admin baru.\n";
            echo "Jika ingin membuat admin baru, gunakan username yang berbeda.\n";
            echo "\nContoh: php create_admin.php username_baru password_baru \"Nama Lengkap\"\n";
            exit(0);
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo "❌ Error: Username '$username' sudah digunakan!\n";
            echo "Silakan gunakan username lain.\n";
            exit(1);
        }
        
        // Validate password
        if (strlen($password) < 6) {
            echo "❌ Error: Password minimal 6 karakter!\n";
            exit(1);
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create admin user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'admin', ?, 'active')");
        $stmt->execute([$username, $hashed_password, $nama]);
        
        $user_id = $pdo->lastInsertId();
        
        echo "✅ User admin berhasil dibuat!\n\n";
        echo "Detail User:\n";
        echo "  - ID: $user_id\n";
        echo "  - Username: $username\n";
        echo "  - Password: $password\n";
        echo "  - Nama: $nama\n";
        echo "  - Role: admin\n";
        echo "  - Status: active\n\n";
        echo "========================================\n";
        echo "⚠️  PERINGATAN KEAMANAN:\n";
        echo "========================================\n";
        echo "1. Segera login dan ganti password!\n";
        echo "2. Simpan kredensial ini di tempat yang aman\n";
        echo "3. Jangan bagikan kredensial ini ke orang lain\n";
        echo "========================================\n";
        
    } catch (PDOException $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
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

