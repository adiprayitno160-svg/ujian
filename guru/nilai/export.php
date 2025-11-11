<?php
/**
 * Export Nilai to Excel - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

// Get nilai
$stmt = $pdo->prepare("SELECT n.*, u.nama as nama_siswa, u.username, s.nama_sesi
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      LEFT JOIN sesi_ujian s ON n.id_sesi = s.id
                      WHERE n.id_ujian = ?
                      ORDER BY n.nilai DESC, u.nama ASC");
$stmt->execute([$ujian_id]);
$nilai_list = $stmt->fetchAll();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Nilai_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $ujian['judul']) . '_' . date('YmdHis') . '.csv"');

// Output CSV
echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
echo "No,Nama Siswa,Username,Sesi,Nilai,Status,Waktu Mulai,Waktu Selesai\n";

foreach ($nilai_list as $index => $nilai) {
    $row = [
        $index + 1,
        '"' . str_replace('"', '""', $nilai['nama_siswa']) . '"',
        '"' . str_replace('"', '""', $nilai['username']) . '"',
        '"' . str_replace('"', '""', $nilai['nama_sesi'] ?? '-') . '"',
        $nilai['nilai'] !== null ? number_format($nilai['nilai'], 2) : '-',
        '"' . str_replace('"', '""', ucfirst(str_replace('_', ' ', $nilai['status']))) . '"',
        '"' . format_date($nilai['waktu_mulai'], 'Y-m-d H:i:s') . '"',
        '"' . format_date($nilai['waktu_selesai'], 'Y-m-d H:i:s') . '"'
    ];
    echo implode(',', $row) . "\n";
}

exit;





