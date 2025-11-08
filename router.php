<?php
/**
 * Router untuk Clean URLs
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Menangani URL seperti:
 * - admin-login -> admin/login.php
 * - siswa-login -> siswa/login.php
 * - admin-manage-users -> admin/manage_users.php
 */

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Parse the full request URI
$parsed = parse_url($request_uri);
$path = isset($parsed['path']) ? $parsed['path'] : '';

// Remove base path (/UJAN)
$base_path = dirname($script_name);
if ($base_path !== '/' && $base_path !== '.') {
    $path = str_replace($base_path, '', $path);
}

// Clean up path
$path = trim($path, '/');

// Preserve query string for GET parameters
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $_GET);
}

// If path is empty, redirect to index
if (empty($path)) {
    require_once __DIR__ . '/index.php';
    exit;
}

// Route mapping
$routes = [
    // Root & Auth
    '' => 'index.php',
    'index' => 'index.php',
    'logout' => 'logout.php',
    'about' => 'about.php',
    
    // Login pages
    'admin-login' => 'admin_guru/login.php',
    'guru-login' => 'admin_guru/login.php',
    'siswa-login' => 'siswa/login.php',
    'operator-login' => 'operator/login.php',
    
    // Admin routes
    'admin' => 'admin/index.php',
    'admin-index' => 'admin/index.php',
    'admin-manage-users' => 'admin/manage_users.php',
    'admin-manage-kelas' => 'admin/manage_kelas.php',
    'admin-manage-mapel' => 'admin/manage_mapel.php',
    'admin-sekolah-settings' => 'admin/sekolah_settings.php',
    'admin-migrasi-kelas' => 'admin/migrasi_kelas.php',
    
    // Guru routes
    'guru' => 'guru/index.php',
    'guru-index' => 'guru/index.php',
    'guru-ujian-list' => 'guru/ujian/list.php',
    'guru-ujian-create' => 'guru/ujian/create.php',
    'guru-ujian-detail' => 'guru/ujian/detail.php',
    'guru-ujian-settings' => 'guru/ujian/settings.php',
    'guru-sesi-list' => 'guru/sesi/list.php',
    'guru-sesi-create' => 'guru/sesi/create.php',
    'guru-sesi-manage' => 'guru/sesi/manage.php',
    'guru-sesi-assign-peserta' => 'guru/sesi/assign_peserta.php',
    'guru-sesi-manage-token' => 'guru/sesi/manage_token.php',
    'guru-soal-create' => 'guru/soal/create.php',
    
    // Siswa routes
    'siswa' => 'siswa/index.php',
    'siswa-index' => 'siswa/index.php',
    'siswa-ujian-list' => 'siswa/ujian/list.php',
    'siswa-ujian-take' => 'siswa/ujian/take.php',
    'siswa-ujian-submit' => 'siswa/ujian/submit.php',
    'siswa-ujian-hasil' => 'siswa/ujian/hasil.php',
    'siswa-pr-list' => 'siswa/pr/list.php',
    'siswa-pr-submit' => 'siswa/pr/submit.php',
    
    // Operator routes
    'operator' => 'operator/index.php',
    'operator-index' => 'operator/index.php',
    'operator-sesi-list' => 'operator/sesi/list.php',
    'operator-sesi-manage' => 'operator/sesi/manage.php',
    'operator-sesi-assign-peserta' => 'operator/sesi/assign_peserta.php',
    'operator-sesi-manage-token' => 'operator/sesi/manage_token.php',
    'operator-monitoring-realtime' => 'operator/monitoring/realtime.php',
];

// Check if route exists
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        require_once $file;
        exit;
    }
}

// If route not found, try to find file directly
// Support for nested routes like admin/manage-users
$path_parts = explode('-', $path);
if (count($path_parts) >= 2) {
    $folder = $path_parts[0];
    $file_name = implode('_', array_slice($path_parts, 1));
    
    // Try direct file path first
    $file_path = __DIR__ . '/' . $folder . '/' . $file_name . '.php';
    if (file_exists($file_path)) {
        require_once $file_path;
        exit;
    }
    
    // Try nested folder (e.g., guru-ujian-list -> guru/ujian/list.php)
    if (count($path_parts) >= 3) {
        $subfolder = $path_parts[1];
        $subfile = implode('_', array_slice($path_parts, 2));
        $file_path = __DIR__ . '/' . $folder . '/' . $subfolder . '/' . $subfile . '.php';
        if (file_exists($file_path)) {
            require_once $file_path;
            exit;
        }
    }
}

// 404 Not Found
http_response_code(404);
?>
<!DOCTYPE html>
<html>
<head>
    <title>404 - Halaman Tidak Ditemukan</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #dc3545; margin: 0; }
        p { color: #666; }
        a { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <p>Halaman tidak ditemukan</p>
        <a href="/UJAN/">Kembali ke Beranda</a>
    </div>
</body>
</html>

