<?php
/**
 * Absensi Report - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
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

$page_title = 'Absensi Report';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'id_kelas' => intval($_GET['id_kelas'] ?? 0),
    'tanggal_mulai' => $_GET['tanggal_mulai'] ?? '',
    'tanggal_selesai' => $_GET['tanggal_selesai'] ?? ''
];

// Get absensi statistics
$stats = [
    'total' => 0,
    'hadir' => 0,
    'tidak_hadir' => 0,
    'izin' => 0,
    'sakit' => 0
];

$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status_absen = 'hadir' THEN 1 ELSE 0 END) as hadir,
    SUM(CASE WHEN status_absen = 'tidak_hadir' THEN 1 ELSE 0 END) as tidak_hadir,
    SUM(CASE WHEN status_absen = 'izin' THEN 1 ELSE 0 END) as izin,
    SUM(CASE WHEN status_absen = 'sakit' THEN 1 ELSE 0 END) as sakit
    FROM absensi_ujian a
    INNER JOIN sesi_ujian s ON a.id_sesi = s.id
    INNER JOIN ujian u ON s.id_ujian = u.id
    WHERE (u.tipe_asesmen IS NOT NULL AND u.tipe_asesmen != '')";

$params_stats = [];

if ($filters['id_kelas']) {
    $sql_stats .= " AND EXISTS (
        SELECT 1 FROM sesi_peserta sp
        INNER JOIN user_kelas uk ON sp.id_kelas = uk.id_kelas
        WHERE sp.id_sesi = s.id AND uk.id_user = a.id_siswa AND uk.id_kelas = ?
    )";
    $params_stats[] = $filters['id_kelas'];
}

if ($filters['tanggal_mulai']) {
    $sql_stats .= " AND DATE(a.waktu_absen) >= ?";
    $params_stats[] = $filters['tanggal_mulai'];
}

if ($filters['tanggal_selesai']) {
    $sql_stats .= " AND DATE(a.waktu_absen) <= ?";
    $params_stats[] = $filters['tanggal_selesai'];
}

$stmt = $pdo->prepare($sql_stats);
$stmt->execute($params_stats);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get kelas
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? ORDER BY nama_kelas ASC");
$stmt->execute([$filters['tahun_ajaran']]);
$kelas_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Absensi Report</h2>
            <button class="btn btn-success" onclick="exportExcel()">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas">
                    <option value="">Semua</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $filters['id_kelas'] == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" name="tanggal_mulai" value="<?php echo $filters['tanggal_mulai']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Selesai</label>
                <input type="date" class="form-control" name="tanggal_selesai" value="<?php echo $filters['tanggal_selesai']; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Total Absensi</h6>
                <h3><?php echo $stats['total'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Hadir</h6>
                <h3 class="text-success"><?php echo $stats['hadir'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Tidak Hadir</h6>
                <h3 class="text-danger"><?php echo $stats['tidak_hadir'] ?? 0; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Izin/Sakit</h6>
                <h3 class="text-warning"><?php echo ($stats['izin'] ?? 0) + ($stats['sakit'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
</div>

<script>
function exportExcel() {
    window.location.href = '<?php echo base_url('operator-assessment-absensi-export?format=excel&id_kelas=' . $filters['id_kelas'] . '&tanggal_mulai=' . $filters['tanggal_mulai'] . '&tanggal_selesai=' . $filters['tanggal_selesai']); ?>';
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



