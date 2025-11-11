<?php
/**
 * Auto Save API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$sesi_id = intval($_POST['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? 0);
$answers = json_decode($_POST['answers'] ?? '{}', true);

if (!$sesi_id || !$ujian_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Validate session
$validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
if (!$validation['valid']) {
    echo json_encode(['success' => false, 'message' => $validation['message']]);
    exit;
}

global $pdo;

try {
    // Check if answers are locked (only after submit/verification, not for fraud)
    $stmt = $pdo->prepare("SELECT answers_locked, is_fraud, status FROM nilai 
                          WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
    $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
    $nilai = $stmt->fetch();
    
    // Only block save if answers are locked AND exam is finished (not for fraud - fraud resets answers)
    if ($nilai && $nilai['answers_locked'] && $nilai['status'] === 'selesai') {
        // Exam finished and answers locked - cannot save
        echo json_encode([
            'success' => false, 
            'message' => 'Ujian sudah selesai. Jawaban sudah dikunci.',
            'locked' => true
        ]);
        exit;
    }
    
    // For fraud, answers are reset (not locked), so allow saving new answers
    $pdo->beginTransaction();
    
    foreach ($answers as $soal_id => $jawaban) {
        $soal_id = intval($soal_id);
        
        // Check if answer is locked (only after submit, not for fraud)
        $stmt = $pdo->prepare("SELECT id, is_locked FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        // Only skip if locked AND exam is finished (not for fraud)
        if ($existing && $existing['is_locked'] && $nilai && $nilai['status'] === 'selesai') {
            // Answer is locked and exam finished - skip update
            continue;
        }
        
        // Handle array answers (for checkbox)
        if (is_array($jawaban)) {
            $jawaban_json = json_encode($jawaban);
            $jawaban_value = null;
        } else {
            $jawaban_json = null;
            $jawaban_value = sanitize($jawaban);
        }
        
        if ($existing) {
            // Update (only if not locked or exam not finished)
            // Allow update if not locked, or if locked but exam still in progress (fraud case)
            if (!$existing['is_locked'] || ($nilai && $nilai['status'] !== 'selesai')) {
                $stmt = $pdo->prepare("UPDATE jawaban_siswa 
                                      SET jawaban = ?, jawaban_json = ?, last_saved_at = NOW() 
                                      WHERE id = ?");
                $stmt->execute([$jawaban_value, $jawaban_json, $existing['id']]);
            }
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
                $jawaban_value,
                $jawaban_json
            ]);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'last_saved_at' => date('H:i:s')
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Auto save error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}

