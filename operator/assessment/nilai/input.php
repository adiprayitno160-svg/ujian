<?php
/**
 * Input Nilai Manual - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator input nilai manual setiap kelas dan setiap mata pelajaran
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Input Nilai Manual';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';
$id_kelas = intval($_GET['id_kelas'] ?? 0);
$id_mapel = intval($_GET['id_mapel'] ?? 0);
$tingkat = $_GET['tingkat'] ?? '';

// Get all mapel
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get kelas
$sql_kelas = "SELECT * FROM kelas WHERE tahun_ajaran = ?";
$params_kelas = [$tahun_ajaran];
if ($tingkat) {
    $sql_kelas .= " AND tingkat = ?";
    $params_kelas[] = $tingkat;
}
$sql_kelas .= " ORDER BY nama_kelas ASC";
$stmt = $pdo->prepare($sql_kelas);
$stmt->execute($params_kelas);
$kelas_list = $stmt->fetchAll();

// Get siswa based on filter
$sql_siswa = "SELECT DISTINCT u.id, u.nama, u.username, u.no_hp, uk.id_kelas
              FROM users u
              INNER JOIN user_kelas uk ON u.id = uk.id_user
              WHERE u.role = 'siswa'
              AND uk.tahun_ajaran = ?";
$params_siswa = [$tahun_ajaran];

if ($id_kelas) {
    $sql_siswa .= " AND uk.id_kelas = ?";
    $params_siswa[] = $id_kelas;
}

if ($semester) {
    $sql_siswa .= " AND uk.semester = ?";
    $params_siswa[] = $semester;
}

$sql_siswa .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($sql_siswa);
$stmt->execute($params_siswa);
$siswa_list = $stmt->fetchAll();

// Get nilai existing untuk siswa dan mapel yang dipilih
$nilai_data = [];
if (!empty($siswa_list) && $id_mapel) {
    foreach ($siswa_list as $siswa) {
        // Get nilai from nilai_semua_mapel untuk mapel tertentu
        $stmt = $pdo->prepare("SELECT nilai, id 
                              FROM nilai_semua_mapel
                              WHERE id_siswa = ? 
                              AND id_mapel = ?
                              AND tahun_ajaran = ?
                              AND semester = ?
                              AND (id_ujian IS NULL OR tipe_asesmen = 'manual')
                              ORDER BY updated_at DESC
                              LIMIT 1");
        $stmt->execute([$siswa['id'], $id_mapel, $tahun_ajaran, $semester]);
        $nilai = $stmt->fetch();
        $nilai_data[$siswa['id']] = $nilai ? $nilai['nilai'] : null;
    }
}

// Get tahun ajaran list
$tahun_ajaran_list = $pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas WHERE tahun_ajaran IS NOT NULL ORDER BY tahun_ajaran DESC")->fetchAll(PDO::FETCH_COLUMN);

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Input Nilai Manual</h2>
                <p class="text-muted mb-0">Input nilai manual untuk setiap kelas dan setiap mata pelajaran</p>
            </div>
            <div>
                <a href="<?php echo base_url('operator-assessment-nilai-form'); ?>" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> Lihat Nilai
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran" onchange="this.form.submit()">
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo $ta; ?>" <?php echo $tahun_ajaran === $ta ? 'selected' : ''; ?>>
                            <?php echo $ta; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester" onchange="this.form.submit()">
                    <option value="ganjil" <?php echo $semester === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester === 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat</label>
                <select class="form-select" name="tingkat" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="VII" <?php echo $tingkat === 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $tingkat === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $tingkat === 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select class="form-select" name="id_kelas" required onchange="this.form.submit()">
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $id_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                <select class="form-select" name="id_mapel" required onchange="this.form.submit()">
                    <option value="">Pilih Mapel</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $id_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($id_kelas && $id_mapel && !empty($siswa_list)): ?>
    <!-- Form Input Nilai -->
    <form method="POST" action="<?php echo base_url('operator-assessment-nilai-save'); ?>" id="formNilai">
        <input type="hidden" name="tahun_ajaran" value="<?php echo escape($tahun_ajaran); ?>">
        <input type="hidden" name="semester" value="<?php echo escape($semester); ?>">
        <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
        <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-edit"></i> Input Nilai Manual
                        <?php 
                        $selected_kelas = array_filter($kelas_list, fn($k) => $k['id'] == $id_kelas);
                        $selected_mapel = array_filter($mapel_list, fn($m) => $m['id'] == $id_mapel);
                        if (!empty($selected_kelas) && !empty($selected_mapel)) {
                            $kelas = reset($selected_kelas);
                            $mapel = reset($selected_mapel);
                            echo escape($kelas['nama_kelas']) . ' - ' . escape($mapel['nama_mapel']);
                        }
                        ?>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-light" onclick="fillAllEmpty()">
                            <i class="fas fa-magic"></i> Isi Semua Kosong
                        </button>
                        <button type="submit" class="btn btn-sm btn-success">
                            <i class="fas fa-save"></i> Simpan Semua
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th style="width: 150px;">Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_list as $index => $siswa): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($siswa['username']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td>
                                    <input type="number" 
                                           class="form-control form-control-sm nilai-input" 
                                           name="nilai[<?php echo $siswa['id']; ?>]" 
                                           value="<?php echo $nilai_data[$siswa['id']] !== null ? number_format($nilai_data[$siswa['id']], 2, '.', '') : ''; ?>"
                                           min="0" 
                                           max="100" 
                                           step="0.01"
                                           placeholder="0.00">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> 
                        Nilai dapat diisi dari 0.00 sampai 100.00. Kosongkan jika tidak ada nilai.
                    </small>
                </div>
            </div>
        </div>
    </form>
<?php elseif ($id_kelas && $id_mapel && empty($siswa_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Tidak ada siswa ditemukan untuk kelas dan semester yang dipilih.
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Silakan pilih <strong>Kelas</strong> dan <strong>Mata Pelajaran</strong> terlebih dahulu untuk mulai input nilai.
    </div>
<?php endif; ?>

<script>
function fillAllEmpty() {
    const inputs = document.querySelectorAll('.nilai-input');
    inputs.forEach(input => {
        if (!input.value || input.value.trim() === '') {
            input.value = '';
            input.focus();
            return;
        }
    });
    // Focus ke input pertama yang kosong
    inputs.forEach(input => {
        if (!input.value || input.value.trim() === '') {
            input.focus();
            return;
        }
    });
}

// Auto-save warning
let formChanged = false;
document.querySelectorAll('.nilai-input').forEach(input => {
    input.addEventListener('change', function() {
        formChanged = true;
    });
});

window.addEventListener('beforeunload', function(e) {
    if (formChanged) {
        e.preventDefault();
        e.returnValue = '';
    }
});

document.getElementById('formNilai')?.addEventListener('submit', function() {
    formChanged = false;
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

