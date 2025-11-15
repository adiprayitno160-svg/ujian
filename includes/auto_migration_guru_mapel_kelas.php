<?php
/**
 * Auto Migration for Guru Mapel Kelas
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel guru_mapel_kelas
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run guru mapel kelas migration
 */
function run_guru_mapel_kelas_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists')) {
        error_log("Guru mapel kelas migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Check if table already exists
        if (table_exists('guru_mapel_kelas')) {
            error_log("Table guru_mapel_kelas already exists. Migration skipped.");
            return true;
        }
        
        // Create table guru_mapel_kelas
        $pdo->exec("CREATE TABLE IF NOT EXISTS guru_mapel_kelas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_guru INT NOT NULL,
            id_mapel INT NOT NULL,
            id_kelas INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (id_guru) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE CASCADE,
            FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
            UNIQUE KEY unique_guru_mapel_kelas (id_guru, id_mapel, id_kelas),
            INDEX idx_guru (id_guru),
            INDEX idx_mapel (id_mapel),
            INDEX idx_kelas (id_kelas),
            INDEX idx_guru_mapel (id_guru, id_mapel)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        error_log("Table guru_mapel_kelas created successfully.");
        return true;
        
    } catch (PDOException $e) {
        error_log("Guru mapel kelas migration error: " . $e->getMessage());
        return false;
    }
}







