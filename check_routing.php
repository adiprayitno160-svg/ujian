<?php
/**
 * Check routing configuration
 * Akses file ini untuk melihat informasi routing
 */

echo "<!DOCTYPE html><html><head><title>Routing Check</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".box{background:white;padding:20px;margin:10px 0;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.1);}";
echo "h1{color:#333;}h2{color:#666;border-bottom:2px solid #ddd;padding-bottom:10px;}";
echo "pre{background:#f8f9fa;padding:15px;border-radius:5px;overflow-x:auto;}";
echo "a{color:#007bff;text-decoration:none;}a:hover{text-decoration:underline;}";
echo ".success{color:green;}.error{color:red;}</style></head><body>";

echo "<div class='box'><h1>Routing Configuration Check</h1></div>";

echo "<div class='box'><h2>Server Information</h2><pre>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n";
echo "SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "</pre></div>";

echo "<div class='box'><h2>File Check</h2><pre>";
$files_to_check = [
    'router.php' => __DIR__ . '/router.php',
    'siswa/login.php' => __DIR__ . '/siswa/login.php',
    '.htaccess' => __DIR__ . '/.htaccess',
    'config/config.php' => __DIR__ . '/config/config.php',
];

foreach ($files_to_check as $name => $path) {
    $exists = file_exists($path);
    $status = $exists ? '<span class="success">EXISTS</span>' : '<span class="error">NOT FOUND</span>';
    echo "$name: $status\n";
    if ($exists) {
        echo "  Path: $path\n";
        echo "  Size: " . filesize($path) . " bytes\n";
    }
}
echo "</pre></div>";

echo "<div class='box'><h2>Folder Structure</h2><pre>";
$folders = ['admin', 'siswa', 'guru', 'operator', 'config', 'includes'];
foreach ($folders as $folder) {
    $path = __DIR__ . '/' . $folder;
    $exists = is_dir($path);
    $status = $exists ? '<span class="success">EXISTS</span>' : '<span class="error">NOT FOUND</span>';
    echo "$folder/: $status\n";
}
echo "</pre></div>";

echo "<div class='box'><h2>Base Path Detection Test</h2><pre>";
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$script_full_path = str_replace('\\', '/', __DIR__);
$relative_path = str_replace($document_root, '', $script_full_path);
echo "Document Root: $document_root\n";
echo "Script Full Path: $script_full_path\n";
echo "Relative Path (Base Path): $relative_path\n";
echo "</pre></div>";

echo "<div class='box'><h2>Test URLs</h2><ul>";
$base_url = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$test_urls = [
    'Direct file access' => $base_url . $relative_path . '/siswa/login.php',
    'Clean URL (siswa-login)' => $base_url . $relative_path . '/siswa-login',
    'Router.php direct' => $base_url . $relative_path . '/router.php',
    'Index.php' => $base_url . $relative_path . '/',
];

foreach ($test_urls as $name => $url) {
    echo "<li><strong>$name:</strong> <a href='$url' target='_blank'>$url</a></li>";
}
echo "</ul></div>";

echo "<div class='box'><h2>Apache mod_rewrite Check</h2><pre>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $mod_rewrite = in_array('mod_rewrite', $modules);
    echo "mod_rewrite: " . ($mod_rewrite ? '<span class="success">ENABLED</span>' : '<span class="error">DISABLED</span>') . "\n";
} else {
    echo "Cannot check Apache modules (apache_get_modules not available)\n";
    echo "This is normal if running via PHP built-in server\n";
}
echo "</pre></div>";

echo "<div class='box'><h2>Recommendations</h2><ul>";
echo "<li>If mod_rewrite is disabled, enable it in Apache configuration</li>";
echo "<li>Make sure .htaccess file is being read by Apache (AllowOverride All)</li>";
echo "<li>Access URL should be: <strong>" . $base_url . $relative_path . "/siswa-login</strong></li>";
echo "<li>Or use direct file access: <strong>" . $base_url . $relative_path . "/siswa/login.php</strong></li>";
echo "</ul></div>";

echo "</body></html>";
?>

