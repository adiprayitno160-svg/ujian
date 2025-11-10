<?php
/**
 * Auto Migration for AI Settings
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk tabel ai_settings dengan default enabled
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run AI settings migration
 */
function run_ai_settings_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists')) {
        error_log("AI settings migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Table: ai_settings
        if (!table_exists('ai_settings')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ai_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(50) DEFAULT 'gemini',
                api_key VARCHAR(255) DEFAULT NULL,
                enabled TINYINT(1) DEFAULT 1,
                model VARCHAR(100) DEFAULT 'gemini-1.5-flash',
                temperature DECIMAL(3,2) DEFAULT 0.70,
                max_tokens INT(11) DEFAULT 2000,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_provider (provider)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            error_log("Migration: Created ai_settings table");
        }
        
        // Insert default ai_settings record if not exists (with enabled = 1)
        $stmt = $pdo->prepare("SELECT id FROM ai_settings WHERE provider = 'gemini'");
        $stmt->execute();
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO ai_settings (provider, enabled, model, temperature, max_tokens) 
                                  VALUES ('gemini', 1, 'gemini-1.5-flash', 0.70, 2000)");
            $stmt->execute();
            error_log("Migration: Created default ai_settings record with enabled = 1");
        } else {
            // Update existing record to enabled = 1 if it's 0 (only if no API key is set, to avoid breaking existing configs)
            // This is optional - we'll let admin configure it manually if they want
            // But we'll ensure the default is 1 for new installations
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("AI settings migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

