<?php
/**
 * Manage Wali Kelas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk mengelola wali kelas
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Kelola Wali Kelas';
$role_css = 'operator';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
        $semester = sanitize($_POST['semester'] ?? 'ganjil');
        $level_access = sanitize($_POST['level_access'] ?? 'operator');
        
        if (!$id_guru || !$id_kelas) {
            $error = 'Guru dan kelas harus dipilih';
        } else {
            try {
                // Check if wali kelas already exists for this kelas, tahun_ajaran, semester
                $stmt = $pdo->prepare("SELECT id FROM wali_kelas WHERE id_kelas = ? AND tahun_ajaran = ? AND semester = ?");
                $stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
                if ($stmt->fetch()) {
                    $error = 'Wali kelas untuk kelas ini pada tahun ajaran dan semester tersebut sudah ada';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO wali_kelas (id_guru, id_kelas, tahun_ajaran, semester, level_access, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_guru, $id_kelas, $tahun_ajaran, $semester, $level_access, $_SESSION['user_id']]);
                    $success = 'Wali kelas berhasil ditambahkan';
                    log_activity('create_wali_kelas', 'wali_kelas', $pdo->lastInsertId());
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Create wali kelas error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
        $semester = sanitize($_POST['semester'] ?? 'ganjil');
        $level_access = sanitize($_POST['level_access'] ?? 'operator');
        
        if (!$id_guru || !$id_kelas) {
            $error = 'Guru dan kelas harus dipilih';
        } else {
            try {
                // Check if wali kelas already exists for this kelas, tahun_ajaran, semester (excluding current record)
                $stmt = $pdo->prepare("SELECT id FROM wali_kelas WHERE id_kelas = ? AND tahun_ajaran = ? AND semester = ? AND id != ?");
                $stmt->execute([$id_kelas, $tahun_ajaran, $semester, $id]);
                if ($stmt->fetch()) {
                    $error = 'Wali kelas untuk kelas ini pada tahun ajaran dan semester tersebut sudah ada';
                } else {
                    $stmt = $pdo->prepare("UPDATE wali_kelas SET id_guru = ?, id_kelas = ?, tahun_ajaran = ?, semester = ?, level_access = ? WHERE id = ?");
                    $stmt->execute([$id_guru, $id_kelas, $tahun_ajaran, $semester, $level_access, $id]);
                    $success = 'Wali kelas berhasil diupdate';
                    log_activity('update_wali_kelas', 'wali_kelas', $id);
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Update wali kelas error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM wali_kelas WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Wali kelas berhasil dihapus';
            log_activity('delete_wali_kelas', 'wali_kelas', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
            error_log("Delete wali kelas error: " . $e->getMessage());
        }
    }
}

// Get filters
$tahun_ajaran_filter = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester_filter = $_GET['semester'] ?? '';

// Get all wali kelas
$sql = "SELECT wk.*, 
        u.nama as guru_nama, u.username as guru_username,
        k.nama_kelas, k.tingkat,
        creator.nama as created_by_name
        FROM wali_kelas wk
        INNER JOIN users u ON wk.id_guru = u.id
        INNER JOIN kelas k ON wk.id_kelas = k.id
        LEFT JOIN users creator ON wk.created_by = creator.id
        WHERE 1=1";
$params = [];

if ($tahun_ajaran_filter) {
    $sql .= " AND wk.tahun_ajaran = ?";
    $params[] = $tahun_ajaran_filter;
}

if ($semester_filter) {
    $sql .= " AND wk.semester = ?";
    $params[] = $semester_filter;
}

$sql .= " ORDER BY wk.tahun_ajaran DESC, wk.semester DESC, k.tingkat ASC, k.nama_kelas ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$wali_kelas_list = $stmt->fetchAll();

// Get all tahun ajaran
$tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
$tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');

// Get all guru
$stmt = $pdo->query("SELECT id, nama, username FROM users WHERE role = 'guru' AND status = 'active' ORDER BY nama ASC");
$guru_list = $stmt->fetchAll();

// Get all kelas
$stmt = $pdo->query("SELECT id, nama_kelas, tingkat, tahun_ajaran FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, tingkat ASC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

// Get wali kelas to edit
$edit_wali_kelas = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM wali_kelas WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_wali_kelas = $stmt->fetch();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1">Kelola Wali Kelas</h2>
                <p class="text-muted mb-0">Atur guru sebagai wali kelas untuk setiap kelas</p>
            </div>
            <?php if (!$edit_wali_kelas): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWaliKelasModal">
                    <i class="fas fa-plus"></i> Tambah Wali Kelas
                </button>
            <?php endif; ?>
        </div>
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
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran" onchange="this.form.submit()">
                    <option value="">Semua Tahun Ajaran</option>
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo escape($ta); ?>" <?php echo $tahun_ajaran_filter === $ta ? 'selected' : ''; ?>>
                            <?php echo escape($ta); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester" onchange="this.form.submit()">
                    <option value="">Semua Semester</option>
                    <option value="ganjil" <?php echo $semester_filter === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester_filter === 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($edit_wali_kelas): ?>
    <!-- Edit Form -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Wali Kelas</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $edit_wali_kelas['id']; ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Guru <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_guru" required>
                                <option value="">Pilih Guru</option>
                                <?php foreach ($guru_list as $guru): ?>
                                    <option value="<?php echo $guru['id']; ?>" <?php echo $edit_wali_kelas['id_guru'] == $guru['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($guru['nama']); ?> (<?php echo escape($guru['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Kelas <span class="text-danger">*</span></label>
                            <select class="form-select" name="id_kelas" required>
                                <option value="">Pilih Kelas</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                    <option value="<?php echo $kelas['id']; ?>" <?php echo $edit_wali_kelas['id_kelas'] == $kelas['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tingkat']); ?> (<?php echo escape($kelas['tahun_ajaran']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                            <select class="form-select" name="tahun_ajaran" required>
                                <?php foreach ($tahun_ajaran_list as $ta): ?>
                                    <option value="<?php echo escape($ta); ?>" <?php echo $edit_wali_kelas['tahun_ajaran'] === $ta ? 'selected' : ''; ?>>
                                        <?php echo escape($ta); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Semester <span class="text-danger">*</span></label>
                            <select class="form-select" name="semester" required>
                                <option value="ganjil" <?php echo $edit_wali_kelas['semester'] === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="genap" <?php echo $edit_wali_kelas['semester'] === 'genap' ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Level Akses <span class="text-danger">*</span></label>
                            <select class="form-select" name="level_access" required>
                                <option value="operator" <?php echo $edit_wali_kelas['level_access'] === 'operator' ? 'selected' : ''; ?>>Operator</option>
                                <option value="admin" <?php echo $edit_wali_kelas['level_access'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <small class="text-muted">Level akses yang diberikan kepada wali kelas</small>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                    <a href="<?php echo base_url('operator/manage_wali_kelas.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Wali Kelas List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-users"></i> Daftar Wali Kelas</h5>
        </div>
        <div class="card-body">
            <?php if (empty($wali_kelas_list)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada wali kelas. Silakan tambah wali kelas baru.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Guru</th>
                                <th>Kelas</th>
                                <th>Tahun Ajaran</th>
                                <th>Semester</th>
                                <th>Level Akses</th>
                                <th>Dibuat Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($wali_kelas_list as $index => $wk): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <strong><?php echo escape($wk['guru_nama']); ?></strong><br>
                                        <small class="text-muted"><?php echo escape($wk['guru_username']); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo escape($wk['nama_kelas']); ?></strong><br>
                                        <small class="text-muted"><?php echo escape($wk['tingkat']); ?></small>
                                    </td>
                                    <td><?php echo escape($wk['tahun_ajaran']); ?></td>
                                    <td><?php echo ucfirst($wk['semester']); ?></td>
                                    <td>
                                        <?php if ($wk['level_access'] === 'admin'): ?>
                                            <span class="badge bg-danger">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Operator</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($wk['created_by_name'] ?? '-'); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo base_url('operator/manage_wali_kelas.php?edit=' . $wk['id']); ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus wali kelas ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $wk['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Create Wali Kelas Modal -->
<div class="modal fade" id="createWaliKelasModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Wali Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Guru <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_guru" required>
                            <option value="">Pilih Guru</option>
                            <?php foreach ($guru_list as $guru): ?>
                                <option value="<?php echo $guru['id']; ?>">
                                    <?php echo escape($guru['nama']); ?> (<?php echo escape($guru['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" name="id_kelas" required>
                            <option value="">Pilih Kelas</option>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>">
                                    <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tingkat']); ?> (<?php echo escape($kelas['tahun_ajaran']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                                <select class="form-select" name="tahun_ajaran" required>
                                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                                        <option value="<?php echo escape($ta); ?>" <?php echo get_tahun_ajaran_aktif() === $ta ? 'selected' : ''; ?>>
                                            <?php echo escape($ta); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Semester <span class="text-danger">*</span></label>
                                <select class="form-select" name="semester" required>
                                    <option value="ganjil">Ganjil</option>
                                    <option value="genap">Genap</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Level Akses <span class="text-danger">*</span></label>
                        <select class="form-select" name="level_access" required>
                            <option value="operator" selected>Operator</option>
                            <option value="admin">Admin</option>
                        </select>
                        <small class="text-muted">Level akses yang diberikan kepada wali kelas</small>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>

