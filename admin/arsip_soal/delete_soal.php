<?php
/**
 * Delete Soal from Pool - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

global $pdo;

$soal_id = intval($_GET['id'] ?? 0);
$pool_id = intval($_GET['pool_id'] ?? 0);

if (!$soal_id || !$pool_id) {
    redirect('admin/arsip_soal/list.php');
}

try {
    // Delete soal from pool
    $stmt = $pdo->prepare("DELETE FROM arsip_soal_item WHERE id = ? AND id_arsip_soal = ?");
    $stmt->execute([$soal_id, $pool_id]);
    
    // Update total_soal
    $stmt = $pdo->prepare("UPDATE arsip_soal SET total_soal = (SELECT COUNT(*) FROM arsip_soal_item WHERE id_arsip_soal = ?) WHERE id = ?");
    $stmt->execute([$pool_id, $pool_id]);
    
    redirect('admin/arsip_soal/detail.php?id=' . $pool_id . '&success=soal_deleted');
} catch (PDOException $e) {
    error_log("Delete soal from pool error: " . $e->getMessage());
    redirect('admin/arsip_soal/detail.php?id=' . $pool_id . '&error=delete_failed');
}

