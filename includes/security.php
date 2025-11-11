<?php
/**
 * Security Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get client IP address
 */
function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get device info
 */
function get_device_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $device_info = [
        'user_agent' => $user_agent,
        'ip_address' => get_client_ip(),
        'browser' => get_browser_name($user_agent),
        'os' => get_os_name($user_agent),
        'device' => is_mobile($user_agent) ? 'mobile' : 'desktop'
    ];
    
    return json_encode($device_info);
}

/**
 * Get browser name from user agent
 */
function get_browser_name($user_agent) {
    if (strpos($user_agent, 'Chrome') !== false) return 'Chrome';
    if (strpos($user_agent, 'Firefox') !== false) return 'Firefox';
    if (strpos($user_agent, 'Safari') !== false) return 'Safari';
    if (strpos($user_agent, 'Edge') !== false) return 'Edge';
    if (strpos($user_agent, 'Opera') !== false) return 'Opera';
    return 'Unknown';
}

/**
 * Get OS name from user agent
 */
function get_os_name($user_agent) {
    if (strpos($user_agent, 'Windows') !== false) return 'Windows';
    if (strpos($user_agent, 'Mac') !== false) return 'macOS';
    if (strpos($user_agent, 'Linux') !== false) return 'Linux';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'iOS') !== false || strpos($user_agent, 'iPhone') !== false) return 'iOS';
    return 'Unknown';
}

/**
 * Check if device is mobile
 */
function is_mobile($user_agent) {
    return preg_match('/(android|iphone|ipad|mobile)/i', $user_agent);
}

/**
 * Generate device fingerprint
 */
function generate_device_fingerprint() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        get_client_ip()
    ];
    
    return hash('sha256', implode('|', $components));
}

/**
 * Register device fingerprint
 */
