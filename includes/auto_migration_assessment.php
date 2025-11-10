<?php
/**
 * Auto Migration for Assessment System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel bank_soal, berita_acara, jadwal_assessment, absensi_ujian
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run assessment system migration
 */
function run_assessment_system_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Assessment migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: bank_soal
        if (!table_exists('bank_soal')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS bank_soal (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_soal INT NOT NULL,
                id_mapel INT NOT NULL,
                tingkat_kelas VARCHAR(50) DEFAULT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                approved_by INT DEFAULT NULL,
                approved_at TIMESTAMP NULL DEFAULT NULL,
                rejection_reason TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_soal) REFERENCES soal(id) ON DELETE CASCADE,
                FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_soal (id_soal),
                INDEX idx_mapel (id_mapel),
                INDEX idx_status (status),
                INDEX idx_tingkat_kelas (tingkat_kelas)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: jadwal_assessment
        if (!table_exists('jadwal_assessment')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS jadwal_assessment (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_ujian INT NOT NULL,
                id_kelas INT NOT NULL,
                tingkat VARCHAR(50) DEFAULT NULL,
                tanggal DATE NOT NULL,
                waktu_mulai TIME NOT NULL,
                waktu_selesai TIME NOT NULL,
                id_sesi INT DEFAULT NULL COMMENT 'ID sesi yang dibuat otomatis',
                status ENUM('aktif', 'selesai', 'dibatalkan') DEFAULT 'aktif',
                is_susulan TINYINT(1) DEFAULT 0,
                id_jadwal_utama INT DEFAULT NULL COMMENT 'ID jadwal utama jika ini susulan',
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_ujian) REFERENCES ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
                FOREIGN KEY (id_sesi) REFERENCES sesi_ujian(id) ON DELETE SET NULL,
                FOREIGN KEY (id_jadwal_utama) REFERENCES jadwal_assessment(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ujian (id_ujian),
                INDEX idx_kelas (id_kelas),
                INDEX idx_tanggal (tanggal),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: berita_acara
        if (!table_exists('berita_acara')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS berita_acara (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_ujian INT NOT NULL,
                id_sesi INT DEFAULT NULL,
                id_kelas INT DEFAULT NULL,
                id_jadwal_assessment INT DEFAULT NULL,
                tanggal DATE NOT NULL,
                waktu_mulai TIME NOT NULL,
                waktu_selesai TIME NOT NULL,
                pengawas TEXT DEFAULT NULL COMMENT 'Nama pengawas (JSON array)',
                total_peserta INT DEFAULT 0,
                total_hadir INT DEFAULT 0,
                total_tidak_hadir INT DEFAULT 0,
                total_izin INT DEFAULT 0,
                total_sakit INT DEFAULT 0,
                catatan TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_ujian) REFERENCES ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (id_sesi) REFERENCES sesi_ujian(id) ON DELETE SET NULL,
                FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE SET NULL,
                FOREIGN KEY (id_jadwal_assessment) REFERENCES jadwal_assessment(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ujian (id_ujian),
                INDEX idx_tanggal (tanggal),
                INDEX idx_sesi (id_sesi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: absensi_ujian
        if (!table_exists('absensi_ujian')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS absensi_ujian (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_sesi INT DEFAULT NULL,
                id_pr INT DEFAULT NULL COMMENT 'Untuk PR jika diperlukan',
                id_siswa INT NOT NULL,
                status_absen ENUM('hadir', 'tidak_hadir', 'izin', 'sakit') DEFAULT 'tidak_hadir',
                waktu_absen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                metode_absen ENUM('auto', 'manual') DEFAULT 'auto',
                created_by INT DEFAULT NULL COMMENT 'User yang membuat absensi (untuk manual)',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_sesi) REFERENCES sesi_ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_absensi_sesi (id_sesi, id_siswa),
                INDEX idx_sesi (id_sesi),
                INDEX idx_siswa (id_siswa),
                INDEX idx_status (status_absen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: sumatip_kelas_target
        if (!table_exists('sumatip_kelas_target')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sumatip_kelas_target (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_ujian INT NOT NULL,
                id_kelas INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_ujian) REFERENCES ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
                UNIQUE KEY unique_sumatip_kelas (id_ujian, id_kelas)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Table: sumatip_log
        if (!table_exists('sumatip_log')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS sumatip_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_ujian INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                keterangan TEXT DEFAULT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_ujian) REFERENCES ujian(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_ujian (id_ujian),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        
        // Add column can_create_assessment_soal to users table if not exists
        if (!column_exists('users', 'can_create_assessment_soal')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN can_create_assessment_soal TINYINT(1) DEFAULT 0 COMMENT 'Permission untuk membuat soal assessment' AFTER is_operator");
        }
        
        // Add column media_type to soal table if not exists
        if (!column_exists('soal', 'media_type')) {
            $pdo->exec("ALTER TABLE soal ADD COLUMN media_type ENUM('gambar', 'video') DEFAULT NULL AFTER gambar");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Assessment system migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

