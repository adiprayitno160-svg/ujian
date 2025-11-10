<?php
/**
 * Maintenance Mode Helper
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Untuk live server - enable/disable maintenance mode
 */

define('MAINTENANCE_FILE', __DIR__ . '/../.maintenance');

/**
 * Enable maintenance mode
 */
function enable_maintenance_mode($message = 'Sistem sedang dalam maintenance. Silakan coba lagi beberapa saat lagi.') {
    $content = [
        'enabled' => true,
        'message' => $message,
        'enabled_at' => date('Y-m-d H:i:s'),
        'enabled_by' => (isset($_SESSION) && isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : 'system'
    ];
    
    return file_put_contents(MAINTENANCE_FILE, json_encode($content, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Disable maintenance mode
 */
function disable_maintenance_mode() {
    if (file_exists(MAINTENANCE_FILE)) {
        return unlink(MAINTENANCE_FILE);
    }
    return true;
}

/**
 * Check if maintenance mode is enabled
 */
function is_maintenance_mode() {
    if (!file_exists(MAINTENANCE_FILE)) {
        return false;
    }
    
    $content = @json_decode(file_get_contents(MAINTENANCE_FILE), true);
    return isset($content['enabled']) && $content['enabled'] === true;
}

/**
 * Get maintenance message
 */
function get_maintenance_message() {
    if (!is_maintenance_mode()) {
        return null;
    }
    
    $content = @json_decode(file_get_contents(MAINTENANCE_FILE), true);
    return $content['message'] ?? 'Sistem sedang dalam maintenance. Silakan coba lagi beberapa saat lagi.';
}

/**
 * Check and redirect if maintenance mode is enabled
 * Call this in header.php or index.php
 */
function check_maintenance_mode() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    // Check if maintenance mode is enabled
    if (!is_maintenance_mode()) {
        return; // Not in maintenance mode, continue normally
    }
    
    // Allow admin to access even during maintenance
    // Check if user is logged in and is admin (check session directly to avoid dependency)
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return; // Admin can access
    }
    
    // Allow access to login page and API endpoints
    $current_page = basename($_SERVER['PHP_SELF'] ?? '');
    $allowed_pages = ['login.php', 'index.php'];
    $allowed_dirs = ['api', 'admin_guru'];
    $current_dir = basename(dirname($_SERVER['PHP_SELF'] ?? ''));
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Allow access to login pages (admin-login, login, etc.)
    if (in_array($current_page, $allowed_pages) || 
        in_array($current_dir, $allowed_dirs) || 
        strpos($request_uri, 'login') !== false ||
        strpos($request_uri, 'admin-login') !== false) {
        return; // Allow login page and API
    }
    
    if (is_maintenance_mode()) {
        http_response_code(503);
        $message = get_maintenance_message();
        
        // Simple maintenance page
        echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .maintenance-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 500px;
        }
        .maintenance-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">🔧</div>
        <h1>Sistem Sedang Maintenance</h1>
        <p>' . htmlspecialchars($message) . '</p>
        <p style="margin-top: 20px; font-size: 14px; color: #999;">Silakan coba lagi beberapa saat lagi.</p>
    </div>
</body>
</html>';
        exit;
    }
}

