<?php
/**
 * Test File - Hapus setelah verifikasi
 */
echo "<h1>PHP Test - UJAN</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script Path: " . __FILE__ . "</p>";

// Test database connection
try {
    require_once __DIR__ . '/config/database.php';
    echo "<p style='color: green;'>✓ Database connection: OK</p>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<p>Host: " . DB_HOST . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection: FAILED - " . $e->getMessage() . "</p>";
}

// Test file structure
echo "<h2>File Structure Test:</h2>";
$files_to_check = [
    'index.php' => 'Main index file',
    'config/database.php' => 'Database config',
    'config/config.php' => 'App config',
    'includes/header.php' => 'Header include',
    'assets/uploads' => 'Upload directory'
];

foreach ($files_to_check as $file => $desc) {
    $exists = file_exists(__DIR__ . '/' . $file);
    $color = $exists ? 'green' : 'red';
    $icon = $exists ? '✓' : '✗';
    echo "<p style='color: $color;'>$icon $desc ($file): " . ($exists ? 'EXISTS' : 'NOT FOUND') . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>Go to Index</a></p>";
echo "<p><a href='install.php'>Go to Install</a></p>";
?>

