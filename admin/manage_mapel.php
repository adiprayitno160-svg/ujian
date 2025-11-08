<?php
/**
 * Manage Mata Pelajaran - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Kelola Mata Pelajaran';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $nama_mapel = sanitize($_POST['nama_mapel'] ?? '');
        $kode_mapel = sanitize($_POST['kode_mapel'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        
        if (empty($nama_mapel) || empty($kode_mapel)) {
            $error = 'Nama dan kode mata pelajaran harus diisi';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO mapel (nama_mapel, kode_mapel, deskripsi) VALUES (?, ?, ?)");
                $stmt->execute([$nama_mapel, $kode_mapel, $deskripsi]);
                $success = 'Mata pelajaran berhasil ditambahkan';
                log_activity('create_mapel', 'mapel', $pdo->lastInsertId());
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Kode mata pelajaran sudah digunakan';
                } else {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $nama_mapel = sanitize($_POST['nama_mapel'] ?? '');
        $kode_mapel = sanitize($_POST['kode_mapel'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        
        try {
            $stmt = $pdo->prepare("UPDATE mapel SET nama_mapel = ?, kode_mapel = ?, deskripsi = ? WHERE id = ?");
            $stmt->execute([$nama_mapel, $kode_mapel, $deskripsi, $id]);
            $success = 'Mata pelajaran berhasil diupdate';
            log_activity('update_mapel', 'mapel', $id);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = 'Kode mata pelajaran sudah digunakan';
            } else {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM mapel WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Mata pelajaran berhasil dihapus';
            log_activity('delete_mapel', 'mapel', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } elseif ($action === 'assign_guru') {
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $guru_ids = $_POST['guru_ids'] ?? [];
        
        if (!$id_mapel) {
            $error = 'Mata pelajaran tidak valid';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Delete existing assignments
                $stmt = $pdo->prepare("DELETE FROM guru_mapel WHERE id_mapel = ?");
                $stmt->execute([$id_mapel]);
                
                // Insert new assignments
                if (!empty($guru_ids)) {
                    $stmt = $pdo->prepare("INSERT INTO guru_mapel (id_guru, id_mapel) VALUES (?, ?)");
                    foreach ($guru_ids as $guru_id) {
                        $guru_id = intval($guru_id);
                        if ($guru_id > 0) {
                            try {
                                $stmt->execute([$guru_id, $id_mapel]);
                            } catch (PDOException $e) {
                                // Skip duplicate
                            }
                        }
                    }
                }
                
                $pdo->commit();
                $success = 'Guru berhasil di-assign ke mata pelajaran';
                log_activity('assign_guru_mapel', 'guru_mapel', $id_mapel);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'remove_guru') {
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $id_guru = intval($_POST['id_guru'] ?? 0);
        
        if ($id_mapel && $id_guru) {
            try {
                $stmt = $pdo->prepare("DELETE FROM guru_mapel WHERE id_mapel = ? AND id_guru = ?");
                $stmt->execute([$id_mapel, $id_guru]);
                $success = 'Guru berhasil dihapus dari mata pelajaran';
                log_activity('remove_guru_mapel', 'guru_mapel', $id_mapel);
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get mapel with assigned gurus
$stmt = $pdo->query("SELECT m.*, 
                     GROUP_CONCAT(DISTINCT CONCAT(u.id, ':', u.nama) SEPARATOR '|') as gurus
                     FROM mapel m
                     LEFT JOIN guru_mapel gm ON m.id = gm.id_mapel
                     LEFT JOIN users u ON gm.id_guru = u.id
                     GROUP BY m.id
                     ORDER BY m.kode_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get all gurus
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'guru' AND status = 'active' ORDER BY nama ASC");
$guru_list = $stmt->fetchAll();
?>

<div class="d-flex justify-content-end mb-4">
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMapelModal">
        <i class="fas fa-plus"></i> Tambah Mata Pelajaran
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
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Deskripsi</th>
                        <th>Guru</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mapel_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada data</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mapel_list as $mapel): ?>
                        <?php
                        // Parse gurus
                        $assigned_gurus = [];
                        if (!empty($mapel['gurus'])) {
                            $guru_pairs = explode('|', $mapel['gurus']);
                            foreach ($guru_pairs as $pair) {
                                if (!empty($pair)) {
                                    list($guru_id, $guru_nama) = explode(':', $pair);
                                    $assigned_gurus[] = ['id' => $guru_id, 'nama' => $guru_nama];
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo $mapel['id']; ?></td>
                            <td><span class="badge bg-primary"><?php echo escape($mapel['kode_mapel']); ?></span></td>
                            <td><?php echo escape($mapel['nama_mapel']); ?></td>
                            <td><?php echo escape($mapel['deskripsi'] ?? '-'); ?></td>
                            <td>
                                <?php if (!empty($assigned_gurus)): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($assigned_gurus as $guru): ?>
                                            <span class="badge bg-info">
                                                <?php echo escape($guru['nama']); ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus guru ini dari mata pelajaran?');">
                                                    <input type="hidden" name="action" value="remove_guru">
                                                    <input type="hidden" name="id_mapel" value="<?php echo $mapel['id']; ?>">
                                                    <input type="hidden" name="id_guru" value="<?php echo $guru['id']; ?>">
                                                    <button type="submit" class="btn-close btn-close-white" style="font-size: 0.6rem; margin-left: 4px;" title="Hapus"></button>
                                                </form>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Belum ada guru</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm btn-success" onclick="assignGuru(<?php echo $mapel['id']; ?>, <?php echo htmlspecialchars(json_encode($assigned_gurus)); ?>)">
                                        <i class="fas fa-user-plus"></i> Assign
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editMapel(<?php echo htmlspecialchars(json_encode($mapel)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus mata pelajaran ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $mapel['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
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

<!-- Create Mapel Modal -->
<div class="modal fade" id="createMapelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Kode Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mapel" required placeholder="Contoh: MAT, BIN, ING">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" required placeholder="Contoh: Matematika">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" rows="3"></textarea>
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

<!-- Edit Mapel Modal -->
<div class="modal fade" id="editMapelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Kode Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="kode_mapel" id="edit_kode_mapel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mapel" id="edit_nama_mapel" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="deskripsi" id="edit_deskripsi" rows="3"></textarea>
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

<!-- Assign Guru Modal -->
<div class="modal fade" id="assignGuruModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Guru ke Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_guru">
                    <input type="hidden" name="id_mapel" id="assign_id_mapel">
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Guru <span class="text-danger">*</span></label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($guru_list as $guru): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="guru_ids[]" 
                                           value="<?php echo $guru['id']; ?>" id="guru_<?php echo $guru['id']; ?>">
                                    <label class="form-check-label" for="guru_<?php echo $guru['id']; ?>">
                                        <?php echo escape($guru['nama']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($guru_list)): ?>
                                <p class="text-muted">Tidak ada guru tersedia</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="text-muted">Pilih satu atau lebih guru untuk mengajar mata pelajaran ini</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editMapel(mapel) {
    document.getElementById('edit_id').value = mapel.id;
    document.getElementById('edit_kode_mapel').value = mapel.kode_mapel;
    document.getElementById('edit_nama_mapel').value = mapel.nama_mapel;
    document.getElementById('edit_deskripsi').value = mapel.deskripsi || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editMapelModal'));
    modal.show();
}

function assignGuru(mapelId, assignedGurus) {
    // Set mapel ID
    document.getElementById('assign_id_mapel').value = mapelId;
    
    // Clear all checkboxes
    document.querySelectorAll('#assignGuruModal input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Check assigned gurus
    if (assignedGurus && assignedGurus.length > 0) {
        assignedGurus.forEach(function(guru) {
            const checkbox = document.getElementById('guru_' + guru.id);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('assignGuruModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
