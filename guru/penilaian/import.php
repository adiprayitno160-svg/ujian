<?php
/**
 * Import Penilaian Manual - Excel
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Import Penilaian Manual';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$guru_id = $_SESSION['user_id'];
$error = '';
$success = '';
$import_results = [];

// Process import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file_import'])) {
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $id_kelas = intval($_POST['id_kelas'] ?? 0);
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
    $semester = sanitize($_POST['semester'] ?? 'ganjil');
    
    if (!$id_mapel || !$id_kelas) {
        $error = 'Mata pelajaran dan kelas harus dipilih';
    } elseif ($_FILES['file_import']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file: ' . $_FILES['file_import']['error'];
    } else {
        $file = $_FILES['file_import'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['xlsx', 'xls'])) {
            $error = 'File harus berformat Excel (.xlsx atau .xls)';
        } else {
            try {
                $vendor_autoload = __DIR__ . '/../../vendor/autoload.php';
                if (!file_exists($vendor_autoload)) {
                    $error = 'PhpSpreadsheet library tidak ditemukan. Silakan install melalui composer: composer require phpoffice/phpspreadsheet';
                } else {
                    require_once $vendor_autoload;
                    
                    use PhpOffice\PhpSpreadsheet\IOFactory;
                    
                    $spreadsheet = IOFactory::load($file['tmp_name']);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();
                    
                    // Skip header rows (rows 1-7 are info and headers)
                    // Data starts from row 8 (index 7)
                    $data_start_row = 7;
                    $imported = 0;
                    $updated = 0;
                    $errors = [];
                    
                    // Get all siswa for this class to map NIS to ID
                    $stmt = $pdo->prepare("SELECT u.id, u.username as nis, u.nama
                                          FROM users u
                                          INNER JOIN user_kelas uk ON u.id = uk.id_user
                                          WHERE u.role = 'siswa' 
                                          AND u.status = 'active'
                                          AND uk.id_kelas = ?
                                          AND uk.tahun_ajaran = ?
                                          AND uk.semester = ?");
                    $stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
                    $siswa_list = $stmt->fetchAll();
                    
                    // Create NIS to ID mapping
                    $nis_to_id = [];
                    foreach ($siswa_list as $siswa) {
                        $nis_to_id[$siswa['nis']] = $siswa['id'];
                    }
                    
                    $pdo->beginTransaction();
                    
                    // Process rows starting from data_start_row
                    for ($i = $data_start_row; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        
                        // Skip empty rows
                        if (empty($row[1]) || empty($row[2])) { // NIS and Nama are required
                            continue;
                        }
                        
                        $nis = trim($row[1]);
                        $nama = trim($row[2]);
                        $nilai_uts = !empty($row[3]) ? floatval($row[3]) : null;
                        $nilai_akhir = !empty($row[4]) ? floatval($row[4]) : null;
                        $predikat = !empty($row[5]) ? strtoupper(trim($row[5])) : null;
                        $keterangan = !empty($row[6]) ? trim($row[6]) : null;
                        
                        // Validate NIS exists
                        if (!isset($nis_to_id[$nis])) {
                            $errors[] = "Baris " . ($i + 1) . ": NIS '$nis' tidak ditemukan di kelas ini";
                            continue;
                        }
                        
                        $id_siswa = $nis_to_id[$nis];
                        
                        // Validate nilai
                        if ($nilai_uts !== null && ($nilai_uts < 0 || $nilai_uts > 100)) {
                            $errors[] = "Baris " . ($i + 1) . ": Nilai UTS harus antara 0-100";
                            continue;
                        }
                        
                        if ($nilai_akhir !== null && ($nilai_akhir < 0 || $nilai_akhir > 100)) {
                            $errors[] = "Baris " . ($i + 1) . ": Nilai Akhir harus antara 0-100";
                            continue;
                        }
                        
                        // Validate predikat
                        if ($predikat !== null && !in_array($predikat, ['A', 'B', 'C', 'D'])) {
                            $errors[] = "Baris " . ($i + 1) . ": Predikat harus A, B, C, atau D";
                            continue;
                        }
                        
                        // Check if penilaian already exists
                        $stmt = $pdo->prepare("SELECT id, status FROM penilaian_manual
                                              WHERE id_guru = ?
                                              AND id_mapel = ?
                                              AND id_kelas = ?
                                              AND id_siswa = ?
                                              AND tahun_ajaran = ?
                                              AND semester = ?");
                        $stmt->execute([$guru_id, $id_mapel, $id_kelas, $id_siswa, $tahun_ajaran, $semester]);
                        $existing = $stmt->fetch();
                        
                        // Don't update if status is submitted or approved
                        if ($existing && ($existing['status'] === 'submitted' || $existing['status'] === 'approved')) {
                            $errors[] = "Baris " . ($i + 1) . ": Nilai untuk siswa '$nama' sudah dikumpulkan/disetujui dan tidak dapat diubah";
                            continue;
                        }
                        
                        if ($existing) {
                            // Update existing
                            $stmt = $pdo->prepare("UPDATE penilaian_manual
                                                  SET nilai_uts = ?, nilai_akhir = ?, predikat = ?, keterangan = ?, updated_at = NOW()
                                                  WHERE id = ?");
                            $stmt->execute([$nilai_uts, $nilai_akhir, $predikat, $keterangan, $existing['id']]);
                            $updated++;
                        } else {
                            // Insert new
                            $stmt = $pdo->prepare("INSERT INTO penilaian_manual
                                                  (id_guru, id_mapel, id_kelas, id_siswa, tahun_ajaran, semester,
                                                   nilai_uts, nilai_akhir, predikat, keterangan, status, created_at, updated_at)
                                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())");
                            $stmt->execute([$guru_id, $id_mapel, $id_kelas, $id_siswa, $tahun_ajaran, $semester,
                                           $nilai_uts, $nilai_akhir, $predikat, $keterangan]);
                            $imported++;
                        }
                    }
                    
                    if (!empty($errors)) {
                        $pdo->rollBack();
                        $error = "Import selesai dengan beberapa error:<br>" . implode("<br>", array_slice($errors, 0, 10));
                        if (count($errors) > 10) {
                            $error .= "<br>... dan " . (count($errors) - 10) . " error lainnya";
                        }
                    } else {
                        $pdo->commit();
                        $success = "Import berhasil! $imported data baru ditambahkan, $updated data diperbarui.";
                    }
                    
                    $import_results = [
                        'imported' => $imported,
                        'updated' => $updated,
                        'errors' => $errors
                    ];
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error importing file: ' . $e->getMessage();
                error_log("Import penilaian manual error: " . $e->getMessage());
            }
        }
    }
}

// Get mapel yang diajar oleh guru ini
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$guru_id]);
$mapel_list = $stmt->fetchAll();

// Get tahun ajaran list
$tahun_ajaran = get_tahun_ajaran_aktif();
$stmt = $pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas WHERE tahun_ajaran IS NOT NULL ORDER BY tahun_ajaran DESC");
$tahun_ajaran_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get kelas - berdasarkan tahun ajaran yang dipilih dan kelas yang di-assign ke guru untuk mapel tersebut
$id_mapel_selected = intval($_GET['id_mapel'] ?? $_POST['id_mapel'] ?? 0);
$tahun_ajaran_selected = sanitize($_GET['tahun_ajaran'] ?? $_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
$kelas_list = [];

if ($id_mapel_selected && $tahun_ajaran_selected) {
    // Get kelas yang di-assign ke guru untuk mapel ini, atau semua kelas jika tidak ada assignment khusus
    $stmt = $pdo->prepare("SELECT DISTINCT k.*
                          FROM kelas k
                          INNER JOIN guru_mapel_kelas gmk ON k.id = gmk.id_kelas
                          WHERE gmk.id_guru = ?
                          AND gmk.id_mapel = ?
                          AND k.tahun_ajaran = ?
                          AND k.status = 'active'
                          ORDER BY k.nama_kelas ASC");
    $stmt->execute([$guru_id, $id_mapel_selected, $tahun_ajaran_selected]);
    $kelas_list = $stmt->fetchAll();
    
    // Jika tidak ada kelas yang di-assign, tampilkan semua kelas untuk tahun ajaran tersebut
    if (empty($kelas_list)) {
        $stmt = $pdo->prepare("SELECT * FROM kelas 
                              WHERE tahun_ajaran = ? 
                              AND status = 'active'
                              ORDER BY nama_kelas ASC");
        $stmt->execute([$tahun_ajaran_selected]);
        $kelas_list = $stmt->fetchAll();
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Import Penilaian Manual</h2>
        <p class="text-muted">Import nilai dari file Excel yang sudah diisi dengan template.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-upload"></i> Upload File Excel
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="tahun_ajaran" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="tahun_ajaran" name="tahun_ajaran" required>
                            <?php foreach ($tahun_ajaran_list as $ta): ?>
                                <option value="<?php echo escape($ta); ?>" <?php echo $tahun_ajaran === $ta ? 'selected' : ''; ?>>
                                    <?php echo escape($ta); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" id="semester" name="semester" required>
                            <option value="ganjil" <?php echo ($_POST['semester'] ?? 'ganjil') === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                            <option value="genap" <?php echo ($_POST['semester'] ?? '') === 'genap' ? 'selected' : ''; ?>>Genap</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_mapel" name="id_mapel" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo $id_mapel_selected == $mapel['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_kelas" class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_kelas" name="id_kelas" required <?php echo empty($kelas_list) ? 'disabled' : ''; ?>>
                            <option value="">Pilih Kelas</option>
                            <?php if (!empty($kelas_list)): ?>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?php echo $kelas['id']; ?>" <?php echo (isset($_POST['id_kelas']) && $_POST['id_kelas'] == $kelas['id']) ? 'selected' : ''; ?>>
                                        <?php echo escape($kelas['nama_kelas']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Pilih mata pelajaran terlebih dahulu</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="file_import" class="form-label">File Excel <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="file_import" name="file_import" accept=".xlsx,.xls" required>
                        <small class="text-muted">Format file: .xlsx atau .xls (maksimal 5MB)</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Import
                    </button>
                    <a href="<?php echo base_url('guru-penilaian-list'); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle"></i> Petunjuk
                </h5>
            </div>
            <div class="card-body">
                <ol class="mb-0">
                    <li>Download template Excel terlebih dahulu</li>
                    <li>Isi kolom Nilai UTS, Nilai Akhir, Predikat, dan Keterangan</li>
                    <li>Jangan mengubah kolom No, NIS, dan Nama Siswa</li>
                    <li>Simpan file dan upload melalui form di sebelah</li>
                    <li>Pastikan format file adalah .xlsx atau .xls</li>
                </ol>
                
                <hr>
                
                <p class="mb-2">
                    <strong>Download Template:</strong>
                </p>
                <button type="button" class="btn btn-success btn-sm" id="btnDownloadTemplate" disabled>
                    <i class="fas fa-download"></i> Download Template
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var mapelSelect = document.getElementById('id_mapel');
    var kelasSelect = document.getElementById('id_kelas');
    var downloadBtn = document.getElementById('btnDownloadTemplate');
    
    // Enable download button when mapel and kelas are selected
    function updateDownloadButton() {
        var mapel = mapelSelect.value;
        var kelas = kelasSelect.value;
        var tahun_ajaran = document.getElementById('tahun_ajaran').value;
        var semester = document.getElementById('semester').value;
        
        if (mapel && kelas && tahun_ajaran && semester) {
            downloadBtn.disabled = false;
            downloadBtn.onclick = function() {
                var url = '<?php echo base_url('guru-penilaian-export-template'); ?>' +
                         '?id_mapel=' + mapel +
                         '&id_kelas=' + kelas +
                         '&tahun_ajaran=' + tahun_ajaran +
                         '&semester=' + semester;
                window.location.href = url;
            };
        } else {
            downloadBtn.disabled = true;
        }
    }
    
    // Update kelas list when mapel or tahun ajaran changes
    function updateKelasList() {
        var mapelId = mapelSelect.value;
        var tahunAjaran = document.getElementById('tahun_ajaran').value;
        
        if (mapelId && tahunAjaran) {
            // Reload page with selected values to get kelas list
            var url = new URL(window.location.href);
            url.searchParams.set('id_mapel', mapelId);
            url.searchParams.set('tahun_ajaran', tahunAjaran);
            window.location.href = url.toString();
        } else {
            kelasSelect.innerHTML = '<option value="">Pilih mata pelajaran dan tahun ajaran terlebih dahulu</option>';
            kelasSelect.disabled = true;
        }
        updateDownloadButton();
    }
    
    mapelSelect.addEventListener('change', updateKelasList);
    document.getElementById('tahun_ajaran').addEventListener('change', updateKelasList);
    kelasSelect.addEventListener('change', updateDownloadButton);
    document.getElementById('semester').addEventListener('change', updateDownloadButton);
    
    // Initial check
    updateDownloadButton();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

