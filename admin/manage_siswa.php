<?php
/**
 * Manage Siswa - Admin/Guru/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

// Handle download template FIRST - before any includes that might output content
if (isset($_GET['download_template']) && $_GET['download_template'] === 'excel') {
    // Start output buffering early
    if (!ob_get_level()) {
        ob_start();
    }
    
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    require_role(['admin', 'guru', 'operator']);
    check_session_timeout();
    
    // Load PhpSpreadsheet
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor_autoload) || !class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $_SESSION['error'] = 'PhpSpreadsheet library tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet';
        redirect('admin-manage-siswa');
        exit;
    }
    
    // Clear all output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Template_Import_Peserta_Didik.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    
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
        ['VII A', '12345', '1234', 'aaa', 'L'],
        ['VII B', '1234563', '1111', 'sdfff', 'P']
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
        'A4' => '2. Kolom NISN: Masukkan Nomor Induk Siswa Nasional (NISN) - akan digunakan sebagai username untuk login',
        'A5' => '3. Kolom NIS: Masukkan Nomor Induk Siswa (NIS) - akan digunakan sebagai password untuk login',
        'A6' => '4. Kolom NAMA: Masukkan nama lengkap siswa',
        'A7' => '5. Kolom L/P: Masukkan L untuk Laki-laki atau P untuk Perempuan',
        'A8' => '',
        'A9' => 'CATATAN PENTING:',
        'A10' => '- NISN harus unik (tidak boleh duplikat)',
        'A11' => '- NIS harus unik (tidak boleh duplikat)',
        'A12' => '- Nama kelas harus sesuai dengan kelas yang sudah ada di sistem',
        'A13' => '- Jika kelas belum ada, silakan buat terlebih dahulu di menu Kelola Kelas',
        'A14' => '- Format file harus .xlsx (Excel 2007 atau lebih baru)',
        'A15' => '- Jangan mengubah header baris pertama',
        'A16' => '- Hapus baris contoh sebelum mengisi data',
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
        // Ensure no output before writing
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Disable output compression if enabled
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);
        
        $writer->save('php://output');
        exit(0);
    } catch (Exception $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        die('Error generating Excel: ' . htmlspecialchars($e->getMessage()) . '<br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine());
    }
}

// Normal page load - continue with includes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'guru', 'operator']);
check_session_timeout();

// Load PhpSpreadsheet if available (for Excel export/import)
$vendor_autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendor_autoload)) {
    require_once $vendor_autoload;
}

$page_title = 'Kelola Siswa';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

// Ensure NIS column exists for storing NIS separately
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'nis'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN nis VARCHAR(20) NULL AFTER nisn");
        $pdo->exec("ALTER TABLE users ADD INDEX idx_nis (nis)");
    }
} catch (PDOException $e) {
    // Column might already exist, ignore error
}

$error = '';
$success = '';
$import_results = null;

// Handle fix data siswa yang masih format gabungan
if (isset($_GET['fix_data']) && $_GET['fix_data'] === 'nisn_nis') {
    try {
        $pdo->beginTransaction();
        $fixed = 0;
        
        // Get all siswa yang mungkin masih format gabungan
        $stmt = $pdo->query("SELECT id, username, nisn, nis FROM users WHERE role = 'siswa'");
        $siswa_list = $stmt->fetchAll();
        
        foreach ($siswa_list as $siswa) {
            $username = $siswa['username'] ?? '';
            $nisn_col = $siswa['nisn'] ?? '';
            $nis_col = $siswa['nis'] ?? '';
            
            // Cek jika username atau nisn masih format gabungan
            $needs_fix = false;
            $new_nisn = '';
            $new_nis = '';
            
            if (preg_match('/\s*\/\s*/', $username)) {
                // Username masih format gabungan
                $parts = preg_split('/\s*\/\s*/', trim($username), 2);
                if (count($parts) == 2) {
                    $new_nisn = trim($parts[0]);
                    $new_nis = trim($parts[1]);
                    $needs_fix = true;
                }
            } elseif (preg_match('/\s*\/\s*/', $nisn_col)) {
                // Kolom nisn masih format gabungan
                $parts = preg_split('/\s*\/\s*/', trim($nisn_col), 2);
                if (count($parts) == 2) {
                    $new_nisn = trim($parts[0]);
                    $new_nis = trim($parts[1]);
                    $needs_fix = true;
                }
            } elseif (empty($nis_col) && !empty($username)) {
                // NIS kosong tapi username ada, mungkin perlu dipisah
                if (preg_match('/\s*\/\s*/', $username)) {
                    $parts = preg_split('/\s*\/\s*/', trim($username), 2);
                    if (count($parts) == 2) {
                        $new_nisn = trim($parts[0]);
                        $new_nis = trim($parts[1]);
                        $needs_fix = true;
                    }
                }
            }
            
            if ($needs_fix && !empty($new_nisn) && !empty($new_nis)) {
                // Update password dengan hash dari NIS baru
                $hashed_password = password_hash($new_nis, PASSWORD_DEFAULT);
                
                // Update user
                $stmt = $pdo->prepare("UPDATE users SET username = ?, nisn = ?, nis = ?, password = ? WHERE id = ?");
                $stmt->execute([$new_nisn, $new_nisn, $new_nis, $hashed_password, $siswa['id']]);
                $fixed++;
            }
        }
        
        $pdo->commit();
        $success = "Berhasil memperbaiki $fixed data siswa yang masih format gabungan";
        log_activity('fix_siswa_nisn_nis', 'users', null);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Terjadi kesalahan saat memperbaiki data: ' . $e->getMessage();
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama = sanitize($_POST['nama'] ?? '');
        $nisn_nis = trim($_POST['nisn_nis'] ?? '');
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        
        // Parse NISN / NIS format: "0129584731 / 14841"
        // Format dari template Excel atau form input akan dipisah menjadi:
        // - NISN: 0129584731 (untuk username/login)
        // - NIS: 14841 (untuk password/login)
        $nisn = '';
        $nis = '';
        if (!empty($nisn_nis)) {
            // Split by "/" dengan berbagai variasi spasi
            $parts = preg_split('/\s*\/\s*/', trim($nisn_nis), 2);
            if (count($parts) == 2) {
                $nisn = trim($parts[0]); // NISN untuk username
                $nis = trim($parts[1]);   // NIS untuk password
            } else {
                // Jika tidak ada "/", error
                $error = 'Format NISN / NIS tidak valid. Gunakan format: NISN / NIS (contoh: 0129584731 / 14841)';
            }
        }
        
        if (empty($nama) || empty($nisn) || empty($nis) || empty($id_kelas)) {
            if (empty($error)) {
                $error = 'Nama, NISN/NIS (format: NISN / NIS), dan kelas harus diisi';
            }
        } else {
            try {
                // Check if NISN already exists (username untuk login)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa'");
                $stmt->execute([$nisn]);
                if ($stmt->fetch()) {
                    $error = 'NISN sudah digunakan';
                } else {
                    // Check if NISN already exists in nisn column
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE nisn = ? AND role = 'siswa'");
                    $stmt->execute([$nisn]);
                    if ($stmt->fetch()) {
                        $error = 'NISN sudah digunakan';
                    } else {
                        // Password menggunakan NIS (hash dari NIS)
                        $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                        
                        // Create user siswa: username = NISN, password = hash dari NIS
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, nisn, nis, tanggal_lahir, no_hp, status) VALUES (?, ?, 'siswa', ?, ?, ?, ?, ?, 'active')");
                        $stmt->execute([$nisn, $hashed_password, $nama, $nisn, $nis, $tanggal_lahir ?: null, $no_hp ?: null]);
                        $user_id = $pdo->lastInsertId();
                    
                        // Assign to kelas
                        $tahun_ajaran = get_tahun_ajaran_aktif();
                        // Check if already exists
                        $stmt = $pdo->prepare("SELECT id FROM user_kelas WHERE id_user = ? AND tahun_ajaran = ?");
                        $stmt->execute([$user_id, $tahun_ajaran]);
                        if ($stmt->fetch()) {
                            $stmt = $pdo->prepare("UPDATE user_kelas SET id_kelas = ? WHERE id_user = ? AND tahun_ajaran = ?");
                            $stmt->execute([$id_kelas, $user_id, $tahun_ajaran]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran, semester) VALUES (?, ?, ?, 'ganjil')");
                            $stmt->execute([$user_id, $id_kelas, $tahun_ajaran]);
                        }
                        
                        $success = 'Siswa berhasil ditambahkan. Login menggunakan NISN sebagai username dan NIS sebagai password.';
                        log_activity('create_siswa', 'users', $user_id);
                    }
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'NISN atau NIS sudah digunakan';
                } else {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nama = sanitize($_POST['nama'] ?? '');
        $nisn_nis = trim($_POST['nisn_nis'] ?? '');
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        
        // Parse NISN / NIS format: "0129584731 / 14841" atau "0129584731/14841"
        // NISN akan digunakan sebagai username, NIS sebagai password
        $nisn = '';
        $nis = '';
        if (!empty($nisn_nis)) {
            // Split by "/" dengan berbagai variasi spasi
            $parts = preg_split('/\s*\/\s*/', trim($nisn_nis), 2);
            if (count($parts) == 2) {
                $nisn = trim($parts[0]);
                $nis = trim($parts[1]);
            } else {
                // Jika tidak ada "/", error
                $error = 'Format NISN / NIS tidak valid. Gunakan format: NISN / NIS (contoh: 0129584731 / 14841)';
            }
        }
        
        if (empty($nama) || empty($nisn) || empty($nis) || empty($id_kelas)) {
            if (empty($error)) {
                $error = 'Nama, NISN/NIS (format: NISN / NIS), dan kelas harus diisi';
            }
        } else {
            try {
                // Check if NISN already exists for other user (username untuk login)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa' AND id != ?");
                $stmt->execute([$nisn, $id]);
                if ($stmt->fetch()) {
                    $error = 'NISN sudah digunakan oleh siswa lain';
                } else {
                    // Check if NISN already exists in nisn column for other user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE nisn = ? AND role = 'siswa' AND id != ?");
                    $stmt->execute([$nisn, $id]);
                    if ($stmt->fetch()) {
                        $error = 'NISN sudah digunakan oleh siswa lain';
                    } else {
                        // Get old NIS to check if password needs update
                        $stmt = $pdo->prepare("SELECT nis FROM users WHERE id = ?");
                        $stmt->execute([$id]);
                        $old_user = $stmt->fetch();
                        
                        // Update user: username = NISN, nis = NIS
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, nama = ?, nisn = ?, nis = ?, tanggal_lahir = ?, no_hp = ? WHERE id = ? AND role = 'siswa'");
                        $stmt->execute([$nisn, $nama, $nisn, $nis, $tanggal_lahir ?: null, $no_hp ?: null, $id]);
                        
                        // Update password jika NIS berubah
                        if ($old_user && $old_user['nis'] !== $nis) {
                            $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed_password, $id]);
                        }
                    
                        // Update kelas
                        $tahun_ajaran = get_tahun_ajaran_aktif();
                        $stmt = $pdo->prepare("SELECT id FROM user_kelas WHERE id_user = ? AND tahun_ajaran = ?");
                        $stmt->execute([$id, $tahun_ajaran]);
                        $user_kelas = $stmt->fetch();
                        
                        if ($user_kelas) {
                            $stmt = $pdo->prepare("UPDATE user_kelas SET id_kelas = ? WHERE id = ?");
                            $stmt->execute([$id_kelas, $user_kelas['id']]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran, semester) VALUES (?, ?, ?, 'ganjil')");
                            $stmt->execute([$id, $id_kelas, $tahun_ajaran]);
                        }
                        
                        $success = 'Siswa berhasil diperbarui';
                        log_activity('update_siswa', 'users', $id);
                    }
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $new_status = sanitize($_POST['status'] ?? 'active');
        
        if ($id && in_array($new_status, ['active', 'inactive'])) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'siswa'");
                $stmt->execute([$new_status, $id]);
                $status_text = $new_status === 'active' ? 'diaktifkan' : 'dinonaktifkan';
                $success = "Siswa berhasil $status_text";
                log_activity('toggle_siswa_status', 'users', $id);
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $pdo->beginTransaction();
                
                // Hapus semua penilaian manual siswa
                $stmt = $pdo->prepare("DELETE FROM penilaian_manual WHERE id_siswa = ?");
                $stmt->execute([$id]);
                
                // Hapus semua user_kelas siswa
                $stmt = $pdo->prepare("DELETE FROM user_kelas WHERE id_user = ?");
                $stmt->execute([$id]);
                
                // Hapus semua migrasi_history siswa
                $stmt = $pdo->prepare("DELETE FROM migrasi_history WHERE id_user = ?");
                $stmt->execute([$id]);
                
                // Hapus user siswa
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa'");
                $stmt->execute([$id]);
                
                $pdo->commit();
                $success = 'Siswa berhasil dihapus beserta semua data terkait (raport, penilaian, dll)';
                log_activity('delete_siswa', 'users', $id);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reset_password') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT nis FROM users WHERE id = ? AND role = 'siswa'");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user && !empty($user['nis'])) {
                    $hashed_password = password_hash($user['nis'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $id]);
                    $success = 'Password berhasil direset (menggunakan NIS)';
                    log_activity('reset_password_siswa', 'users', $id);
                } else {
                    $error = 'NIS tidak ditemukan untuk siswa ini';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'import_excel') {
        // Handle Excel import
        if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file_import'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, ['xlsx', 'xls'])) {
                $error = 'File harus berformat Excel (.xlsx atau .xls)';
            } else {
                try {
                    // Check PhpSpreadsheet availability
                    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
                    if (!file_exists($vendor_autoload)) {
                        $error = 'Library PhpSpreadsheet tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet';
                    } else {
                        require_once $vendor_autoload;
                        
                        $pdo->beginTransaction();
                        $imported = 0;
                        $skipped = 0;
                        $errors = [];
                        
                        $tahun_ajaran = get_tahun_ajaran_aktif();
                        
                        // Get kelas mapping (nama kelas to ID)
                        $stmt = $pdo->query("SELECT id, nama_kelas FROM kelas WHERE status = 'active'");
                        $kelas_map = [];
                        while ($row = $stmt->fetch()) {
                            $kelas_map[strtolower(trim($row['nama_kelas']))] = $row['id'];
                        }
                        
                        // Excel import using PhpSpreadsheet
                        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
                        $reader->setReadDataOnly(true);
                        $spreadsheet = $reader->load($file['tmp_name']);
                        $worksheet = $spreadsheet->getActiveSheet();
                        $rows = $worksheet->toArray();
                        
                        if (empty($rows)) {
                            throw new Exception('File Excel kosong');
                        }
                        
                        // Get header (first row) - format: KLS, NISN, NIS, NAMA, L/P
                        $header = array_map('trim', array_map('strtoupper', $rows[0]));
                        
                        // Find column indices
                        $kls_col = array_search('KLS', $header);
                        $nisn_col = array_search('NISN', $header);
                        $nis_col = array_search('NIS', $header);
                        $nama_col = array_search('NAMA', $header);
                        $lp_col = array_search('L/P', $header);
                        
                        // Fallback: cek format gabungan (NISN / NIS) untuk backward compatibility
                        $nisn_nis_col = false;
                        if ($nisn_col === false || $nis_col === false) {
                            $nisn_nis_col = array_search('NISN / NIS', $header);
                        }
                        
                        if ($kls_col === false || ($nisn_col === false && $nisn_nis_col === false) || $nama_col === false) {
                            throw new Exception('Format Excel tidak valid. Kolom harus: KLS, NISN, NIS, NAMA, L/P');
                        }
                        
                        // Process rows (skip header)
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            
                            if (count($row) < 4) continue;
                            
                            $kls = trim($row[$kls_col] ?? '');
                            $nama = trim($row[$nama_col] ?? '');
                            $lp = trim($row[$lp_col] ?? '');
                            
                            // Parse NISN dan NIS dari template Excel
                            // Format terpisah: NISN di kolom B, NIS di kolom C
                            // - NISN: untuk username/login
                            // - NIS: untuk password/login
                            $nisn = '';
                            $nis = '';
                            if ($nisn_col !== false && $nis_col !== false) {
                                // Format terpisah (format baru)
                                $nisn = trim($row[$nisn_col] ?? '');
                                $nis = trim($row[$nis_col] ?? '');
                            } elseif ($nisn_nis_col !== false) {
                                // Format gabungan (backward compatibility)
                                $nisn_nis = trim($row[$nisn_nis_col] ?? '');
                                if (!empty($nisn_nis)) {
                                    $parts = preg_split('/\s*\/\s*/', $nisn_nis, 2);
                                    if (count($parts) == 2) {
                                        $nisn = trim($parts[0]);
                                        $nis = trim($parts[1]);
                                    } else {
                                        $skipped++;
                                        $errors[] = "Baris " . ($i + 1) . ": Format NISN / NIS tidak valid";
                                        continue;
                                    }
                                }
                            }
                            
                            if (empty($nisn) || empty($nis) || empty($nama) || empty($kls)) {
                                $skipped++;
                                $errors[] = "Baris " . ($i + 1) . ": Data tidak lengkap (NISN/NIS, NAMA, dan KLS wajib diisi)";
                                continue;
                            }
                            
                            // Find kelas ID
                            $kelas_lower = strtolower($kls);
                            if (!isset($kelas_map[$kelas_lower])) {
                                $skipped++;
                                $errors[] = "Baris " . ($i + 1) . ": Kelas '$kls' tidak ditemukan";
                                continue;
                            }
                            $id_kelas = $kelas_map[$kelas_lower];
                            
                            // Check if NISN already exists (username untuk login)
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa'");
                            $stmt->execute([$nisn]);
                            if ($stmt->fetch()) {
                                $skipped++;
                                $errors[] = "Baris " . ($i + 1) . ": NISN '$nisn' sudah digunakan";
                                continue;
                            }
                            
                            // Check if NISN already exists in nisn column
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE nisn = ? AND role = 'siswa'");
                            $stmt->execute([$nisn]);
                            if ($stmt->fetch()) {
                                $skipped++;
                                $errors[] = "Baris " . ($i + 1) . ": NISN '$nisn' sudah digunakan";
                                continue;
                            }
                            
                            // Create user: username = NISN, password = hash dari NIS
                            $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, nisn, nis, status) VALUES (?, ?, 'siswa', ?, ?, ?, 'active')");
                            $stmt->execute([$nisn, $hashed_password, $nama, $nisn, $nis]);
                            $user_id = $pdo->lastInsertId();
                            
                            // Assign to kelas
                            $stmt = $pdo->prepare("SELECT id FROM user_kelas WHERE id_user = ? AND tahun_ajaran = ?");
                            $stmt->execute([$user_id, $tahun_ajaran]);
                            if ($stmt->fetch()) {
                                $stmt = $pdo->prepare("UPDATE user_kelas SET id_kelas = ? WHERE id_user = ? AND tahun_ajaran = ?");
                                $stmt->execute([$id_kelas, $user_id, $tahun_ajaran]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran, semester) VALUES (?, ?, ?, 'ganjil')");
                                $stmt->execute([$user_id, $id_kelas, $tahun_ajaran]);
                            }
                            
                            $imported++;
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        $import_results = [
                            'imported' => $imported,
                            'skipped' => $skipped,
                            'errors' => $errors
                        ];
                        $success = "Berhasil mengimport $imported peserta didik" . ($skipped > 0 ? ", $skipped data dilewati" : "");
                        log_activity('import_siswa', 'users', null);
                    }
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Import siswa error: " . $e->getMessage());
                    $error = 'Terjadi kesalahan saat mengimport: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'File harus diupload';
        }
    }
}

