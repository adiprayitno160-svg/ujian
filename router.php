<?php
/**
 * Router untuk Clean URLs
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Menangani URL seperti:
 * - admin-login -> admin/login.php
 * - login -> siswa/login.php (main login page)
 * - siswa-login -> siswa/login.php (backward compatibility)
 * - admin-manage-users -> admin/manage_users.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Set error handler to catch all errors (only log, don't output to avoid interfering with page output)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_message = "Error [$errno]: $errstr in $errfile on line $errline";
    error_log($error_message);
    
    // Don't output error to page - let PHP handle it naturally or log it
    // Only log to error log file
    return false; // Let PHP handle the error normally
});

// Set exception handler (only for uncaught exceptions)
set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    error_log("Stack trace: " . $exception->getTraceAsString());
    
    // Don't output to page - let it be caught by try-catch in router
    // This handler is just a fallback
});

// Load config first
try {
    if (!file_exists(__DIR__ . '/config/config.php')) {
        throw new Exception('config/config.php not found');
    }
    require_once __DIR__ . '/config/config.php';
} catch (Exception $e) {
    http_response_code(500);
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Configuration Error</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Configuration Error</h2>
            <p>Error loading configuration file.</p>
            <p><small>" . htmlspecialchars($e->getMessage()) . "</small></p>
        </div>
    </body>
    </html>
    ");
}

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/router.php';

// Parse the full request URI
$parsed = parse_url($request_uri);
$path = isset($parsed['path']) ? $parsed['path'] : '';

// Normalize paths (handle Windows backslashes)
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$script_full_path = str_replace('\\', '/', __DIR__);
$script_dir = dirname($script_name);

// Calculate base path - the directory where router.php is located relative to document root
$base_path = '';
if ($document_root && strpos($script_full_path, $document_root) === 0) {
    // Get relative path from document root to project root
    $base_path = substr($script_full_path, strlen($document_root));
    $base_path = rtrim($base_path, '/');
}

// If base_path is empty, try to detect from SCRIPT_NAME
if (empty($base_path) && $script_dir && $script_dir !== '/' && $script_dir !== '.') {
    $base_path = $script_dir;
}

// If still empty, try to detect from REQUEST_URI
if (empty($base_path) && !empty($path)) {
    $path_parts = array_filter(explode('/', trim($path, '/')));
    $path_parts = array_values($path_parts);
    
    if (count($path_parts) > 0) {
        // Try each possible base path
        for ($i = 1; $i <= count($path_parts); $i++) {
            $test_parts = array_slice($path_parts, 0, $i);
            $potential_base = '/' . implode('/', $test_parts);
            
            // Check if router.php exists at this path (case-insensitive for Windows)
            $test_path = $document_root . $potential_base . '/router.php';
            if (file_exists($test_path)) {
                $base_path = $potential_base;
                break;
            }
            
            // Also try case-insensitive matching for Windows
            // Check if any folder in document_root matches (case-insensitive)
            if ($document_root && is_dir($document_root)) {
                $dirs = scandir($document_root);
                foreach ($dirs as $dir) {
                    if ($dir !== '.' && $dir !== '..' && is_dir($document_root . '/' . $dir)) {
                        // Case-insensitive comparison
                        if (strcasecmp($test_parts[0], $dir) === 0) {
                            // Found matching folder, check if router.php exists
                            $actual_base = '/' . $dir;
                            if (file_exists($document_root . $actual_base . '/router.php')) {
                                $base_path = $actual_base;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
    }
}

// Remove base path from the request path
if (!empty($base_path)) {
    $base_path = rtrim($base_path, '/');
    // Remove base path from beginning of path (case-insensitive)
    if (!empty($base_path)) {
        $path_lower = strtolower($path);
        $base_path_lower = strtolower($base_path);
        if (strpos($path_lower, $base_path_lower) === 0) {
            $path = substr($path, strlen($base_path));
        }
    }
}

// Also try removing script_dir if it's still in the path (case-insensitive)
if ($script_dir !== '/' && $script_dir !== '.') {
    $path_lower = strtolower($path);
    $script_dir_lower = strtolower($script_dir);
    if (strpos($path_lower, $script_dir_lower) === 0) {
        $path = substr($path, strlen($script_dir));
    }
}

// Clean up path - remove leading/trailing slashes
$path = trim($path, '/');

// Handle case where path might still contain base path segments
// This can happen if base path detection didn't work perfectly
// For example, if path is "ujian/siswa-login", and we're in UJAN folder
if (!empty($path)) {
    $path_segments = explode('/', $path);
    if (count($path_segments) > 1) {
        $first_segment = $path_segments[0];
        
        // Check if first segment matches the actual project folder name (case-insensitive)
        // Get the actual folder name from __DIR__
        $actual_folder = basename(__DIR__);
        
        // If first segment matches actual folder name (case-insensitive), remove it
        if (strcasecmp($first_segment, $actual_folder) === 0) {
            // Remove the folder name segment
            $path = implode('/', array_slice($path_segments, 1));
        } else {
            // Also check if it matches common variations
            $common_variations = ['ujian', 'UJAN', 'Ujan', 'ujan'];
            foreach ($common_variations as $variation) {
                if (strcasecmp($first_segment, $variation) === 0 && strcasecmp($actual_folder, 'UJAN') === 0) {
                    // Remove the folder name segment
                    $path = implode('/', array_slice($path_segments, 1));
                    break;
                }
            }
        }
    }
}

// Preserve query string for GET parameters
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $_GET);
}

        // Handle static files (manifest.json, service-worker.js)
        if ($path === 'manifest.json') {
            header('Content-Type: application/json');
            readfile(__DIR__ . '/manifest.json');
            exit;
        }
        
        if ($path === 'service-worker.js') {
            header('Content-Type: application/javascript');
            readfile(__DIR__ . '/service-worker.js');
            exit;
        }
        
        // If path is empty or 'index', redirect directly to login
        // Don't include index.php to prevent any redirect loops
        if (empty($path) || $path === 'index') {
            // Check if user is logged in, redirect to appropriate dashboard
            if (function_exists('is_logged_in') && is_logged_in() && isset($_SESSION['role'])) {
                $role = $_SESSION['role'];
                if ($role === 'admin') {
                    header('Location: ' . base_url('admin'), true, 302);
                } elseif ($role === 'guru') {
                    header('Location: ' . base_url('guru'), true, 302);
                } elseif ($role === 'operator') {
                    header('Location: ' . base_url('operator'), true, 302);
                } else {
                    header('Location: ' . base_url('siswa-dashboard'), true, 302);
                }
            } else {
                header('Location: ' . base_url('login'), true, 302);
            }
            exit;
        }

// Route mapping
$routes = [
    // Root & Auth
    '' => 'index.php',
    // Remove 'index' route to prevent confusion - use root or login instead
    'logout' => 'logout.php',
    'about' => 'about.php',
    
    // Login pages
    'admin-login' => 'admin_guru/login.php',
    'guru-login' => 'admin_guru/login.php',
    'login' => 'siswa/login.php',  // Main login page (for students)
    'siswa-login' => 'siswa/login.php',  // Keep for backward compatibility
    'operator-login' => 'operator/login.php',
    
    // Admin routes
    'admin' => 'admin/index.php',
    'admin-index' => 'admin/index.php',
    'admin-about' => 'admin/about.php',
    'admin-bulk-operations' => 'admin/bulk_operations.php',
    'admin-manage-users' => 'admin/manage_users.php',
    'admin-ujian-bulk' => 'admin/ujian/bulk_operations.php',
    'admin-manage-kelas' => 'admin/manage_kelas.php',
    'admin-manage-mapel' => 'admin/manage_mapel.php',
    'admin-manage-tahun-ajaran' => 'admin/manage_tahun_ajaran.php',
    'admin-sekolah-settings' => 'admin/sekolah_settings.php',
    'admin-migrasi-kelas' => 'admin/migrasi_kelas.php',
    'admin-arsip-soal' => 'admin/arsip_soal/list.php',
    
    // Guru routes
    'guru' => 'guru/index.php',
    'guru-index' => 'guru/index.php',
    'guru-ujian-list' => 'guru/ujian/list.php',
    'guru-ujian-create' => 'guru/ujian/create.php',
    'guru-ujian-detail' => 'guru/ujian/detail.php',
    'guru-ujian-settings' => 'guru/ujian/settings.php',
    'guru-ujian-templates' => 'guru/ujian/templates.php',
    // SUMATIP hanya untuk operator - redirect ke operator assessment
    'guru-ujian-sumatip-list' => 'operator/assessment/sumatip/list.php',
    'guru-ujian-sumatip-create' => 'operator/assessment/sumatip/list.php',
    'guru-sesi-list' => 'guru/sesi/list.php',
    'guru-sesi-create' => 'guru/sesi/create.php',
    'guru-sesi-manage' => 'guru/sesi/manage.php',
    'guru-sesi-assign-peserta' => 'guru/sesi/assign_peserta.php',
    'guru-sesi-manage-token' => 'guru/sesi/manage_token.php',
    'guru-soal-create' => 'guru/soal/create.php',
    'guru-tugas-list' => 'guru/tugas/list.php',
    'guru-tugas-create' => 'guru/tugas/create.php',
    'guru-tugas-edit' => 'guru/tugas/edit.php',
    'guru-tugas-detail' => 'guru/tugas/detail.php',
    'guru-tugas-review' => 'guru/tugas/review.php',
    'guru-tugas-delete' => 'guru/tugas/delete.php',
    'guru-penilaian-list' => 'guru/penilaian/list.php',
    'guru-penilaian-save' => 'guru/penilaian/save.php',
    'guru-penilaian-import' => 'guru/penilaian/import.php',
    'guru-penilaian-export-template' => 'guru/penilaian/export_template.php',
    
    // Siswa routes
    'siswa' => 'siswa/index.php',
    'siswa-index' => 'siswa/index.php',
    'siswa-dashboard' => 'siswa/dashboard.php',
    'siswa-progress' => 'siswa/progress.php',
    'siswa-notifications' => 'siswa/notifications.php',
    'siswa-about' => 'siswa/about.php',
    'siswa-ujian-list' => 'siswa/ujian/list.php',
    'siswa-ujian-review' => 'siswa/ujian/review.php',
    'siswa-ujian-take' => 'siswa/ujian/take.php',
    'siswa-ujian-submit' => 'siswa/ujian/submit.php',
    'siswa-ujian-hasil' => 'siswa/ujian/hasil.php',
    'siswa-pr-list' => 'siswa/pr/list.php',
    'siswa-pr-submit' => 'siswa/pr/submit.php',
    'siswa-tugas-list' => 'siswa/tugas/list.php',
    'siswa-tugas-detail' => 'siswa/tugas/detail.php',
    'siswa-tugas-submit' => 'siswa/tugas/submit.php',
    'siswa-raport-list' => 'siswa/raport/list.php',
    'siswa-raport-print' => 'siswa/raport/print.php',
    'siswa-raport-export-pdf' => 'siswa/raport/export_pdf.php',
    
    // Guru routes
    'guru-about' => 'guru/about.php',
    
    // Operator routes
    'operator' => 'operator/index.php',
    'operator-index' => 'operator/index.php',
    'operator-about' => 'operator/about.php',
    'operator-manage-siswa' => 'operator/manage_siswa.php',
    'operator-manage-kelas' => 'operator/manage_kelas.php',
    'operator-template-raport' => 'operator/template_raport.php',
    'operator-raport-export-pdf' => 'operator/raport/export_pdf.php',
    'operator-sesi-list' => 'operator/sesi/list.php',
    'operator-sesi-manage' => 'operator/sesi/manage.php',
    'operator-sesi-assign-peserta' => 'operator/sesi/assign_peserta.php',
    'operator-sesi-manage-token' => 'operator/sesi/manage_token.php',
    'operator-tugas-list' => 'operator/tugas/list.php',
    'operator-tugas-create' => 'operator/tugas/create.php',
    'operator-tugas-detail' => 'operator/tugas/detail.php',
    'operator-tugas-edit' => 'operator/tugas/edit.php',
    'operator-tugas-review' => 'operator/tugas/review.php',
    'operator-monitoring-realtime' => 'operator/monitoring/realtime.php',
    
    // Operator Assessment routes
    'operator-assessment-index' => 'operator/assessment/index.php',
    'operator-assessment-sumatip-list' => 'operator/assessment/sumatip/list.php',
    'operator-assessment-sumatip-create' => 'operator/assessment/sumatip/create.php',
    'operator-assessment-sumatip-detail' => 'operator/assessment/sumatip/detail.php',
    'operator-assessment-bank-soal-list' => 'operator/assessment/bank_soal/list.php',
    'operator-assessment-bank-soal-approve' => 'operator/assessment/bank_soal/approve.php',
    'operator-assessment-bank-soal-create-assessment' => 'operator/assessment/bank_soal/create_assessment.php',
    'operator-assessment-berita-acara-generate' => 'operator/assessment/berita_acara/generate.php',
    'operator-assessment-berita-acara-detail' => 'operator/assessment/berita_acara/detail.php',
    'operator-assessment-berita-acara-print' => 'operator/assessment/berita_acara/print.php',
    'operator-assessment-nilai-form' => 'operator/assessment/nilai/form.php',
    'operator-assessment-nilai-input' => 'operator/assessment/nilai/input.php',
    'operator-assessment-nilai-save' => 'operator/assessment/nilai/save.php',
    'operator-assessment-jadwal-list' => 'operator/assessment/jadwal/list.php',
    'operator-assessment-jadwal-create' => 'operator/assessment/jadwal/create.php',
    'operator-assessment-jadwal-susulan' => 'operator/assessment/jadwal/susulan.php',
    'operator-assessment-jadwal-deactivate' => 'operator/assessment/jadwal/deactivate.php',
    'operator-assessment-absensi-list' => 'operator/assessment/absensi/list.php',
    'operator-assessment-absensi-report' => 'operator/assessment/absensi/report.php',
    'operator-assessment-manage-guru-soal' => 'operator/assessment/manage_guru_soal.php',
    'operator-penilaian-list' => 'operator/penilaian/list.php',
    'operator-ledger-nilai-manual' => 'operator/penilaian/form.php',
    'operator-ledger-nilai-export' => 'operator/penilaian/export.php',
    'operator-raport-list' => 'operator/raport/list.php',
    'operator-verifikasi-dokumen-index' => 'operator/verifikasi_dokumen/index.php',
    'operator-verifikasi-dokumen-detail' => 'operator/verifikasi_dokumen/detail.php',
    'operator-raport-print' => 'operator/raport/print.php',
    'operator-raport-detail' => 'operator/raport/detail.php',
    
    // Guru Assessment Soal routes
    'guru-assessment-soal-create' => 'guru/assessment/soal/create.php',
    'guru-assessment-soal-list' => 'guru/assessment/soal/list.php',
    
    // Guru Absensi routes
    'guru-absensi-list' => 'guru/absensi/list.php',
    'guru-absensi-export' => 'guru/absensi/export.php',
    'guru-absensi-retake' => 'guru/absensi/retake.php',
];

// Check if route exists
if (isset($routes[$path])) {
    $file = __DIR__ . '/' . $routes[$path];
    if (file_exists($file)) {
        try {
            // Include the file - output will be buffered automatically by config.php
            // Output buffer was started in config.php to prevent header errors
            require_once $file;
            
            // Ensure output is sent - flush all output buffers
            // Don't use ob_end_flush() as it might cause issues if called multiple times
            // Instead, let PHP automatically flush on script end
            // But ensure we don't have multiple output buffers
            $buffer_level = ob_get_level();
            if ($buffer_level > 1) {
                // Multiple buffers - flush all but keep the outermost one
                while (ob_get_level() > 1) {
                    ob_end_flush();
                }
            }
            // The outermost buffer will be flushed automatically by PHP
            
            // Exit - script is done
            exit;
        } catch (Throwable $e) {
            // Clear output buffer only on error
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            error_log("Router error loading file $file: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            die("
            <!DOCTYPE html>
            <html>
            <head>
                <title>Error Loading Page</title>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                    .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
                    .error h2 { margin-top: 0; }
                    .error pre { background: #fff; padding: 10px; border-radius: 3px; overflow-x: auto; margin-top: 10px; }
                    .error small { display: block; margin-top: 10px; color: #856404; }
                </style>
            </head>
            <body>
                <div class='error'>
                    <h2>Error Loading Page</h2>
                    <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line: " . $e->getLine() . ")</p>
                    <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
                    <small>Please check the error log for more details.</small>
                </div>
            </body>
            </html>
            ");
        }
    } else {
        // File not found
        http_response_code(404);
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <title>File Not Found</title>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
                .error { background: #fff3cd; color: #856404; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
            </style>
        </head>
        <body>
            <div class='error'>
                <h2>File Not Found</h2>
                <p>File tidak ditemukan: " . htmlspecialchars($file) . "</p>
                <p>Route: " . htmlspecialchars($path) . "</p>
            </div>
        </body>
        </html>
        ");
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

// Log 404 for debugging (optional - remove in production)
error_log("Router 404: Path not found - '$path' (REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . ", SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . ")");

// 404 Not Found
http_response_code(404);

// Debug info (remove in production or make it conditional)
$debug_mode = (isset($_GET['debug']) && $_GET['debug'] === '1');
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
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
            padding: 20px;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        h1 { color: #dc3545; margin: 0; font-size: 72px; }
        h2 { color: #333; margin-top: 20px; }
        p { color: #666; margin: 10px 0; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .debug-info {
            text-align: left;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 12px;
            font-family: monospace;
            display: <?php echo $debug_mode ? 'block' : 'none'; ?>;
        }
        .debug-toggle {
            margin-top: 20px;
            font-size: 14px;
        }
        .routes-list {
            text-align: left;
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .routes-list ul {
            list-style: none;
            padding: 0;
        }
        .routes-list li {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .routes-list a {
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>404</h1>
        <h2>Halaman tidak ditemukan</h2>
        <p>URL yang diminta: <strong><?php echo htmlspecialchars($path ?: '(empty)'); ?></strong></p>
        
        <?php if ($debug_mode): ?>
        <div class="debug-info">
            <strong>Debug Information:</strong><br>
            REQUEST_URI: <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A'); ?><br>
            SCRIPT_NAME: <?php echo htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A'); ?><br>
            DOCUMENT_ROOT: <?php echo htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A'); ?><br>
            Detected Base Path: <?php echo htmlspecialchars($base_path ?? 'N/A'); ?><br>
            Final Path: <?php echo htmlspecialchars($path ?: '(empty)'); ?><br>
            Router.php exists: <?php echo file_exists(__DIR__ . '/router.php') ? 'YES' : 'NO'; ?><br>
            Siswa login.php exists: <?php echo file_exists(__DIR__ . '/siswa/login.php') ? 'YES' : 'NO'; ?><br>
        </div>
        <?php endif; ?>
        
        <div class="debug-toggle">
            <a href="?debug=1">Tampilkan Debug Info</a> | 
            <a href="<?php echo base_url('login'); ?>">Coba Login</a>
        </div>
        
        <div style="margin-top: 30px;">
            <p><strong>Halaman yang tersedia:</strong></p>
            <div class="routes-list">
                <ul>
                    <li><a href="<?php echo base_url('login'); ?>">Login</a></li>
                    <li><a href="<?php echo base_url('admin-login'); ?>">Admin Login</a></li>
                    <li><a href="<?php echo base_url('guru-login'); ?>">Guru Login</a></li>
                    <li><a href="<?php echo base_url('operator-login'); ?>">Operator Login</a></li>
                    <li><a href="<?php echo base_url(''); ?>">Halaman Utama</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>

