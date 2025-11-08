<?php
/**
 * Save PR Answer API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

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
$pr_id = intval($_POST['pr_id'] ?? 0);
$soal_id = intval($_POST['soal_id'] ?? 0);

if (!$pr_id || !$soal_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

global $pdo;

// Verify PR exists and student has access
$stmt = $pdo->prepare("SELECT p.* FROM pr p
                      INNER JOIN pr_kelas pk ON p.id = pk.id_pr
                      INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                      WHERE p.id = ? AND uk.id_user = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    echo json_encode(['success' => false, 'message' => 'PR tidak ditemukan atau tidak memiliki akses']);
    exit;
}

// Check deadline
$deadline = new DateTime($pr['deadline']);
$now = new DateTime();
if ($now > $deadline && !$pr['allow_edit_after_submit']) {
    echo json_encode(['success' => false, 'message' => 'Deadline sudah lewat']);
    exit;
}

// Check submission status
$stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    // Create submission
    $stmt = $pdo->prepare("INSERT INTO pr_submission (id_pr, id_siswa, status) VALUES (?, ?, 'draft')");
    $stmt->execute([$pr_id, $_SESSION['user_id']]);
    
    $stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
    $stmt->execute([$pr_id, $_SESSION['user_id']]);
    $submission = $stmt->fetch();
}

// Check if already submitted and not allowed to edit
if ($submission['status'] === 'sudah_dikumpulkan' && !$pr['allow_edit_after_submit']) {
    echo json_encode(['success' => false, 'message' => 'PR sudah disubmit dan tidak dapat diubah']);
    exit;
}

try {
    if ($action === 'save') {
        $jawaban = $_POST['jawaban'] ?? '';
        $jawaban = sanitize($jawaban);
        
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id, is_ragu FROM pr_jawaban 
                              WHERE id_pr = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$pr_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE pr_jawaban 
                                  SET jawaban = ?, status = 'draft', updated_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$jawaban, $existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO pr_jawaban 
                                  (id_pr, id_siswa, id_soal, jawaban, status) 
                                  VALUES (?, ?, ?, ?, 'draft')");
            $stmt->execute([$pr_id, $_SESSION['user_id'], $soal_id, $jawaban]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Jawaban tersimpan',
            'last_saved_at' => date('H:i:s')
        ]);
        
    } elseif ($action === 'toggle_ragu') {
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id, is_ragu FROM pr_jawaban 
                              WHERE id_pr = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$pr_id, $_SESSION['user_id'], $soal_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_ragu = $existing['is_ragu'] ? 0 : 1;
            // Update
            $stmt = $pdo->prepare("UPDATE pr_jawaban 
                                  SET is_ragu = ?, updated_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$new_ragu, $existing['id']]);
            
            echo json_encode([
                'success' => true,
                'is_ragu' => $new_ragu,
                'message' => $new_ragu ? 'Ditandai ragu-ragu' : 'Batal ragu-ragu'
            ]);
        } else {
            // Create with ragu
            $stmt = $pdo->prepare("INSERT INTO pr_jawaban 
                                  (id_pr, id_siswa, id_soal, is_ragu, status) 
                                  VALUES (?, ?, ?, 1, 'draft')");
            $stmt->execute([$pr_id, $_SESSION['user_id'], $soal_id]);
            
            echo json_encode([
                'success' => true,
                'is_ragu' => 1,
                'message' => 'Ditandai ragu-ragu'
            ]);
        }
        
    } elseif ($action === 'submit') {
        // Check max attempts
        if ($pr['max_attempts'] && $submission['attempt_count'] >= $pr['max_attempts']) {
            echo json_encode(['success' => false, 'message' => 'Anda sudah mencapai batas maksimal percobaan']);
            exit;
        }
        
        // Update all answers to submitted
        $stmt = $pdo->prepare("UPDATE pr_jawaban 
                              SET status = 'submitted' 
                              WHERE id_pr = ? AND id_siswa = ?");
        $stmt->execute([$pr_id, $_SESSION['user_id']]);
        
        // Update submission
        $new_status = ($now > $deadline) ? 'terlambat' : 'sudah_dikumpulkan';
        $stmt = $pdo->prepare("UPDATE pr_submission 
                              SET status = ?, waktu_submit = NOW(), attempt_count = attempt_count + 1 
                              WHERE id = ?");
        $stmt->execute([$new_status, $submission['id']]);
        
        log_activity('submit_pr', 'pr_submission', $pr_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'PR berhasil disubmit',
            'status' => $new_status
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Save PR answer error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat menyimpan']);
}

