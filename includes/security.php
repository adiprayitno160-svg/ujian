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
 */
function log_anti_contek_event($ujian_id, $siswa_id, $sesi_id, $action_type, $description, $warning_level = 1) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO anti_contek_logs 
                              (id_ujian, id_siswa, id_sesi, action_type, description, warning_level) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ujian_id, $siswa_id, $sesi_id, $action_type, $description, $warning_level]);
        
        // Update warning count in nilai table
        $stmt = $pdo->prepare("UPDATE nilai SET warning_count = warning_count + 1 
                              WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
        $stmt->execute([$ujian_id, $siswa_id, $sesi_id]);
        
        // Check if max warnings reached
        $settings = get_anti_contek_settings($ujian_id);
        if ($settings) {
            $stmt = $pdo->prepare("SELECT warning_count FROM nilai 
                                  WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
            $stmt->execute([$ujian_id, $siswa_id, $sesi_id]);
            $nilai = $stmt->fetch();
            
            if ($nilai && $nilai['warning_count'] >= $settings['max_warnings']) {
                // Mark as suspicious and auto-submit
                $stmt = $pdo->prepare("UPDATE nilai SET is_suspicious = 1, status = 'selesai' 
                                      WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
                $stmt->execute([$ujian_id, $siswa_id, $sesi_id]);
                
                return ['auto_submit' => true, 'message' => 'Ujian otomatis diselesaikan karena terlalu banyak pelanggaran'];
            }
        }
        
        return ['auto_submit' => false];
    } catch (PDOException $e) {
        error_log("Log anti contek event error: " . $e->getMessage());
        return ['auto_submit' => false, 'error' => $e->getMessage()];
    }
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

