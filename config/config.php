<?php
/**
 * Main Configuration File
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * CATATAN SISTEM:
 * - Sistem menggunakan guru mata pelajaran (bukan guru kelas)
 * - Untuk SMP: Guru mengajar mata pelajaran tertentu ke berbagai kelas
 * - Satu guru bisa mengajar beberapa mata pelajaran
 * - Satu mata pelajaran bisa diajar oleh beberapa guru
 * - Guru bisa membuat ujian/PR/tugas untuk semua kelas yang relevan dengan mata pelajarannya
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistem Ujian dan Pekerjaan Rumah');
    define('APP_VERSION', '1.0.15');
    
    // Auto-detect APP_URL based on server environment
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                 !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
                 ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    
    // Detect base path from document root
    // Use parent directory (project root), not config directory
    $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $project_root = str_replace('\\', '/', dirname(__DIR__)); // Get parent of config folder (project root)
    $base_path = '';
    
    if ($document_root) {
        $document_root = str_replace('\\', '/', $document_root);
        // Get relative path from document root to project root
        if (strpos($project_root, $document_root) === 0) {
            $base_path = substr($project_root, strlen($document_root));
            $base_path = rtrim($base_path, '/');
        }
    }
    
    // Fallback: detect from REQUEST_URI or SCRIPT_NAME
    if (empty($base_path)) {
        // Try to detect from REQUEST_URI
        if (!empty($_SERVER['REQUEST_URI'])) {
            $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $request_uri = rtrim($request_uri, '/');
            // If REQUEST_URI is like /UJAN or /UJAN/, extract base path
            if (preg_match('#^/([^/]+)/?$#', $request_uri, $matches)) {
                $potential_base = '/' . $matches[1];
                // Check if this folder exists in document root
                if (is_dir($document_root . $potential_base . '/config')) {
                    $base_path = $potential_base;
                }
            } elseif (preg_match('#^/([^/]+)/#', $request_uri, $matches)) {
                $potential_base = '/' . $matches[1];
                if (is_dir($document_root . $potential_base . '/config')) {
                    $base_path = $potential_base;
                }
            }
        }
        
        // Last fallback: use SCRIPT_NAME
        if (empty($base_path) && !empty($_SERVER['SCRIPT_NAME'])) {
            $script_name = $_SERVER['SCRIPT_NAME'];
            // Remove /router.php or /index.php from script name to get base path
            $script_name = str_replace(['/router.php', '/index.php'], '', $script_name);
            $base_path = dirname($script_name);
            if ($base_path === '.' || $base_path === '/') {
                $base_path = '';
            } else {
                $base_path = rtrim($base_path, '/');
            }
        }
    }
    
    define('APP_URL', $protocol . '://' . $host . $base_path);
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
// Use Lax instead of Strict to allow redirects after login
ini_set('session.cookie_samesite', 'Lax');

// Start output buffering to prevent header errors
if (!ob_get_level()) {
    ob_start();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads');
define('UPLOAD_URL', APP_URL . '/assets/uploads');

// Upload directories
define('UPLOAD_SOAL', UPLOAD_PATH . '/soal');
define('UPLOAD_PR', UPLOAD_PATH . '/pr');
define('UPLOAD_PROFILE', UPLOAD_PATH . '/profile');
define('UPLOAD_VERIFIKASI', UPLOAD_PATH . '/verifikasi');

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_SOAL)) mkdir(UPLOAD_SOAL, 0755, true);
if (!file_exists(UPLOAD_PR)) mkdir(UPLOAD_PR, 0755, true);
if (!file_exists(UPLOAD_PROFILE)) mkdir(UPLOAD_PROFILE, 0755, true);
if (!file_exists(UPLOAD_VERIFIKASI)) mkdir(UPLOAD_VERIFIKASI, 0755, true);

// File upload settings
define('MAX_FILE_SIZE', 512000); // 500KB in bytes (for faster loading)
define('MAX_VIDEO_SIZE', 52428800); // 50MB in bytes for videos (deprecated - video disabled)
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime']); // Deprecated - video disabled
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']);
define('ALLOWED_SOAL_MEDIA_TYPES', ALLOWED_IMAGE_TYPES); // Only images, no videos

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 6);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Auto-save interval (in seconds)
define('AUTO_SAVE_INTERVAL', 30);

// Minimum submit minutes (default)
// Siswa harus menunggu minimal 3 menit setelah mulai ujian sebelum bisa submit
define('DEFAULT_MIN_SUBMIT_MINUTES', 3);

// Verifikasi Dokumen Settings
define('VERIFIKASI_MIN_FILE_SIZE', 102400); // 100KB in bytes (minimum size)
define('VERIFIKASI_MAX_FILE_SIZE', 204800); // 200KB in bytes (maximum size)
define('VERIFIKASI_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg']);
define('VERIFIKASI_MAX_UPLOAD_ULANG', 1); // Maksimal 1x upload ulang

// GitHub CLI Settings
define('USE_GITHUB_CLI', true); // Aktifkan GitHub CLI jika tersedia (akan fallback ke Git jika tidak tersedia)

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_GURU', 'guru');
define('ROLE_OPERATOR', 'operator');
define('ROLE_SISWA', 'siswa');

// Ujian status
define('UJIAN_DRAFT', 'draft');
define('UJIAN_PUBLISHED', 'published');
define('UJIAN_COMPLETED', 'completed');
define('UJIAN_CANCELLED', 'cancelled');

// Sesi status
define('SESI_DRAFT', 'draft');
define('SESI_AKTIF', 'aktif');
define('SESI_SELESAI', 'selesai');
define('SESI_DIBATALKAN', 'dibatalkan');

/**
 * Convert file path to clean URL
 * Example: admin/login.php -> admin-login
 *          siswa/ujian/list.php -> siswa-ujian-list
 */
