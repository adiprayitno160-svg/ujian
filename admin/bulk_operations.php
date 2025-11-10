<?php
/**
 * Bulk Operations - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Bulk operations untuk ujian, siswa, dll
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Bulk Operations';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$action = $_GET['action'] ?? 'list';
$type = $_GET['type'] ?? 'ujian';

// Handle bulk operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulk_action = $_POST['bulk_action'];
    $selected_ids = $_POST['selected_ids'] ?? [];
    
    if (empty($selected_ids)) {
        $_SESSION['error_message'] = 'Tidak ada item yang dipilih';
        redirect('admin/bulk_operations.php?type=' . $type);
    }
    
    try {
        $pdo->beginTransaction();
        $affected = 0;
        
        switch ($bulk_action) {
            case 'archive_ujian':
                // Archive ujian
                $stmt = $pdo->prepare("UPDATE ujian SET archived_at = NOW(), status = 'archived' WHERE id IN (" . implode(',', array_fill(0, count($selected_ids), '?')) . ")");
                $stmt->execute($selected_ids);
                $affected = $stmt->rowCount();
                $_SESSION['success_message'] = "{$affected} ujian berhasil di-archive";
                break;
                
            case 'delete_ujian':
                // Delete ujian (soft delete by archiving)
                $stmt = $pdo->prepare("UPDATE ujian SET archived_at = NOW(), status = 'archived' WHERE id IN (" . implode(',', array_fill(0, count($selected_ids), '?')) . ")");
                $stmt->execute($selected_ids);
                $affected = $stmt->rowCount();
                $_SESSION['success_message'] = "{$affected} ujian berhasil dihapus";
                break;
                
            case 'activate_users':
                // Activate users
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id IN (" . implode(',', array_fill(0, count($selected_ids), '?')) . ")");
                $stmt->execute($selected_ids);
                $affected = $stmt->rowCount();
                $_SESSION['success_message'] = "{$affected} user berhasil diaktifkan";
                break;
                
            case 'deactivate_users':
                // Deactivate users
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id IN (" . implode(',', array_fill(0, count($selected_ids), '?')) . ")");
                $stmt->execute($selected_ids);
                $affected = $stmt->rowCount();
                $_SESSION['success_message'] = "{$affected} user berhasil dinonaktifkan";
                break;
                
            case 'assign_kelas':
                // Assign users to kelas
                $kelas_id = intval($_POST['kelas_id'] ?? 0);
                if ($kelas_id > 0) {
                    $tahun_ajaran = get_tahun_ajaran_aktif();
                    foreach ($selected_ids as $user_id) {
                        // Check if already assigned
                        $stmt = $pdo->prepare("SELECT id FROM user_kelas WHERE id_user = ? AND id_kelas = ? AND tahun_ajaran = ?");
                        $stmt->execute([$user_id, $kelas_id, $tahun_ajaran]);
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO user_kelas (id_user, id_kelas, tahun_ajaran) VALUES (?, ?, ?)");
                            $stmt->execute([$user_id, $kelas_id, $tahun_ajaran]);
                            $affected++;
                        }
                    }
                    $_SESSION['success_message'] = "{$affected} user berhasil ditambahkan ke kelas";
                }
                break;
        }
        
        $pdo->commit();
        redirect('admin/bulk_operations.php?type=' . $type);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Bulk operation error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Terjadi kesalahan saat melakukan bulk operation';
        redirect('admin/bulk_operations.php?type=' . $type);
    }
}

// Get data based on type
$data_list = [];
$total_count = 0;

try {
    switch ($type) {
        case 'ujian':
            $stmt = $pdo->query("SELECT u.*, m.nama_mapel, u2.nama as nama_guru, 
                                COUNT(DISTINCT n.id) as total_peserta
                                FROM ujian u 
                                LEFT JOIN mapel m ON u.id_mapel = m.id 
                                LEFT JOIN users u2 ON u.id_guru = u2.id 
                                LEFT JOIN nilai n ON u.id = n.id_ujian 
                                WHERE u.archived_at IS NULL 
                                GROUP BY u.id 
                                ORDER BY u.created_at DESC 
                                LIMIT 100");
            $data_list = $stmt->fetchAll();
            $total_count = count($data_list);
            break;
            
        case 'users':
            $role_filter = $_GET['role'] ?? '';
            $sql = "SELECT * FROM users WHERE 1=1";
            $params = [];
            if ($role_filter) {
                $sql .= " AND role = ?";
                $params[] = $role_filter;
            }
            $sql .= " ORDER BY nama ASC LIMIT 200";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data_list = $stmt->fetchAll();
            $total_count = count($data_list);
            break;
    }
} catch (PDOException $e) {
    error_log("Get data list error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat memuat data';
}

// Get kelas list for assignment
$kelas_list = [];
try {
    $stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY nama_kelas ASC");
    $kelas_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get kelas list error: " . $e->getMessage());
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold mb-2">
            <i class="fas fa-tasks"></i> Bulk Operations
        </h2>
        <p class="text-muted mb-0">Operasi massal untuk mengelola data</p>
    </div>
</div>

<!-- Type Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="btn-group" role="group">
                    <a href="<?php echo base_url('admin/bulk_operations.php?type=ujian'); ?>" 
                       class="btn btn-<?php echo $type === 'ujian' ? 'primary' : 'outline-primary'; ?>">
                        <i class="fas fa-clipboard-list"></i> Ujian
                    </a>
                    <a href="<?php echo base_url('admin/bulk_operations.php?type=users'); ?>" 
                       class="btn btn-<?php echo $type === 'users' ? 'primary' : 'outline-primary'; ?>">
                        <i class="fas fa-users"></i> Users
                    </a>
                </div>
                
                <?php if ($type === 'users'): ?>
                <div class="mt-3">
                    <div class="btn-group" role="group">
                        <a href="<?php echo base_url('admin/bulk_operations.php?type=users&role='); ?>" 
                           class="btn btn-sm btn-<?php echo empty($_GET['role']) ? 'primary' : 'outline-primary'; ?>">
                            Semua
                        </a>
                        <a href="<?php echo base_url('admin/bulk_operations.php?type=users&role=siswa'); ?>" 
                           class="btn btn-sm btn-<?php echo $_GET['role'] === 'siswa' ? 'primary' : 'outline-primary'; ?>">
                            Siswa
                        </a>
                        <a href="<?php echo base_url('admin/bulk_operations.php?type=users&role=guru'); ?>" 
                           class="btn btn-sm btn-<?php echo $_GET['role'] === 'guru' ? 'primary' : 'outline-primary'; ?>">
                            Guru
                        </a>
                        <a href="<?php echo base_url('admin/bulk_operations.php?type=users&role=operator'); ?>" 
                           class="btn btn-sm btn-<?php echo $_GET['role'] === 'operator' ? 'primary' : 'outline-primary'; ?>">
                            Operator
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Operations Form -->
<?php if (!empty($data_list)): ?>
<form method="POST" action="" id="bulkForm">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Daftar <?php echo ucfirst($type); ?> (<?php echo $total_count; ?>)
                        </h5>
                        <div>
                            <button type="button" class="btn btn-sm btn-light" onclick="selectAll()">
                                <i class="fas fa-check-square"></i> Pilih Semua
                            </button>
                            <button type="button" class="btn btn-sm btn-light" onclick="deselectAll()">
                                <i class="fas fa-square"></i> Batal Pilih
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Bulk Actions -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <select name="bulk_action" class="form-select" id="bulkAction" required>
                                <option value="">-- Pilih Aksi --</option>
                                <?php if ($type === 'ujian'): ?>
                                <option value="archive_ujian">Archive Ujian</option>
                                <option value="delete_ujian">Hapus Ujian</option>
                                <?php elseif ($type === 'users'): ?>
                                <option value="activate_users">Aktifkan User</option>
                                <option value="deactivate_users">Nonaktifkan User</option>
                                <option value="assign_kelas">Assign ke Kelas</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <?php if ($type === 'users'): ?>
                            <select name="kelas_id" class="form-select" id="kelasId" style="display: none;">
                                <option value="">-- Pilih Kelas --</option>
                                <?php foreach ($kelas_list as $kelas): ?>
                                <option value="<?php echo $kelas['id']; ?>">
                                    <?php echo escape($kelas['nama_kelas']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" id="submitBulk" disabled>
                                <i class="fas fa-play"></i> Jalankan Aksi
                            </button>
                        </div>
                    </div>
                    
                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                    </th>
                                    <?php if ($type === 'ujian'): ?>
                                    <th>Judul</th>
                                    <th>Mata Pelajaran</th>
                                    <th>Guru</th>
                                    <th>Status</th>
                                    <th>Total Peserta</th>
                                    <th>Tanggal Dibuat</th>
                                    <?php elseif ($type === 'users'): ?>
                                    <th>Nama</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Email</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data_list as $item): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?php echo $item['id']; ?>" 
                                               class="item-checkbox" onchange="updateSubmitButton()">
                                    </td>
                                    <?php if ($type === 'ujian'): ?>
                                    <td><?php echo escape($item['judul']); ?></td>
                                    <td><?php echo escape($item['nama_mapel'] ?? '-'); ?></td>
                                    <td><?php echo escape($item['nama_guru'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['status'] === 'published' ? 'success' : ($item['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['total_peserta']; ?></td>
                                    <td><?php 
                                    try {
                                        if (function_exists('format_date')) {
                                            echo format_date($item['created_at']);
                                        } else {
                                            $dt = new DateTime($item['created_at']);
                                            echo $dt->format('d/m/Y H:i');
                                        }
                                    } catch (Exception $e) {
                                        echo $item['created_at'];
                                    }
                                    ?></td>
                                    <?php elseif ($type === 'users'): ?>
                                    <td><?php echo escape($item['nama']); ?></td>
                                    <td><?php echo escape($item['username']); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($item['role']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($item['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo escape($item['email'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> Tidak ada data untuk ditampilkan.
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSubmitButton();
}

function selectAll() {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
    updateSubmitButton();
}

function deselectAll() {
    document.querySelectorAll('.item-checkbox').forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
    updateSubmitButton();
}

function updateSubmitButton() {
    const checked = document.querySelectorAll('.item-checkbox:checked').length;
    const bulkAction = document.getElementById('bulkAction').value;
    const submitBtn = document.getElementById('submitBulk');
    
    if (checked > 0 && bulkAction) {
        submitBtn.disabled = false;
        
        // Show kelas select if assign_kelas
        if (bulkAction === 'assign_kelas') {
            document.getElementById('kelasId').style.display = 'block';
            document.getElementById('kelasId').required = true;
        } else {
            document.getElementById('kelasId').style.display = 'none';
            document.getElementById('kelasId').required = false;
        }
    } else {
        submitBtn.disabled = true;
    }
}

// Update button when bulk action changes
document.getElementById('bulkAction').addEventListener('change', function() {
    updateSubmitButton();
});

// Confirm before submit
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.item-checkbox:checked').length;
    const bulkAction = document.getElementById('bulkAction').value;
    
    if (checked === 0) {
        e.preventDefault();
        alert('Pilih minimal satu item');
        return false;
    }
    
    if (!bulkAction) {
        e.preventDefault();
        alert('Pilih aksi yang akan dilakukan');
        return false;
    }
    
    if (bulkAction === 'assign_kelas' && !document.getElementById('kelasId').value) {
        e.preventDefault();
        alert('Pilih kelas');
        return false;
    }
    
    const actionName = document.getElementById('bulkAction').options[document.getElementById('bulkAction').selectedIndex].text;
    if (!confirm(`Apakah Anda yakin ingin ${actionName.toLowerCase()} untuk ${checked} item?`)) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

