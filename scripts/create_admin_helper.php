<?php
/**
 * Helper Script - Generate Password Hash untuk SQL
 * Script ini membantu generate hash password untuk digunakan di SQL
 * 
 * Cara penggunaan:
 * php scripts/create_admin_helper.php
 * 
 * Atau dengan parameter:
 * php scripts/create_admin_helper.php password_anda
 */

$password = $argv[1] ?? 'admin123';

echo "========================================\n";
echo "Generate Password Hash untuk SQL\n";
echo "========================================\n\n";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: $password\n";
echo "Hash: $hash\n\n";

echo "========================================\n";
echo "SQL Query untuk membuat admin:\n";
echo "========================================\n\n";

echo "INSERT INTO users (username, password, role, nama, status) VALUES\n";
echo "('admin', '$hash', 'admin', 'Administrator', 'active');\n\n";

echo "========================================\n";
echo "Atau gunakan query lengkap:\n";
echo "========================================\n\n";

echo "-- Cek apakah sudah ada admin\n";
echo "SELECT id, username, nama FROM users WHERE role = 'admin';\n\n";
echo "-- Buat admin baru\n";
echo "INSERT INTO users (username, password, role, nama, status) VALUES\n";
echo "('admin', '$hash', 'admin', 'Administrator', 'active');\n\n";
echo "-- Verifikasi\n";
echo "SELECT id, username, nama, role, status FROM users WHERE username = 'admin';\n\n";

