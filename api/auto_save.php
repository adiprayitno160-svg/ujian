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
    $pdo->beginTransaction();
    
    foreach ($answers as $soal_id => $jawaban) {
        $soal_id = intval($soal_id);
        
        // Handle array answers (for checkbox)
        if (is_array($jawaban)) {
            $jawaban = json_encode($jawaban);
        }
        
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE jawaban_siswa 
                                  SET jawaban = ?, jawaban_json = ?, last_saved_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$jawaban, is_array($jawaban) ? json_encode($jawaban) : null, $existing['id']]);
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
                is_array($jawaban) ? json_encode($jawaban) : null
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

