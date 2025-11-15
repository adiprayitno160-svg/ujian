<?php
/**
 * Auto Migration for Mapel Tingkat, NISN, and Raport Menu Settings
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Features:
 * 1. Mapel untuk beberapa tingkat kelas
 * 2. NISN untuk login siswa
 * 3. Setting show/hide menu raport
 */

/**
 * Run migration for mapel tingkat, NISN, and raport menu settings
 */
function run_mapel_tingkat_nisn_raport_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists')) {
        error_log("Mapel tingkat migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // 1. Add NISN column to users table
        if (!column_exists('users', 'nisn')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN nisn VARCHAR(20) NULL AFTER username");
            $pdo->exec("ALTER TABLE users ADD INDEX idx_nisn (nisn)");
            error_log("Column nisn added to users table.");
        }
        
        // 2. Create mapel_tingkat table for mapel-level assignments
        if (!table_exists('mapel_tingkat')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS mapel_tingkat (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_mapel INT NOT NULL,
                tingkat VARCHAR(10) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE CASCADE,
                UNIQUE KEY unique_mapel_tingkat (id_mapel, tingkat),
                INDEX idx_mapel (id_mapel),
                INDEX idx_tingkat (tingkat)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Table mapel_tingkat created successfully.");
        }
        
        // 3. Create system_settings table if not exists (for raport menu visibility)
        if (!table_exists('system_settings')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                updated_by INT NULL,
                INDEX idx_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Table system_settings created successfully.");
        }
        
        // 4. Insert default setting for raport menu visibility (default: visible)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'siswa_raport_menu_visible'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            $stmt->execute(['siswa_raport_menu_visible', '1', 'Tampilkan menu raport di halaman siswa (1=visible, 0=hidden)']);
            error_log("Default setting siswa_raport_menu_visible inserted.");
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error in mapel_tingkat_nisn_raport migration: " . $e->getMessage());
        return false;
    }
}

