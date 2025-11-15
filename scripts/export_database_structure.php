<?php
/**
 * Export Database Structure (Schema Only)
 * Export struktur database tanpa data untuk keperluan version control
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain; charset=utf-8');

global $pdo;

try {
    // Get all tables
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    
    $output = "-- Database Structure Export\n";
    $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Database: " . DB_NAME . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Get CREATE TABLE statement
        $create_table = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $output .= "-- Table: $table\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $create_table['Create Table'] . ";\n\n";
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Save to file
    $filename = __DIR__ . '/../database_structure.sql';
    file_put_contents($filename, $output);
    
    echo "Database structure exported to: database_structure.sql\n";
    echo "Total tables: " . count($tables) . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

