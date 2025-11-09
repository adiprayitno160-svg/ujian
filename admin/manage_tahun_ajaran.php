<?php
/**
 * Manage Tahun Ajaran - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk mengelola tahun ajaran dengan form batch input
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Kelola Tahun Ajaran';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'batch_create') {
        // Batch create tahun ajaran
        $tahun_ajaran_input = $_POST['tahun_ajaran'] ?? '';
        
        if (empty($tahun_ajaran_input)) {
            $error = 'Tahun ajaran tidak boleh kosong';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Split by newlines and process each line
                $lines = explode("\n", $tahun_ajaran_input);
                $created = 0;
                $skipped = 0;
                $errors = [];
                
                foreach ($lines as $line) {
                    $tahun_ajaran_str = trim($line);
                    if (empty($tahun_ajaran_str)) {
                        continue;
                    }
                    
                    // Parse tahun ajaran
                    $parsed = parse_tahun_ajaran($tahun_ajaran_str);
                    if (!$parsed) {
                        $errors[] = "Format tidak valid: $tahun_ajaran_str";
                        $skipped++;
                        continue;
                    }
                    
                    // Check if already exists
                    $stmt = $pdo->prepare("SELECT id FROM tahun_ajaran WHERE tahun_ajaran = ?");
                    $stmt->execute([$parsed['tahun_ajaran']]);
                    if ($stmt->fetch()) {
                        $skipped++;
                        continue;
                    }
                    
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO tahun_ajaran (tahun_ajaran, tahun_mulai, tahun_selesai, is_active) VALUES (?, ?, ?, 0)");
                    $stmt->execute([
                        $parsed['tahun_ajaran'],
                        $parsed['tahun_mulai'],
                        $parsed['tahun_selesai']
                    ]);
                    $created++;
                }
                
                $pdo->commit();
                
                if ($created > 0) {
                    $success = "Berhasil menambahkan $created tahun ajaran";
                    if ($skipped > 0) {
                        $success .= " ($skipped tahun ajaran dilewati karena sudah ada atau format tidak valid)";
                    }
                } else {
                    $error = "Tidak ada tahun ajaran baru yang ditambahkan. $skipped tahun ajaran sudah ada atau format tidak valid.";
                }
                
                if (!empty($errors) && $created == 0) {
                    $error = "Error: " . implode('<br>', array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $error .= "<br>... dan " . (count($errors) - 5) . " error lainnya";
                    }
                }
                
                log_activity('batch_create_tahun_ajaran', 'tahun_ajaran', null);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Batch create tahun ajaran error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $tahun_ajaran_str = trim($_POST['tahun_ajaran'] ?? '');
        
        if (empty($tahun_ajaran_str)) {
            $error = 'Tahun ajaran tidak boleh kosong';
        } else {
            $parsed = parse_tahun_ajaran($tahun_ajaran_str);
            if (!$parsed) {
                $error = 'Format tahun ajaran tidak valid. Gunakan format: 2024/2025';
            } else {
                try {
                    // Check if tahun ajaran already exists (excluding current record)
                    $stmt = $pdo->prepare("SELECT id FROM tahun_ajaran WHERE tahun_ajaran = ? AND id != ?");
                    $stmt->execute([$parsed['tahun_ajaran'], $id]);
                    if ($stmt->fetch()) {
                        $error = 'Tahun ajaran sudah ada';
                    } else {
                        $stmt = $pdo->prepare("UPDATE tahun_ajaran SET tahun_ajaran = ?, tahun_mulai = ?, tahun_selesai = ? WHERE id = ?");
                        $stmt->execute([
                            $parsed['tahun_ajaran'],
                            $parsed['tahun_mulai'],
                            $parsed['tahun_selesai'],
                            $id
                        ]);
                        $success = 'Tahun ajaran berhasil diupdate';
                        log_activity('update_tahun_ajaran', 'tahun_ajaran', $id);
                    }
                } catch (PDOException $e) {
                    $error = 'Terjadi kesalahan: ' . $e->getMessage();
                    error_log("Update tahun ajaran error: " . $e->getMessage());
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        try {
            // Check if tahun ajaran is active
            $stmt = $pdo->prepare("SELECT is_active FROM tahun_ajaran WHERE id = ?");
            $stmt->execute([$id]);
            $tahun_ajaran = $stmt->fetch();
            
            if ($tahun_ajaran && $tahun_ajaran['is_active']) {
                $error = 'Tidak dapat menghapus tahun ajaran yang sedang aktif';
            } else {
                $stmt = $pdo->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Tahun ajaran berhasil dihapus';
                log_activity('delete_tahun_ajaran', 'tahun_ajaran', $id);
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
            error_log("Delete tahun ajaran error: " . $e->getMessage());
        }
    } elseif ($action === 'set_active') {
        $id = intval($_POST['id'] ?? 0);
        if (set_tahun_ajaran_aktif($id)) {
            $success = 'Tahun ajaran aktif berhasil diubah';
            log_activity('set_tahun_ajaran_aktif', 'tahun_ajaran', $id);
        } else {
            $error = 'Gagal mengubah tahun ajaran aktif';
        }
    }
}

// Get all tahun ajaran
$tahun_ajaran_list = get_all_tahun_ajaran('tahun_mulai DESC');
$tahun_ajaran_aktif = get_tahun_ajaran_aktif();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Kelola Tahun Ajaran</h2>
        <p class="text-muted mb-0">Tahun Ajaran Aktif: <strong><?php echo escape($tahun_ajaran_aktif); ?></strong></p>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#batchCreateModal">
        <i class="fas fa-plus"></i> Tambah Tahun Ajaran
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<!-- Batch Create Form -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Tambah Tahun Ajaran (Batch)</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="batchCreateForm">
            <input type="hidden" name="action" value="batch_create">
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Petunjuk:</strong>
                <ul class="mb-0 mt-2">
                    <li>Masukkan tahun ajaran dalam format: <strong>2024/2025</strong> atau <strong>2024-2025</strong></li>
                    <li>Setiap tahun ajaran dipisahkan dengan baris baru</li>
                    <li>Anda dapat menambahkan beberapa tahun ajaran sekaligus</li>
                    <li>Format akan dinormalisasi secara otomatis</li>
                </ul>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                <textarea class="form-control" name="tahun_ajaran" id="batch_tahun_ajaran" rows="10" 
                          placeholder="2024/2025&#10;2025/2026&#10;2026/2027&#10;2027/2028" 
                          required></textarea>
                <small class="form-text text-muted">
                    Masukkan tahun ajaran, satu per baris. Contoh: 2024/2025, 2025/2026, dst.
                </small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Semua
                </button>
                <button type="button" class="btn btn-secondary" onclick="fillSampleData()">
                    <i class="fas fa-magic"></i> Isi Contoh Data
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                    <i class="fas fa-times"></i> Bersihkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tahun Ajaran List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Tahun Ajaran</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tahun Ajaran</th>
                        <th>Tahun Mulai</th>
                        <th>Tahun Selesai</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tahun_ajaran_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Belum ada tahun ajaran</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <tr class="<?php echo $ta['is_active'] ? 'table-success' : ''; ?>">
                            <td><?php echo $ta['id']; ?></td>
                            <td>
                                <strong><?php echo escape($ta['tahun_ajaran']); ?></strong>
                            </td>
                            <td><?php echo $ta['tahun_mulai']; ?></td>
                            <td><?php echo $ta['tahun_selesai']; ?></td>
                            <td>
                                <?php if ($ta['is_active']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($ta['created_at'], 'd/m/Y'); ?></td>
                            <td>
                                <?php if (!$ta['is_active']): ?>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('Set tahun ajaran <?php echo escape($ta['tahun_ajaran']); ?> sebagai aktif?');">
                                        <input type="hidden" name="action" value="set_active">
                                        <input type="hidden" name="id" value="<?php echo $ta['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Set Aktif">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="editTahunAjaran(<?php echo htmlspecialchars(json_encode($ta)); ?>)" 
                                        title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <?php if (!$ta['is_active']): ?>
                                    <form method="POST" style="display:inline;" 
                                          onsubmit="return confirm('Yakin hapus tahun ajaran <?php echo escape($ta['tahun_ajaran']); ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ta['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus">
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
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Tahun Ajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="tahun_ajaran" id="edit_tahun_ajaran" 
                               required placeholder="2024/2025">
                        <small class="form-text text-muted">Format: 2024/2025 atau 2024-2025</small>
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

<!-- Batch Create Modal -->
<div class="modal fade" id="batchCreateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Tahun Ajaran (Batch)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="modalBatchForm">
                    <input type="hidden" name="action" value="batch_create">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Petunjuk:</strong> Masukkan tahun ajaran, satu per baris. Format: 2024/2025
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="tahun_ajaran" id="modal_tahun_ajaran" rows="8" 
                                  placeholder="2024/2025&#10;2025/2026&#10;2026/2027" 
                                  required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-info" onclick="fillSampleDataModal()">Contoh Data</button>
                <button type="submit" form="modalBatchForm" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script>
function editTahunAjaran(ta) {
    document.getElementById('edit_id').value = ta.id;
    document.getElementById('edit_tahun_ajaran').value = ta.tahun_ajaran || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
}

function fillSampleData() {
    const currentYear = new Date().getFullYear();
    const sampleData = [];
    for (let i = 0; i < 5; i++) {
        const year = currentYear + i;
        sampleData.push(year + '/' + (year + 1));
    }
    document.getElementById('batch_tahun_ajaran').value = sampleData.join('\n');
}

function fillSampleDataModal() {
    const currentYear = new Date().getFullYear();
    const sampleData = [];
    for (let i = 0; i < 5; i++) {
        const year = currentYear + i;
        sampleData.push(year + '/' + (year + 1));
    }
    document.getElementById('modal_tahun_ajaran').value = sampleData.join('\n');
}

function clearForm() {
    if (confirm('Bersihkan form?')) {
        document.getElementById('batch_tahun_ajaran').value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

