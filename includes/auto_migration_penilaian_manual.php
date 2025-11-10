<?php
/**
 * Auto Migration for Penilaian Manual System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel penilaian manual (input nilai oleh guru, dikumpulkan operator untuk raport)
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run penilaian manual system migration
 */
function run_penilaian_manual_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Penilaian manual migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: penilaian_manual
        if (!table_exists('penilaian_manual')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS penilaian_manual (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_guru INT NOT NULL COMMENT 'Guru mata pelajaran yang memberikan nilai',
                id_siswa INT NOT NULL COMMENT 'Siswa yang dinilai',
                id_mapel INT NOT NULL COMMENT 'Mata pelajaran',
                id_kelas INT NOT NULL COMMENT 'Kelas siswa',
                tahun_ajaran VARCHAR(20) NOT NULL COMMENT 'Tahun ajaran',
                semester ENUM('ganjil', 'genap') NOT NULL COMMENT 'Semester',
                nilai_tugas DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai tugas',
                nilai_uts DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai UTS',
                nilai_uas DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai UAS',
                nilai_akhir DECIMAL(5,2) DEFAULT NULL COMMENT 'Nilai akhir (rata-rata atau sesuai kebijakan)',
                predikat VARCHAR(20) DEFAULT NULL COMMENT 'Predikat (A, B, C, D)',
                keterangan TEXT DEFAULT NULL COMMENT 'Keterangan tambahan dari guru',
                status ENUM('draft', 'submitted', 'approved') DEFAULT 'draft' COMMENT 'Status: draft = belum dikumpulkan, submitted = sudah dikumpulkan ke operator, approved = sudah disetujui operator',
                aktif TINYINT(1) DEFAULT 0 COMMENT 'Status aktif: 0 = tidak aktif, 1 = aktif (diaktifkan oleh operator)',
                submitted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu dikumpulkan ke operator',
                approved_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu disetujui operator',
                approved_by INT DEFAULT NULL COMMENT 'Operator yang menyetujui',
                activated_by INT DEFAULT NULL COMMENT 'Operator yang mengaktifkan',
                activated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu diaktifkan',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_guru) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (id_siswa) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE CASCADE,
                FOREIGN KEY (id_kelas) REFERENCES kelas(id) ON DELETE CASCADE,
                FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL,
                UNIQUE KEY unique_penilaian (id_guru, id_siswa, id_mapel, id_kelas, tahun_ajaran, semester),
                INDEX idx_guru (id_guru),
                INDEX idx_siswa (id_siswa),
                INDEX idx_mapel (id_mapel),
                INDEX idx_kelas (id_kelas),
                INDEX idx_tahun_ajaran (tahun_ajaran),
                INDEX idx_semester (semester),
                INDEX idx_status (status),
                INDEX idx_aktif (aktif)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } else {
            // Add aktif column if it doesn't exist
            if (!column_exists('penilaian_manual', 'aktif')) {
                try {
                    $pdo->exec("ALTER TABLE penilaian_manual 
                               ADD COLUMN aktif TINYINT(1) DEFAULT 0 COMMENT 'Status aktif: 0 = tidak aktif, 1 = aktif (diaktifkan oleh operator)' AFTER status");
                    $pdo->exec("ALTER TABLE penilaian_manual ADD INDEX idx_aktif (aktif)");
                    error_log("Penilaian manual migration: Added aktif column");
                } catch (PDOException $e) {
                    error_log("Penilaian manual migration: Error adding aktif column: " . $e->getMessage());
                }
            }
            
            // Add activated_by column if it doesn't exist
            if (!column_exists('penilaian_manual', 'activated_by')) {
                try {
                    $pdo->exec("ALTER TABLE penilaian_manual 
                               ADD COLUMN activated_by INT DEFAULT NULL COMMENT 'Operator yang mengaktifkan' AFTER approved_by");
                    error_log("Penilaian manual migration: Added activated_by column");
                } catch (PDOException $e) {
                    error_log("Penilaian manual migration: Error adding activated_by column: " . $e->getMessage());
                }
            }
            
            // Add activated_at column if it doesn't exist
            if (!column_exists('penilaian_manual', 'activated_at')) {
                try {
                    $pdo->exec("ALTER TABLE penilaian_manual 
                               ADD COLUMN activated_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu diaktifkan' AFTER activated_by");
                    error_log("Penilaian manual migration: Added activated_at column");
                } catch (PDOException $e) {
                    error_log("Penilaian manual migration: Error adding activated_at column: " . $e->getMessage());
                }
            }
            
            // Add foreign key for activated_by if it doesn't exist
            // Check if foreign key exists by querying INFORMATION_SCHEMA
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'penilaian_manual' 
                    AND COLUMN_NAME = 'activated_by' 
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                $stmt->execute([DB_NAME]);
                $fk_exists = $stmt->fetch()['count'] > 0;
                
                if (!$fk_exists && column_exists('penilaian_manual', 'activated_by')) {
                    // Try to add foreign key, but ignore error if it fails (might already exist with different name)
                    try {
                        $pdo->exec("ALTER TABLE penilaian_manual 
                                   ADD CONSTRAINT fk_penilaian_manual_activated_by 
                                   FOREIGN KEY (activated_by) REFERENCES users(id) ON DELETE SET NULL");
                        error_log("Penilaian manual migration: Added foreign key for activated_by");
                    } catch (PDOException $e) {
                        // Foreign key might already exist or there might be data issues
                        error_log("Penilaian manual migration: Note - Could not add foreign key for activated_by (might already exist): " . $e->getMessage());
                    }
                }
            } catch (PDOException $e) {
                error_log("Penilaian manual migration: Error checking foreign key: " . $e->getMessage());
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Penilaian manual migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