function path_to_url($path) {
    // Remove .php extension
    $path = str_replace('.php', '', $path);
    
    // Remove leading slash
    $path = ltrim($path, '/');
    
    // Convert slashes to hyphens
    $path = str_replace('/', '-', $path);
    
    // Handle special cases
    $special_routes = [
        'admin_guru-login' => 'admin-login',
    ];
    
    if (isset($special_routes[$path])) {
        return $special_routes[$path];
    }
    
    return $path;
}

// Helper function to get base URL
function base_url($path = '') {
    // Separate path and query string
    $query_string = '';
    if (strpos($path, '?') !== false) {
        list($path, $query_string) = explode('?', $path, 2);
        $query_string = '?' . $query_string;
    }
    
    // If path contains .php or /, convert to clean URL
    if (strpos($path, '.php') !== false || (strpos($path, '/') !== false && strpos($path, 'http') === false && strpos($path, 'assets') === false)) {
        $path = path_to_url($path);
    }
    
    return APP_URL . '/' . ltrim($path, '/') . $query_string;
}

// Helper function to get asset URL
function asset_url($path = '') {
    return APP_URL . '/assets/' . ltrim($path, '/');
}

// Helper function to redirect
function redirect($url) {
    // Clear all output buffers first
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // If URL contains .php or /, convert to clean URL
    if (strpos($url, '.php') !== false || (strpos($url, '/') !== false && strpos($url, 'http') === false && strpos($url, 'assets') === false)) {
        $url = path_to_url($url);
    }
    
    $redirect_url = base_url($url);
    
    // Check if headers already sent
    if (headers_sent($file, $line)) {
        // Headers already sent - output JavaScript redirect as fallback
        echo "<!DOCTYPE html><html><head><title>Redirecting...</title><meta charset='UTF-8'>";
        echo "<script>window.location.href = '" . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . "'></noscript>";
        echo "</head><body>";
        echo "<p>Redirecting... <a href='" . htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8') . "'>Click here if you are not redirected</a></p>";
        echo "</body></html>";
        exit();
    }
    
    // Send redirect header with 302 status
    header("Location: " . $redirect_url, true, 302);
    exit();
}

// Helper function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// Helper function to require login
function require_login() {
    if (!is_logged_in()) {
        // Redirect to appropriate login based on current path
        $current_path = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($current_path, 'admin') !== false || strpos($current_path, 'guru') !== false || strpos($current_path, 'operator') !== false) {
            redirect('admin-login');
        } elseif (strpos($current_path, 'siswa') !== false) {
            redirect('login');
        } else {
            redirect('login');
        }
    }
}

// Helper function to require role
function require_role($roles) {
    require_login();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array($_SESSION['role'], $roles)) {
        redirect('');
    }
}

