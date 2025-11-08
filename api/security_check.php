<?php
/**
 * Security Check API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
                // Auto-submit
                $stmt = $pdo->prepare("UPDATE nilai SET status = 'selesai', is_suspicious = 1 
                                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
                $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
                
                echo json_encode([
                    'success' => false,
                    'auto_submit' => true,
                    'message' => 'Terlalu banyak pelanggaran keamanan'
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

