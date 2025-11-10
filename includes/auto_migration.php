<?php
/**
 * Auto Migration System
 * Sistem migrasi database otomatis saat aplikasi dijalankan
 * Mengecek dan menambahkan field baru jika belum ada
 * 
 * Note: This file is called from database.php after connection is established
 * DB_NAME and $pdo should already be available
 */

/**
 * Check if column exists in table
 */
function column_exists($table, $column) {
    global $pdo;
    
    if (!defined('DB_NAME')) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([DB_NAME, $table, $column]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking column existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if table exists
 */
function table_exists($table) {
    global $pdo;
    
    if (!defined('DB_NAME')) {
        return false;
    }
    
    try {
        // Use INFORMATION_SCHEMA for more reliable checking
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
        ");
        $stmt->execute([DB_NAME, $table]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        // Fallback to SHOW TABLES
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e2) {
            error_log("Error checking table existence (fallback): " . $e2->getMessage());
            return false;
        }
    }
}

/**
 * Add column to table if it doesn't exist
 */
function add_column_if_not_exists($table, $column, $definition) {
    global $pdo;
    
    if (!table_exists($table)) {
        error_log("Table $table does not exist. Skipping migration.");
        return false;
    }
    
    if (column_exists($table, $column)) {
        // Column already exists, skip
        return true;
    }
    
    try {
        $sql = "ALTER TABLE `$table` ADD COLUMN $definition";
        $pdo->exec($sql);
        error_log("Migration: Added column $column to table $table");
        return true;
    } catch (PDOException $e) {
        error_log("Error adding column $column to table $table: " . $e->getMessage());
        return false;
    }
}

/**
 * Run migration for submission text fields
 */
function run_submission_text_fields_migration() {
    global $pdo;
    
    // Ensure database constants are defined
    if (!defined('DB_NAME') || !isset($pdo)) {
        error_log("Migration skipped: Database not initialized");
        return false;
    }
    
    try {
        // PR Submission table migrations
        if (table_exists('pr_submission')) {
            // Add jawaban_text column
            if (!column_exists('pr_submission', 'jawaban_text')) {
                // Try to add after komentar, if komentar exists
                if (column_exists('pr_submission', 'komentar')) {
                    $pdo->exec("ALTER TABLE `pr_submission` ADD COLUMN `jawaban_text` TEXT NULL AFTER `komentar`");
                    error_log("Migration: Added column jawaban_text to pr_submission");
                } else {
                    // If komentar doesn't exist, just add at the end
                    $pdo->exec("ALTER TABLE `pr_submission` ADD COLUMN `jawaban_text` TEXT NULL");
                    error_log("Migration: Added column jawaban_text to pr_submission (at end)");
                }
            }
            
            // Add tipe_submission column
            if (!column_exists('pr_submission', 'tipe_submission')) {
                // Try to add after jawaban_text if it exists, otherwise after komentar or at end
                if (column_exists('pr_submission', 'jawaban_text')) {
                    $pdo->exec("ALTER TABLE `pr_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file' AFTER `jawaban_text`");
                } elseif (column_exists('pr_submission', 'komentar')) {
                    $pdo->exec("ALTER TABLE `pr_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file' AFTER `komentar`");
                } else {
                    $pdo->exec("ALTER TABLE `pr_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file'");
                }
                error_log("Migration: Added column tipe_submission to pr_submission");
            }
            
            // Update existing records
            if (column_exists('pr_submission', 'tipe_submission')) {
                $pdo->exec("UPDATE `pr_submission` SET `tipe_submission` = 'file' WHERE `tipe_submission` IS NULL");
            }
        }
        
        // Tugas Submission table migrations
        if (table_exists('tugas_submission')) {
            // Add jawaban_text column
            if (!column_exists('tugas_submission', 'jawaban_text')) {
                // Try to add after komentar, if komentar exists
                if (column_exists('tugas_submission', 'komentar')) {
                    $pdo->exec("ALTER TABLE `tugas_submission` ADD COLUMN `jawaban_text` TEXT NULL AFTER `komentar`");
                    error_log("Migration: Added column jawaban_text to tugas_submission");
                } else {
                    // If komentar doesn't exist, just add at the end
                    $pdo->exec("ALTER TABLE `tugas_submission` ADD COLUMN `jawaban_text` TEXT NULL");
                    error_log("Migration: Added column jawaban_text to tugas_submission (at end)");
                }
            }
            
            // Add tipe_submission column
            if (!column_exists('tugas_submission', 'tipe_submission')) {
                // Try to add after jawaban_text if it exists, otherwise after komentar or at end
                if (column_exists('tugas_submission', 'jawaban_text')) {
                    $pdo->exec("ALTER TABLE `tugas_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file' AFTER `jawaban_text`");
                } elseif (column_exists('tugas_submission', 'komentar')) {
                    $pdo->exec("ALTER TABLE `tugas_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file' AFTER `komentar`");
                } else {
                    $pdo->exec("ALTER TABLE `tugas_submission` ADD COLUMN `tipe_submission` ENUM('file', 'text', 'both') DEFAULT 'file'");
                }
                error_log("Migration: Added column tipe_submission to tugas_submission");
            }
            
            // Update existing records
            if (column_exists('tugas_submission', 'tipe_submission')) {
                $pdo->exec("UPDATE `tugas_submission` SET `tipe_submission` = 'file' WHERE `tipe_submission` IS NULL");
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

