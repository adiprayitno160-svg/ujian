<?php
/**
 * Deactivate Jadwal - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    redirect('operator-assessment-jadwal-list');
}

// Deactivate jadwal
try {
    $stmt = $pdo->prepare("UPDATE jadwal_assessment SET status = 'nonaktif' WHERE id = ?");
    $stmt->execute([$id]);
    
    log_activity('deactivate_jadwal', 'jadwal_assessment', $id);
    redirect('operator-assessment-jadwal-list?success=deactivated');
} catch (PDOException $e) {
    error_log("Deactivate jadwal error: " . $e->getMessage());
    redirect('operator-assessment-jadwal-list?error=deactivate_failed');
}



