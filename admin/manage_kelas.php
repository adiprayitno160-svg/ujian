<?php
/**
 * Manage Kelas - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Kelola Kelas';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama_kelas = sanitize($_POST['nama_kelas'] ?? '');
        $tingkat = sanitize($_POST['tingkat'] ?? '');
        $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
        
        if (empty($nama_kelas) || empty($tahun_ajaran)) {
            $error = 'Nama kelas dan tahun ajaran harus diisi';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, tingkat, tahun_ajaran) VALUES (?, ?, ?)");
                $stmt->execute([$nama_kelas, $tingkat, $tahun_ajaran]);
                $success = 'Kelas berhasil ditambahkan';
                log_activity('create_kelas', 'kelas', $pdo->lastInsertId());
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nama_kelas = sanitize($_POST['nama_kelas'] ?? '');
        $tingkat = sanitize($_POST['tingkat'] ?? '');
        $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        
        try {
            $stmt = $pdo->prepare("UPDATE kelas SET nama_kelas = ?, tingkat = ?, tahun_ajaran = ?, status = ? WHERE id = ?");
            $stmt->execute([$nama_kelas, $tingkat, $tahun_ajaran, $status, $id]);
            $success = 'Kelas berhasil diupdate';
            log_activity('update_kelas', 'kelas', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM kelas WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Kelas berhasil dihapus';
            log_activity('delete_kelas', 'kelas', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get filter tingkat
$filter_tingkat = $_GET['tingkat'] ?? '';

// Get kelas dengan filter dan jumlah siswa
$tahun_ajaran = get_tahun_ajaran_aktif();
$sql = "SELECT k.*, 
        COUNT(DISTINCT uk.id_user) as jumlah_siswa
        FROM kelas k
        LEFT JOIN user_kelas uk ON k.id = uk.id_kelas AND uk.tahun_ajaran = ?
        WHERE 1=1";
$params = [$tahun_ajaran];

if ($filter_tingkat && in_array($filter_tingkat, ['VII', 'VIII', 'IX'])) {
    $sql .= " AND k.tingkat = ?";
    $params[] = $filter_tingkat;
}

$sql .= " GROUP BY k.id
          ORDER BY k.tahun_ajaran DESC, k.tingkat ASC, k.nama_kelas ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$kelas_list = $stmt->fetchAll();

// Get siswa per kelas untuk detail
$siswa_per_kelas = [];
foreach ($kelas_list as $kelas) {
    $stmt = $pdo->prepare("SELECT u.id, u.username as nis, u.nama, u.status
                           FROM users u
                           INNER JOIN user_kelas uk ON u.id = uk.id_user
                           WHERE uk.id_kelas = ? AND uk.tahun_ajaran = ?
                           ORDER BY u.nama ASC");
    $stmt->execute([$kelas['id'], $tahun_ajaran]);
    $siswa_per_kelas[$kelas['id']] = $stmt->fetchAll();
}

// Get count per tingkat
$stmt = $pdo->query("SELECT tingkat, COUNT(*) as total FROM kelas WHERE status = 'active' GROUP BY tingkat");
$count_per_tingkat = [];
while ($row = $stmt->fetch()) {
    $count_per_tingkat[$row['tingkat']] = $row['total'];
}

// Get total semua kelas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM kelas WHERE status = 'active'");
$total_all = $stmt->fetch()['total'];
?>

<div class="d-flex justify-content-end mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKelasModal">
        <i class="fas fa-plus"></i> Tambah Kelas
    </button>
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

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <!-- Tabs untuk filter tingkat -->
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo empty($filter_tingkat) ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin-manage-kelas'); ?>">
                    <i class="fas fa-list"></i> Semua
                    <span class="badge bg-secondary ms-1"><?php echo $total_all; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $filter_tingkat === 'VII' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin-manage-kelas?tingkat=VII'); ?>">
                    <i class="fas fa-graduation-cap"></i> Kelas VII
                    <span class="badge bg-primary ms-1"><?php echo $count_per_tingkat['VII'] ?? 0; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $filter_tingkat === 'VIII' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin-manage-kelas?tingkat=VIII'); ?>">
                    <i class="fas fa-graduation-cap"></i> Kelas VIII
                    <span class="badge bg-success ms-1"><?php echo $count_per_tingkat['VIII'] ?? 0; ?></span>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo $filter_tingkat === 'IX' ? 'active' : ''; ?>" 
                   href="<?php echo base_url('admin-manage-kelas?tingkat=IX'); ?>">
                    <i class="fas fa-graduation-cap"></i> Kelas IX
                    <span class="badge bg-warning ms-1"><?php echo $count_per_tingkat['IX'] ?? 0; ?></span>
                </a>
            </li>
        </ul>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama Kelas</th>
                        <th>Tingkat</th>
                        <th>Tahun Ajaran</th>
                        <th>Jumlah Siswa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($kelas_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Tidak ada kelas<?php echo $filter_tingkat ? ' untuk tingkat ' . $filter_tingkat : ''; ?></p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kelas_list as $kelas): ?>
                        <tr>
                            <td><?php echo $kelas['id']; ?></td>
                            <td><?php echo escape($kelas['nama_kelas']); ?></td>
                            <td><?php echo escape($kelas['tingkat'] ?? '-'); ?></td>
                            <td><?php echo escape($kelas['tahun_ajaran']); ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <i class="fas fa-users"></i> <?php echo intval($kelas['jumlah_siswa'] ?? 0); ?> siswa
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $kelas['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($kelas['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-info" onclick="showSiswaList(<?php echo $kelas['id']; ?>, '<?php echo escape($kelas['nama_kelas']); ?>')" title="Lihat Daftar Siswa">
                                    <i class="fas fa-users"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editKelas(<?php echo htmlspecialchars(json_encode($kelas)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus kelas ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $kelas['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Kelas Modal -->
<div class="modal fade" id="createKelasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" required placeholder="Contoh: VII A">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat</label>
                        <select class="form-select" name="tingkat">
                            <option value="">Pilih Tingkat</option>
                            <option value="VII">VII</option>
                            <option value="VIII">VIII</option>
                            <option value="IX">IX</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="tahun_ajaran" required placeholder="Contoh: 2024/2025">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Kelas Modal -->
<div class="modal fade" id="editKelasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_kelas" id="edit_nama_kelas" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tingkat</label>
                        <select class="form-select" name="tingkat" id="edit_tingkat">
                            <option value="">Pilih Tingkat</option>
                            <option value="VII">VII</option>
                            <option value="VIII">VIII</option>
                            <option value="IX">IX</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="tahun_ajaran" id="edit_tahun_ajaran" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status" id="edit_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Daftar Siswa -->
<div class="modal fade" id="siswaListModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-users"></i> Daftar Siswa - <span id="modalKelasName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="siswaListContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
const siswaPerKelas = <?php echo json_encode($siswa_per_kelas); ?>;

function editKelas(kelas) {
    document.getElementById('edit_id').value = kelas.id;
    document.getElementById('edit_nama_kelas').value = kelas.nama_kelas || '';
    document.getElementById('edit_tingkat').value = kelas.tingkat || '';
    document.getElementById('edit_tahun_ajaran').value = kelas.tahun_ajaran || '';
    document.getElementById('edit_status').value = kelas.status || 'active';
    
    const modal = new bootstrap.Modal(document.getElementById('editKelasModal'));
    modal.show();
}

function showSiswaList(kelasId, kelasName) {
    document.getElementById('modalKelasName').textContent = kelasName;
    
    const siswaList = siswaPerKelas[kelasId] || [];
    let html = '';
    
    if (siswaList.length === 0) {
        html = '<div class="alert alert-info">';
        html += '<i class="fas fa-info-circle"></i> Belum ada siswa di kelas ini.';
        html += '</div>';
    } else {
        html = '<div class="table-responsive">';
        html += '<table class="table table-hover table-sm">';
        html += '<thead><tr>';
        html += '<th>No</th>';
        html += '<th>NIS</th>';
        html += '<th>Nama</th>';
        html += '<th>Status</th>';
        html += '</tr></thead>';
        html += '<tbody>';
        
        siswaList.forEach(function(siswa, index) {
            html += '<tr>';
            html += '<td>' + (index + 1) + '</td>';
            html += '<td>' + escapeHtml(siswa.nis || '-') + '</td>';
            html += '<td>' + escapeHtml(siswa.nama || '-') + '</td>';
            html += '<td>';
            html += '<span class="badge bg-' + (siswa.status === 'active' ? 'success' : 'secondary') + '">';
            html += siswa.status === 'active' ? 'Aktif' : 'Tidak Aktif';
            html += '</span>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        html += '<div class="mt-3">';
        html += '<p class="text-muted mb-0"><strong>Total:</strong> ' + siswaList.length + ' siswa</p>';
        html += '</div>';
    }
    
    document.getElementById('siswaListContent').innerHTML = html;
    
    const modal = new bootstrap.Modal(document.getElementById('siswaListModal'));
    modal.show();
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
