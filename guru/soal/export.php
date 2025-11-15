<?php
/**
 * Export Soal ke Excel - Guru
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

// Get soal
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$ujian_id]);
$soal_list = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Soal_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $ujian['judul']) . '_' . date('YmdHis') . '.csv"');

// Output CSV
echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
echo "Tipe Soal,Pertanyaan,Opsi A,Opsi B,Opsi C,Opsi D,Opsi E,Kunci Jawaban,Bobot\n";

foreach ($soal_list as $soal) {
    $opsi = $soal['opsi_json'] ? json_decode($soal['opsi_json'], true) : [];
    
    $row = [
        '"' . str_replace('"', '""', $soal['tipe_soal']) . '"',
        '"' . str_replace('"', '""', $soal['pertanyaan']) . '"',
        '"' . str_replace('"', '""', $opsi['A'] ?? '') . '"',
        '"' . str_replace('"', '""', $opsi['B'] ?? '') . '"',
        '"' . str_replace('"', '""', $opsi['C'] ?? '') . '"',
        '"' . str_replace('"', '""', $opsi['D'] ?? '') . '"',
        '"' . str_replace('"', '""', $opsi['E'] ?? '') . '"',
        '"' . str_replace('"', '""', $soal['kunci_jawaban'] ?? '') . '"',
        $soal['bobot']
    ];
    echo implode(',', $row) . "\n";
}

exit;








