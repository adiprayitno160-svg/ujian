<?php
/**
 * Create Admin User via SSH
 * Script sederhana untuk membuat user admin via command line/SSH
 * 
 * Cara penggunaan di live server via SSH:
 * php scripts/create_admin_ssh.php
 * 
 * Atau dengan parameter:
 * php scripts/create_admin_ssh.php username password "Nama Lengkap"
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Get parameters from command line
$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';
$nama = $argv[3] ?? 'Administrator';

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

