<?php
/**
 * Manage Siswa - Admin/Guru/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(['admin', 'guru', 'operator']);
check_session_timeout();

$page_title = 'Kelola Siswa';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama = sanitize($_POST['nama'] ?? '');
        $nis = sanitize($_POST['nis'] ?? '');
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        
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
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, tanggal_lahir, no_hp, status) VALUES (?, ?, 'siswa', ?, ?, ?, 'active')");
                    $stmt->execute([$nis, $hashed_password, $nama, $tanggal_lahir ?: null, $no_hp ?: null]);
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
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        
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
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, nama = ?, tanggal_lahir = ?, no_hp = ? WHERE id = ? AND role = 'siswa'");
                    $stmt->execute([$nis, $nama, $tanggal_lahir ?: null, $no_hp ?: null, $id]);
                    
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
    }
}

// Get filter
$search = sanitize($_GET['search'] ?? '');
$kelas_filter = intval($_GET['kelas'] ?? 0);

// Build query
$tahun_ajaran = get_tahun_ajaran_aktif();
$query = "SELECT u.id, u.username as nis, u.nama, u.status, u.created_at, u.tanggal_lahir, u.no_hp,
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
    $tahun_ajaran = get_tahun_ajaran_aktif();
    $stmt = $pdo->prepare("SELECT u.*, k.id as id_kelas FROM users u
                          LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
                          LEFT JOIN kelas k ON uk.id_kelas = k.id
                          WHERE u.id = ? AND u.role = 'siswa'");
    $stmt->execute([$tahun_ajaran, $edit_id]);
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

<div class="row mb-4">
    <div class="col-md-6">
        <h3 class="fw-bold">Kelola Siswa</h3>
        <p class="text-muted">Manajemen data siswa - Login menggunakan NIS dan password NIS</p>
    </div>
    <div class="col-md-6 text-end">
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
                        <th>Tanggal Lahir</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($siswa_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada data siswa</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($siswa_list as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
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

<?php if ($edit_siswa): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modal = new bootstrap.Modal(document.getElementById('addSiswaModal'));
        modal.show();
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

