<?php
/**
 * Download Template Excel untuk Import Peserta Didik
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Format: KLS, NISN, NIS, NAMA, L/P
 */

// Disable all output and error reporting first
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any existing output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Load config first (before any output)
require_once __DIR__ . '/../config/config.php';

// Start fresh output buffer AFTER config
ob_start();

// Load auth and functions
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check authentication
if (!is_logged_in()) {
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(401);
    }
    die('Unauthorized: Please login first.');
}

// Check role
$allowed_roles = ['admin', 'guru', 'operator'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(403);
    }
    die('Forbidden: You do not have permission to access this page.');
}

// Check session timeout
if (function_exists('check_session_timeout')) {
    check_session_timeout();
}

// Check PhpSpreadsheet availability
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    die('ERROR: PhpSpreadsheet library tidak ditemukan.<br>Silakan install melalui composer:<br><code>composer require phpoffice/phpspreadsheet</code><br><br>File yang dicari: ' . htmlspecialchars($vendor_autoload));
}

require_once $vendor_autoload;

// Verify PhpSpreadsheet is loaded
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    die('ERROR: PhpSpreadsheet class tidak ditemukan setelah require autoload.<br>Pastikan library sudah terinstall dengan benar.');
}

// Clear output buffer before headers
ob_end_clean();

// Check if headers already sent
if (headers_sent($file, $line)) {
    die("Cannot send headers - headers already sent in $file on line $line");
}

// Set headers for Excel download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Import_Peserta_Didik.xlsx"');
header('Cache-Control: max-age=0');
header('Pragma: public');
header('Expires: 0');
header('Content-Transfer-Encoding: binary');

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import');

// Header style
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

// Data style
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
        'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
    ]
];

// Set header row
$headers = ['KLS', 'NISN', 'NIS', 'NAMA', 'L/P'];
$sheet->setCellValue('A1', $headers[0]);
$sheet->setCellValue('B1', $headers[1]);
$sheet->setCellValue('C1', $headers[2]);
$sheet->setCellValue('D1', $headers[3]);
$sheet->setCellValue('E1', $headers[4]);

// Apply header style
$sheet->getStyle('A1:E1')->applyFromArray($headerStyle);
$sheet->getRowDimension(1)->setRowHeight(25);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(15); // KLS
$sheet->getColumnDimension('B')->setWidth(15); // NISN
$sheet->getColumnDimension('C')->setWidth(15); // NIS
$sheet->getColumnDimension('D')->setWidth(30); // NAMA
$sheet->getColumnDimension('E')->setWidth(10); // L/P

// Sample data rows
$sampleData = [
    ['VII A', '0129584731', '14841', 'Ahmad Fauzi', 'L'],
    ['VII B', '0129584732', '14842', 'Siti Nurhaliza', 'P']
];

$row = 2;
foreach ($sampleData as $data) {
    $sheet->setCellValue('A' . $row, $data[0]);
    $sheet->setCellValue('B' . $row, $data[1]);
    $sheet->setCellValue('C' . $row, $data[2]);
    $sheet->setCellValue('D' . $row, $data[3]);
    $sheet->setCellValue('E' . $row, $data[4]);
    
    // Apply data style
    $sheet->getStyle('A' . $row . ':E' . $row)->applyFromArray($dataStyle);
    $row++;
}

// Add data validation for L/P column
$validation = $sheet->getCell('E2')->getDataValidation();
$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
$validation->setAllowBlank(false);
$validation->setShowInputMessage(true);
$validation->setShowErrorMessage(true);
$validation->setShowDropDown(true);
$validation->setErrorTitle('Input error');
$validation->setError('Hanya boleh diisi dengan L atau P');
$validation->setPromptTitle('Pilih Jenis Kelamin');
$validation->setPrompt('Pilih L untuk Laki-laki atau P untuk Perempuan');
$validation->setFormula1('"L,P"');

// Apply validation to all data rows (rows 2-100)
for ($i = 2; $i <= 100; $i++) {
    $cell = $sheet->getCell('E' . $i);
    $cell->setDataValidation(clone $validation);
}

// Instructions sheet
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Petunjuk');
$instructionsSheet->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE IMPORT PESERTA DIDIK');
$instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$instructionsSheet->mergeCells('A1:E1');

$instructions = [
    'A3' => '1. Kolom KLS: Masukkan nama kelas (contoh: VII A, VII B, VIII A, dll)',
    'A4' => '2. Kolom NISN: Masukkan Nomor Induk Siswa Nasional (NISN)',
    'A5' => '   - NISN akan digunakan sebagai username untuk login',
    'A6' => '   - NISN harus unik (tidak boleh duplikat)',
    'A7' => '3. Kolom NIS: Masukkan Nomor Induk Siswa (NIS)',
    'A8' => '   - NIS akan digunakan sebagai password untuk login',
    'A9' => '   - NIS harus unik (tidak boleh duplikat)',
    'A10' => '4. Kolom NAMA: Masukkan nama lengkap siswa',
    'A11' => '5. Kolom L/P: Masukkan L untuk Laki-laki atau P untuk Perempuan',
    'A12' => '',
    'A13' => 'CATATAN PENTING:',
    'A14' => '- NISN dan NIS harus diisi di kolom terpisah',
    'A15' => '- Nama kelas harus sesuai dengan kelas yang sudah ada di sistem',
    'A16' => '- Jika kelas belum ada, silakan buat terlebih dahulu di menu Kelola Kelas',
    'A17' => '- Format file harus .xlsx (Excel 2007 atau lebih baru)',
    'A18' => '- Jangan mengubah header baris pertama',
    'A19' => '- Hapus baris contoh sebelum mengisi data',
];

foreach ($instructions as $cell => $text) {
    $instructionsSheet->setCellValue($cell, $text);
    if (strpos($cell, 'A9') !== false) {
        $instructionsSheet->getStyle($cell)->getFont()->setBold(true);
    }
}

$instructionsSheet->getColumnDimension('A')->setWidth(80);
$instructionsSheet->getRowDimension(1)->setRowHeight(30);

// Set active sheet back to template
$spreadsheet->setActiveSheetIndex(0);

// Write to output
try {
    // Ensure no output buffer
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Disable compression
    @ini_set('zlib.output_compression', 0);
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', 1);
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    
    exit(0);
} catch (Exception $e) {
    // Clear all buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Log error
    error_log("Error in manage_siswa_template.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // Send error header if not sent yet
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(500);
    }
    
    // Show user-friendly error message
    $error_msg = 'Error generating Excel file.';
    if (strpos($e->getMessage(), 'PhpSpreadsheet') !== false || strpos($e->getMessage(), 'autoload') !== false) {
        $error_msg .= '<br><br>PhpSpreadsheet library tidak ditemukan atau tidak terinstall dengan benar.<br>';
        $error_msg .= 'Silakan install melalui composer:<br>';
        $error_msg .= '<code>composer require phpoffice/phpspreadsheet</code>';
    } else {
        $error_msg .= '<br>Message: ' . htmlspecialchars($e->getMessage());
    }
    
    die($error_msg);
}

