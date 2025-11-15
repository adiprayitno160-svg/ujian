<?php
/**
 * Auto Migration for Tugas Soal System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Migration untuk menambahkan fitur soal pada tugas
 */

function run_tugas_soal_migration() {
    global $pdo;
    
    if (!isset($pdo)) {
        error_log("Tugas soal migration: PDO not available");
        return false;
    }
    
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
    
    // Use the table_exists function from auto_migration.php if available
    if (!function_exists('table_exists')) {
        function table_exists($table) {
            global $pdo;
            if (!defined('DB_NAME')) {
                return false;
            }
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                                      WHERE TABLE_SCHEMA = ? 
                                      AND TABLE_NAME = ?");
                $stmt->execute([DB_NAME, $table]);
                return $stmt->fetchColumn() > 0;
            } catch (PDOException $e) {
                error_log("Check table exists error: " . $e->getMessage());
                return false;
            }
        }
    }
    
    try {
        // Add tipe_tugas_mode to tugas table (soal or file)
        if (!column_exists('tugas', 'tipe_tugas_mode')) {
            try {
                $pdo->exec("ALTER TABLE tugas ADD COLUMN tipe_tugas_mode ENUM('file', 'soal') DEFAULT 'file' COMMENT 'Tipe tugas: file submission atau soal'");
                error_log("Tugas soal migration: Added tipe_tugas_mode column to tugas");
            } catch (PDOException $e) {
                error_log("Tugas soal migration: Error adding tipe_tugas_mode column: " . $e->getMessage());
            }
        }
        
        // Create tugas_soal table (similar to soal table for ujian)
        if (!table_exists('tugas_soal')) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS tugas_soal (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_tugas INT NOT NULL,
                    pertanyaan TEXT NOT NULL,
                    tipe_soal ENUM('pilihan_ganda', 'isian_singkat', 'benar_salah', 'matching', 'esai') NOT NULL,
                    opsi_json TEXT DEFAULT NULL COMMENT 'JSON array untuk pilihan ganda, benar/salah, matching',
                    kunci_jawaban TEXT DEFAULT NULL COMMENT 'Jawaban benar',
                    bobot DECIMAL(5,2) DEFAULT 1.00,
                    urutan INT DEFAULT 0,
                    gambar VARCHAR(255) DEFAULT NULL,
                    media_type ENUM('gambar', 'video') DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_tugas) REFERENCES tugas(id) ON DELETE CASCADE,
                    INDEX idx_tugas (id_tugas),
                    INDEX idx_urutan (urutan),
                    INDEX idx_tipe (tipe_soal)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                error_log("Tugas soal migration: Created tugas_soal table");
            } catch (PDOException $e) {
                error_log("Tugas soal migration: Error creating tugas_soal table: " . $e->getMessage());
            }
        }
        
        // Create tugas_soal_matching table (for matching type questions)
        if (!table_exists('tugas_soal_matching')) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS tugas_soal_matching (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_tugas_soal INT NOT NULL,
                    item_kiri VARCHAR(255) NOT NULL,
                    item_kanan VARCHAR(255) NOT NULL,
                    urutan INT DEFAULT 0,
                    FOREIGN KEY (id_tugas_soal) REFERENCES tugas_soal(id) ON DELETE CASCADE,
                    INDEX idx_tugas_soal (id_tugas_soal)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                error_log("Tugas soal migration: Created tugas_soal_matching table");
            } catch (PDOException $e) {
                error_log("Tugas soal migration: Error creating tugas_soal_matching table: " . $e->getMessage());
            }
        }
        
        // Create tugas_soal_jawaban table (for student answers)
        if (!table_exists('tugas_soal_jawaban')) {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS tugas_soal_jawaban (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_tugas_soal INT NOT NULL,
                    id_tugas_submission INT NOT NULL,
                    jawaban TEXT DEFAULT NULL,
                    jawaban_json TEXT DEFAULT NULL COMMENT 'JSON untuk multiple answers (matching, etc)',
                    nilai DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai yang diberikan guru (untuk esai)',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (id_tugas_soal) REFERENCES tugas_soal(id) ON DELETE CASCADE,
                    FOREIGN KEY (id_tugas_submission) REFERENCES tugas_submission(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_jawaban (id_tugas_soal, id_tugas_submission),
                    INDEX idx_tugas_soal (id_tugas_soal),
                    INDEX idx_submission (id_tugas_submission)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                error_log("Tugas soal migration: Created tugas_soal_jawaban table");
            } catch (PDOException $e) {
                error_log("Tugas soal migration: Error creating tugas_soal_jawaban table: " . $e->getMessage());
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Tugas soal migration error: " . $e->getMessage());
        return false;
    }
}







