<?php
/**
 * Auto Migration for Arsip Soal System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel arsip_soal dan arsip_soal_item
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run arsip soal system migration
 */
function run_pool_soal_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Arsip soal migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: arsip_soal
        if (!table_exists('arsip_soal')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS arsip_soal (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nama_pool VARCHAR(255) NOT NULL COMMENT 'Contoh: Ujian A, Ujian B, dll',
                id_mapel INT NOT NULL,
                tingkat_kelas VARCHAR(50) DEFAULT NULL,
                deskripsi TEXT,
                total_soal INT DEFAULT 0,
                created_by INT NOT NULL COMMENT 'ID operator/guru yang membuat',
                status ENUM('draft', 'aktif', 'arsip') DEFAULT 'draft',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_mapel (id_mapel),
                INDEX idx_status (status),
                INDEX idx_created_by (created_by),
                INDEX idx_nama_pool (nama_pool)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Arsip soal migration: Created table arsip_soal");
        }
        
        // Table: arsip_soal_item
        if (!table_exists('arsip_soal_item')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS arsip_soal_item (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_arsip_soal INT NOT NULL,
                pertanyaan TEXT NOT NULL,
                tipe_soal ENUM('pilihan_ganda', 'benar_salah', 'essay', 'matching', 'isian_singkat') NOT NULL,
                opsi_json TEXT COMMENT 'JSON untuk opsi pilihan ganda',
                kunci_jawaban TEXT,
                bobot DECIMAL(5,2) DEFAULT 1.00,
                urutan INT DEFAULT 0,
                gambar VARCHAR(255) DEFAULT NULL,
                media_type ENUM('gambar', 'video') DEFAULT NULL,
                tingkat_kesulitan ENUM('mudah', 'sedang', 'sulit') DEFAULT 'sedang',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_arsip_soal) REFERENCES arsip_soal(id) ON DELETE CASCADE,
                INDEX idx_pool (id_arsip_soal),
                INDEX idx_urutan (urutan),
                INDEX idx_tipe (tipe_soal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Arsip soal migration: Created table arsip_soal_item");
        }
        
        // Table: arsip_soal_matching (untuk tipe matching)
        if (!table_exists('arsip_soal_matching')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS arsip_soal_matching (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_arsip_soal_item INT NOT NULL,
                item_kiri VARCHAR(255) NOT NULL,
                item_kanan VARCHAR(255) NOT NULL,
                urutan INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_arsip_soal_item) REFERENCES arsip_soal_item(id) ON DELETE CASCADE,
                INDEX idx_pool_item (id_arsip_soal_item),
                INDEX idx_urutan (urutan)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Arsip soal migration: Created table arsip_soal_matching");
        }
        
        // Note: Triggers akan dibuat setelah tabel dibuat
        // Trigger akan di-handle oleh database atau bisa dibuat manual jika diperlukan
        // Untuk sekarang, total_soal akan diupdate manual saat insert/delete
        // atau bisa dibuat trigger manual di database
        
        return true;
    } catch (PDOException $e) {
        error_log("Arsip soal migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

