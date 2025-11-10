<?php
/**
 * Save Answer API
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

$action = $_POST['action'] ?? 'save';
$sesi_id = intval($_POST['sesi_id'] ?? 0);
$ujian_id = intval($_POST['ujian_id'] ?? 0);
$soal_id = intval($_POST['soal_id'] ?? 0);

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
        $stmt = $pdo->prepare("SELECT id, is_ragu FROM jawaban_siswa 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
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
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Jawaban tersimpan',
            'last_saved_at' => date('H:i:s')
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



