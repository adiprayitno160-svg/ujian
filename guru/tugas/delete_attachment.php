<?php
/**
 * Delete Tugas Attachment - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$attachment_id = intval($_GET['id'] ?? 0);
$tugas_id = intval($_GET['tugas_id'] ?? 0);

// Verify attachment belongs to tugas owned by this guru
$stmt = $pdo->prepare("SELECT ta.* FROM tugas_attachment ta
                      INNER JOIN tugas t ON ta.id_tugas = t.id
                      WHERE ta.id = ? AND t.id_guru = ?");
$stmt->execute([$attachment_id, $_SESSION['user_id']]);
$attachment = $stmt->fetch();

if (!$attachment) {
    header("Location: " . base_url('guru/tugas/edit.php?id=' . $tugas_id));
    exit;
}

// Delete file
$file_path = UPLOAD_PR . '/' . $attachment['file_path'];
if (file_exists($file_path)) {
    unlink($file_path);
}

// Delete from database
$stmt = $pdo->prepare("DELETE FROM tugas_attachment WHERE id = ?");
$stmt->execute([$attachment_id]);

log_activity('delete_tugas_attachment', 'tugas_attachment', $attachment_id);

header("Location: " . base_url('guru/tugas/edit.php?id=' . $tugas_id));
exit;




