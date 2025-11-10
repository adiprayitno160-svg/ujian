<?php
/**
 * Auto Migration for Verifikasi Dokumen System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel verifikasi dokumen siswa kelas IX
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run verifikasi dokumen migration
 */
function run_verifikasi_dokumen_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Verifikasi migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: verifikasi_settings
        if (!table_exists('verifikasi_settings')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS verifikasi_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description TEXT,
                updated_by INT DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            // Insert default settings
            $pdo->exec("INSERT INTO verifikasi_settings (setting_key, setting_value, description) VALUES
                ('deadline_verifikasi', NULL, 'Deadline upload dokumen verifikasi (format: YYYY-MM-DD)'),
                ('gemini_enabled', '0', 'Enable/disable Gemini API untuk OCR (0=disabled, 1=enabled)'),
                ('gemini_api_key', '', 'Gemini API Key untuk OCR'),
                ('gemini_model', 'gemini-1.5-flash', 'Model Gemini yang digunakan (gemini-1.5-flash atau gemini-1.5-pro)'),
                ('menu_aktif_default', '1', 'Menu verifikasi aktif secara default untuk siswa kelas IX (0=hidden, 1=visible)')
            ON DUPLICATE KEY UPDATE setting_key=setting_key");
        }
        
        // Table: siswa_dokumen_verifikasi
        if (!table_exists('siswa_dokumen_verifikasi')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS siswa_dokumen_verifikasi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_siswa INT NOT NULL,
                jenis_dokumen ENUM('ijazah', 'kk', 'akte') NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_type ENUM('pdf', 'jpg', 'jpeg', 'png') NOT NULL,
                file_size INT DEFAULT 0,
                ocr_text TEXT,
                ocr_confidence INT DEFAULT 0 COMMENT '0-100, confidence score dari OCR',
                nama_anak VARCHAR(255),
                nama_ayah VARCHAR(255),
                nama_ibu VARCHAR(255),
                nik VARCHAR(50),
                tempat_lahir VARCHAR(100),
                tanggal_lahir DATE,
                data_ekstrak_lainnya JSON,
                status_ocr ENUM('pending', 'success', 'failed') DEFAULT 'pending',
                status_verifikasi ENUM('belum', 'menunggu', 'valid', 'tidak_valid', 'residu') DEFAULT 'belum',
                jumlah_upload_ulang INT DEFAULT 0 COMMENT 'Maksimal 1',
                keterangan_admin TEXT,
                diverifikasi_oleh INT DEFAULT NULL,
                tanggal_verifikasi DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_siswa_dokumen (id_siswa, jenis_dokumen),
                INDEX idx_siswa (id_siswa),
                INDEX idx_jenis (jenis_dokumen),
                INDEX idx_status (status_verifikasi),
                INDEX idx_status_ocr (status_ocr)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: verifikasi_data_siswa
        if (!table_exists('verifikasi_data_siswa')) {
            try {
                $create_table_sql = "CREATE TABLE IF NOT EXISTS verifikasi_data_siswa (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    id_siswa INT NOT NULL UNIQUE,
                    status_overall ENUM('belum_lengkap', 'menunggu_verifikasi', 'valid', 'tidak_valid', 'upload_ulang', 'residu') DEFAULT 'belum_lengkap',
                    nama_anak_ijazah VARCHAR(255),
                    nama_anak_kk VARCHAR(255),
                    nama_ayah_kk VARCHAR(255),
                    nama_ibu_kk VARCHAR(255),
                    nama_anak_akte VARCHAR(255),
                    nama_ayah_akte VARCHAR(255),
                    nama_ibu_akte VARCHAR(255),
                    kesesuaian_nama_anak ENUM('sesuai', 'tidak_sesuai', 'belum_dicek') DEFAULT 'belum_dicek',
                    kesesuaian_nama_ayah ENUM('sesuai', 'tidak_sesuai', 'belum_dicek') DEFAULT 'belum_dicek',
                    kesesuaian_nama_ibu ENUM('sesuai', 'tidak_sesuai', 'belum_dicek') DEFAULT 'belum_dicek',
                    detail_ketidaksesuaian JSON,
                    menu_aktif BOOLEAN DEFAULT TRUE,
                    catatan_admin TEXT,
                    jumlah_upload_ulang INT DEFAULT 0,
                    diverifikasi_oleh INT DEFAULT NULL,
                    tanggal_verifikasi DATETIME DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status_overall),
                    INDEX idx_kesesuaian_anak (kesesuaian_nama_anak),
                    INDEX idx_kesesuaian_ayah (kesesuaian_nama_ayah),
                    INDEX idx_kesesuaian_ibu (kesesuaian_nama_ibu)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                
                $pdo->exec($create_table_sql);
                
                // Add foreign keys separately (in case users table doesn't exist yet or has issues)
                try {
                    $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                        ADD CONSTRAINT fk_verifikasi_id_siswa 
                        FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE");
                } catch (PDOException $e) {
                    error_log("Could not add foreign key for id_siswa: " . $e->getMessage());
                    // Try without constraint name
                    try {
                        $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                            ADD FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE");
                    } catch (PDOException $e2) {
                        error_log("Could not add foreign key for id_siswa (retry): " . $e2->getMessage());
                    }
                }
                
                try {
                    $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                        ADD CONSTRAINT fk_verifikasi_diverifikasi_oleh 
                        FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL");
                } catch (PDOException $e) {
                    error_log("Could not add foreign key for diverifikasi_oleh: " . $e->getMessage());
                    // Try without constraint name
                    try {
                        $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                            ADD FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL");
                    } catch (PDOException $e2) {
                        error_log("Could not add foreign key for diverifikasi_oleh (retry): " . $e2->getMessage());
                    }
                }
                
                error_log("Migration: Created table verifikasi_data_siswa");
            } catch (PDOException $e) {
                error_log("Error creating verifikasi_data_siswa table: " . $e->getMessage());
                throw $e; // Re-throw to let caller know it failed
            }
        } else {
            // Add missing columns if table exists but columns are missing
            if (!column_exists('verifikasi_data_siswa', 'diverifikasi_oleh')) {
                $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                    ADD COLUMN diverifikasi_oleh INT DEFAULT NULL AFTER jumlah_upload_ulang");
                
                // Add foreign key constraint separately
                try {
                    $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                        ADD CONSTRAINT fk_verifikasi_diverifikasi_oleh 
                        FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL");
                } catch (PDOException $e) {
                    // Foreign key might already exist, or constraint name conflict - try without name
                    try {
                        $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                            ADD FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL");
                    } catch (PDOException $e2) {
                        error_log("Could not add foreign key for diverifikasi_oleh: " . $e2->getMessage());
                    }
                }
            }
            
            if (!column_exists('verifikasi_data_siswa', 'tanggal_verifikasi')) {
                if (column_exists('verifikasi_data_siswa', 'diverifikasi_oleh')) {
                    $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                        ADD COLUMN tanggal_verifikasi DATETIME DEFAULT NULL AFTER diverifikasi_oleh");
                } else {
                    $pdo->exec("ALTER TABLE verifikasi_data_siswa 
                        ADD COLUMN tanggal_verifikasi DATETIME DEFAULT NULL AFTER jumlah_upload_ulang");
                }
            }
        }
        
        // Table: verifikasi_data_history
        if (!table_exists('verifikasi_data_history')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS verifikasi_data_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_verifikasi INT NOT NULL COMMENT 'ID dari verifikasi_data_siswa',
                id_siswa INT NOT NULL,
                action ENUM('upload', 'upload_ulang', 'verifikasi_valid', 'verifikasi_tidak_valid', 'set_residu', 'edit_admin', 'scan_ocr') NOT NULL,
                status_sebelum VARCHAR(50),
                status_sesudah VARCHAR(50),
                data_sebelum JSON COMMENT 'Snapshot data sebelum perubahan',
                data_sesudah JSON COMMENT 'Snapshot data sesudah perubahan',
                keterangan TEXT,
                dilakukan_oleh INT NOT NULL COMMENT 'ID user (siswa atau admin)',
                role_user VARCHAR(20) NOT NULL COMMENT 'siswa atau admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_verifikasi) REFERENCES verifikasi_data_siswa(id) ON DELETE CASCADE,
                FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (dilakukan_oleh) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_verifikasi (id_verifikasi),
                INDEX idx_siswa (id_siswa),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: notifikasi_verifikasi
        if (!table_exists('notifikasi_verifikasi')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi_verifikasi (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_user INT NOT NULL COMMENT 'User yang menerima notifikasi',
                id_verifikasi INT DEFAULT NULL COMMENT 'ID dari verifikasi_data_siswa',
                jenis ENUM('upload_berhasil', 'verifikasi_valid', 'verifikasi_tidak_valid', 'deadline_mendekati', 'deadline_terlewat', 'upload_ulang_diperlukan') NOT NULL,
                judul VARCHAR(255) NOT NULL,
                pesan TEXT NOT NULL,
                dibaca BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_user) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (id_verifikasi) REFERENCES verifikasi_data_siswa(id) ON DELETE CASCADE,
                INDEX idx_user (id_user),
                INDEX idx_dibaca (dibaca),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Verifikasi dokumen migration error: " . $e->getMessage());
        return false;
    }
}

