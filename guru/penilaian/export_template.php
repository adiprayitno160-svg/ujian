<?php
/**
 * Export Template Penilaian Manual - Excel dengan Nama Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$id_mapel = intval($_GET['id_mapel'] ?? 0);
$id_kelas = intval($_GET['id_kelas'] ?? 0);
$tahun_ajaran = sanitize($_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
$semester = sanitize($_GET['semester'] ?? 'ganjil');

if (!$id_mapel || !$id_kelas) {
    $_SESSION['error'] = 'Mata pelajaran dan kelas harus dipilih';
    redirect('guru-penilaian-list');
}

// Get mapel info
$stmt = $pdo->prepare("SELECT * FROM mapel WHERE id = ?");
$stmt->execute([$id_mapel]);
$mapel = $stmt->fetch();

// Get kelas info
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
$stmt->execute([$id_kelas]);
$kelas = $stmt->fetch();

// Get siswa list
$stmt = $pdo->prepare("SELECT u.id, u.username as nis, u.nama
                      FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      WHERE u.role = 'siswa' 
                      AND u.status = 'active'
                      AND uk.id_kelas = ?
                      AND uk.tahun_ajaran = ?
                      AND uk.semester = ?
                      ORDER BY u.nama ASC");
$stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
$siswa_list = $stmt->fetchAll();

// Get existing nilai
$penilaian_data = [];
if (!empty($siswa_list)) {
    $siswa_ids = array_column($siswa_list, 'id');
    $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM penilaian_manual
                          WHERE id_guru = ?
                          AND id_mapel = ?
                          AND id_kelas = ?
                          AND tahun_ajaran = ?
                          AND semester = ?
                          AND id_siswa IN ($placeholders)");
    $params = array_merge([$_SESSION['user_id'], $id_mapel, $id_kelas, $tahun_ajaran, $semester], $siswa_ids);
    $stmt->execute($params);
    $penilaian_results = $stmt->fetchAll();
    
    foreach ($penilaian_results as $p) {
        $penilaian_data[$p['id_siswa']] = $p;
    }
}

// Set headers for Excel download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Template_Penilaian_' . escape($mapel['nama_mapel']) . '_' . escape($kelas['nama_kelas']) . '_' . $tahun_ajaran . '_' . $semester . '.xlsx"');
header('Cache-Control: max-age=0');

// Create Excel file using PhpSpreadsheet
$vendor_autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    $_SESSION['error'] = 'PhpSpreadsheet library tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet';
    redirect('guru-penilaian-list');
    exit;
}

require_once $vendor_autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set title
$sheet->setTitle('Penilaian Manual');

// Header info
$sheet->setCellValue('A1', 'TEMPLATE PENILAIAN MANUAL');
$sheet->mergeCells('A1:E1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A2', 'Mata Pelajaran:');
$sheet->setCellValue('B2', $mapel['nama_mapel']);
$sheet->setCellValue('A3', 'Kelas:');
$sheet->setCellValue('B3', $kelas['nama_kelas']);
$sheet->setCellValue('A4', 'Tahun Ajaran:');
$sheet->setCellValue('B4', $tahun_ajaran);
$sheet->setCellValue('A5', 'Semester:');
$sheet->setCellValue('B5', ucfirst($semester));

// Table headers
$sheet->setCellValue('A7', 'No');
$sheet->setCellValue('B7', 'NIS');
$sheet->setCellValue('C7', 'Nama Siswa');
$sheet->setCellValue('D7', 'Nilai UTS');
$sheet->setCellValue('E7', 'Nilai Akhir');
$sheet->setCellValue('F7', 'Predikat');
$sheet->setCellValue('G7', 'Keterangan');

// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$sheet->getStyle('A7:G7')->applyFromArray($headerStyle);

// Data rows
$row = 8;
if (empty($siswa_list)) {
    // No students - show message
    $sheet->setCellValue('A' . $row, 'Tidak ada siswa di kelas ini untuk semester yang dipilih');
    $sheet->mergeCells('A' . $row . ':G' . $row);
    $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
} else {
    foreach ($siswa_list as $index => $siswa) {
        $penilaian = $penilaian_data[$siswa['id']] ?? null;
        
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $siswa['nis']);
        $sheet->setCellValue('C' . $row, $siswa['nama']);
        $sheet->setCellValue('D' . $row, $penilaian ? number_format($penilaian['nilai_uts'], 2, '.', '') : '');
        $sheet->setCellValue('E' . $row, $penilaian ? number_format($penilaian['nilai_akhir'], 2, '.', '') : '');
        $sheet->setCellValue('F' . $row, $penilaian ? $penilaian['predikat'] : '');
        $sheet->setCellValue('G' . $row, $penilaian ? $penilaian['keterangan'] : '');
        
        // Style data rows
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
        ];
        $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($dataStyle);
        
        // Center align for number columns
        $sheet->getStyle('A' . $row . ':B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        $row++;
    }
}

// Set column widths
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(12);
$sheet->getColumnDimension('G')->setWidth(40);

// Add data validation for Nilai UTS and Nilai Akhir (0-100) - only if there are students
if (!empty($siswa_list) && $row > 8) {
    try {
        $validation = $sheet->getCell('D8')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_DECIMAL);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Nilai tidak valid');
        $validation->setError('Nilai harus antara 0-100');
        $validation->setPromptTitle('Input Nilai');
        $validation->setPrompt('Masukkan nilai antara 0-100');
        $validation->setFormula1('0');
        $validation->setFormula2('100');

        // Apply validation to all data rows
        $startRow = 8;
        $endRow = 8 + count($siswa_list);
        for ($i = $startRow; $i < $endRow; $i++) {
            $cellD = $sheet->getCell('D' . $i);
            $cellE = $sheet->getCell('E' . $i);
            if ($cellD && $cellE) {
                $cellD->setDataValidation(clone $validation);
                $cellE->setDataValidation(clone $validation);
            }
        }

        // Add data validation for Predikat (A, B, C, D)
        $predikatValidation = $sheet->getCell('F8')->getDataValidation();
        $predikatValidation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $predikatValidation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
        $predikatValidation->setAllowBlank(true);
        $predikatValidation->setShowInputMessage(true);
        $predikatValidation->setShowErrorMessage(true);
        $predikatValidation->setErrorTitle('Predikat tidak valid');
        $predikatValidation->setError('Predikat harus A, B, C, atau D');
        $predikatValidation->setPromptTitle('Pilih Predikat');
        $predikatValidation->setPrompt('Pilih predikat: A, B, C, atau D');
        $predikatValidation->setFormula1('"A,B,C,D"');

        // Apply validation to all data rows
        for ($i = $startRow; $i < $endRow; $i++) {
            $cellF = $sheet->getCell('F' . $i);
            if ($cellF) {
                $cellF->setDataValidation(clone $predikatValidation);
            }
        }
    } catch (Exception $e) {
        // Skip validation if there's an error (optional feature)
        error_log("Data validation error in export template: " . $e->getMessage());
    }
}

// Instructions sheet
$instructionsSheet = $spreadsheet->createSheet();
$instructionsSheet->setTitle('Petunjuk');
$instructionsSheet->setCellValue('A1', 'PETUNJUK PENGISIAN TEMPLATE PENILAIAN MANUAL');
$instructionsSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$instructionsSheet->mergeCells('A1:D1');

$instructions = [
    'A3' => '1. Template ini sudah berisi nama siswa berdasarkan kelas dan semester yang dipilih.',
    'A4' => '2. Isi kolom "Nilai UTS" dengan nilai UTS siswa (0-100).',
    'A5' => '3. Isi kolom "Nilai Akhir" dengan nilai akhir siswa (0-100) - opsional.',
    'A6' => '4. Isi kolom "Predikat" dengan predikat (A, B, C, atau D) - opsional.',
    'A7' => '5. Isi kolom "Keterangan" dengan keterangan tambahan - opsional.',
    'A8' => '6. Jangan mengubah kolom No, NIS, dan Nama Siswa.',
    'A9' => '7. Setelah selesai, simpan file dan upload melalui menu Import.',
    'A10' => '8. Pastikan format file adalah .xlsx (Excel 2007 atau lebih baru).',
];

foreach ($instructions as $cell => $text) {
    $instructionsSheet->setCellValue($cell, $text);
}

$instructionsSheet->getColumnDimension('A')->setWidth(80);

// Set instructions sheet as active
$spreadsheet->setActiveSheetIndex(0);

// Write to file
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

