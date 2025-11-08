<?php
/**
 * Mark Ragu-Ragu API
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
$soal_id = intval($_POST['soal_id'] ?? 0);
$status = sanitize($_POST['status'] ?? '');

if (!$sesi_id || !$ujian_id || !$soal_id || !in_array($status, ['ragu', 'yakin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Get nilai ID
global $pdo;

try {
    $stmt = $pdo->prepare("SELECT id FROM nilai 
                          WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
    $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
    $nilai = $stmt->fetch();
    
    if (!$nilai) {
        echo json_encode(['success' => false, 'message' => 'Nilai tidak ditemukan']);
        exit;
    }
    
    // Check if ragu record exists
    $stmt = $pdo->prepare("SELECT id FROM ragu_ragu 
                          WHERE id_nilai = ? AND id_soal = ?");
    $stmt->execute([$nilai['id'], $soal_id]);
    $existing = $stmt->fetch();
    
    if ($status === 'ragu') {
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE ragu_ragu 
                                  SET status = 'ragu', waktu_mark = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$existing['id']]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO ragu_ragu (id_nilai, id_soal, status, waktu_mark) 
                                  VALUES (?, ?, 'ragu', NOW())");
            $stmt->execute([$nilai['id'], $soal_id]);
        }
        
        // Update jawaban_siswa
        $stmt = $pdo->prepare("UPDATE jawaban_siswa SET is_ragu = 1 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
    } else {
        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE ragu_ragu 
                                  SET status = 'yakin', waktu_unmark = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$existing['id']]);
        }
        
        // Update jawaban_siswa
        $stmt = $pdo->prepare("UPDATE jawaban_siswa SET is_ragu = 0 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? AND id_soal = ?");
        $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $soal_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Mark ragu error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan']);
}

