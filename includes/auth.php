<?php
/**
 * Authentication Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Login user
 */
function login($username, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['logged_in'] = true;
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            return ['success' => true, 'user' => $user];
        } else {
            return ['success' => false, 'message' => 'Username atau password salah'];
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat login'];
    }
}

/**
 * Logout user
 */
function logout() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Get current logged in user data
 * Note: Renamed from get_current_user() to avoid conflict with PHP built-in function
 * PHP has a built-in get_current_user() function that returns the owner of the current PHP script
 */
function get_logged_in_user() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get logged in user error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user has permission
 */
function has_permission($permission) {
    if (!is_logged_in()) {
        return false;
    }
    
    $role = $_SESSION['role'];
    
    // Admin has all permissions
    if ($role === ROLE_ADMIN) {
        return true;
    }
    
    // Define permissions per role
    $permissions = [
        ROLE_GURU => ['manage_ujian', 'manage_soal', 'manage_pr', 'view_nilai', 'manage_sesi'],
        ROLE_OPERATOR => ['manage_sesi', 'assign_peserta', 'manage_token', 'view_all_ujian'],
        ROLE_SISWA => ['take_ujian', 'submit_pr', 'view_hasil']
    ];
    
    return isset($permissions[$role]) && in_array($permission, $permissions[$role]);
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Check session timeout
 */
function check_session_timeout() {
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            logout();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check if user has operator access
 * Operator access is a feature for guru, not a separate role
 */
function has_operator_access($user_id = null) {
    global $pdo;
    
    if ($user_id === null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        $user_id = $_SESSION['user_id'];
    }
    
    // Admin always has operator access
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        return true;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT is_operator FROM users WHERE id = ? AND role = 'guru'");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result && (bool)$result['is_operator'];
    } catch (PDOException $e) {
        error_log("Check operator access error: " . $e->getMessage());
        return false;
    }
}

