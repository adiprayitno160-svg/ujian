<?php
/**
 * Download Template Excel untuk Import Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    http_response_code(403);
    die('Access denied');
}

// Set headers untuk download CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_import_siswa.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Output CSV dengan BOM untuk Excel (agar bisa dibaca dengan benar di Excel)
echo "\xEF\xBB\xBF"; // UTF-8 BOM

// Create output stream
$output = fopen('php://output', 'w');

// Write header
fputcsv($output, ['NIS', 'Nama', 'Kelas'], ',');

// Write sample data
fputcsv($output, ['12345', 'Contoh Siswa 1', 'X IPA 1'], ',');
fputcsv($output, ['12346', 'Contoh Siswa 2', 'X IPA 2'], ',');
fputcsv($output, ['12347', 'Contoh Siswa 3', 'XI IPA 1'], ',');

// Add empty row for instruction
fputcsv($output, [], ',');
fputcsv($output, ['CATATAN:', '', ''], ',');
fputcsv($output, ['- NIS harus unik (tidak boleh duplikat)', '', ''], ',');
fputcsv($output, ['- Nama kelas harus sesuai dengan kelas yang sudah ada di sistem', '', ''], ',');
fputcsv($output, ['- NIS akan digunakan sebagai username dan password untuk login', '', ''], ',');

fclose($output);
exit;

