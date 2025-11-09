<?php
/**
 * Manage Siswa - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Operator dapat menambah, edit, hapus siswa dan assign ke kelas
 * Juga dapat import siswa dari Excel
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    redirect('');
}

$page_title = 'Kelola Siswa';
$role_css = 'operator';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';
$import_results = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama = sanitize($_POST['nama'] ?? '');
        $nis = sanitize($_POST['nis'] ?? '');
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        
        if (empty($nama) || empty($nis) || empty($id_kelas)) {
            $error = 'Semua field wajib harus diisi';
        } else {
            try {
                // Check if NIS already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa'");
                $stmt->execute([$nis]);
                if ($stmt->fetch()) {
                    $error = 'NIS sudah digunakan';
                } else {
                    // Password menggunakan NIS (plain untuk login dengan NIS)
                    $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                    
                    // Create user siswa
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'siswa', ?, 'active')");
                    $stmt->execute([$nis, $hashed_password, $nama]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Assign to kelas
                    $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
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
                    
                    $success = 'Siswa berhasil ditambahkan';
                    log_activity('create_siswa', 'users', $user_id);
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'NIS sudah digunakan';
                } else {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nama = sanitize($_POST['nama'] ?? '');
        $nis = sanitize($_POST['nis'] ?? '');
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        
        if (empty($nama) || empty($nis) || empty($id_kelas)) {
            $error = 'Semua field wajib harus diisi';
        } else {
            try {
                // Check if NIS already exists for other user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa' AND id != ?");
                $stmt->execute([$nis, $id]);
                if ($stmt->fetch()) {
                    $error = 'NIS sudah digunakan oleh siswa lain';
                } else {
                    // Update user
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, nama = ? WHERE id = ? AND role = 'siswa'");
                    $stmt->execute([$nis, $nama, $id]);
                    
                    // Update password jika NIS berubah
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $old_user = $stmt->fetch();
                    if ($old_user && $old_user['username'] !== $nis) {
                        $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $id]);
                    }
                    
                    // Update kelas
                    $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
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
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'siswa'");
                $stmt->execute([$id]);
                $success = 'Siswa berhasil dihapus';
                log_activity('delete_siswa', 'users', $id);
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reset_password') {
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'siswa'");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                if ($user) {
                    $hashed_password = password_hash($user['username'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $id]);
                    $success = 'Password berhasil direset (menggunakan NIS)';
                    log_activity('reset_password_siswa', 'users', $id);
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
            
            if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
                $error = 'File harus berformat Excel (.xlsx, .xls) atau CSV (.csv)';
            } else {
                try {
                    $pdo->beginTransaction();
                    $imported = 0;
                    $skipped = 0;
                    $errors = [];
                    
                    $tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
                    
                    // Get kelas mapping (nama kelas to ID)
                    $stmt = $pdo->query("SELECT id, nama_kelas FROM kelas WHERE status = 'active'");
                    $kelas_map = [];
                    while ($row = $stmt->fetch()) {
                        $kelas_map[strtolower(trim($row['nama_kelas']))] = $row['id'];
                    }
                    
                    if ($extension === 'csv') {
                        // CSV import
                        $handle = fopen($file['tmp_name'], 'r');
                        
                        // Skip BOM if present
                        $first_line = fgets($handle);
                        if (substr($first_line, 0, 3) === "\xEF\xBB\xBF") {
                            $first_line = substr($first_line, 3);
                        }
                        rewind($handle);
                        
                        // Read header
                        $header = fgetcsv($handle);
                        if (!$header) {
                            throw new Exception('File CSV tidak valid');
                        }
                        
                        // Normalize header
                        $header = array_map('trim', $header);
                        $header = array_map('strtolower', $header);
                        
                        // Find column indices
                        $nis_col = array_search('nis', $header);
                        $nama_col = array_search('nama', $header);
                        $kelas_col = array_search('kelas', $header);
                        
                        if ($nis_col === false || $nama_col === false || $kelas_col === false) {
                            throw new Exception('Format CSV tidak valid. Kolom harus: NIS, Nama, Kelas');
                        }
                        
                        $line_number = 1;
                        while (($row = fgetcsv($handle)) !== false) {
                            $line_number++;
                            
                            if (count($row) < 3) continue;
                            
                            $nis = trim($row[$nis_col] ?? '');
                            $nama = trim($row[$nama_col] ?? '');
                            $kelas_nama = trim($row[$kelas_col] ?? '');
                            
                            if (empty($nis) || empty($nama) || empty($kelas_nama)) {
                                $skipped++;
                                $errors[] = "Baris $line_number: Data tidak lengkap (NIS, Nama, atau Kelas kosong)";
                                continue;
                            }
                            
                            // Find kelas ID
                            $kelas_lower = strtolower($kelas_nama);
                            if (!isset($kelas_map[$kelas_lower])) {
                                $skipped++;
                                $errors[] = "Baris $line_number: Kelas '$kelas_nama' tidak ditemukan";
                                continue;
                            }
                            $id_kelas = $kelas_map[$kelas_lower];
                            
                            // Check if NIS already exists
                            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa'");
                            $stmt->execute([$nis]);
                            if ($stmt->fetch()) {
                                $skipped++;
                                $errors[] = "Baris $line_number: NIS '$nis' sudah digunakan";
                                continue;
                            }
                            
                            // Create user
                            $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'siswa', ?, 'active')");
                            $stmt->execute([$nis, $hashed_password, $nama]);
                            $user_id = $pdo->lastInsertId();
                            
                            // Assign to kelas
                            $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran, semester) VALUES (?, ?, ?, 'ganjil')");
                            $stmt->execute([$user_id, $id_kelas, $tahun_ajaran]);
                            
                            $imported++;
                        }
                        
                        fclose($handle);
                    } elseif (in_array($extension, ['xlsx', 'xls'])) {
                        // Excel import using PhpSpreadsheet if available, else use CSV conversion
                        if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
                            // Use PhpSpreadsheet
                            require_once __DIR__ . '/../vendor/autoload.php';
                            
                            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file['tmp_name']);
                            $reader->setReadDataOnly(true);
                            $spreadsheet = $reader->load($file['tmp_name']);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $rows = $worksheet->toArray();
                            
                            if (empty($rows)) {
                                throw new Exception('File Excel kosong');
                            }
                            
                            // Get header (first row)
                            $header = array_map('trim', array_map('strtolower', $rows[0]));
                            
                            // Find column indices
                            $nis_col = array_search('nis', $header);
                            $nama_col = array_search('nama', $header);
                            $kelas_col = array_search('kelas', $header);
                            
                            if ($nis_col === false || $nama_col === false || $kelas_col === false) {
                                throw new Exception('Format Excel tidak valid. Kolom harus: NIS, Nama, Kelas');
                            }
                            
                            // Process rows (skip header)
                            for ($i = 1; $i < count($rows); $i++) {
                                $row = $rows[$i];
                                
                                if (count($row) < 3) continue;
                                
                                $nis = trim($row[$nis_col] ?? '');
                                $nama = trim($row[$nama_col] ?? '');
                                $kelas_nama = trim($row[$kelas_col] ?? '');
                                
                                if (empty($nis) || empty($nama) || empty($kelas_nama)) {
                                    $skipped++;
                                    $errors[] = "Baris " . ($i + 1) . ": Data tidak lengkap";
                                    continue;
                                }
                                
                                // Find kelas ID
                                $kelas_lower = strtolower($kelas_nama);
                                if (!isset($kelas_map[$kelas_lower])) {
                                    $skipped++;
                                    $errors[] = "Baris " . ($i + 1) . ": Kelas '$kelas_nama' tidak ditemukan";
                                    continue;
                                }
                                $id_kelas = $kelas_map[$kelas_lower];
                                
                                // Check if NIS already exists
                                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND role = 'siswa'");
                                $stmt->execute([$nis]);
                                if ($stmt->fetch()) {
                                    $skipped++;
                                    $errors[] = "Baris " . ($i + 1) . ": NIS '$nis' sudah digunakan";
                                    continue;
                                }
                                
                                // Create user
                                $hashed_password = password_hash($nis, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, status) VALUES (?, ?, 'siswa', ?, 'active')");
                                $stmt->execute([$nis, $hashed_password, $nama]);
                                $user_id = $pdo->lastInsertId();
                                
                                // Assign to kelas
                                $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran, semester) VALUES (?, ?, ?, 'ganjil')");
                                $stmt->execute([$user_id, $id_kelas, $tahun_ajaran]);
                                
                                $imported++;
                            }
                        } else {
                            // PhpSpreadsheet not available, show error with CSV alternative
                            $error = 'Library PhpSpreadsheet tidak tersedia. Silakan gunakan format CSV atau install PhpSpreadsheet. Untuk install: composer require phpoffice/phpspreadsheet';
                        }
                    }
                    
                    if (!$error) {
                        $pdo->commit();
                        $import_results = [
                            'imported' => $imported,
                            'skipped' => $skipped,
                            'errors' => $errors
                        ];
                        $success = "Berhasil mengimport $imported siswa" . ($skipped > 0 ? ", $skipped data dilewati" : "");
                        log_activity('import_siswa', 'users', null);
                    } else {
                        $pdo->rollBack();
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
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
$tahun_ajaran = date('Y') . '/' . (date('Y') + 1);
$query = "SELECT u.id, u.username as nis, u.nama, u.status, u.created_at,
          k.id as id_kelas, k.nama_kelas
          FROM users u
          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          LEFT JOIN kelas k ON uk.id_kelas = k.id
          WHERE u.role = 'siswa'";
$params = [$tahun_ajaran];

if ($search) {
    $query .= " AND (u.nama LIKE ? OR u.username LIKE ?)";
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
    $stmt = $pdo->prepare("SELECT u.*, k.id as id_kelas FROM users u
                          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
                          LEFT JOIN kelas k ON uk.id_kelas = k.id
                          WHERE u.id = ? AND u.role = 'siswa'");
    $stmt->execute([date('Y') . '/' . (date('Y') + 1), $edit_id]);
    $edit_siswa = $stmt->fetch();
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

<?php if ($import_results): ?>
    <div class="alert alert-info" role="alert">
        <h5><i class="fas fa-info-circle"></i> Hasil Import</h5>
        <ul class="mb-0">
            <li>Berhasil diimport: <strong><?php echo $import_results['imported']; ?></strong> siswa</li>
            <li>Dilewati: <strong><?php echo $import_results['skipped']; ?></strong> data</li>
        </ul>
        <?php if (!empty($import_results['errors'])): ?>
            <hr>
            <h6>Detail Error:</h6>
            <ul class="small mb-0">
                <?php foreach (array_slice($import_results['errors'], 0, 10) as $err): ?>
                    <li><?php echo escape($err); ?></li>
                <?php endforeach; ?>
                <?php if (count($import_results['errors']) > 10): ?>
                    <li class="text-muted">... dan <?php echo count($import_results['errors']) - 10; ?> error lainnya</li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold">Kelola Siswa</h3>
        <p class="text-muted">Manajemen data siswa - Login menggunakan NIS dan password NIS</p>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="fas fa-file-excel"></i> Import Excel
        </button>
        <a href="<?php echo base_url('operator/manage_siswa_template.php'); ?>" class="btn btn-outline-success me-2">
            <i class="fas fa-download"></i> Download Template
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSiswaModal">
            <i class="fas fa-plus"></i> Tambah Siswa
        </button>
    </div>
</div>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Cari nama atau NIS..." value="<?php echo escape($search); ?>">
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
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada data siswa</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($siswa_list as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
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
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password siswa ini? Password akan diubah menjadi NIS.');">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="id" value="<?php echo $siswa['id']; ?>">
                                            <button type="submit" class="btn btn-outline-warning" title="Reset Password">
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
                        <label for="nis" class="form-label">NIS <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nis" name="nis" 
                               value="<?php echo escape($edit_siswa['username'] ?? ''); ?>" required
                               placeholder="Nomor Induk Siswa">
                        <small class="text-muted">NIS akan digunakan sebagai username dan password untuk login</small>
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
                <h5 class="modal-title">Import Siswa dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="import_excel">
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Format File:</h6>
                        <ul class="mb-0 small">
                            <li>File harus berformat CSV atau Excel (.xlsx, .xls)</li>
                            <li>Baris pertama adalah header: <strong>NIS, Nama, Kelas</strong></li>
                            <li>Nama kelas harus sesuai dengan kelas yang sudah ada di sistem</li>
                            <li>NIS harus unik (tidak boleh duplikat)</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_import" class="form-label">Pilih File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_import" name="file_import" 
                               accept=".csv,.xlsx,.xls" required>
                        <small class="text-muted">Format: CSV, XLSX, atau XLS</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Catatan:</strong> NIS yang sudah ada akan dilewati. Pastikan nama kelas sudah sesuai dengan kelas di sistem.
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="<?php echo base_url('operator/manage_siswa_template.php'); ?>" class="btn btn-outline-success">
                        <i class="fas fa-download"></i> Download Template
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">
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

