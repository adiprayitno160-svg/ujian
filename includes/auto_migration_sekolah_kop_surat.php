<?php
/**
 * Auto Migration for Sekolah Kop Surat
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk menambahkan field kop surat di tabel sekolah
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run sekolah kop surat migration
 */
function run_sekolah_kop_surat_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists') || !function_exists('add_column_if_not_exists')) {
        error_log("Sekolah kop surat migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Check if sekolah table exists
        if (!table_exists('sekolah')) {
            // Create sekolah table if it doesn't exist
            $pdo->exec("CREATE TABLE IF NOT EXISTS sekolah (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_sekolah VARCHAR(255) NOT NULL,
                alamat TEXT,
                no_telp VARCHAR(50),
                website VARCHAR(255),
                logo VARCHAR(255),
                pemerintah_kabupaten VARCHAR(255) DEFAULT 'PEMERINTAH KABUPATEN TULUNGAGUNG',
                dinas_pendidikan VARCHAR(255) DEFAULT 'DINAS PENDIDIKAN',
                nss VARCHAR(50),
                npsn VARCHAR(50),
                kode_pos VARCHAR(10),
                logo_kop_surat VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Sekolah kop surat migration: Created table sekolah");
        } else {
            // Add columns if they don't exist
            add_column_if_not_exists('sekolah', 'pemerintah_kabupaten', "VARCHAR(255) DEFAULT 'PEMERINTAH KABUPATEN TULUNGAGUNG' AFTER website");
            add_column_if_not_exists('sekolah', 'dinas_pendidikan', "VARCHAR(255) DEFAULT 'DINAS PENDIDIKAN' AFTER pemerintah_kabupaten");
            add_column_if_not_exists('sekolah', 'nss', "VARCHAR(50) DEFAULT NULL AFTER dinas_pendidikan");
            add_column_if_not_exists('sekolah', 'npsn', "VARCHAR(50) DEFAULT NULL AFTER nss");
            add_column_if_not_exists('sekolah', 'kode_pos', "VARCHAR(10) DEFAULT NULL AFTER npsn");
            add_column_if_not_exists('sekolah', 'logo_kop_surat', "VARCHAR(255) DEFAULT NULL AFTER kode_pos");
            add_column_if_not_exists('sekolah', 'kepala_sekolah', "VARCHAR(255) DEFAULT NULL AFTER logo_kop_surat");
            add_column_if_not_exists('sekolah', 'nip_kepala_sekolah', "VARCHAR(50) DEFAULT NULL AFTER kepala_sekolah");
        }
        
        // Create wali_kelas table
        if (!table_exists('wali_kelas')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wali_kelas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_guru INT NOT NULL COMMENT 'ID guru yang menjadi wali kelas',
                id_kelas INT NOT NULL COMMENT 'ID kelas yang diwalikan',
                tahun_ajaran VARCHAR(20) NOT NULL COMMENT 'Tahun ajaran',
                semester ENUM('ganjil', 'genap') DEFAULT 'ganjil',
                level_access ENUM('admin', 'operator') DEFAULT 'operator' COMMENT 'Level akses wali kelas',
                created_by INT NOT NULL COMMENT 'ID user yang membuat assignment',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_guru) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_wali_kelas (id_kelas, tahun_ajaran, semester),
                INDEX idx_guru (id_guru),
                INDEX idx_kelas (id_kelas),
                INDEX idx_tahun_ajaran (tahun_ajaran)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Sekolah kop surat migration: Created table wali_kelas");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Sekolah kop surat migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

