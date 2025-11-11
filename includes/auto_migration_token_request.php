<?php
/**
 * Auto Migration for Token Request System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel token_request
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run token request migration
 */
function run_token_request_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Token request migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: token_request
        if (!table_exists('token_request')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS token_request (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_sesi INT NOT NULL,
                id_siswa INT NOT NULL,
                status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_by INT DEFAULT NULL,
                approved_at TIMESTAMP NULL DEFAULT NULL,
                rejected_at TIMESTAMP NULL DEFAULT NULL,
                completed_at TIMESTAMP NULL DEFAULT NULL,
                id_token INT DEFAULT NULL COMMENT 'Token yang diberikan setelah approve',
                notes TEXT DEFAULT NULL COMMENT 'Catatan dari operator',
                ip_address VARCHAR(45) DEFAULT NULL,
                device_info TEXT DEFAULT NULL,
                FOREIGN KEY (id_sesi) REFERENCES sesi_ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (id_token) REFERENCES token_ujian(id) ON DELETE SET NULL,
                INDEX idx_sesi (id_sesi),
                INDEX idx_siswa (id_siswa),
                INDEX idx_status (status),
                INDEX idx_requested_at (requested_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Token request migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

