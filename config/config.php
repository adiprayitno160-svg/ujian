<?php
/**
 * Main Configuration File
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

// Prevent direct access
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistem Ujian dan Pekerjaan Rumah');
    define('APP_VERSION', '1.0.0');
    define('APP_URL', 'http://localhost/UJAN');
}

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');

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

// Create upload directories if they don't exist
if (!file_exists(UPLOAD_SOAL)) mkdir(UPLOAD_SOAL, 0755, true);
if (!file_exists(UPLOAD_PR)) mkdir(UPLOAD_PR, 0755, true);
if (!file_exists(UPLOAD_PROFILE)) mkdir(UPLOAD_PROFILE, 0755, true);

// File upload settings
define('MAX_FILE_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip']);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 6);
define('CSRF_TOKEN_NAME', 'csrf_token');

// Pagination
define('ITEMS_PER_PAGE', 20);

// Auto-save interval (in seconds)
define('AUTO_SAVE_INTERVAL', 30);

// Minimum submit minutes (default)
define('DEFAULT_MIN_SUBMIT_MINUTES', 0);

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
    // If URL contains .php or /, convert to clean URL
    if (strpos($url, '.php') !== false || (strpos($url, '/') !== false && strpos($url, 'http') === false && strpos($url, 'assets') === false)) {
        $url = path_to_url($url);
    }
    
    header("Location: " . base_url($url));
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
        if (strpos($current_path, 'admin') !== false || strpos($current_path, 'guru') !== false) {
            redirect('admin-login');
        } elseif (strpos($current_path, 'siswa') !== false) {
            redirect('siswa-login');
        } else {
            redirect('siswa-login');
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

