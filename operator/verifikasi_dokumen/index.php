<?php
/**
 * Check Verifikasi Dokumen - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator check berkas file verifikasi dengan filter bermasalah/residu
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Check Verifikasi Dokumen';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = sanitize($_GET['search'] ?? '');
$filter_bermasalah = isset($_GET['bermasalah']) ? intval($_GET['bermasalah']) : 0;
$filter_residu = isset($_GET['residu']) ? intval($_GET['residu']) : 0;

// Build query
$tahun_ajaran = get_tahun_ajaran_aktif();
$query = "SELECT u.id, u.nama, u.username as nis, 
          vds.*, k.nama_kelas
          FROM users u
          INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          INNER JOIN kelas k ON uk.id_kelas = k.id
          LEFT JOIN verifikasi_data_siswa vds ON u.id = vds.id_siswa
          WHERE u.role = 'siswa' AND k.tingkat = 'IX'";
$params = [$tahun_ajaran];

// Filter by status
if ($status_filter) {
    $query .= " AND vds.status_overall = ?";
    $params[] = $status_filter;
}

// Filter residu
if ($filter_residu) {
    $query .= " AND vds.status_overall = 'residu'";
}

// Search filter
if ($search) {
    $query .= " AND (u.nama LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$siswa_list_all = $stmt->fetchAll();

// Filter by file bermasalah (file yang tidak bisa dibaca)
// File bermasalah adalah file yang tidak bisa terbaca
$siswa_list = [];
foreach ($siswa_list_all as $siswa) {
    $has_bermasalah = has_file_bermasalah($siswa['id']);
    
    // Apply filter bermasalah if set
    if ($filter_bermasalah && !$has_bermasalah) {
        continue;
    }
    
    $siswa_list[] = $siswa;
}

// Get statistics
$stats = [
    'total' => 0,
    'belum_lengkap' => 0,
    'menunggu_verifikasi' => 0,
    'valid' => 0,
    'tidak_valid' => 0,
    'upload_ulang' => 0,
    'residu' => 0,
    'bermasalah' => 0
];

$stmt_stats = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN vds.status_overall = 'belum_lengkap' THEN 1 ELSE 0 END) as belum_lengkap,
    SUM(CASE WHEN vds.status_overall = 'menunggu_verifikasi' THEN 1 ELSE 0 END) as menunggu_verifikasi,
    SUM(CASE WHEN vds.status_overall = 'valid' THEN 1 ELSE 0 END) as valid,
    SUM(CASE WHEN vds.status_overall = 'tidak_valid' THEN 1 ELSE 0 END) as tidak_valid,
    SUM(CASE WHEN vds.status_overall = 'upload_ulang' THEN 1 ELSE 0 END) as upload_ulang,
    SUM(CASE WHEN vds.status_overall = 'residu' THEN 1 ELSE 0 END) as residu
    FROM users u
    INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
    INNER JOIN kelas k ON uk.id_kelas = k.id
    LEFT JOIN verifikasi_data_siswa vds ON u.id = vds.id_siswa
    WHERE u.role = 'siswa' AND k.tingkat = 'IX'");
$stmt_stats->execute([$tahun_ajaran]);
$stats_data = $stmt_stats->fetch();
if ($stats_data) {
    $stats = array_merge($stats, $stats_data);
}

// Count bermasalah (file yang tidak bisa dibaca)
// File bermasalah adalah file yang tidak bisa terbaca
$stmt_bermasalah = $pdo->prepare("SELECT DISTINCT u.id
    FROM users u
    INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
    INNER JOIN kelas k ON uk.id_kelas = k.id
    WHERE u.role = 'siswa' AND k.tingkat = 'IX'");
$stmt_bermasalah->execute([$tahun_ajaran]);
$all_siswa_ids = $stmt_bermasalah->fetchAll(PDO::FETCH_COLUMN);

$bermasalah_count = 0;
foreach ($all_siswa_ids as $id_siswa) {
    if (has_file_bermasalah($id_siswa)) {
        $bermasalah_count++;
    }
}
$stats['bermasalah'] = $bermasalah_count;
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Check Verifikasi Dokumen</h2>
                <p class="text-muted mb-0">Check berkas file verifikasi siswa kelas IX</p>
                <small class="text-muted">
                    <strong>File Bermasalah:</strong> File yang tidak bisa dibaca | 
                    <strong>Residu:</strong> Data tidak cocok antara dokumen (setelah upload ulang maksimal 1x)
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-users fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Siswa</h6>
                        <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-danger bg-opacity-10 rounded p-3">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Bermasalah</h6>
                        <h3 class="mb-0"><?php echo $stats['bermasalah']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-dark bg-opacity-10 rounded p-3">
                            <i class="fas fa-ban fa-2x text-dark"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Residu</h6>
                        <h3 class="mb-0"><?php echo $stats['residu']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Valid</h6>
                        <h3 class="mb-0"><?php echo $stats['valid']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Cari Nama/NIS</label>
                <input type="text" class="form-control" name="search" placeholder="Cari nama atau NIS..." 
                       value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="belum_lengkap" <?php echo $status_filter === 'belum_lengkap' ? 'selected' : ''; ?>>Belum Lengkap</option>
                    <option value="menunggu_verifikasi" <?php echo $status_filter === 'menunggu_verifikasi' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                    <option value="valid" <?php echo $status_filter === 'valid' ? 'selected' : ''; ?>>Valid</option>
                    <option value="tidak_valid" <?php echo $status_filter === 'tidak_valid' ? 'selected' : ''; ?>>Tidak Valid</option>
                    <option value="upload_ulang" <?php echo $status_filter === 'upload_ulang' ? 'selected' : ''; ?>>Upload Ulang</option>
                    <option value="residu" <?php echo $status_filter === 'residu' ? 'selected' : ''; ?>>Residu</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Filter Khusus</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="bermasalah" value="1" id="filterBermasalah" 
                           <?php echo $filter_bermasalah ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="filterBermasalah" title="File yang tidak bisa dibaca">
                        File Bermasalah
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="residu" value="1" id="filterResidu" 
                           <?php echo $filter_residu ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="filterResidu" title="Data tidak cocok antara dokumen">
                        Residu
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="<?php echo base_url('operator-verifikasi-dokumen-index'); ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($siswa_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada data ditemukan.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Status</th>
                            <th>File Bermasalah</th>
                            <th>Kesesuaian</th>
                            <th>Upload Ulang</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($siswa_list as $index => $siswa): ?>
                            <?php
                            // Get file bermasalah (file yang tidak bisa dibaca)
                            // File bermasalah adalah file yang tidak bisa terbaca
                            $doc_bermasalah = get_file_bermasalah($siswa['id']);
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($siswa['status_overall']): ?>
                                        <?php
                                        $status_badge = [
                                            'belum_lengkap' => ['bg' => 'secondary', 'text' => 'Belum Lengkap'],
                                            'menunggu_verifikasi' => ['bg' => 'warning', 'text' => 'Menunggu'],
                                            'valid' => ['bg' => 'success', 'text' => 'Valid'],
                                            'tidak_valid' => ['bg' => 'danger', 'text' => 'Tidak Valid'],
                                            'upload_ulang' => ['bg' => 'info', 'text' => 'Upload Ulang'],
                                            'residu' => ['bg' => 'dark', 'text' => 'Residu']
                                        ];
                                        $status_info = $status_badge[$siswa['status_overall']] ?? ['bg' => 'secondary', 'text' => $siswa['status_overall']];
                                        ?>
                                        <span class="badge bg-<?php echo $status_info['bg']; ?>">
                                            <?php echo $status_info['text']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum Ada Data</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($doc_bermasalah)): ?>
                                        <div class="small">
                                            <?php foreach ($doc_bermasalah as $doc): ?>
                                                <span class="badge bg-danger me-1" title="File tidak bisa dibaca">
                                                    <i class="fas fa-exclamation-circle"></i> <?php echo ucfirst($doc['jenis_dokumen']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($siswa['kesesuaian_nama_anak']): ?>
                                        <small>
                                            Anak: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_anak'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                                <?php echo $siswa['kesesuaian_nama_anak'] === 'sesuai' ? '✓' : '✗'; ?>
                                            </span><br>
                                            Ayah: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_ayah'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                                <?php echo $siswa['kesesuaian_nama_ayah'] === 'sesuai' ? '✓' : '✗'; ?>
                                            </span><br>
                                            Ibu: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_ibu'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                                <?php echo $siswa['kesesuaian_nama_ibu'] === 'sesuai' ? '✓' : '✗'; ?>
                                            </span>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $siswa['jumlah_upload_ulang'] ?? 0; ?> / <?php echo VERIFIKASI_MAX_UPLOAD_ULANG; ?>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('operator-verifikasi-dokumen-detail?id=' . $siswa['id']); ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

