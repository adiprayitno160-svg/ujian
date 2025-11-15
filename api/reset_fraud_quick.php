<?php
/**
 * Quick Reset Fraud Flags - API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Quick reset fraud flags for specific sesi_id
 * 
 * Usage: 
 * - GET: api/reset_fraud_quick.php?sesi_id=9
 * - POST: api/reset_fraud_quick.php with sesi_id in POST data
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Allow both GET and POST
$sesi_id = intval($_GET['sesi_id'] ?? $_POST['sesi_id'] ?? 0);

if (!$sesi_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Parameter sesi_id diperlukan'
    ]);
    exit;
}

global $pdo;

try {
    // Get sesi info
    $stmt = $pdo->prepare("SELECT id_ujian FROM sesi_ujian WHERE id = ?");
    $stmt->execute([$sesi_id]);
    $sesi = $stmt->fetch();
    
    if (!$sesi) {
        echo json_encode([
            'success' => false,
            'message' => 'Sesi tidak ditemukan'
        ]);
        exit;
    }
    
    $ujian_id = $sesi['id_ujian'];
    
    // Check if anti_contek is enabled
    $stmt = $pdo->prepare("SELECT anti_contek_enabled FROM ujian WHERE id = ?");
    $stmt->execute([$ujian_id]);
    $ujian = $stmt->fetch();
    $anti_contek_enabled = $ujian && ($ujian['anti_contek_enabled'] ?? 0);
    
    // Get all nilai records for this sesi that have fraud flags
    $stmt = $pdo->prepare("SELECT id_siswa, id_ujian, is_fraud, requires_relogin 
                          FROM nilai 
                          WHERE id_sesi = ? AND (is_fraud = 1 OR requires_relogin = 1)");
    $stmt->execute([$sesi_id]);
    $nilai_records = $stmt->fetchAll();
    
    if (empty($nilai_records)) {
        echo json_encode([
            'success' => true,
            'message' => 'Tidak ada fraud flags untuk sesi ini',
            'anti_contek_enabled' => (bool)$anti_contek_enabled,
            'sesi_id' => $sesi_id,
            'ujian_id' => $ujian_id
        ]);
        exit;
    }
    
    // Reset all fraud flags for this sesi
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE nilai 
                          SET requires_relogin = 0,
                              is_fraud = 0,
                              fraud_reason = NULL,
                              fraud_detected_at = NULL,
                              warning_count = 0,
                              is_suspicious = 0
                          WHERE id_sesi = ?");
    $stmt->execute([$sesi_id]);
    
    $affected_rows = $stmt->rowCount();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Berhasil mereset {$affected_rows} fraud flag(s) untuk sesi_id={$sesi_id}",
        'anti_contek_enabled' => (bool)$anti_contek_enabled,
        'sesi_id' => $sesi_id,
        'ujian_id' => $ujian_id,
        'affected_rows' => $affected_rows,
        'note' => $anti_contek_enabled ? 
            'Anti contek masih aktif. Pastikan untuk menonaktifkannya di pengaturan ujian jika tidak ingin fraud terdeteksi lagi.' : 
            'Anti contek sudah dinonaktifkan. Fraud flags telah direset.'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quick reset fraud error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}






