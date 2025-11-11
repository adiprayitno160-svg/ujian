<?php
/**
 * Auto Migration for Fraud Detection System
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Menambahkan kolom untuk fraud detection dan normal disruption handling
 */

/**
 * Run fraud detection migration
 */
function run_fraud_detection_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists')) {
        error_log("Fraud detection migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Add columns to nilai table
        if (table_exists('nilai')) {
            // is_fraud - flag untuk fraud detection
            if (!column_exists('nilai', 'is_fraud')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN is_fraud TINYINT(1) DEFAULT 0 AFTER is_suspicious");
            }
            
            // requires_relogin - flag untuk memaksa login ulang
            if (!column_exists('nilai', 'requires_relogin')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN requires_relogin TINYINT(1) DEFAULT 0 AFTER is_fraud");
            }
            
            // answers_locked - flag untuk jawaban yang sudah dikunci
            if (!column_exists('nilai', 'answers_locked')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN answers_locked TINYINT(1) DEFAULT 0 AFTER requires_relogin");
            }
            
            // requires_token - flag untuk memerlukan token untuk resume
            if (!column_exists('nilai', 'requires_token')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN requires_token TINYINT(1) DEFAULT 0 AFTER answers_locked");
            }
            
            // fraud_reason - alasan fraud terdeteksi
            if (!column_exists('nilai', 'fraud_reason')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN fraud_reason TEXT DEFAULT NULL AFTER requires_token");
            }
            
            // fraud_detected_at - waktu fraud terdeteksi
            if (!column_exists('nilai', 'fraud_detected_at')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN fraud_detected_at DATETIME DEFAULT NULL AFTER fraud_reason");
            }
            
            // disruption_reason - alasan gangguan normal
            if (!column_exists('nilai', 'disruption_reason')) {
                $pdo->exec("ALTER TABLE nilai ADD COLUMN disruption_reason TEXT DEFAULT NULL AFTER fraud_detected_at");
            }
            
            // Add indexes
            try {
                $pdo->exec("CREATE INDEX idx_is_fraud ON nilai(is_fraud)");
            } catch (PDOException $e) {
                // Index might already exist
            }
            
            try {
                $pdo->exec("CREATE INDEX idx_requires_relogin ON nilai(requires_relogin)");
            } catch (PDOException $e) {
                // Index might already exist
            }
            
            try {
                $pdo->exec("CREATE INDEX idx_answers_locked ON nilai(answers_locked)");
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
        
        // Add columns to jawaban_siswa table
        if (table_exists('jawaban_siswa')) {
            // is_locked - flag untuk jawaban yang sudah dikunci
            if (!column_exists('jawaban_siswa', 'is_locked')) {
                $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN is_locked TINYINT(1) DEFAULT 0 AFTER is_ragu");
            }
            
            // locked_at - waktu jawaban dikunci
            if (!column_exists('jawaban_siswa', 'locked_at')) {
                $pdo->exec("ALTER TABLE jawaban_siswa ADD COLUMN locked_at DATETIME DEFAULT NULL AFTER is_locked");
            }
            
            // Add index
            try {
                $pdo->exec("CREATE INDEX idx_is_locked ON jawaban_siswa(is_locked)");
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Fraud detection migration error: " . $e->getMessage());
        return false;
    }
}

// Auto-run migration if this file is included
if (function_exists('table_exists') && function_exists('column_exists')) {
    run_fraud_detection_migration();
}



