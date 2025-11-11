<?php
/**
 * Security Check API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Rate limiting for security checks
$user_id = $_SESSION['user_id'] ?? 0;
$ip_address = get_client_ip();
$rate_limit = check_rate_limit('security_check', $user_id, 120, 60); // 120 requests per minute
$ip_rate_limit = check_ip_rate_limit('security_check', $ip_address, 200, 60); // 200 requests per minute per IP

if (!$rate_limit['allowed'] || !$ip_rate_limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => 'Rate limit exceeded',
        'rate_limit' => true
    ]);
    exit;
}

$sesi_id = intval($_POST['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? 0);
$action = sanitize($_POST['action'] ?? '');
$description = sanitize($_POST['description'] ?? '');

if (!$sesi_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

global $pdo;

try {
    // Handle specific actions
    if ($action === 'check_ip') {
        // Check if IP address has changed
        $current_ip = get_client_ip();
        
        // Get ujian_id from sesi if not provided
        if (!$ujian_id) {
            $stmt = $pdo->prepare("SELECT id_ujian FROM sesi_ujian WHERE id = ?");
            $stmt->execute([$sesi_id]);
            $sesi = $stmt->fetch();
            $ujian_id = $sesi['id_ujian'] ?? 0;
        }
        
        // Get initial IP from nilai record
        $stmt = $pdo->prepare("SELECT ip_address FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        $ip_changed = false;
        if ($nilai && $nilai['ip_address'] && $nilai['ip_address'] !== $current_ip) {
            $ip_changed = true;
            log_security_event(
                $_SESSION['user_id'],
                $sesi_id,
                'ip_change',
                "IP changed from {$nilai['ip_address']} to {$current_ip}",
                true
            );
        }
        
        echo json_encode([
            'success' => true,
            'ip_changed' => $ip_changed,
            'current_ip' => $current_ip
        ]);
        exit;
    }
    
    if ($action === 'validate_session') {
        // Get ujian_id from sesi if not provided
        if (!$ujian_id) {
            $stmt = $pdo->prepare("SELECT id_ujian FROM sesi_ujian WHERE id = ?");
            $stmt->execute([$sesi_id]);
            $sesi = $stmt->fetch();
            $ujian_id = $sesi['id_ujian'] ?? 0;
        }
        
        // Validate session
        $validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
        
        // Also check if session is still active
        $stmt = $pdo->prepare("SELECT status FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        $valid = $validation['valid'] && $nilai && $nilai['status'] === 'sedang_mengerjakan';
        
        echo json_encode([
            'success' => true,
            'valid' => $valid
        ]);
        exit;
    }
    
    // Log security event if action provided
    if ($action && $ujian_id) {
        log_security_event(
            $_SESSION['user_id'],
            $sesi_id,
            $action,
            $description,
            true // is_suspicious
        );
        
        // Check anti contek settings
        $settings = get_anti_contek_settings($ujian_id);
        if ($settings && $settings['enabled']) {
            // Log anti contek event
            log_anti_contek_event($ujian_id, $_SESSION['user_id'], $sesi_id, $action, $description);
            
            // Check warning count
            $stmt = $pdo->prepare("SELECT warning_count FROM nilai 
                                  WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
            $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
            $nilai = $stmt->fetch();
            
            if ($nilai && $nilai['warning_count'] >= $settings['max_warnings']) {
                // Mark as fraud and require relogin
                // Reset all answers (not lock) for fraud
                $pdo->beginTransaction();
                
                // Delete all answers (reset) - not lock, but reset
                $stmt = $pdo->prepare("DELETE FROM jawaban_siswa 
                                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
                $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
                
                // Mark as fraud (but don't lock answers - they're reset)
                $reason = "Terlalu banyak pelanggaran keamanan (warning count: {$nilai['warning_count']})";
                $stmt = $pdo->prepare("UPDATE nilai 
                                      SET is_suspicious = 1, 
                                          is_fraud = 1,
                                          fraud_reason = ?,
                                          fraud_detected_at = NOW(),
                                          requires_relogin = 1,
                                          answers_locked = 0
                                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
                $stmt->execute([$reason, $sesi_id, $ujian_id, $_SESSION['user_id']]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => false,
                    'fraud' => true,
                    'requires_logout' => true,
                    'message' => 'Fraud terdeteksi. Anda harus login ulang. Waktu ujian terus berjalan.'
                ]);
                exit;
            }
        }
    }
    
    // Validate session
    $validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
    if (!$validation['valid']) {
        echo json_encode([
            'success' => false,
            'auto_submit' => true,
            'message' => $validation['message']
        ]);
        exit;
    }
    
    // Check device fingerprint
    $fingerprint = $_SESSION['device_fingerprint'] ?? null;
    if ($fingerprint && !is_device_trusted($_SESSION['user_id'], $fingerprint)) {
        // Log suspicious device
        log_security_event(
            $_SESSION['user_id'],
            $sesi_id,
            'untrusted_device',
            'Access from untrusted device',
            true
        );
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Security check error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}

