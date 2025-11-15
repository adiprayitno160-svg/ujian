<?php
/**
 * Auto Migration for Users NIP
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto migration untuk menambahkan kolom NIP di tabel users
 * 
 * Note: Functions table_exists() and column_exists() are defined in auto_migration.php
 * This file should be included after auto_migration.php
 */

/**
 * Run users NIP migration
 */
function run_users_nip_migration() {
    global $pdo;
    
    // Ensure helper functions are available
    if (!function_exists('table_exists') || !function_exists('column_exists') || !function_exists('add_column_if_not_exists')) {
        error_log("Users NIP migration: Helper functions not available. Make sure auto_migration.php is included first.");
        return false;
    }
    
    try {
        // Check if users table exists
        if (!table_exists('users')) {
            error_log("Users NIP migration: Table users does not exist.");
            return false;
        }
        
        // Add NIP column if it doesn't exist
        add_column_if_not_exists('users', 'nip', "VARCHAR(50) DEFAULT NULL COMMENT 'Nomor Induk Pegawai untuk guru' AFTER nama");
        
        return true;
    } catch (PDOException $e) {
        error_log("Users NIP migration error: " . $e->getMessage());
        return false;
    }
}

// Migration will be called from database.php after connection is established
// Don't auto-run here to avoid issues

