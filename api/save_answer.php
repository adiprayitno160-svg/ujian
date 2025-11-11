<?php
/**
 * Save Answer API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/answer_analysis.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

// Rate limiting
$rate_limit = check_rate_limit('save_answer', $_SESSION['user_id'], 60, 60); // 60 requests per minute
if (!$rate_limit['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => $rate_limit['message'] ?? 'Rate limit exceeded',
        'rate_limit' => true
    ]);
    exit;
}

$action = $_POST['action'] ?? 'save';
$sesi_id = intval($_POST['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? 0);
$soal_id = intval($_POST['soal_id'] ?? 0);
$time_since_page_load = isset($_POST['time_since_page_load']) ? floatval($_POST['time_since_page_load']) : null;

if (!$sesi_id || !$ujian_id || !$soal_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Validate session
$validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

// Server-side answer validation
$answer_validation = validate_answer_submission($sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id, $time_since_page_load);
if (!$answer_validation['valid']) {
    if (isset($answer_validation['suspicious']) && $answer_validation['suspicious']) {
        log_security_event($_SESSION['user_id'], $sesi_id, 'suspicious_answer_submission', 
            $answer_validation['message'], true);
    }
    echo json_encode(['success' => false, 'message' => $answer_validation['message']]);
    exit;
}

global $pdo;

try {
    if ($action === 'save') {
        $jawaban = $_POST['jawaban'] ?? '';
        
        // Handle array answers
        if (is_array($jawaban)) {
            $jawaban_json = json_encode($jawaban);
            $jawaban = null;
        } else {
            $jawaban = sanitize($jawaban);
            $jawaban_json = null;
        }
        
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id, is_ragu, jawaban as old_jawaban, jawaban_json as old_jawaban_json FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Track answer change (audit trail)
            if ($existing['old_jawaban'] !== $jawaban || $existing['old_jawaban_json'] !== $jawaban_json) {
                track_answer_change($sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id, 
                    $existing['old_jawaban'] ?? $existing['old_jawaban_json'], 
                    $jawaban ?? $jawaban_json);
            }
            
            // Update
            $stmt = $pdo->prepare("UPDATE jawaban_siswa 
                                  SET jawaban = ?, jawaban_json = ?, last_saved_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$jawaban, $jawaban_json, $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO jawaban_siswa 
                                  (id_sesi, id_ujian, id_siswa, id_soal, jawaban, jawaban_json, last_saved_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $sesi_id,
                $ujian_id,
                $_SESSION['user_id'],
                $soal_id,
                $jawaban,
                $jawaban_json
            ]);
            
            // Track timing if provided
            if ($time_since_page_load !== null) {
                track_answer_timing($sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id, 
                    intval($time_since_page_load));
            }
        }
        
        // Analyze answer timing for suspicious patterns
        $timing_analysis = analyze_answer_timing($sesi_id, $ujian_id, $_SESSION['user_id']);
        if ($timing_analysis['suspicious']) {
            log_security_event($_SESSION['user_id'], $sesi_id, 'suspicious_timing_pattern', 
                'Suspicious answer timing patterns: ' . implode(', ', $timing_analysis['patterns']), true);
        }
        
        // Analyze answer changes
        $change_analysis = analyze_answer_changes($sesi_id, $ujian_id, $_SESSION['user_id']);
        if ($change_analysis['suspicious']) {
            log_security_event($_SESSION['user_id'], $sesi_id, 'suspicious_change_pattern', 
                'Suspicious answer change patterns: ' . implode(', ', $change_analysis['patterns']), true);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Jawaban tersimpan',
            'last_saved_at' => date('H:i:s'),
            'rate_limit' => [
                'remaining' => $rate_limit['remaining'],
                'reset_time' => $rate_limit['reset_time']
            ]
        ]);
        
    } elseif ($action === 'toggle_ragu') {
        $is_ragu = intval($_POST['is_ragu'] ?? 0);
        
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE jawaban_siswa 
                                  SET is_ragu = ?, last_saved_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$is_ragu, $existing['id']]);
        } else {
            // Insert with ragu flag
            $stmt = $pdo->prepare("INSERT INTO jawaban_siswa 
                                  (id_sesi, id_ujian, id_siswa, id_soal, is_ragu, last_saved_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $sesi_id,
                $ujian_id,
                $_SESSION['user_id'],
                $soal_id,
                $is_ragu
            ]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $is_ragu ? 'Ditandai ragu-ragu' : 'Batal ragu-ragu',
            'is_ragu' => $is_ragu
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Save answer error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}



