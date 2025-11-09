<?php
/**
 * Migration: Add is_operator column to users table
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/config/database.php';

echo "========================================\n";
echo "Migration: Add is_operator to users\n";
echo "========================================\n\n";

try {
    global $pdo;
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_operator'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Kolom 'is_operator' sudah ada\n";
    } else {
        // Add column
        $pdo->exec("ALTER TABLE users 
                    ADD COLUMN is_operator TINYINT(1) DEFAULT 0 COMMENT '1 = guru yang juga bisa akses operator' 
                    AFTER no_hp");
        echo "✓ Kolom 'is_operator' berhasil ditambahkan\n";
        
        // Add index
        try {
            $pdo->exec("ALTER TABLE users ADD INDEX idx_is_operator (is_operator)");
            echo "✓ Index 'idx_is_operator' berhasil ditambahkan\n";
        } catch (PDOException $e) {
            echo "ℹ Index mungkin sudah ada\n";
        }
    }
    
    echo "\nMigration selesai!\n";
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

