<?php
/**
 * Delete PR Soal - Guru
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$soal_id = intval($_GET['id'] ?? 0);
$pr_id = intval($_GET['pr_id'] ?? 0);

if (!$soal_id || !$pr_id) {
    redirect('guru/pr/list.php');
}

// Verify ownership
$stmt = $pdo->prepare("SELECT ps.* FROM pr_soal ps
                      INNER JOIN pr p ON ps.id_pr = p.id
                      WHERE ps.id = ? AND p.id = ? AND p.id_guru = ?");
$stmt->execute([$soal_id, $pr_id, $_SESSION['user_id']]);
$soal = $stmt->fetch();

if (!$soal) {
    redirect('guru/pr/list.php');
}

try {
    $pdo->beginTransaction();
    
    // Delete media file if exists
    if (!empty($soal['gambar'])) {
        delete_soal_media($soal['gambar']);
    }
    
    // Delete soal (cascade will delete matching items if any)
    $stmt = $pdo->prepare("DELETE FROM pr_soal WHERE id = ?");
    $stmt->execute([$soal_id]);
    
    $pdo->commit();
    log_activity('delete_pr_soal', 'pr_soal', $soal_id);
    $_SESSION['success_message'] = 'Soal berhasil dihapus';
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete PR soal error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat menghapus soal';
}

redirect('guru/pr/soal.php?id=' . $pr_id);

