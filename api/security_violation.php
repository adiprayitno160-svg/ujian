<?php
/**
 * API: Security Violation Handler
 * Handle security violations by logging out and redirecting to login
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode([
        'success' => true, 
        'redirect' => true,
        'url' => base_url('siswa/login.php?violation=1')
    ]);
    exit;
}

$violation_type = sanitize($_POST['violation_type'] ?? '');
$reason = sanitize($_POST['reason'] ?? '');
$sesi_id = intval($_POST['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? 0);

// Get user info
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Log the violation
try {
    if ($sesi_id > 0) {
        // Log security event
        log_security_event($user_id, $sesi_id, 'security_violation', $reason, true);
        
        // Log anti contek event if ujian_id is provided and anti_contek is enabled
        if ($ujian_id > 0) {
            // Check if anti_contek is enabled for this ujian
            $stmt = $pdo->prepare("SELECT anti_contek_enabled FROM ujian WHERE id = ?");
            $stmt->execute([$ujian_id]);
            $ujian = $stmt->fetch();
            
            // Only log anti contek event if anti_contek is enabled
            if ($ujian && ($ujian['anti_contek_enabled'] ?? 0)) {
                log_anti_contek_event(
                    $ujian_id, 
                    $user_id, 
                    $sesi_id, 
                    $violation_type, 
                    $reason, 
                    3 // High warning level
                );
            }
            
            // Check if this is a cheating violation (not just an error)
            $is_cheating = in_array($violation_type, [
                'tab_switch', 
                'window_blur', 
                'multiple_windows', 
                'developer_tools', 
                'copy_paste', 
                'screenshot',
                'multiple_device',
                'automation_tool',
                'ip_change',
                'security_violation'
            ]);
            
            global $pdo;
            
            if ($is_cheating) {
                // Cheating detected: Delete all answers and mark as suspicious
                // Delete jawaban_siswa records
                $stmt = $pdo->prepare("DELETE FROM jawaban_siswa 
                                      WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
                $stmt->execute([$ujian_id, $user_id, $sesi_id]);
                
                // Mark nilai as suspicious, set status to selesai, and clear nilai
                $stmt = $pdo->prepare("UPDATE nilai 
                                      SET is_suspicious = 1, 
                                          status = 'selesai',
                                          nilai = 0,
                                          warning_count = warning_count + 1,
                                          updated_at = NOW()
                                      WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
                $stmt->execute([$ujian_id, $user_id, $sesi_id]);
            } else {
                // Error case (idle_timeout, browser_close, etc.): Keep answers but mark as completed
                // Mark nilai as suspicious and set status to completed, but keep answers
                $stmt = $pdo->prepare("UPDATE nilai 
                                      SET is_suspicious = 0, 
                                          status = 'selesai',
                                          warning_count = warning_count + 1,
                                          updated_at = NOW()
                                      WHERE id_ujian = ? AND id_siswa = ? AND id_sesi = ?");
                $stmt->execute([$ujian_id, $user_id, $sesi_id]);
            }
        }
    }
} catch (Exception $e) {
    error_log("Security violation logging error: " . $e->getMessage());
}

// Clear exam mode (this will also clear token verification)
if (function_exists('clear_exam_mode')) {
    clear_exam_mode($sesi_id);
}

// Also clear all token verifications for this sesi
if ($sesi_id > 0) {
    $token_verified_key = 'token_verified_' . $sesi_id;
    $token_id_key = 'token_id_' . $sesi_id;
    unset($_SESSION[$token_verified_key]);
    unset($_SESSION[$token_id_key]);
}

// Logout user (this will clear all session data including token verifications)
logout();

// Return redirect URL based on role
$login_url = '';
if ($role === 'siswa') {
    $login_url = base_url('siswa/login.php?violation=1&reason=' . urlencode($reason));
} elseif ($role === 'guru') {
    $login_url = base_url('guru/login.php?violation=1&reason=' . urlencode($reason));
} elseif ($role === 'operator') {
    $login_url = base_url('operator/login.php?violation=1&reason=' . urlencode($reason));
} else {
    $login_url = base_url('index.php?violation=1&reason=' . urlencode($reason));
}

echo json_encode([
    'success' => true,
    'redirect' => true,
    'url' => $login_url,
    'message' => 'Pelanggaran keamanan terdeteksi. Anda telah di-logout.'
]);