// Get filter
$search = sanitize($_GET['search'] ?? '');
$kelas_filter = intval($_GET['kelas'] ?? 0);

// Build query
$tahun_ajaran = get_tahun_ajaran_aktif();
// Pastikan mengambil kolom nisn dan nis yang terpisah
$query = "SELECT u.id, 
          COALESCE(NULLIF(u.nisn, ''), u.username) as nisn, 
          u.nis, 
          u.nama, 
          u.status, 
          u.created_at, 
          u.tanggal_lahir, 
          u.no_hp,
          k.id as id_kelas, 
          k.nama_kelas
          FROM users u
          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          LEFT JOIN kelas k ON uk.id_kelas = k.id
          WHERE u.role = 'siswa'";
$params = [$tahun_ajaran];

if ($search) {
    $query .= " AND (u.nama LIKE ? OR u.username LIKE ? OR u.nis LIKE ? OR u.nisn LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($kelas_filter) {
    $query .= " AND k.id = ?";
    $params[] = $kelas_filter;
}

$query .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$siswa_list = $stmt->fetchAll();

// Get kelas list
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

// Get siswa for edit
$edit_siswa = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $tahun_ajaran = get_tahun_ajaran_aktif();
    $stmt = $pdo->prepare("SELECT u.*, k.id as id_kelas FROM users u
                          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
                          LEFT JOIN kelas k ON uk.id_kelas = k.id
                          WHERE u.id = ? AND u.role = 'siswa'");
    $stmt->execute([$tahun_ajaran, $edit_id]);
    $edit_siswa = $stmt->fetch();
    // Ensure nisn is set from username if nisn column is empty (backward compatibility)
    if ($edit_siswa && empty($edit_siswa['nisn']) && !empty($edit_siswa['username'])) {
        $edit_siswa['nisn'] = $edit_siswa['username'];
    }
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

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold">Kelola Siswa</h3>
        <p class="text-muted">Manajemen data siswa - Login menggunakan NISN sebagai username dan NIS sebagai password</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <a href="<?php echo base_url('admin-manage-siswa-template'); ?>" class="btn btn-success">
                <i class="fas fa-download"></i> Download Template Excel
            </a>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-file-import"></i> Import Excel
            </button>
            <a href="?fix_data=nisn_nis" class="btn btn-warning" onclick="return confirm('Apakah Anda yakin ingin memperbaiki data siswa yang masih format gabungan (NISN / NIS)?')">
                <i class="fas fa-tools"></i> Perbaiki Data
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSiswaModal">
                <i class="fas fa-plus"></i> Tambah Siswa
            </button>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Cari nama, NISN, atau NIS..." value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-4">
                <select class="form-select" name="kelas">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $kelas_filter == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                        <thead>
                    <tr>
                        <th>No</th>
                        <th>NISN</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Tanggal Lahir</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada data siswa</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($siswa_list as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo escape($siswa['nisn'] ?? '-'); ?></strong></td>
                                <td><strong><?php echo escape($siswa['nis'] ?? '-'); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($siswa['tanggal_lahir']): ?>
                                        <?php echo format_date($siswa['tanggal_lahir'], 'd/m/Y'); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $siswa['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($siswa['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?edit=<?php echo $siswa['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($siswa['status'] == 'active'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menonaktifkan siswa ini?');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $siswa['id']; ?>">
                                                <input type="hidden" name="status" value="inactive">
                                                <button type="submit" class="btn btn-outline-warning" title="Nonaktifkan">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengaktifkan siswa ini?');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?php echo $siswa['id']; ?>">
                                                <input type="hidden" name="status" value="active">
                                                <button type="submit" class="btn btn-outline-success" title="Aktifkan">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password siswa ini? Password akan diubah menjadi NIS (password untuk login).');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?php echo $siswa['id']; ?>">
                                            <button type="submit" class="btn btn-outline-info" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Hapus siswa ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $siswa['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="addSiswaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?php echo $edit_siswa ? 'Edit Siswa' : 'Tambah Siswa'; ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $edit_siswa ? 'update' : 'create'; ?>">
                    <?php if ($edit_siswa): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_siswa['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Siswa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama" name="nama" 
                               value="<?php echo escape($edit_siswa['nama'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nisn_nis" class="form-label">NISN / NIS <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nisn_nis" name="nisn_nis" 
                               value="<?php 
                                   if ($edit_siswa) {
                                       $nisn_val = $edit_siswa['username'] ?? ($edit_siswa['nisn'] ?? '');
                                       $nis_val = $edit_siswa['nis'] ?? '';
                                       echo escape($nisn_val . ($nis_val ? ' / ' . $nis_val : ''));
                                   }
                               ?>" required
                               placeholder="0129584731 / 14841">
                        <small class="text-muted">Format: NISN / NIS (contoh: 0129584731 / 14841). NISN digunakan sebagai username, NIS sebagai password untuk login.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_kelas" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_kelas" name="id_kelas" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>" 
                                        <?php echo ($edit_siswa && $edit_siswa['id_kelas'] == $kelas['id']) ? 'selected' : ''; ?>>
                                    <?php echo escape($kelas['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tanggal_lahir" class="form-label">
                            Tanggal Lahir <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" id="tanggal_lahir" 
                               name="tanggal_lahir" 
                               value="<?php echo ($edit_siswa && isset($edit_siswa['tanggal_lahir']) && $edit_siswa['tanggal_lahir']) ? date('Y-m-d', strtotime($edit_siswa['tanggal_lahir'])) : ''; ?>" 
                               required>
                        <small class="text-muted">Tanggal lahir diperlukan untuk verifikasi saat mengerjakan ujian</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="no_hp" class="form-label">Nomor Handphone</label>
                        <input type="text" class="form-control" id="no_hp" 
                               name="no_hp" 
                               value="<?php echo escape($edit_siswa['no_hp'] ?? ''); ?>" 
                               placeholder="08xxxxxxxxxx">
                        <small class="text-muted">Nomor handphone (opsional)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Peserta Didik dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_excel">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Petunjuk:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Download template Excel terlebih dahulu</li>
                            <li>Format kolom: KLS, NISN, NIS, NAMA, L/P</li>
                            <li>NISN akan digunakan sebagai username untuk login</li>
                            <li>NIS akan digunakan sebagai password untuk login</li>
                            <li>Nama kelas harus sesuai dengan kelas yang sudah ada di sistem</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_import" class="form-label">File Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_import" name="file_import" accept=".xlsx,.xls" required>
                        <small class="text-muted">Format file: .xlsx atau .xls (Excel 2007 atau lebih baru)</small>
                    </div>
                    
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

<?php if ($edit_siswa): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('addSiswaModal'));
        modal.show();
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

