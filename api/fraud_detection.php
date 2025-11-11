<?php
/**
 * Fraud Detection API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Handle fraud detection dan force logout
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

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');
$sesi_id = intval($_POST['sesi_id'] ?? $_GET['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? $_GET['ujian_id'] ?? 0);
$reason = sanitize($_POST['reason'] ?? '');

global $pdo;

try {
    if ($action === 'mark_fraud') {
        // Mark as fraud and force logout
        if (!$sesi_id || !$ujian_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        // Get nilai record
        $stmt = $pdo->prepare("SELECT * FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            echo json_encode(['success' => false, 'message' => 'Nilai record not found']);
            exit;
        }
        
        // Reset all answers (for fraud detection)
        $pdo->beginTransaction();
        
        // Delete all answers (reset) - not lock, but reset
        $stmt = $pdo->prepare("DELETE FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        
        // Mark as fraud and set flag for force logout (but don't lock answers - they're reset)
        $stmt = $pdo->prepare("UPDATE nilai 
                              SET is_suspicious = 1, 
                                  is_fraud = 1,
                                  fraud_reason = ?,
                                  fraud_detected_at = NOW(),
                                  requires_relogin = 1,
                                  answers_locked = 0
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$reason, $sesi_id, $ujian_id, $_SESSION['user_id']]);
        
        // Log fraud event
        log_security_event(
            $_SESSION['user_id'],
            $sesi_id,
            'fraud_detected',
            $reason ?: 'Fraud detected',
            true
        );
        
        $pdo->commit();
        
        // Return response with logout flag
        echo json_encode([
            'success' => true,
            'fraud' => true,
            'requires_logout' => true,
            'message' => 'Fraud terdeteksi. Anda harus login ulang. Waktu ujian terus berjalan.'
        ]);
        exit;
    }
    
    if ($action === 'check_fraud') {
        // Check if user has fraud flag
        if (!$sesi_id || !$ujian_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT is_fraud, requires_relogin, fraud_reason, answers_locked 
                              FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        if ($nilai && ($nilai['is_fraud'] || $nilai['requires_relogin'])) {
            echo json_encode([
                'success' => true,
                'fraud' => true,
                'requires_logout' => true,
                'answers_locked' => $nilai['answers_locked'] ?? 0,
                'reason' => $nilai['fraud_reason'] ?? 'Fraud terdeteksi'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'fraud' => false,
                'requires_logout' => false
            ]);
        }
        exit;
    }
    
    if ($action === 'check_normal_disruption') {
        // Check if there's a normal disruption (connection lost, etc)
        // Answers should be locked but no fraud
        if (!$sesi_id || !$ujian_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT status, answers_locked, requires_token 
                              FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        if ($nilai && $nilai['status'] === 'sedang_mengerjakan' && $nilai['answers_locked'] && !$nilai['is_fraud']) {
            echo json_encode([
                'success' => true,
                'normal_disruption' => true,
                'requires_token' => $nilai['requires_token'] ?? 0,
                'message' => 'Jawaban sudah dikunci. Silakan login ulang dan minta token baru untuk melanjutkan.'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'normal_disruption' => false
            ]);
        }
        exit;
    }
    
    if ($action === 'lock_answers') {
        // Lock answers before logout (for normal disruption)
        if (!$sesi_id || !$ujian_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Lock all answers
        $stmt = $pdo->prepare("UPDATE jawaban_siswa 
                              SET is_locked = 1, locked_at = NOW() 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND is_locked = 0");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        
        // Mark nilai as requiring token for resume
        $stmt = $pdo->prepare("UPDATE nilai 
                              SET answers_locked = 1, 
                                  requires_token = 1,
                                  disruption_reason = ?
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$reason ?: 'Gangguan koneksi', $sesi_id, $ujian_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'answers_locked' => true,
            'message' => 'Jawaban berhasil dikunci'
        ]);
        exit;
    }
    
    if ($action === 'reset_answers') {
        // Reset all answers (for tab switch detection)
        if (!$sesi_id || !$ujian_id) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }
        
        $pdo->beginTransaction();
        
        // Delete all answers (reset)
        $stmt = $pdo->prepare("DELETE FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
        
        // Log the reset event
        log_security_event(
            $_SESSION['user_id'],
            $sesi_id,
            'answers_reset',
            $reason ?: 'Tab switch detected - answers reset',
            false
        );
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'answers_reset' => true,
            'message' => 'Semua jawaban telah di-reset karena terdeteksi beralih tab/window'
        ]);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fraud detection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}

