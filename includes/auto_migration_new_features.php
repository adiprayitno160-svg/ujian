<?php
/**
 * Auto Migration for New Features
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk fitur baru: notifications, waktu_pengerjaan, progress tracking, dll
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run new features migration
 */
function run_new_features_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("New features migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: notifications
        if (!table_exists('notifications')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('ujian', 'pr', 'tugas', 'nilai', 'system', 'reminder') DEFAULT 'system',
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                link VARCHAR(500) DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                read_at TIMESTAMP NULL DEFAULT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_is_read (is_read),
                INDEX idx_type (type),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table notifications");
        }
        
        // Table: notification_preferences
        if (!table_exists('notification_preferences')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notification_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type ENUM('ujian', 'pr', 'tugas', 'nilai', 'system', 'reminder') NOT NULL,
                email_enabled TINYINT(1) DEFAULT 1,
                in_app_enabled TINYINT(1) DEFAULT 1,
                push_enabled TINYINT(1) DEFAULT 0,
                reminder_before_hours INT DEFAULT 24,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_user_type (user_id, type),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table notification_preferences");
        }
        
        // Add waktu_pengerjaan column to jawaban_siswa if not exists
        if (!column_exists('jawaban_siswa', 'waktu_pengerjaan')) {
            $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN waktu_pengerjaan INT DEFAULT NULL COMMENT 'Waktu pengerjaan dalam detik' AFTER waktu_submit");
            error_log("Migration: Added column waktu_pengerjaan to jawaban_siswa");
        }
        
        // Add waktu_mulai_jawab column to jawaban_siswa if not exists
        if (!column_exists('jawaban_siswa', 'waktu_mulai_jawab')) {
            $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN waktu_mulai_jawab TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu mulai mengerjakan soal' AFTER waktu_pengerjaan");
            error_log("Migration: Added column waktu_mulai_jawab to jawaban_siswa");
        }
        
        // Add waktu_selesai_jawab column to jawaban_siswa if not exists
        if (!column_exists('jawaban_siswa', 'waktu_selesai_jawab')) {
            $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN waktu_selesai_jawab TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu selesai mengerjakan soal' AFTER waktu_mulai_jawab");
            error_log("Migration: Added column waktu_selesai_jawab to jawaban_siswa");
        }
        
        // Add is_ragu column to jawaban_siswa if not exists
        if (!column_exists('jawaban_siswa', 'is_ragu')) {
            $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN is_ragu TINYINT(1) DEFAULT 0 COMMENT 'Flag ragu-ragu untuk jawaban' AFTER waktu_selesai_jawab");
            error_log("Migration: Added column is_ragu to jawaban_siswa");
        }
        
        // Table: student_progress
        if (!table_exists('student_progress')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS student_progress (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                mapel_id INT NOT NULL,
                semester VARCHAR(20) DEFAULT NULL,
                tahun_ajaran VARCHAR(20) DEFAULT NULL,
                total_ujian INT DEFAULT 0,
                total_nilai DECIMAL(5,2) DEFAULT 0,
                rata_rata DECIMAL(5,2) DEFAULT 0,
                total_soal INT DEFAULT 0,
                total_benar INT DEFAULT 0,
                total_salah INT DEFAULT 0,
                accuracy DECIMAL(5,2) DEFAULT 0,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (mapel_id) REFERENCES mapel(id) ON DELETE CASCADE,
                UNIQUE KEY unique_student_mapel_semester (student_id, mapel_id, semester, tahun_ajaran),
                INDEX idx_student_id (student_id),
                INDEX idx_mapel_id (mapel_id),
                INDEX idx_semester (semester),
                INDEX idx_tahun_ajaran (tahun_ajaran)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table student_progress");
        }
        
        // Table: ujian_templates
        if (!table_exists('ujian_templates')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ujian_templates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                id_mapel INT DEFAULT NULL,
                durasi INT DEFAULT 90,
                acak_soal TINYINT(1) DEFAULT 1,
                acak_opsi TINYINT(1) DEFAULT 1,
                anti_contek_enabled TINYINT(1) DEFAULT 1,
                min_submit_minutes INT DEFAULT 0,
                ai_correction_enabled TINYINT(1) DEFAULT 0,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (id_mapel) REFERENCES mapel(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_created_by (created_by),
                INDEX idx_id_mapel (id_mapel)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table ujian_templates");
        }
        
        // Table: soal_tags
        if (!table_exists('soal_tags')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS soal_tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                type ENUM('topik', 'kurikulum', 'kompetensi_dasar', 'tingkat_kesulitan', 'custom') DEFAULT 'custom',
                color VARCHAR(20) DEFAULT '#007bff',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_name (name),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table soal_tags");
        }
        
        // Table: soal_tag_relations
        if (!table_exists('soal_tag_relations')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS soal_tag_relations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                soal_id INT NOT NULL,
                tag_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (soal_id) REFERENCES soal(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES soal_tags(id) ON DELETE CASCADE,
                UNIQUE KEY unique_soal_tag (soal_id, tag_id),
                INDEX idx_soal_id (soal_id),
                INDEX idx_tag_id (tag_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table soal_tag_relations");
        }
        
        // Table: audit_logs (for administrative actions)
        if (!table_exists('audit_logs')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(100) DEFAULT NULL,
                record_id INT DEFAULT NULL,
                old_value TEXT DEFAULT NULL,
                new_value TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_table_name (table_name),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            error_log("Migration: Created table audit_logs");
        }
        
        // Add show_review_mode column to ujian if not exists
        if (!column_exists('ujian', 'show_review_mode')) {
            $pdo->exec("ALTER TABLE ujian ADD COLUMN show_review_mode TINYINT(1) DEFAULT 1 COMMENT 'Tampilkan mode review sebelum submit' AFTER anti_contek_enabled");
            error_log("Migration: Added column show_review_mode to ujian");
        }
        
        // Add archived_at column to ujian if not exists
        if (!column_exists('ujian', 'archived_at')) {
            $pdo->exec("ALTER TABLE ujian ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Waktu ujian di-archive' AFTER status");
            error_log("Migration: Added column archived_at to ujian");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("New features migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

