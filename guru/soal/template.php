<?php
/**
 * Download Template Import Soal
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$format = sanitize($_GET['format'] ?? 'csv');

if ($format === 'csv') {
    // Generate CSV template
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template_import_soal.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, [
        'Tipe Soal',
        'Pertanyaan',
        'Opsi A',
        'Opsi B',
        'Opsi C',
        'Opsi D',
        'Opsi E',
        'Kunci Jawaban',
        'Bobot'
    ]);
    
    // Example rows
    fputcsv($output, [
        'pilihan_ganda',
        'Apa ibukota Indonesia?',
        'Jakarta',
        'Bandung',
        'Surabaya',
        'Yogyakarta',
        '',
        'A',
        '1.0'
    ]);
    
    fputcsv($output, [
        'pilihan_ganda',
        '2 + 2 = ?',
        '3',
        '4',
        '5',
        '6',
        '',
        'B',
        '1.0'
    ]);
    
    fputcsv($output, [
        'benar_salah',
        'Jakarta adalah ibukota Indonesia',
        '',
        '',
        '',
        '',
        '',
        'Benar',
        '1.0'
    ]);
    
    fputcsv($output, [
        'isian_singkat',
        'Ibukota Indonesia adalah ...',
        '',
        '',
        '',
        '',
        '',
        'Jakarta, DKI Jakarta',
        '1.0'
    ]);
    
    fputcsv($output, [
        'esai',
        'Jelaskan sejarah kemerdekaan Indonesia!',
        '',
        '',
        '',
        '',
        '',
        '',
        '2.0'
    ]);
    
    fclose($output);
    exit;
    
} elseif ($format === 'word') {
    // Generate Word template
    $content = "TEMPLATE IMPORT SOAL\n";
    $content .= "===================\n\n";
    $content .= "Format yang didukung:\n";
    $content .= "Nomor. Pertanyaan?\n";
    $content .= "A. Opsi A\n";
    $content .= "B. Opsi B\n";
    $content .= "C. Opsi C\n";
    $content .= "D. Opsi D\n";
    $content .= "E. Opsi E (opsional)\n";
    $content .= "Kunci: A\n\n";
    $content .= "CONTOH:\n\n";
    $content .= "1. Apa ibukota Indonesia?\n";
    $content .= "A. Jakarta\n";
    $content .= "B. Bandung\n";
    $content .= "C. Surabaya\n";
    $content .= "D. Yogyakarta\n";
    $content .= "Kunci: A\n\n";
    $content .= "2. 2 + 2 = ?\n";
    $content .= "A. 3\n";
    $content .= "B. 4\n";
    $content .= "C. 5\n";
    $content .= "D. 6\n";
    $content .= "Kunci: B\n\n";
    $content .= "3. Jakarta adalah ibukota Indonesia\n";
    $content .= "A. Benar\n";
    $content .= "B. Salah\n";
    $content .= "Kunci: A\n\n";
    $content .= "4. Ibukota Indonesia adalah ...\n";
    $content .= "Kunci: Jakarta\n\n";
    $content .= "5. Jelaskan sejarah kemerdekaan Indonesia!\n";
    $content .= "Kunci: (opsional, untuk referensi)\n";
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="template_import_soal.txt"');
    header('Content-Length: ' . strlen($content));
    
    echo $content;
    exit;
}

redirect('guru/soal/import.php');





