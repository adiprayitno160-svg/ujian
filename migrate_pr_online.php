<?php
/**
 * Migration Script: Add PR Online Features
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Cara penggunaan:
 * 1. Buka browser: http://localhost/UJAN/migrate_pr_online.php
 * 2. Atau jalankan via command line: php migrate_pr_online.php
 */

require_once __DIR__ . '/config/config.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if running from CLI or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    header('Content-Type: text/html; charset=utf-8');
}

echo $is_cli ? "" : "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Migration PR Online</title>";
echo $is_cli ? "" : "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}";
echo $is_cli ? "" : ".success{color:green;}.error{color:red;}.info{color:blue;}.sql{background:#f5f5f5;padding:10px;border-left:3px solid #007bff;margin:10px 0;}</style></head><body>";
echo $is_cli ? "" : "<h1>Migration: PR Online Features</h1>";

try {
    global $pdo;
    
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check config/database.php");
    }
    
    echo $is_cli ? "Starting migration...\n" : "<p class='info'>Starting migration...</p>";
    
    $queries = [];
    $errors = [];
    $success_count = 0;
    
    // 1. Add columns to pr table
    echo $is_cli ? "\n1. Adding columns to pr table...\n" : "<h3>1. Adding columns to pr table...</h3>";
    
    $alter_queries = [
        "ALTER TABLE pr ADD COLUMN tipe_pr ENUM('file_upload', 'online', 'hybrid') DEFAULT 'file_upload' COMMENT 'Tipe PR: file upload, online, atau hybrid'",
        "ALTER TABLE pr ADD COLUMN timer_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable timer untuk PR online'",
        "ALTER TABLE pr ADD COLUMN timer_minutes INT DEFAULT NULL COMMENT 'Durasi timer dalam menit'",
        "ALTER TABLE pr ADD COLUMN allow_edit_after_submit TINYINT(1) DEFAULT 1 COMMENT 'Boleh edit setelah submit (sebelum deadline)'",
        "ALTER TABLE pr ADD COLUMN max_attempts INT DEFAULT NULL COMMENT 'Maksimal percobaan (NULL = unlimited)'"
    ];
    
    foreach ($alter_queries as $query) {
        try {
            // Check if column exists first
            $column_name = '';
            if (preg_match("/ADD COLUMN (\w+)/", $query, $matches)) {
                $column_name = $matches[1];
                $check_query = "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
                               WHERE TABLE_SCHEMA = DATABASE() 
                               AND TABLE_NAME = 'pr' 
                               AND COLUMN_NAME = '$column_name'";
                $check = $pdo->query($check_query)->fetch();
                
                if ($check['cnt'] > 0) {
                    echo $is_cli ? "  - Column '$column_name' already exists, skipping...\n" : 
                         "<p class='info'>Column '$column_name' already exists, skipping...</p>";
                    continue;
                }
            }
            
            $pdo->exec($query);
            echo $is_cli ? "  ✓ Added column: $column_name\n" : 
                 "<p class='success'>✓ Added column: $column_name</p>";
            $success_count++;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                $errors[] = "Error adding column: " . $e->getMessage();
                echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
                     "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            } else {
                echo $is_cli ? "  - Column already exists, skipping...\n" : 
                     "<p class='info'>Column already exists, skipping...</p>";
            }
        }
    }
    
    // Add index
    try {
        $pdo->exec("ALTER TABLE pr ADD INDEX idx_tipe_pr (tipe_pr)");
        echo $is_cli ? "  ✓ Added index idx_tipe_pr\n" : 
             "<p class='success'>✓ Added index idx_tipe_pr</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') === false) {
            echo $is_cli ? "  ✗ Error adding index: " . $e->getMessage() . "\n" : 
                 "<p class='error'>✗ Error adding index: " . htmlspecialchars($e->getMessage()) . "</p>";
        } else {
            echo $is_cli ? "  - Index already exists, skipping...\n" : 
                 "<p class='info'>Index already exists, skipping...</p>";
        }
    }
    
    // 2. Update pr_submission table
    echo $is_cli ? "\n2. Updating pr_submission table...\n" : "<h3>2. Updating pr_submission table...</h3>";
    
    try {
        // Check current enum values
        $check_enum = $pdo->query("SHOW COLUMNS FROM pr_submission WHERE Field = 'status'")->fetch();
        $current_enum = $check_enum['Type'];
        
        if (strpos($current_enum, 'draft') === false) {
            $pdo->exec("ALTER TABLE pr_submission 
                       MODIFY COLUMN status ENUM('belum_dikumpulkan', 'draft', 'sudah_dikumpulkan', 'dinilai', 'terlambat') DEFAULT 'belum_dikumpulkan'");
            echo $is_cli ? "  ✓ Updated status enum\n" : 
                 "<p class='success'>✓ Updated status enum</p>";
        } else {
            echo $is_cli ? "  - Status enum already updated, skipping...\n" : 
                 "<p class='info'>Status enum already updated, skipping...</p>";
        }
    } catch (PDOException $e) {
        echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
             "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Add attempt_count
    try {
        $check = $pdo->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS 
                             WHERE TABLE_SCHEMA = DATABASE() 
                             AND TABLE_NAME = 'pr_submission' 
                             AND COLUMN_NAME = 'attempt_count'")->fetch();
        
        if ($check['cnt'] == 0) {
            $pdo->exec("ALTER TABLE pr_submission 
                       ADD COLUMN attempt_count INT DEFAULT 0 COMMENT 'Jumlah percobaan submit'");
            echo $is_cli ? "  ✓ Added attempt_count column\n" : 
                 "<p class='success'>✓ Added attempt_count column</p>";
        } else {
            echo $is_cli ? "  - attempt_count column already exists, skipping...\n" : 
                 "<p class='info'>attempt_count column already exists, skipping...</p>";
        }
    } catch (PDOException $e) {
        echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
             "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 3. Create pr_soal table
    echo $is_cli ? "\n3. Creating pr_soal table...\n" : "<h3>3. Creating pr_soal table...</h3>";
    
    $create_pr_soal = "CREATE TABLE IF NOT EXISTS pr_soal (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pr INT NOT NULL,
        pertanyaan TEXT NOT NULL,
        tipe_soal ENUM('pilihan_ganda', 'isian_singkat', 'benar_salah', 'matching', 'esai') NOT NULL,
        opsi_json TEXT DEFAULT NULL COMMENT 'JSON untuk opsi jawaban',
        kunci_jawaban TEXT DEFAULT NULL,
        bobot DECIMAL(5,2) DEFAULT 1.00,
        urutan INT DEFAULT 0,
        gambar VARCHAR(255) DEFAULT NULL,
        tingkat_kesulitan ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
        tags VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_pr) REFERENCES pr(id) ON DELETE CASCADE,
        INDEX idx_pr (id_pr),
        INDEX idx_tipe (tipe_soal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($create_pr_soal);
        echo $is_cli ? "  ✓ Created pr_soal table\n" : 
             "<p class='success'>✓ Created pr_soal table</p>";
    } catch (PDOException $e) {
        echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
             "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 4. Create pr_soal_matching table
    echo $is_cli ? "\n4. Creating pr_soal_matching table...\n" : "<h3>4. Creating pr_soal_matching table...</h3>";
    
    $create_matching = "CREATE TABLE IF NOT EXISTS pr_soal_matching (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_soal INT NOT NULL,
        item_kiri VARCHAR(255) NOT NULL,
        item_kanan VARCHAR(255) NOT NULL,
        urutan INT DEFAULT 0,
        FOREIGN KEY (id_soal) REFERENCES pr_soal(id) ON DELETE CASCADE,
        INDEX idx_soal (id_soal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($create_matching);
        echo $is_cli ? "  ✓ Created pr_soal_matching table\n" : 
             "<p class='success'>✓ Created pr_soal_matching table</p>";
    } catch (PDOException $e) {
        echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
             "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // 5. Create pr_jawaban table
    echo $is_cli ? "\n5. Creating pr_jawaban table...\n" : "<h3>5. Creating pr_jawaban table...</h3>";
    
    $create_jawaban = "CREATE TABLE IF NOT EXISTS pr_jawaban (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_pr INT NOT NULL,
        id_siswa INT NOT NULL,
        id_soal INT NOT NULL,
        jawaban TEXT DEFAULT NULL,
        is_ragu TINYINT(1) DEFAULT 0,
        status ENUM('draft', 'submitted') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (id_pr) REFERENCES pr(id) ON DELETE CASCADE,
        FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (id_soal) REFERENCES pr_soal(id) ON DELETE CASCADE,
        UNIQUE KEY unique_jawaban (id_pr, id_siswa, id_soal),
        INDEX idx_pr (id_pr),
        INDEX idx_siswa (id_siswa),
        INDEX idx_soal (id_soal)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    try {
        $pdo->exec($create_jawaban);
        echo $is_cli ? "  ✓ Created pr_jawaban table\n" : 
             "<p class='success'>✓ Created pr_jawaban table</p>";
    } catch (PDOException $e) {
        echo $is_cli ? "  ✗ Error: " . $e->getMessage() . "\n" : 
             "<p class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Summary
    echo $is_cli ? "\n" : "<hr>";
    echo $is_cli ? "=== Migration Summary ===\n" : "<h2>Migration Summary</h2>";
    echo $is_cli ? "Success: $success_count operations\n" : "<p class='success'><strong>Success:</strong> $success_count operations completed</p>";
    
    if (count($errors) > 0) {
        echo $is_cli ? "Errors: " . count($errors) . "\n" : "<p class='error'><strong>Errors:</strong> " . count($errors) . "</p>";
        foreach ($errors as $error) {
            echo $is_cli ? "  - $error\n" : "<p class='error'>- " . htmlspecialchars($error) . "</p>";
        }
    }
    
    echo $is_cli ? "\nMigration completed!\n" : "<p class='success'><strong>Migration completed!</strong></p>";
    echo $is_cli ? "" : "<p><a href='guru/pr/list.php' class='btn btn-primary'>Kembali ke Daftar PR</a></p>";
    
} catch (Exception $e) {
    echo $is_cli ? "Fatal Error: " . $e->getMessage() . "\n" : 
         "<p class='error'><strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo $is_cli ? "Stack trace:\n" . $e->getTraceAsString() . "\n" : 
         "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo $is_cli ? "" : "</body></html>";

