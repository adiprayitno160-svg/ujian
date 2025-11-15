<?php
/**
 * Ledger Nilai - Admin/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Export/Import nilai siswa per mata pelajaran
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Load PhpSpreadsheet if available
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

// Handle download template - MUST BE BEFORE HEADER
if (isset($_GET['download_template']) && $_GET['download_template'] === 'excel') {
    // Check authentication first
    require_login();
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
        die('Access denied');
    }
    
    // Disable all output and error reporting first
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Clear any existing output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (!file_exists($vendor_autoload) || !class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        ob_start();
        die('ERROR: PhpSpreadsheet library tidak ditemukan.<br>Silakan install melalui composer:<br><code>composer require phpoffice/phpspreadsheet</code>');
    }
    
    global $pdo;
    
    try {
        // Create Excel template
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // Remove default sheet
        $spreadsheet->removeSheetByIndex(0);
        
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
        
        // Set headers - pivot format
        $headers = ['Nama Siswa', 'Kelas', 'PA&PBP', 'P.PANQ', 'B.INDO', 'MAT', 'IPA', 'IPS', 'B.INGG', 'PRAK', 'PJOK', 'INFOR', 'B.JAWA'];
        
        // Get siswa data dari database - grup per tingkat
        $tahun_ajaran = get_tahun_ajaran_aktif();
        $query_tingkat = "SELECT DISTINCT k.tingkat
                         FROM kelas k
                         INNER JOIN user_kelas uk ON k.id = uk.id_kelas AND uk.tahun_ajaran = ?
                         WHERE k.status = 'active'
                         ORDER BY k.tingkat ASC";
        $stmt_tingkat = $pdo->prepare($query_tingkat);
        $stmt_tingkat->execute([$tahun_ajaran]);
        $tingkat_list = $stmt_tingkat->fetchAll();
        
        // Jika tidak ada tingkat, buat sheet default
        if (empty($tingkat_list)) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle('Template Nilai');
            
            // Set headers
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }
            
            // Apply header style
            $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
            $sheet->getRowDimension(1)->setRowHeight(25);
            
            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(30);
            $sheet->getColumnDimension('B')->setWidth(15);
            for ($col = 'C'; $col <= 'M'; $col++) {
                $sheet->getColumnDimension($col)->setWidth(12);
            }
            
            // Contoh data
            $sheet->setCellValue('A2', 'Contoh: Nama Siswa');
            $sheet->setCellValue('B2', 'Contoh: VII A');
            for ($col = 'C'; $col <= 'M'; $col++) {
                $sheet->setCellValue($col . '2', '');
            }
            $sheet->getStyle('A2:M2')->applyFromArray($dataStyle);
        } else {
            // Buat sheet untuk setiap tingkat
            foreach ($tingkat_list as $tingkat_data) {
                $tingkat = $tingkat_data['tingkat'];
                $sheet = $spreadsheet->createSheet();
                $sheet->setTitle('Kelas ' . $tingkat);
                
                // Set headers
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . '1', $header);
                    $col++;
                }
                
                // Apply header style
                $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
                $sheet->getRowDimension(1)->setRowHeight(25);
                
                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(30); // Nama Siswa
                $sheet->getColumnDimension('B')->setWidth(15); // Kelas
                $sheet->getColumnDimension('C')->setWidth(12); // PA&PBP
                $sheet->getColumnDimension('D')->setWidth(12); // P.PANQ
                $sheet->getColumnDimension('E')->setWidth(12); // B.INDO
                $sheet->getColumnDimension('F')->setWidth(12); // MAT
                $sheet->getColumnDimension('G')->setWidth(12); // IPA
                $sheet->getColumnDimension('H')->setWidth(12); // IPS
                $sheet->getColumnDimension('I')->setWidth(12); // B.INGG
                $sheet->getColumnDimension('J')->setWidth(12); // PRAK
                $sheet->getColumnDimension('K')->setWidth(12); // PJOK
                $sheet->getColumnDimension('L')->setWidth(12); // INFOR
                $sheet->getColumnDimension('M')->setWidth(12); // B.JAWA
                
                // Get siswa untuk tingkat ini
                $query_siswa = "SELECT u.nama, k.nama_kelas
                               FROM users u
                               INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
                               INNER JOIN kelas k ON uk.id_kelas = k.id AND k.status = 'active'
                               WHERE u.role = 'siswa' 
                                 AND u.status = 'active'
                                 AND k.tingkat = ?
                               ORDER BY k.nama_kelas, u.nama";
                $stmt_siswa = $pdo->prepare($query_siswa);
                $stmt_siswa->execute([$tahun_ajaran, $tingkat]);
                $siswa_list = $stmt_siswa->fetchAll();
                
                // Write data siswa
                $row = 2;
                if (empty($siswa_list)) {
                    // Jika tidak ada siswa, tambahkan contoh
                    $sheet->setCellValue('A' . $row, 'Contoh: Nama Siswa');
                    $sheet->setCellValue('B' . $row, 'Contoh: ' . $tingkat . ' A');
                    for ($col = 'C'; $col <= 'M'; $col++) {
                        $sheet->setCellValue($col . $row, '');
                    }
                    $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray($dataStyle);
                } else {
                    foreach ($siswa_list as $siswa) {
                        $sheet->setCellValue('A' . $row, $siswa['nama']);
                        $sheet->setCellValue('B' . $row, $siswa['nama_kelas']);
                        // Kolom nilai dikosongkan untuk diisi admin
                        $sheet->setCellValue('C' . $row, '');
                        $sheet->setCellValue('D' . $row, '');
                        $sheet->setCellValue('E' . $row, '');
                        $sheet->setCellValue('F' . $row, '');
                        $sheet->setCellValue('G' . $row, '');
                        $sheet->setCellValue('H' . $row, '');
                        $sheet->setCellValue('I' . $row, '');
                        $sheet->setCellValue('J' . $row, '');
                        $sheet->setCellValue('K' . $row, '');
                        $sheet->setCellValue('L' . $row, '');
                        $sheet->setCellValue('M' . $row, '');
                        
                        $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray($dataStyle);
                        $row++;
                    }
                }
            }
        }
        
        // Set active sheet to first sheet
        $spreadsheet->setActiveSheetIndex(0);
        
        // Set headers for download - must be before any output
        if (headers_sent($file, $line)) {
            die("Cannot send headers - headers already sent in $file on line $line");
        }
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Template_Ledger_Nilai.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        header('Expires: 0');
        header('Content-Transfer-Encoding: binary');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit(0);
    } catch (Exception $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        die('Error generating template: ' . htmlspecialchars($e->getMessage()));
    }
}

// Continue with normal page if not downloading
try {
    require_role(['admin', 'operator']);
    check_session_timeout();
} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

$page_title = 'Ledger Nilai';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';
$import_results = null;

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file_import'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['xlsx', 'xls'])) {
            $error = 'File harus berformat Excel (.xlsx atau .xls)';
        } else {
            try {
                if (!file_exists($vendor_autoload) || !class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                    $error = 'Library PhpSpreadsheet tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet';
                } else {
                    require_once $vendor_autoload;
                    
                    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
                    $semester = sanitize($_POST['semester'] ?? 'ganjil');
                    
                    $pdo->beginTransaction();
                    $imported = 0;
                    $updated = 0;
                    $skipped = 0;
                    $errors = [];
                    
                    // Read Excel file
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
                    $reader->setReadDataOnly(true);
                    $spreadsheet = $reader->load($file['tmp_name']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    if (empty($rows) || count($rows) < 2) {
                        throw new Exception('File Excel kosong atau tidak memiliki data');
                    }
                    
                    // Get header
                    $header = array_map('trim', array_map('strtoupper', $rows[0]));
                    
                    // Find column indices - pivot format
                    $nama_col = array_search('NAMA SISWA', $header);
                    $kelas_col = array_search('KELAS', $header);
                    $pa_pbp_col = array_search('PA&PBP', $header);
                    $p_panq_col = array_search('P.PANQ', $header);
                    $b_indo_col = array_search('B.INDO', $header);
                    $mat_col = array_search('MAT', $header);
                    $ipa_col = array_search('IPA', $header);
                    $ips_col = array_search('IPS', $header);
                    $b_ingg_col = array_search('B.INGG', $header);
                    $prak_col = array_search('PRAK', $header);
                    $pjok_col = array_search('PJOK', $header);
                    $infor_col = array_search('INFOR', $header);
                    $b_jawa_col = array_search('B.JAWA', $header);
                    
                    if ($nama_col === false || $kelas_col === false) {
                        throw new Exception('Format Excel tidak valid. Kolom harus: Nama Siswa, Kelas, PA&PBP, P.PANQ, B.INDO, MAT, IPA, IPS, B.INGG, PRAK, PJOK, INFOR, B.JAWA');
                    }
                    
                    // Get mapel mapping
                    $stmt = $pdo->query("SELECT id, kode_mapel FROM mapel");
                    $mapel_map = [];
                    while ($row = $stmt->fetch()) {
                        $mapel_map[strtoupper(trim($row['kode_mapel']))] = $row['id'];
                    }
                    
                    // Get kelas mapping
                    $stmt = $pdo->query("SELECT id, nama_kelas FROM kelas WHERE status = 'active'");
                    $kelas_map = [];
                    while ($row = $stmt->fetch()) {
                        $kelas_map[strtolower(trim($row['nama_kelas']))] = $row['id'];
                    }
                    
                    // Process rows - pivot format: setiap baris = satu siswa dengan semua nilai mapel
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        
                        if (count($row) < 3) continue;
                        
                        $nama = trim($row[$nama_col] ?? '');
                        $kelas = trim($row[$kelas_col] ?? '');
                        
                        if (empty($nama) || empty($kelas)) {
                            $skipped++;
                            $errors[] = "Baris " . ($i + 1) . ": Nama atau Kelas tidak boleh kosong";
                            continue;
                        }
                        
                        // Find siswa by name and kelas
                        $kelas_lower = strtolower($kelas);
                        if (!isset($kelas_map[$kelas_lower])) {
                            $skipped++;
                            $errors[] = "Baris " . ($i + 1) . ": Kelas '$kelas' tidak ditemukan";
                            continue;
                        }
                        $id_kelas = $kelas_map[$kelas_lower];
                        
                        $stmt = $pdo->prepare("SELECT u.id FROM users u
                                              INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.id_kelas = ? AND uk.tahun_ajaran = ?
                                              WHERE u.nama = ? AND u.role = 'siswa' AND u.status = 'active' LIMIT 1");
                        $stmt->execute([$id_kelas, $tahun_ajaran, $nama]);
                        $siswa = $stmt->fetch();
                        
                        if (!$siswa) {
                            $skipped++;
                            $errors[] = "Baris " . ($i + 1) . ": Siswa '$nama' di kelas '$kelas' tidak ditemukan";
                            continue;
                        }
                        
                        $id_siswa = $siswa['id'];
                        $guru_id = $_SESSION['user_id'];
                        
                        // Map kode mapel to column mapping
                        $mapel_columns = [
                            'PA&PBP' => $pa_pbp_col,
                            'P.PANQ' => $p_panq_col,
                            'B.INDO' => $b_indo_col,
                            'MAT' => $mat_col,
                            'IPA' => $ipa_col,
                            'IPS' => $ips_col,
                            'B.INGG' => $b_ingg_col,
                            'PRAK' => $prak_col,
                            'PJOK' => $pjok_col,
                            'INFOR' => $infor_col,
                            'B.JAWA' => $b_jawa_col
                        ];
                        
                        // Process each mapel
                        foreach ($mapel_columns as $kode_mapel => $col_idx) {
                            if ($col_idx === false) continue; // Skip if column not found
                            
                            $nilai_uts = floatval($row[$col_idx] ?? 0);
                            
                            // Skip if nilai is 0 or empty
                            if ($nilai_uts <= 0) continue;
                            
                            // Find mapel
                            $kode_mapel_upper = strtoupper($kode_mapel);
                            if (!isset($mapel_map[$kode_mapel_upper])) {
                                $errors[] = "Baris " . ($i + 1) . ": Mata pelajaran dengan kode '$kode_mapel' tidak ditemukan";
                                continue;
                            }
                            $id_mapel = $mapel_map[$kode_mapel_upper];
                            
                            // Check if penilaian exists
                            $stmt = $pdo->prepare("SELECT id FROM penilaian_manual 
                                                  WHERE id_siswa = ? AND id_mapel = ? AND id_kelas = ? 
                                                  AND tahun_ajaran = ? AND semester = ?");
                            $stmt->execute([$id_siswa, $id_mapel, $id_kelas, $tahun_ajaran, $semester]);
                            $existing = $stmt->fetch();
                            
                            if ($existing) {
                                // Update - hanya update nilai_uts
                                $stmt = $pdo->prepare("UPDATE penilaian_manual 
                                                      SET nilai_uts = ?, updated_at = NOW()
                                                      WHERE id = ?");
                                $stmt->execute([$nilai_uts, $existing['id']]);
                                $updated++;
                            } else {
                                // Insert - hanya set nilai_uts
                                $stmt = $pdo->prepare("INSERT INTO penilaian_manual 
                                                      (id_guru, id_siswa, id_mapel, id_kelas, tahun_ajaran, semester,
                                                       nilai_uts, status, created_at, updated_at)
                                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())");
                                $stmt->execute([$guru_id, $id_siswa, $id_mapel, $id_kelas, $tahun_ajaran, $semester, $nilai_uts]);
                                $imported++;
                            }
                        }
                    }
                    
                    if (!$error) {
                        $pdo->commit();
                        $import_results = [
                            'imported' => $imported,
                            'updated' => $updated,
                            'skipped' => $skipped,
                            'errors' => $errors
                        ];
                        $success = "Berhasil mengimport $imported data baru, memperbarui $updated data" . ($skipped > 0 ? ", $skipped data dilewati" : "");
                        log_activity('import_ledger_nilai', 'penilaian_manual', null);
                    } else {
                        $pdo->rollBack();
                    }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Import ledger nilai error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengimport: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'File harus diupload';
    }
}

// Get filters
$tahun_ajaran_filter = sanitize($_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
$semester_filter = sanitize($_GET['semester'] ?? 'ganjil');
$kelas_filter = intval($_GET['kelas'] ?? 0);
$tingkat_filter = sanitize($_GET['tingkat'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Build query - pivot format: setiap siswa satu baris, setiap mapel menjadi kolom
// Hanya ambil nilai UTS (tengah semester)
$query = "SELECT 
            u.id as id_siswa,
            u.nama as nama_siswa,
            k.id as id_kelas,
            k.nama_kelas,
            k.tingkat,
            MAX(CASE WHEN m.kode_mapel = 'PA&PBP' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_pa_pbp,
            MAX(CASE WHEN m.kode_mapel = 'P.PANQ' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_p_panq,
            MAX(CASE WHEN m.kode_mapel = 'B.INDO' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_b_indo,
            MAX(CASE WHEN m.kode_mapel = 'MAT' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_mat,
            MAX(CASE WHEN m.kode_mapel = 'IPA' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_ipa,
            MAX(CASE WHEN m.kode_mapel = 'IPS' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_ips,
            MAX(CASE WHEN m.kode_mapel = 'B.INGG' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_b_ingg,
            MAX(CASE WHEN m.kode_mapel = 'PRAK' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_prak,
            MAX(CASE WHEN m.kode_mapel = 'PJOK' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_pjok,
            MAX(CASE WHEN m.kode_mapel = 'INFOR' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_infor,
            MAX(CASE WHEN m.kode_mapel = 'B.JAWA' THEN COALESCE(pm.nilai_uts, 0) END) as nilai_b_jawa
          FROM users u
          INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          INNER JOIN kelas k ON uk.id_kelas = k.id AND k.status = 'active'
          LEFT JOIN penilaian_manual pm ON pm.id_siswa = u.id 
            AND pm.id_kelas = k.id
            AND pm.tahun_ajaran = ?
            AND pm.semester = ?
          LEFT JOIN mapel m ON pm.id_mapel = m.id
          WHERE u.role = 'siswa' 
            AND u.status = 'active'";
$params = [$tahun_ajaran_filter, $tahun_ajaran_filter, $semester_filter];

if ($kelas_filter > 0) {
    $query .= " AND k.id = ?";
    $params[] = $kelas_filter;
}

if ($tingkat_filter && in_array($tingkat_filter, ['VII', 'VIII', 'IX'])) {
    $query .= " AND k.tingkat = ?";
    $params[] = $tingkat_filter;
}

if ($search) {
    $query .= " AND (u.nama LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
}

$query .= " GROUP BY u.id, u.nama, k.id, k.nama_kelas, k.tingkat
            ORDER BY k.tingkat ASC, k.nama_kelas, u.nama";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $nilai_list_all = $stmt->fetchAll();
    
    // Group by tingkat
    $nilai_per_tingkat = [];
    foreach ($nilai_list_all as $nilai) {
        $tingkat = $nilai['tingkat'] ?? 'Unknown';
        if (!isset($nilai_per_tingkat[$tingkat])) {
            $nilai_per_tingkat[$tingkat] = [];
        }
        $nilai_per_tingkat[$tingkat][] = $nilai;
    }
    
    // Sort tingkat: VII, VIII, IX
    ksort($nilai_per_tingkat);
    $nilai_list = $nilai_list_all; // Keep for backward compatibility
} catch (PDOException $e) {
    error_log("Ledger nilai query error: " . $e->getMessage());
    error_log("Query: " . $query);
    error_log("Params: " . print_r($params, true));
    $nilai_list = [];
    $nilai_per_tingkat = [];
    if (empty($error)) {
        $error = 'Terjadi kesalahan saat mengambil data: ' . $e->getMessage();
    }
}

// Get tahun ajaran list
try {
    // Try to get from tahun_ajaran table first
    $stmt = $pdo->query("SELECT DISTINCT tahun_ajaran FROM tahun_ajaran ORDER BY tahun_ajaran DESC");
    $tahun_ajaran_list = $stmt->fetchAll();
    
    // If empty, try alternative query
    if (empty($tahun_ajaran_list)) {
        $stmt = $pdo->query("SELECT DISTINCT tahun_ajaran FROM user_kelas ORDER BY tahun_ajaran DESC");
        $tahun_ajaran_list = $stmt->fetchAll();
    }
    
    // If still empty, use get_all_tahun_ajaran function
    if (empty($tahun_ajaran_list)) {
        $tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
        $tahun_ajaran_list = [];
        foreach ($tahun_ajaran_all as $ta) {
            $tahun_ajaran_list[] = ['tahun_ajaran' => $ta['tahun_ajaran']];
        }
    }
    
    // Ensure all items have 'tahun_ajaran' key
    foreach ($tahun_ajaran_list as &$ta) {
        if (!isset($ta['tahun_ajaran'])) {
            // If it's a string, wrap it
            if (is_string($ta)) {
                $ta = ['tahun_ajaran' => $ta];
            } elseif (isset($ta[0])) {
                $ta = ['tahun_ajaran' => $ta[0]];
            }
        }
    }
    unset($ta);
    
    // Fallback: use get_tahun_ajaran_aktif() result if still empty
    if (empty($tahun_ajaran_list)) {
        $tahun_ajaran_aktif = get_tahun_ajaran_aktif();
        $tahun_ajaran_list = [['tahun_ajaran' => $tahun_ajaran_aktif]];
    }
} catch (PDOException $e) {
    error_log("Get tahun ajaran list error: " . $e->getMessage());
    // Fallback: use get_tahun_ajaran_aktif() result
    $tahun_ajaran_aktif = get_tahun_ajaran_aktif();
    $tahun_ajaran_list = [['tahun_ajaran' => $tahun_ajaran_aktif]];
}

// Get kelas list
try {
    $stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY nama_kelas ASC");
    $kelas_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get kelas list error: " . $e->getMessage());
    $kelas_list = [];
}
?>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<?php if (isset($import_results) && !empty($import_results['errors'])): ?>
    <div class="alert alert-warning">
        <strong>Peringatan:</strong>
        <ul class="mb-0">
            <?php foreach (array_slice($import_results['errors'], 0, 10) as $err): ?>
                <li><?php echo escape($err); ?></li>
            <?php endforeach; ?>
            <?php if (count($import_results['errors']) > 10): ?>
                <li>... dan <?php echo count($import_results['errors']) - 10; ?> error lainnya</li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold">Ledger Nilai</h3>
        <p class="text-muted">Download template dan Import nilai tengah semester (UTS) siswa per mata pelajaran</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <a href="?download_template=excel" 
               class="btn btn-success" target="_blank">
                <i class="fas fa-download"></i> Download Template
            </a>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-file-import"></i> Import Excel
            </button>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?php echo base_url('admin-ledger-nilai'); ?>" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran">
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo escape($ta['tahun_ajaran']); ?>" 
                                <?php echo $tahun_ajaran_filter == $ta['tahun_ajaran'] ? 'selected' : ''; ?>>
                            <?php echo escape($ta['tahun_ajaran']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="ganjil" <?php echo $semester_filter == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester_filter == 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua Tingkat</option>
                    <option value="VII" <?php echo $tingkat_filter == 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $tingkat_filter == 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $tingkat_filter == 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="kelas">
                    <option value="0">Semua Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" 
                                <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cari</label>
                <input type="text" class="form-control" name="search" 
                       value="<?php echo escape($search); ?>" 
                       placeholder="Cari nama siswa...">
            </div>
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table - Grouped by Tingkat -->
<?php if (empty($nilai_per_tingkat)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3"></i>
                <p>Tidak ada data nilai</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($nilai_per_tingkat as $tingkat => $nilai_list_tingkat): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-layer-group"></i> Tingkat <?php echo escape($tingkat); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>PA&PBP</th>
                                <th>P.PANQ</th>
                                <th>B.INDO</th>
                                <th>MAT</th>
                                <th>IPA</th>
                                <th>IPS</th>
                                <th>B.INGG</th>
                                <th>PRAK</th>
                                <th>PJOK</th>
                                <th>INFOR</th>
                                <th>B.JAWA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($nilai_list_tingkat as $nilai): ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><strong><?php echo escape($nilai['nama_siswa']); ?></strong></td>
                                    <td><?php echo escape($nilai['nama_kelas']); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_pa_pbp'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_p_panq'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_b_indo'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_mat'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_ipa'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_ips'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_b_ingg'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_prak'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_pjok'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_infor'] ?? 0, 0); ?></td>
                                    <td class="text-center"><?php echo number_format($nilai['nilai_b_jawa'] ?? 0, 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Ledger Nilai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_excel">
                    
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <select class="form-select" name="tahun_ajaran" required>
                            <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?php echo escape($ta['tahun_ajaran']); ?>" 
                                        <?php echo $tahun_ajaran_filter == $ta['tahun_ajaran'] ? 'selected' : ''; ?>>
                                    <?php echo escape($ta['tahun_ajaran']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" name="semester" required>
                            <option value="ganjil" <?php echo $semester_filter == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                            <option value="genap" <?php echo $semester_filter == 'genap' ? 'selected' : ''; ?>>Genap</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_import" class="form-label">File Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_import" name="file_import" accept=".xlsx,.xls" required>
                        <small class="text-muted">Format file: .xlsx atau .xls. Kolom: Nama Siswa, Kelas, PA&PBP, P.PANQ, B.INDO, MAT, IPA, IPS, B.INGG, PRAK, PJOK, INFOR, B.JAWA (nilai UTS tengah semester)</small>
                    </div>
                    
                    <?php if (isset($import_results) && !empty($import_results['errors'])): ?>
                        <div class="alert alert-warning">
                            <strong>Peringatan:</strong>
                            <ul class="mb-0">
                                <?php foreach (array_slice($import_results['errors'], 0, 10) as $err): ?>
                                    <li><?php echo escape($err); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

