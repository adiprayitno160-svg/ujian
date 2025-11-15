<?php
/**
 * Manage Users - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Kelola Users';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $nama = sanitize($_POST['nama'] ?? '');
        $nip = sanitize($_POST['nip'] ?? '');
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        
        if (empty($username) || empty($password) || empty($role) || empty($nama)) {
            $error = 'Semua field wajib harus diisi';
        } else {
            try {
                // Hanya admin yang bisa set is_operator
                // is_operator hanya berlaku untuk role 'guru'
                $is_operator = 0;
                if (isset($_POST['is_operator']) && $_POST['is_operator'] === '1' && $role === 'guru') {
                    $is_operator = 1;
                }
                
                // Jika role bukan guru, pastikan is_operator = 0
                if ($role !== 'guru') {
                    $is_operator = 0;
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, nama, nip, no_hp, is_operator) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $role, $nama, $nip ?: null, $no_hp, $is_operator]);
                $success = 'User berhasil ditambahkan';
                log_activity('create_user', 'users', $pdo->lastInsertId());
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Username sudah digunakan';
                } else {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? '');
        $nama = sanitize($_POST['nama'] ?? '');
        $nip = sanitize($_POST['nip'] ?? '');
        $no_hp = sanitize($_POST['no_hp'] ?? '');
        $status = sanitize($_POST['status'] ?? 'active');
        
        // Hanya admin yang bisa set is_operator
        // is_operator hanya berlaku untuk role 'guru'
        $is_operator = 0;
        if (isset($_POST['is_operator']) && $_POST['is_operator'] === '1' && $role === 'guru') {
            $is_operator = 1;
        }
        
        // Jika role bukan guru, pastikan is_operator = 0
        if ($role !== 'guru') {
            $is_operator = 0;
        }
        
        try {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, role = ?, nama = ?, nip = ?, no_hp = ?, status = ?, is_operator = ? WHERE id = ?");
                $stmt->execute([$username, $hashed_password, $role, $nama, $nip ?: null, $no_hp, $status, $is_operator, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, nama = ?, nip = ?, no_hp = ?, status = ?, is_operator = ? WHERE id = ?");
                $stmt->execute([$username, $role, $nama, $nip ?: null, $no_hp, $status, $is_operator, $id]);
            }
            $success = 'User berhasil diupdate';
            log_activity('update_user', 'users', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id != $_SESSION['user_id']) {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'User berhasil dihapus';
                log_activity('delete_user', 'users', $id);
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        } else {
            $error = 'Tidak dapat menghapus akun sendiri';
        }
    }
}

// Get users
$page = intval($_GET['page'] ?? 1);
$search = sanitize($_GET['search'] ?? '');
$role_filter = sanitize($_GET['role'] ?? '');

$where = "1=1 AND role != 'siswa'"; // Exclude siswa from admin view
$params = [];

if ($search) {
    $where .= " AND (username LIKE ? OR nama LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where .= " AND role = ?";
    $params[] = $role_filter;
}

$pagination = paginate(0, $page);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $where");
$stmt->execute($params);
$pagination = paginate($stmt->fetchColumn(), $page);

$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $pagination['items_per_page'];
$params[] = $pagination['offset'];
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="d-flex justify-content-end mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fas fa-plus"></i> Tambah User
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Cari username/nama..." value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="">Semua Role</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="guru" <?php echo $role_filter === 'guru' ? 'selected' : ''; ?>>Guru</option>
                    <option value="operator" <?php echo $role_filter === 'operator' ? 'selected' : ''; ?>>Operator</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo base_url('admin/manage_users.php'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>Role</th>
                        <th>Operator</th>
                        <th>No. HP</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo escape($user['username']); ?></td>
                            <td><?php echo escape($user['nama']); ?></td>
                            <td><?php echo escape($user['nip'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'guru' ? 'success' : 
                                        ($user['role'] === 'operator' ? 'warning' : 'info')); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'guru'): ?>
                                    <span class="badge bg-<?php echo ($user['is_operator'] ?? 0) ? 'info' : 'secondary'; ?>">
                                        <?php echo ($user['is_operator'] ?? 0) ? 'Ya' : 'Tidak'; ?>
                                    </span>
                                <?php elseif ($user['role'] === 'admin'): ?>
                                    <span class="badge bg-success">Auto</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($user['no_hp'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? format_date($user['last_login']) : '-'; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus user ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($pagination['total_pages'] > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($pagination['has_prev']): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Previous</a></li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($pagination['has_next']): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>">Next</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" name="nip" placeholder="Nomor Induk Pegawai (untuk guru)">
                        <small class="text-muted">Khusus untuk guru</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="create_role" required onchange="toggleOperatorField('create')">
                            <option value="">Pilih Role</option>
                            <option value="admin">Admin</option>
                            <option value="guru">Guru</option>
                            <option value="operator">Operator</option>
                        </select>
                    </div>
                    <div class="mb-3" id="create_operator_field" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_operator" value="1" id="create_is_operator">
                            <label class="form-check-label" for="create_is_operator">
                                Juga sebagai Operator
                            </label>
                            <small class="form-text text-muted d-block">Guru ini juga bisa mengakses halaman operator</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. HP</label>
                        <input type="text" class="form-control" name="no_hp">
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

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="edit_username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password (kosongkan jika tidak diubah)</label>
                        <input type="password" class="form-control" name="password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" id="edit_nama" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP</label>
                        <input type="text" class="form-control" name="nip" id="edit_nip" placeholder="Nomor Induk Pegawai (untuk guru)">
                        <small class="text-muted">Khusus untuk guru</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="edit_role" required onchange="toggleOperatorField('edit')">
                            <option value="admin">Admin</option>
                            <option value="guru">Guru</option>
                            <option value="operator">Operator</option>
                        </select>
                    </div>
                    <div class="mb-3" id="edit_operator_field" style="display: none;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_operator" value="1" id="edit_is_operator">
                            <label class="form-check-label" for="edit_is_operator">
                                Juga sebagai Operator
                            </label>
                            <small class="form-text text-muted d-block">Guru ini juga bisa mengakses halaman operator</small>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">No. HP</label>
                        <input type="text" class="form-control" name="no_hp" id="edit_no_hp">
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

<script>
function toggleOperatorField(formType) {
    const roleSelect = document.getElementById(formType + '_role');
    const operatorField = document.getElementById(formType + '_operator_field');
    const operatorCheckbox = document.getElementById(formType + '_is_operator');
    
    if (roleSelect && roleSelect.value === 'guru') {
        if (operatorField) operatorField.style.display = 'block';
    } else {
        if (operatorField) operatorField.style.display = 'none';
        if (operatorCheckbox) operatorCheckbox.checked = false;
    }
}

function editUser(user) {
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_nama').value = user.nama;
    document.getElementById('edit_nip').value = user.nip || '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_no_hp').value = user.no_hp || '';
    document.getElementById('edit_status').value = user.status;
    document.getElementById('edit_is_operator').checked = (user.is_operator == 1);
    
    // Toggle operator field based on role
    toggleOperatorField('edit');
    
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