function register_device_fingerprint($user_id, $fingerprint, $device_info) {
    global $pdo;
    
    try {
        // Check if fingerprint exists
        $stmt = $pdo->prepare("SELECT id FROM device_fingerprint WHERE fingerprint = ? AND id_user = ?");
        $stmt->execute([$fingerprint, $user_id]);
        $existing = $stmt->fetch();
        
        $device_data = json_decode($device_info, true);
        
        if ($existing) {
            // Update last used
            $stmt = $pdo->prepare("UPDATE device_fingerprint SET last_used = NOW() WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            // Insert new fingerprint
            $stmt = $pdo->prepare("INSERT INTO device_fingerprint (id_user, fingerprint, device_name, browser, os) 
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user_id,
                $fingerprint,
                $device_data['device'] ?? 'Unknown',
                $device_data['browser'] ?? 'Unknown',
                $device_data['os'] ?? 'Unknown'
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Register device fingerprint error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if device is trusted
 */
function is_device_trusted($user_id, $fingerprint) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT is_trusted FROM device_fingerprint 
                              WHERE id_user = ? AND fingerprint = ?");
        $stmt->execute([$user_id, $fingerprint]);
        $device = $stmt->fetch();
        
        return $device ? (bool)$device['is_trusted'] : false;
    } catch (PDOException $e) {
        error_log("Check device trusted error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log security event
 */
function log_security_event($user_id, $sesi_id, $action, $description, $is_suspicious = false) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO security_logs 
                              (id_user, id_sesi, action, description, ip_address, user_agent, device_info, is_suspicious) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $sesi_id,
            $action,
            $description,
            get_client_ip(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            get_device_info(),
            $is_suspicious ? 1 : 0
        ]);
    } catch (PDOException $e) {
        error_log("Log security event error: " . $e->getMessage());
    }
}

/**
 * Get anti contek settings for ujian
 */
function get_anti_contek_settings($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM anti_contek_settings WHERE id_ujian = ?");
        $stmt->execute([$ujian_id]);
        $settings = $stmt->fetch();
        
        if (!$settings) {
            // Create default settings
            $stmt = $pdo->prepare("INSERT INTO anti_contek_settings 
                                  (id_ujian, enabled, detect_tab_switch, detect_copy_paste, detect_screenshot, 
                                   detect_multiple_device, detect_idle, max_warnings) 
                                  VALUES (?, 1, 1, 1, 1, 1, 1, 3)");
            $stmt->execute([$ujian_id]);
            
            $stmt = $pdo->prepare("SELECT * FROM anti_contek_settings WHERE id_ujian = ?");
            $stmt->execute([$ujian_id]);
            $settings = $stmt->fetch();
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Get anti contek settings error: " . $e->getMessage());
        return null;
    }
}

/**
 * Log anti contek event
 * DISABLED: Fitur Anti Contek telah dihapus
 */
function log_anti_contek_event($ujian_id, $siswa_id, $sesi_id, $action_type, $description, $warning_level = 1) {
    // Fitur Anti Contek telah dihapus - fungsi ini tidak melakukan apa-apa
    return ['auto_submit' => false, 'anti_contek_disabled' => true];
}

/**
 * Check for multiple device login
 */
function check_multiple_device($user_id, $sesi_id) {
    global $pdo;
    
    try {
        // Get current device fingerprint from session
        $current_fingerprint = $_SESSION['device_fingerprint'] ?? null;
        
        if (!$current_fingerprint) {
            return false;
        }
        
        // Check if user has active session with different device
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM session_activity sa
                              INNER JOIN device_fingerprint df ON sa.id_user = df.id_user
                              WHERE sa.id_user = ? AND sa.id_sesi = ? 
                              AND df.fingerprint != ? AND sa.timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute([$user_id, $sesi_id, $current_fingerprint]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Check multiple device error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate session for exam
 */
function validate_exam_session($sesi_id, $user_id) {
    global $pdo;
    
    try {
        // Check if sesi exists and is active
        $stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id = ? AND status = 'aktif'");
        $stmt->execute([$sesi_id]);
        $sesi = $stmt->fetch();
        
        if (!$sesi) {
            return ['valid' => false, 'message' => 'Sesi tidak ditemukan atau tidak aktif'];
        }
        
        // Check if user is assigned
        if (!is_user_assigned_to_sesi($user_id, $sesi_id)) {
            return ['valid' => false, 'message' => 'Anda tidak terdaftar pada sesi ini'];
        }
        
        // Check time
        $now = new DateTime();
        $waktu_mulai = new DateTime($sesi['waktu_mulai']);
        $waktu_selesai = new DateTime($sesi['waktu_selesai']);
        
        if ($now < $waktu_mulai) {
            return ['valid' => false, 'message' => 'Sesi belum dimulai'];
        }
        
        if ($now > $waktu_selesai) {
            return ['valid' => false, 'message' => 'Sesi sudah berakhir'];
        }
        
        return ['valid' => true, 'sesi' => $sesi];
    } catch (PDOException $e) {
        error_log("Validate exam session error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Terjadi kesalahan'];
    }
}

/**
 * Set exam mode in session (student is actively taking an exam)
 */
function set_exam_mode($sesi_id, $ujian_id) {
    $_SESSION['exam_mode'] = true;
    $_SESSION['exam_sesi_id'] = $sesi_id;
    $_SESSION['exam_ujian_id'] = $ujian_id;
    $_SESSION['exam_start_time'] = time();
}

/**
 * Clear exam mode from session (exam finished or cancelled)
 */
function clear_exam_mode($sesi_id = null) {
    $sesi_to_clear = $sesi_id ?? ($_SESSION['exam_sesi_id'] ?? null);
    
    // Clear exam mode session variables
    unset($_SESSION['exam_mode']);
    unset($_SESSION['exam_sesi_id']);
    unset($_SESSION['exam_ujian_id']);
    unset($_SESSION['exam_start_time']);
    
    // Clear on_exam_page flag (allow access to other pages)
    unset($_SESSION['on_exam_page']);
    
    // Clear token verification for this sesi if sesi_id is provided
    if ($sesi_to_clear) {
        $token_verified_key = 'token_verified_' . $sesi_to_clear;
        $token_id_key = 'token_id_' . $sesi_to_clear;
        unset($_SESSION[$token_verified_key]);
        unset($_SESSION[$token_id_key]);
    } else {
        // Clear all token verifications if no specific sesi_id
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, 'token_verified_') === 0 || strpos($key, 'token_id_') === 0) {
                unset($_SESSION[$key]);
            }
        }
    }
}

/**
 * Check if student is currently in exam mode
 */
function is_in_exam_mode() {
    if (!isset($_SESSION['exam_mode']) || !$_SESSION['exam_mode']) {
        return false;
    }
    
    // Verify that the exam session is still active
    global $pdo;
    try {
        $sesi_id = $_SESSION['exam_sesi_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if (!$sesi_id || !$user_id) {
            clear_exam_mode();
            return false;
        }
        
        // Check if nilai record exists and status is 'sedang_mengerjakan'
        $stmt = $pdo->prepare("SELECT * FROM nilai 
                              WHERE id_sesi = ? AND id_siswa = ? AND status = 'sedang_mengerjakan'");
        $stmt->execute([$sesi_id, $user_id]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            // Exam finished or doesn't exist - clear exam mode
            clear_exam_mode();
            return false;
        }
        
        // Check if exam session is still active
        $stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id = ? AND status = 'aktif'");
        $stmt->execute([$sesi_id]);
        $sesi = $stmt->fetch();
        
        if (!$sesi) {
            clear_exam_mode();
            return false;
        }
        
        // Check if time is still valid
        $now = new DateTime();
        $waktu_selesai = new DateTime($sesi['waktu_selesai']);
        
        if ($now > $waktu_selesai) {
            clear_exam_mode();
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Check exam mode error: " . $e->getMessage());
        clear_exam_mode();
        return false;
    }
}

/**
 * Get current exam session ID if in exam mode
 */
function get_current_exam_sesi_id() {
    if (is_in_exam_mode()) {
        return $_SESSION['exam_sesi_id'] ?? null;
    }
    return null;
}

/**
 * Check and redirect if student tries to access other pages while in exam mode
 * Call this function on student pages (except exam and submit pages)
 * 
 * SISTEM LOCK: Setelah klik mulai ujian, siswa TIDAK BISA kembali ke halaman siswa lainnya
 * kecuali halaman ujian itu sendiri. Jika terpaksa kembali karena masalah, perlu request token
 * untuk melanjutkan kembali.
 */
function check_exam_mode_restriction($allowed_pages = []) {
    // If not in exam mode, allow access
    if (!is_in_exam_mode()) {
        return true;
    }
    
    // Prevent redirect loops - check if we're already on an exam page
    // First check constant (set by exam pages) - most reliable
    if (defined('ON_EXAM_PAGE') && ON_EXAM_PAGE) {
        return true; // Already on exam page, allow access
    }
    
    // Also check session flag as backup
    if (isset($_SESSION['on_exam_page']) && $_SESSION['on_exam_page']) {
        return true; // Already on exam page, allow access
    }
    
    $script_name = $_SERVER['SCRIPT_NAME'] ?? '';
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Check if we're already on an exam page - if so, don't redirect (prevent loop)
    $is_exam_page = false;
    
    // Check script name (physical file path)
    if (strpos($script_name, '/ujian/take.php') !== false ||
        strpos($script_name, '/ujian/submit.php') !== false ||
        strpos($script_name, '/ujian/hasil.php') !== false ||
        strpos($script_name, '/ujian/review.php') !== false) {
        $is_exam_page = true;
    }
    
    // Check request URI (clean URLs and physical paths)
    if (!$is_exam_page) {
        if (strpos($request_uri, '/ujian/take') !== false ||
            strpos($request_uri, '/ujian/submit') !== false ||
            strpos($request_uri, '/ujian/hasil') !== false ||
            strpos($request_uri, '/ujian/review') !== false ||
            strpos($request_uri, 'siswa-ujian-take') !== false ||
            strpos($request_uri, 'siswa-ujian-submit') !== false ||
            strpos($request_uri, 'siswa-ujian-hasil') !== false ||
            strpos($request_uri, 'siswa-ujian-review') !== false) {
            $is_exam_page = true;
        }
    }
    
    // If already on exam page, allow access (prevent redirect loop)
    if ($is_exam_page) {
        return true;
    }
    
    // Get current page and full request URI
    $current_page = $_SERVER['PHP_SELF'] ?? '';
    $current_page = basename($current_page);
    
    // Pages that are always allowed even in exam mode (SANGAT TERBATAS)
    $always_allowed = [
        'take.php',           // Exam page (halaman ujian)
        'submit.php',         // Submit page (halaman submit)
        'hasil.php',          // Results page (halaman hasil)
        'logout.php',         // Logout (harus bisa logout)
    ];
    
    // API endpoints are allowed (for auto-save, etc.)
    $is_api = (strpos($request_uri, '/api/') !== false);
    
    // Check if current page is allowed
    $is_allowed = false;
    foreach ($always_allowed as $allowed) {
        if (strpos($current_page, $allowed) !== false) {
            $is_allowed = true;
            break;
        }
    }
    
    // Allow API endpoints
    if ($is_api) {
        $is_allowed = true;
    }
    
    // Check additional allowed pages (should be minimal)
    foreach ($allowed_pages as $allowed) {
        if (strpos($current_page, $allowed) !== false || strpos($request_uri, $allowed) !== false) {
            $is_allowed = true;
            break;
        }
    }
    
    // If not allowed, redirect back to exam with error message
    if (!$is_allowed) {
        $sesi_id = get_current_exam_sesi_id();
        if ($sesi_id) {
            // Double-check: if we're already on exam page (constant or session flag), don't redirect
            if (defined('ON_EXAM_PAGE') && ON_EXAM_PAGE) {
                return true; // Already on exam page, allow access
            }
            if (isset($_SESSION['on_exam_page']) && $_SESSION['on_exam_page']) {
                return true; // Already on exam page, allow access
            }
            
            // Check if student needs token to continue
            global $pdo;
            try {
                $stmt = $pdo->prepare("SELECT requires_token FROM nilai 
                                      WHERE id_sesi = ? AND id_siswa = ? AND status = 'sedang_mengerjakan'");
                $stmt->execute([$sesi_id, $_SESSION['user_id']]);
                $nilai = $stmt->fetch();
                
                if ($nilai && $nilai['requires_token']) {
                    // Student needs token to continue - redirect to exam page (which will show token request)
                    $_SESSION['error_message'] = 'Anda sedang dalam ujian. Untuk melanjutkan, Anda perlu memasukkan token yang telah diberikan.';
                } else {
                    // Normal case - just redirect to exam
                    $_SESSION['error_message'] = 'Anda sedang dalam ujian. Silakan selesaikan ujian terlebih dahulu sebelum mengakses halaman lain.';
                }
                
                // Redirect to exam page using clean URL to avoid routing issues
                // The constant ON_EXAM_PAGE will be set when take.php loads, preventing further redirects
                redirect('siswa-ujian-take?id=' . $sesi_id);
            } catch (PDOException $e) {
                error_log("Error checking exam restriction: " . $e->getMessage());
                $_SESSION['error_message'] = 'Anda sedang dalam ujian. Silakan selesaikan ujian terlebih dahulu.';
                redirect('siswa-ujian-take?id=' . $sesi_id);
            }
        } else {
            // If sesi_id is not available, clear exam mode
            clear_exam_mode();
        }
    }
    
    return $is_allowed;
}

