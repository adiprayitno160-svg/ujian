<?php
/**
 * Daftar Verifikasi Dokumen - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Verifikasi Dokumen Kelas IX';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'toggle_menu') {
        $id_siswa = intval($_POST['id_siswa'] ?? 0);
        $menu_aktif = isset($_POST['menu_aktif']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE verifikasi_data_siswa SET menu_aktif = ? WHERE id_siswa = ?");
            $stmt->execute([$menu_aktif, $id_siswa]);
            $success = 'Menu berhasil diupdate';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$search = sanitize($_GET['search'] ?? '');

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

if ($status_filter) {
    $query .= " AND vds.status_overall = ?";
    $params[] = $status_filter;
}

if ($search) {
    $query .= " AND (u.nama LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY u.nama ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$siswa_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold">Verifikasi Dokumen Kelas IX</h3>
        <p class="text-muted">Kelola verifikasi dokumen siswa kelas IX</p>
    </div>
</div>

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

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Cari nama atau NIS..." 
                       value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-3">
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
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
            <div class="col-md-3 text-end">
                <a href="<?php echo base_url('admin/verifikasi_dokumen/settings.php'); ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-cog"></i> Pengaturan
                </a>
                <a href="<?php echo base_url('admin/verifikasi_dokumen/residu.php'); ?>" class="btn btn-outline-dark">
                    <i class="fas fa-exclamation-triangle"></i> Residu
                </a>
                <a href="<?php echo base_url('admin/verifikasi_dokumen/export.php'); ?>" class="btn btn-outline-success">
                    <i class="fas fa-download"></i> Export
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <?php
    $stats = [
        'belum_lengkap' => 0,
        'menunggu_verifikasi' => 0,
        'valid' => 0,
        'tidak_valid' => 0,
        'residu' => 0
    ];
    
    foreach ($siswa_list as $siswa) {
        $status = $siswa['status_overall'] ?? 'belum_lengkap';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    ?>
    
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-muted"><?php echo $stats['belum_lengkap']; ?></h3>
                <small>Belum Lengkap</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-warning"><?php echo $stats['menunggu_verifikasi']; ?></h3>
                <small>Menunggu</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo $stats['valid']; ?></h3>
                <small>Valid</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-danger"><?php echo $stats['tidak_valid']; ?></h3>
                <small>Tidak Valid</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-dark"><?php echo $stats['residu']; ?></h3>
                <small>Residu</small>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo count($siswa_list); ?></h3>
                <small>Total</small>
            </div>
        </div>
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
                        <th>Kesesuaian</th>
                        <th>Upload Ulang</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($siswa_list as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                                <td>
                                    <?php
                                    $status = $siswa['status_overall'] ?? 'belum_lengkap';
                                    $badge_class = [
                                        'belum_lengkap' => 'secondary',
                                        'menunggu_verifikasi' => 'warning',
                                        'valid' => 'success',
                                        'tidak_valid' => 'danger',
                                        'upload_ulang' => 'info',
                                        'residu' => 'dark'
                                    ];
                                    $status_text = [
                                        'belum_lengkap' => 'Belum Lengkap',
                                        'menunggu_verifikasi' => 'Menunggu',
                                        'valid' => 'Valid',
                                        'tidak_valid' => 'Tidak Valid',
                                        'upload_ulang' => 'Upload Ulang',
                                        'residu' => 'Residu'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class[$status] ?? 'secondary'; ?>">
                                        <?php echo $status_text[$status] ?? $status; ?>
                                    </span>
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
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo base_url('admin/verifikasi_dokumen/detail.php?id=' . $siswa['id']); ?>" 
                                           class="btn btn-outline-primary" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

