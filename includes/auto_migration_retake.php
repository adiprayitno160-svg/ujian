<?php
/**
 * Auto Migration for Retake Exam System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Migration untuk menambahkan kolom retake di sesi_ujian dan absensi_ujian
 */

function run_retake_migration() {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("Retake migration: PDO not available");
        return false;
    }
    
    try {
        // Add is_retake and original_sesi_id to sesi_ujian
        if (!column_exists('sesi_ujian', 'is_retake')) {
            try {
                $pdo->exec("ALTER TABLE sesi_ujian ADD COLUMN is_retake TINYINT(1) DEFAULT 0 COMMENT 'Flag untuk sesi retake'");
                $pdo->exec("ALTER TABLE sesi_ujian ADD INDEX idx_is_retake (is_retake)");
                error_log("Retake migration: Added is_retake column to sesi_ujian");
            } catch (PDOException $e) {
                error_log("Retake migration: Error adding is_retake column: " . $e->getMessage());
            }
        }
        
        if (!column_exists('sesi_ujian', 'original_sesi_id')) {
            try {
                $pdo->exec("ALTER TABLE sesi_ujian ADD COLUMN original_sesi_id INT DEFAULT NULL COMMENT 'ID sesi original untuk retake'");
                $pdo->exec("ALTER TABLE sesi_ujian ADD INDEX idx_original_sesi (original_sesi_id)");
                error_log("Retake migration: Added original_sesi_id column to sesi_ujian");
            } catch (PDOException $e) {
                error_log("Retake migration: Error adding original_sesi_id column: " . $e->getMessage());
            }
        }
        
        // Add retake_sesi_id to absensi_ujian and update status_absen enum
        if (!column_exists('absensi_ujian', 'retake_sesi_id')) {
            try {
                $pdo->exec("ALTER TABLE absensi_ujian ADD COLUMN retake_sesi_id INT DEFAULT NULL COMMENT 'ID sesi retake untuk siswa ini'");
                $pdo->exec("ALTER TABLE absensi_ujian ADD INDEX idx_retake_sesi (retake_sesi_id)");
                error_log("Retake migration: Added retake_sesi_id column to absensi_ujian");
            } catch (PDOException $e) {
                error_log("Retake migration: Error adding retake_sesi_id column: " . $e->getMessage());
            }
        }
        
        // Update status_absen enum to include 'retake'
        try {
            $pdo->exec("ALTER TABLE absensi_ujian MODIFY COLUMN status_absen ENUM('hadir', 'tidak_hadir', 'izin', 'sakit', 'retake') DEFAULT 'tidak_hadir'");
            error_log("Retake migration: Updated status_absen enum to include 'retake'");
        } catch (PDOException $e) {
            // If enum update fails, it might already be updated or need manual update
            error_log("Retake migration: Note - Could not update status_absen enum (might already be updated): " . $e->getMessage());
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Retake migration error: " . $e->getMessage());
        return false;
    }
}

// Helper function to check if column exists
// Use the column_exists function from auto_migration.php if available
if (!function_exists('column_exists')) {
    function column_exists($table, $column) {
        global $pdo;
        if (!defined('DB_NAME')) {
            return false;
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_SCHEMA = ? 
                                  AND TABLE_NAME = ? 
                                  AND COLUMN_NAME = ?");
            $stmt->execute([DB_NAME, $table, $column]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Check column exists error: " . $e->getMessage());
            return false;
        }
    }
}

