<?php
/**
 * Ledger Nilai Manual - Form Nilai dari Penilaian Manual
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Menampilkan nilai dari input nilai manual guru mata pelajaran
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Ledger Nilai Manual';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';
$id_kelas = intval($_GET['id_kelas'] ?? 0);
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
$sql_siswa = "SELECT DISTINCT u.id, u.nama, u.username, u.no_hp, k.nama_kelas, k.id as id_kelas
              FROM users u
              INNER JOIN user_kelas uk ON u.id = uk.id_user
              INNER JOIN kelas k ON uk.id_kelas = k.id
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

if ($tingkat) {
    $sql_siswa .= " AND k.tingkat = ?";
    $params_siswa[] = $tingkat;
}

$sql_siswa .= " ORDER BY k.nama_kelas ASC, u.nama ASC";

$stmt = $pdo->prepare($sql_siswa);
$stmt->execute($params_siswa);
$siswa_list = $stmt->fetchAll();

// Check if aktif column exists
try {
    $check_aktif = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_aktif->rowCount() > 0;
} catch (PDOException $e) {
    $aktif_column_exists = false;
}

// Get nilai from penilaian_manual for all siswa and mapel
$nilai_data = [];
if (!empty($siswa_list) && !empty($mapel_list)) {
    foreach ($siswa_list as $siswa) {
        $nilai_data[$siswa['id']] = [];
        foreach ($mapel_list as $mapel) {
            // Get nilai from penilaian_manual
            // Only get approved nilai, prioritize aktif = 1 if column exists
            if ($aktif_column_exists) {
                // First try to get aktif = 1
                $stmt = $pdo->prepare("SELECT nilai_akhir 
                                      FROM penilaian_manual
                                      WHERE id_siswa = ? 
                                      AND id_mapel = ?
                                      AND tahun_ajaran = ?
                                      AND semester = ?
                                      AND status = 'approved'
                                      AND aktif = 1
                                      ORDER BY id DESC
                                      LIMIT 1");
                $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                $nilai = $stmt->fetch();
                
                // If no aktif = 1, get any approved
                if (!$nilai) {
                    $stmt = $pdo->prepare("SELECT nilai_akhir 
                                          FROM penilaian_manual
                                          WHERE id_siswa = ? 
                                          AND id_mapel = ?
                                          AND tahun_ajaran = ?
                                          AND semester = ?
                                          AND status = 'approved'
                                          ORDER BY id DESC
                                          LIMIT 1");
                    $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                    $nilai = $stmt->fetch();
                }
            } else {
                // If aktif column doesn't exist, just get approved
                $stmt = $pdo->prepare("SELECT nilai_akhir 
                                      FROM penilaian_manual
                                      WHERE id_siswa = ? 
                                      AND id_mapel = ?
                                      AND tahun_ajaran = ?
                                      AND semester = ?
                                      AND status = 'approved'
                                      ORDER BY id DESC
                                      LIMIT 1");
                $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                $nilai = $stmt->fetch();
            }
            
            $nilai_data[$siswa['id']][$mapel['id']] = $nilai ? $nilai['nilai_akhir'] : null;
        }
    }
}

// Get tahun ajaran list - ambil dari tabel tahun_ajaran (Kelola Tahun Ajaran)
$tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
$tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Ledger Nilai Manual</h2>
                <p class="text-muted mb-0">Nilai dari input nilai manual guru mata pelajaran</p>
            </div>
            <div>
                <button class="btn btn-success" onclick="exportExcel()">
                    <i class="fas fa-file-excel"></i> Export Excel
                </button>
                <button class="btn btn-danger" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran">
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo $ta; ?>" <?php echo $tahun_ajaran === $ta ? 'selected' : ''; ?>>
                            <?php echo $ta; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="ganjil" <?php echo $semester === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester === 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua</option>
                    <option value="VII" <?php echo $tingkat === 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $tingkat === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $tingkat === 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $id_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Form Nilai Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="tableNilai">
                <thead class="table-light">
                    <tr>
                        <th>No</th>
                        <th>Nama Siswa</th>
                        <th>NIS</th>
                        <?php if (!$id_kelas): ?>
                            <th>Kelas</th>
                        <?php endif; ?>
                        <?php foreach ($mapel_list as $mapel): ?>
                            <th><?php echo escape($mapel['nama_mapel']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="<?php echo ($id_kelas ? 3 : 4) + count($mapel_list); ?>" class="text-center text-muted">
                                Tidak ada siswa ditemukan
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($siswa_list as $index => $siswa): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($siswa['nama']); ?></td>
                            <td><?php echo escape($siswa['username']); ?></td>
                            <?php if (!$id_kelas): ?>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                            <?php endif; ?>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <td class="text-center">
                                    <?php 
                                    $nilai = $nilai_data[$siswa['id']][$mapel['id']] ?? null;
                                    if ($nilai !== null) {
                                        echo number_format($nilai, 2);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportExcel() {
    // Implement Excel export
    const params = new URLSearchParams({
        format: 'excel',
        tahun_ajaran: '<?php echo $tahun_ajaran; ?>',
        semester: '<?php echo $semester; ?>',
        id_kelas: '<?php echo $id_kelas; ?>',
        tingkat: '<?php echo $tingkat; ?>'
    });
    window.location.href = '<?php echo base_url('operator-ledger-nilai-export'); ?>?' + params.toString();
}

function exportPDF() {
    // Implement PDF export
    const params = new URLSearchParams({
        format: 'pdf',
        tahun_ajaran: '<?php echo $tahun_ajaran; ?>',
        semester: '<?php echo $semester; ?>',
        id_kelas: '<?php echo $id_kelas; ?>',
        tingkat: '<?php echo $tingkat; ?>'
    });
    window.location.href = '<?php echo base_url('operator-ledger-nilai-export'); ?>?' + params.toString();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

