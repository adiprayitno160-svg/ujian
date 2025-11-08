<?php
/**
 * Manage Siswa - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Hanya menampilkan siswa di kelas yang pernah di-assign ke ujian/PR yang dibuat oleh guru
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('guru');
check_session_timeout();

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$page_title = 'Kelola Siswa';
$role_css = 'guru';
include __DIR__ . '/../includes/header.php';

global $pdo;

$guru_id = $_SESSION['user_id'];

// Get filter
$search = sanitize($_GET['search'] ?? '');
$kelas_filter = intval($_GET['kelas'] ?? 0);

// Build query - hanya siswa di kelas yang pernah di-assign ke ujian/PR yang dibuat oleh guru
$tahun_ajaran = date('Y') . '/' . (date('Y') + 1);

// Get kelas IDs yang pernah di-assign ke ujian/PR yang dibuat oleh guru ini
// 1. Dari ujian_kelas (jika ada)
// 2. Dari sesi_peserta dengan tipe_assign = 'kelas'
// 3. Dari pr_kelas (jika ada tabel ini)
$stmt = $pdo->prepare("
    SELECT DISTINCT uk.id_kelas
    FROM (
        -- Kelas dari ujian_kelas
        SELECT DISTINCT id_kelas 
        FROM ujian_kelas 
        WHERE id_ujian IN (SELECT id FROM ujian WHERE id_guru = ?)
        
        UNION
        
        -- Kelas dari sesi_peserta (kelas assignment)
        SELECT DISTINCT sp.id_kelas 
        FROM sesi_peserta sp
        INNER JOIN sesi_ujian su ON sp.id_sesi = su.id
        INNER JOIN ujian u ON su.id_ujian = u.id
        WHERE u.id_guru = ? AND sp.tipe_assign = 'kelas' AND sp.id_kelas IS NOT NULL
        
        UNION
        
        -- Kelas dari PR (jika ada)
        SELECT DISTINCT pk.id_kelas 
        FROM pr_kelas pk
        INNER JOIN pr p ON pk.id_pr = p.id
        WHERE p.id_guru = ?
    ) uk
    WHERE uk.id_kelas IS NOT NULL
");
$stmt->execute([$guru_id, $guru_id, $guru_id]);
$assigned_kelas_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get siswa IDs yang pernah di-assign secara individual
$stmt = $pdo->prepare("
    SELECT DISTINCT sp.id_user
    FROM sesi_peserta sp
    INNER JOIN sesi_ujian su ON sp.id_sesi = su.id
    INNER JOIN ujian u ON su.id_ujian = u.id
    WHERE u.id_guru = ? AND sp.tipe_assign = 'individual' AND sp.id_user IS NOT NULL
");
$stmt->execute([$guru_id]);
$assigned_siswa_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build main query
$query = "SELECT DISTINCT u.id, u.username as nis, u.nama, u.status, u.created_at,
          k.id as id_kelas, k.nama_kelas
          FROM users u
          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          LEFT JOIN kelas k ON uk.id_kelas = k.id
          WHERE u.role = 'siswa' AND u.status = 'active'";
$params = [$tahun_ajaran];

// Filter by assigned kelas or individual assignment
if (!empty($assigned_kelas_ids) || !empty($assigned_siswa_ids)) {
    $query .= " AND (";
    $conditions = [];
    
    if (!empty($assigned_kelas_ids)) {
        $placeholders = implode(',', array_fill(0, count($assigned_kelas_ids), '?'));
        $query .= "k.id IN ($placeholders)";
        $params = array_merge($params, $assigned_kelas_ids);
    }
    
    if (!empty($assigned_siswa_ids)) {
        if (!empty($assigned_kelas_ids)) {
            $query .= " OR ";
        }
        $placeholders = implode(',', array_fill(0, count($assigned_siswa_ids), '?'));
        $query .= "u.id IN ($placeholders)";
        $params = array_merge($params, $assigned_siswa_ids);
    }
    
    $query .= ")";
} else {
    // Jika guru belum pernah assign kelas/siswa, tampilkan kosong
    $query .= " AND 1 = 0";
}

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

// Get kelas list (hanya kelas yang pernah di-assign)
$kelas_list = [];
if (!empty($assigned_kelas_ids)) {
    $placeholders = implode(',', array_fill(0, count($assigned_kelas_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id IN ($placeholders) AND status = 'active' ORDER BY nama_kelas ASC");
    $stmt->execute($assigned_kelas_ids);
    $kelas_list = $stmt->fetchAll();
}
?>

<?php if (empty($assigned_kelas_ids) && empty($assigned_siswa_ids)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        Belum ada siswa yang di-assign ke ujian/PR Anda. 
        Siswa akan muncul setelah Anda meng-assign kelas atau siswa ke ujian/PR yang Anda buat.
    </div>
<?php else: ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <h3 class="fw-bold">Kelola Siswa</h3>
            <p class="text-muted">Daftar siswa di kelas yang pernah di-assign ke ujian/PR Anda</p>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($siswa_list)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Tidak ada data siswa</td>
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
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

