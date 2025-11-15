<?php
/**
 * Manage Mata Pelajaran - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * CATATAN PENTING: Sistem menggunakan guru mata pelajaran (bukan guru kelas)
 * Untuk SMP: Guru mengajar mata pelajaran tertentu ke berbagai kelas
 * - Satu guru bisa mengajar beberapa mata pelajaran
 * - Satu mata pelajaran bisa diajar oleh beberapa guru
 * - Guru bisa membuat ujian/PR/tugas untuk semua kelas yang relevan dengan mata pelajarannya
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
    } elseif ($action === 'import_default_mapel') {
        // Import semua mata pelajaran default sesuai capture
        $default_mapel = [
            // Kelompok A (Muatan Nasional)
            ['kode' => 'PAI', 'nama' => 'Pendidikan Agama Islam dan Budi Pekerti'],
            ['kode' => 'PPKN', 'nama' => 'Pendidikan Pancasila'],
            ['kode' => 'BIN', 'nama' => 'Bahasa Indonesia'],
            ['kode' => 'MAT', 'nama' => 'Matematika'],
            ['kode' => 'IPA', 'nama' => 'Ilmu Pengetahuan Alam'],
            ['kode' => 'IPS', 'nama' => 'Ilmu Pengetahuan Sosial'],
            ['kode' => 'ING', 'nama' => 'Bahasa Inggris'],
            // Kelompok B (Muatan Nasional)
            ['kode' => 'SENI', 'nama' => 'Seni Rupa/Prakarya'],
            ['kode' => 'PJOK', 'nama' => 'Pendidikan Jasmani, Olahraga, dan Kesehatan'],
            ['kode' => 'INF', 'nama' => 'Informatika'],
            // Muatan Lokal
            ['kode' => 'BJW', 'nama' => 'Bahasa Jawa'],
        ];
        
        $imported = 0;
        $skipped = 0;
        
        try {
            $pdo->beginTransaction();
            
            foreach ($default_mapel as $mapel) {
                // Check if mapel already exists
                $stmt = $pdo->prepare("SELECT id FROM mapel WHERE kode_mapel = ? OR nama_mapel = ?");
                $stmt->execute([$mapel['kode'], $mapel['nama']]);
                if ($stmt->fetch()) {
                    $skipped++;
                    continue;
                }
                
                // Insert mapel
                $stmt = $pdo->prepare("INSERT INTO mapel (nama_mapel, kode_mapel, deskripsi) VALUES (?, ?, ?)");
                $stmt->execute([$mapel['nama'], $mapel['kode'], '']);
                $imported++;
            }
            
            $pdo->commit();
            $success = "Import berhasil! Ditambahkan: $imported mata pelajaran, Dilewati: $skipped mata pelajaran";
            log_activity('import_default_mapel', 'mapel', null);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat import: ' . $e->getMessage();
        }
    } elseif ($action === 'assign_tingkat') {
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $tingkat_list = $_POST['tingkat'] ?? [];
        
        if (!$id_mapel) {
            $error = 'Mata pelajaran tidak valid';
        } elseif (empty($tingkat_list)) {
            $error = 'Pilih minimal satu tingkat kelas';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Delete existing tingkat assignments for this mapel
                $stmt = $pdo->prepare("DELETE FROM mapel_tingkat WHERE id_mapel = ?");
                $stmt->execute([$id_mapel]);
                
                // Insert new tingkat assignments
                $stmt = $pdo->prepare("INSERT INTO mapel_tingkat (id_mapel, tingkat) VALUES (?, ?)");
                foreach ($tingkat_list as $tingkat) {
                    $tingkat = sanitize($tingkat);
                    if (!empty($tingkat)) {
                        $stmt->execute([$id_mapel, $tingkat]);
                    }
                }
                
                $pdo->commit();
                $success = 'Tingkat kelas berhasil di-assign ke mata pelajaran';
                log_activity('assign_mapel_tingkat', 'mapel_tingkat', $id_mapel);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Error in assign_tingkat: " . $e->getMessage());
            }
        }
    } elseif ($action === 'assign_guru') {
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $assignments = json_decode($_POST['assignments'] ?? '[]', true);
        
        if (!$id_mapel) {
            $error = 'Mata pelajaran tidak valid';
        } elseif (empty($assignments)) {
            $error = 'Pilih minimal satu guru dengan kelas';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Check if guru_mapel_kelas table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'guru_mapel_kelas'");
                $guru_mapel_kelas_exists = $stmt->rowCount() > 0;
                
                // Check if guru_mapel table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'guru_mapel'");
                $guru_mapel_exists = $stmt->rowCount() > 0;
                
                // Delete existing assignments for this mapel
                if ($guru_mapel_kelas_exists) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM guru_mapel_kelas WHERE id_mapel = ?");
                        $stmt->execute([$id_mapel]);
                    } catch (PDOException $e) {
                        error_log("Error deleting from guru_mapel_kelas: " . $e->getMessage());
                    }
                }
                
                // Also update guru_mapel table (for backward compatibility)
                if ($guru_mapel_exists) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM guru_mapel WHERE id_mapel = ?");
                        $stmt->execute([$id_mapel]);
                    } catch (PDOException $e) {
                        error_log("Error deleting from guru_mapel: " . $e->getMessage());
                    }
                }
                
                // Track which gurus are assigned
                $assigned_gurus = [];
                
                // Insert new assignments
                if ($guru_mapel_exists) {
                    $stmt_guru_mapel = $pdo->prepare("INSERT INTO guru_mapel (id_guru, id_mapel) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id");
                }
                
                if ($guru_mapel_kelas_exists) {
                    $stmt_guru_mapel_kelas = $pdo->prepare("INSERT INTO guru_mapel_kelas (id_guru, id_mapel, id_kelas) VALUES (?, ?, ?)");
                }
                
                foreach ($assignments as $assignment) {
                    $guru_id = intval($assignment['guru_id'] ?? 0);
                    $kelas_ids = $assignment['kelas_ids'] ?? [];
                    
                    if ($guru_id > 0 && !empty($kelas_ids)) {
                        // Add to guru_mapel (for backward compatibility)
                        if ($guru_mapel_exists) {
                            try {
                                $stmt_guru_mapel->execute([$guru_id, $id_mapel]);
                            } catch (PDOException $e) {
                                // Skip duplicate or log error
                                error_log("Error inserting into guru_mapel: " . $e->getMessage());
                            }
                        }
                        
                        // Add to guru_mapel_kelas for each kelas
                        if ($guru_mapel_kelas_exists) {
                            foreach ($kelas_ids as $kelas_id) {
                                $kelas_id = intval($kelas_id);
                                if ($kelas_id > 0) {
                                    try {
                                        $stmt_guru_mapel_kelas->execute([$guru_id, $id_mapel, $kelas_id]);
                                    } catch (PDOException $e) {
                                        // Skip duplicate or log error
                                        error_log("Error inserting into guru_mapel_kelas: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                        
                        $assigned_gurus[] = $guru_id;
                    }
                }
                
                $pdo->commit();
                $success = 'Guru dan kelas berhasil di-assign ke mata pelajaran';
                log_activity('assign_guru_mapel_kelas', 'guru_mapel_kelas', $id_mapel);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Error in assign_guru: " . $e->getMessage());
            }
        }
    } elseif ($action === 'remove_guru') {
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $id_guru = intval($_POST['id_guru'] ?? 0);
        
        if ($id_mapel && $id_guru) {
            try {
                $pdo->beginTransaction();
                
                // Check if guru_mapel_kelas table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'guru_mapel_kelas'");
                $guru_mapel_kelas_exists = $stmt->rowCount() > 0;
                
                // Check if guru_mapel table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'guru_mapel'");
                $guru_mapel_exists = $stmt->rowCount() > 0;
                
                // Remove from guru_mapel_kelas
                if ($guru_mapel_kelas_exists) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM guru_mapel_kelas WHERE id_mapel = ? AND id_guru = ?");
                        $stmt->execute([$id_mapel, $id_guru]);
                        
                        // Check if guru still has other kelas assignments for this mapel
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM guru_mapel_kelas WHERE id_guru = ? AND id_mapel = ?");
                        $stmt->execute([$id_guru, $id_mapel]);
                        $remaining = $stmt->fetchColumn();
                    } catch (PDOException $e) {
                        error_log("Error removing from guru_mapel_kelas: " . $e->getMessage());
                        $remaining = 0;
                    }
                } else {
                    $remaining = 0;
                }
                
                // If no more kelas assignments, remove from guru_mapel
                if ($guru_mapel_exists && $remaining == 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM guru_mapel WHERE id_mapel = ? AND id_guru = ?");
                        $stmt->execute([$id_mapel, $id_guru]);
                    } catch (PDOException $e) {
                        error_log("Error removing from guru_mapel: " . $e->getMessage());
                    }
                }
                
                $pdo->commit();
                $success = 'Guru berhasil dihapus dari mata pelajaran';
                log_activity('remove_guru_mapel', 'guru_mapel', $id_mapel);
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Error in remove_guru: " . $e->getMessage());
            }
        }
    }
}

// Get mapel with assigned gurus and kelas
try {
    $stmt = $pdo->query("SELECT m.* 
                         FROM mapel m
                         ORDER BY m.kode_mapel ASC");
    $mapel_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching mapel: " . $e->getMessage());
    $mapel_list = [];
    $error = 'Terjadi kesalahan saat mengambil data mata pelajaran: ' . $e->getMessage();
}

// Get all gurus
try {
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'guru' AND status = 'active' ORDER BY nama ASC");
    $guru_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching gurus: " . $e->getMessage());
    $guru_list = [];
}

// Get all active classes
try {
    // Check if status column exists in kelas table
    $stmt = $pdo->query("SHOW COLUMNS FROM kelas LIKE 'status'");
    $has_status = $stmt->rowCount() > 0;
    
    if ($has_status) {
        $stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tingkat ASC, nama_kelas ASC");
    } else {
        $stmt = $pdo->query("SELECT * FROM kelas ORDER BY tingkat ASC, nama_kelas ASC");
    }
    $kelas_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching kelas: " . $e->getMessage());
    $kelas_list = [];
}

// Get unique tingkat from kelas
$tingkat_list = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT tingkat FROM kelas WHERE tingkat IS NOT NULL AND tingkat != '' ORDER BY tingkat ASC");
    $tingkat_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching tingkat: " . $e->getMessage());
    $tingkat_list = [];
}

// Get guru-mapel-kelas assignments for each mapel
$guru_mapel_kelas_data = [];
foreach ($mapel_list as &$mapel) {
    // Get tingkat assignments for this mapel
    try {
        $stmt = $pdo->prepare("SELECT tingkat FROM mapel_tingkat WHERE id_mapel = ? ORDER BY tingkat ASC");
        $stmt->execute([$mapel['id']]);
        $mapel['tingkat'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching tingkat for mapel {$mapel['id']}: " . $e->getMessage());
        $mapel['tingkat'] = [];
    }
    
    // Get gurus with their kelas assignments
    try {
        // Check if guru_mapel_kelas table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'guru_mapel_kelas'");
        $table_exists = $stmt->rowCount() > 0;
        
        if ($table_exists) {
            $stmt = $pdo->prepare("SELECT DISTINCT 
                              u.id as guru_id, 
                              u.nama as guru_nama,
                              GROUP_CONCAT(DISTINCT CONCAT(k.id, ':', k.nama_kelas) SEPARATOR '|') as kelas_list
                              FROM guru_mapel_kelas gmk
                              INNER JOIN users u ON gmk.id_guru = u.id
                              INNER JOIN kelas k ON gmk.id_kelas = k.id
                              WHERE gmk.id_mapel = ?
                              GROUP BY u.id, u.nama
                              ORDER BY u.nama ASC");
            $stmt->execute([$mapel['id']]);
            $guru_assignments = $stmt->fetchAll();
        } else {
            // Fallback to guru_mapel table if guru_mapel_kelas doesn't exist
            $stmt = $pdo->prepare("SELECT DISTINCT 
                              u.id as guru_id, 
                              u.nama as guru_nama
                              FROM guru_mapel gm
                              INNER JOIN users u ON gm.id_guru = u.id
                              WHERE gm.id_mapel = ?
                              ORDER BY u.nama ASC");
            $stmt->execute([$mapel['id']]);
            $guru_assignments = $stmt->fetchAll();
            // Set empty kelas_list for each guru
            foreach ($guru_assignments as &$assignment) {
                $assignment['kelas_list'] = '';
            }
            unset($assignment);
        }
    } catch (PDOException $e) {
        error_log("Error fetching guru assignments for mapel {$mapel['id']}: " . $e->getMessage());
        $guru_assignments = [];
    }
    
    $mapel['gurus'] = [];
    foreach ($guru_assignments as $assignment) {
        $kelas_array = [];
        if (!empty($assignment['kelas_list'])) {
            $kelas_pairs = explode('|', $assignment['kelas_list']);
            foreach ($kelas_pairs as $pair) {
                if (!empty($pair)) {
                    list($kelas_id, $kelas_nama) = explode(':', $pair, 2);
                    $kelas_array[] = ['id' => $kelas_id, 'nama' => $kelas_nama];
                }
            }
        }
        
        $mapel['gurus'][] = [
            'id' => $assignment['guru_id'],
            'nama' => $assignment['guru_nama'],
            'kelas' => $kelas_array
        ];
    }
}
unset($mapel);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">Kelola Mata Pelajaran</h2>
        <p class="text-muted mb-0">Daftar semua mata pelajaran yang tersedia di sistem</p>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin mengimport semua mata pelajaran default? Mata pelajaran yang sudah ada akan dilewati.');">
            <input type="hidden" name="action" value="import_default_mapel">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-download"></i> Import Mata Pelajaran Default
            </button>
        </form>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createMapelModal">
            <i class="fas fa-plus"></i> Tambah Mata Pelajaran
        </button>
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
                        <th>Tingkat</th>
                        <th>Guru</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mapel_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted mb-3">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p class="mb-2"><strong>Belum ada mata pelajaran</strong></p>
                                    <p class="small">Klik tombol "Import Mata Pelajaran Default" di atas untuk menambahkan mata pelajaran sesuai kurikulum.</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mapel_list as $mapel): ?>
                        <?php
                        $assigned_gurus = $mapel['gurus'] ?? [];
                        $assigned_tingkat = $mapel['tingkat'] ?? [];
                        ?>
                        <tr>
                            <td><?php echo $mapel['id']; ?></td>
                            <td><span class="badge bg-primary"><?php echo escape($mapel['kode_mapel']); ?></span></td>
                            <td><?php echo escape($mapel['nama_mapel']); ?></td>
                            <td><?php echo escape($mapel['deskripsi'] ?? '-'); ?></td>
                            <td>
                                <?php if (!empty($assigned_tingkat)): ?>
                                    <?php foreach ($assigned_tingkat as $tingkat): ?>
                                        <span class="badge bg-success"><?php echo escape($tingkat); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Belum di-assign</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($assigned_gurus)): ?>
                                    <div class="d-flex flex-column gap-2">
                                        <?php foreach ($assigned_gurus as $guru): ?>
                                            <div>
                                                <span class="badge bg-info mb-1">
                                                    <i class="fas fa-user"></i> <?php echo escape($guru['nama']); ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus guru ini dari mata pelajaran?');">
                                                        <input type="hidden" name="action" value="remove_guru">
                                                        <input type="hidden" name="id_mapel" value="<?php echo $mapel['id']; ?>">
                                                        <input type="hidden" name="id_guru" value="<?php echo $guru['id']; ?>">
                                                        <button type="submit" class="btn-close btn-close-white" style="font-size: 0.6rem; margin-left: 4px;" title="Hapus"></button>
                                                    </form>
                                                </span>
                                                <?php if (!empty($guru['kelas'])): ?>
                                                    <div class="ms-3">
                                                        <small class="text-muted">Kelas: </small>
                                                        <?php foreach ($guru['kelas'] as $kelas): ?>
                                                            <span class="badge bg-secondary"><?php echo escape($kelas['nama']); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Belum ada guru</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-sm btn-info" onclick="assignTingkat(<?php echo $mapel['id']; ?>, <?php echo htmlspecialchars(json_encode($assigned_tingkat)); ?>)">
                                        <i class="fas fa-layer-group"></i> Tingkat
                                    </button>
                                    <button type="button" class="btn btn-sm btn-success" onclick="assignGuru(<?php echo $mapel['id']; ?>, <?php echo htmlspecialchars(json_encode($assigned_gurus)); ?>)">
                                        <i class="fas fa-user-plus"></i> Guru
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

<!-- Assign Tingkat Modal -->
<div class="modal fade" id="assignTingkatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="assignTingkatForm">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Tingkat Kelas ke Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_tingkat">
                    <input type="hidden" name="id_mapel" id="assign_tingkat_id_mapel">
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Tingkat Kelas <span class="text-danger">*</span></label>
                        <div class="border rounded p-3">
                            <?php if (!empty($tingkat_list)): ?>
                                <?php foreach ($tingkat_list as $tingkat): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input tingkat-checkbox" 
                                               type="checkbox" 
                                               name="tingkat[]"
                                               value="<?php echo escape($tingkat); ?>"
                                               id="tingkat_<?php echo escape($tingkat); ?>">
                                        <label class="form-check-label" for="tingkat_<?php echo escape($tingkat); ?>">
                                            <strong><?php echo escape($tingkat); ?></strong>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">Tidak ada tingkat kelas tersedia. Pastikan kelas sudah dibuat dengan tingkat yang sesuai.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="text-muted">
                        <strong>Catatan:</strong> Pilih tingkat kelas yang akan menggunakan mata pelajaran ini. 
                        Setelah di-assign, mata pelajaran ini akan tersedia untuk semua kelas di tingkat yang dipilih.
                        Contoh: Jika memilih "VII", maka semua kelas VII (VII A, VII B, VII C, dll) akan menggunakan mata pelajaran ini.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Guru Modal -->
<div class="modal fade" id="assignGuruModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="assignGuruForm">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Guru dan Kelas ke Mata Pelajaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_guru">
                    <input type="hidden" name="id_mapel" id="assign_id_mapel">
                    <input type="hidden" name="assignments" id="assignments_input">
                    
                    <div class="mb-3">
                        <label class="form-label">Pilih Guru dan Kelas <span class="text-danger">*</span></label>
                        <div class="border rounded p-3" style="max-height: 500px; overflow-y: auto;" id="guruKelasContainer">
                            <?php foreach ($guru_list as $guru): ?>
                                <div class="card mb-3 guru-card" data-guru-id="<?php echo $guru['id']; ?>">
                                    <div class="card-body">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input guru-checkbox" 
                                                   type="checkbox" 
                                                   data-guru-id="<?php echo $guru['id']; ?>"
                                                   id="guru_<?php echo $guru['id']; ?>"
                                                   onchange="toggleGuruKelas(<?php echo $guru['id']; ?>)">
                                            <label class="form-check-label fw-bold" for="guru_<?php echo $guru['id']; ?>">
                                                <i class="fas fa-user"></i> <?php echo escape($guru['nama']); ?>
                                            </label>
                                        </div>
                                        <div class="kelas-container ms-4" id="kelas_<?php echo $guru['id']; ?>" style="display: none;">
                                            <label class="form-label small">Pilih Kelas:</label>
                                            <div class="row g-2">
                                                <?php 
                                                // Group kelas by tingkat
                                                $kelas_by_tingkat = [];
                                                foreach ($kelas_list as $kelas) {
                                                    $tingkat = $kelas['tingkat'] ?? 'Lainnya';
                                                    if (!isset($kelas_by_tingkat[$tingkat])) {
                                                        $kelas_by_tingkat[$tingkat] = [];
                                                    }
                                                    $kelas_by_tingkat[$tingkat][] = $kelas;
                                                }
                                                ksort($kelas_by_tingkat);
                                                ?>
                                                <?php foreach ($kelas_by_tingkat as $tingkat => $kelas_group): ?>
                                                    <div class="col-12">
                                                        <small class="text-muted fw-bold"><?php echo $tingkat ? $tingkat : 'Lainnya'; ?>:</small>
                                                        <?php foreach ($kelas_group as $kelas): ?>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input kelas-checkbox" 
                                                                       type="checkbox" 
                                                                       data-guru-id="<?php echo $guru['id']; ?>"
                                                                       data-kelas-id="<?php echo $kelas['id']; ?>"
                                                                       id="kelas_<?php echo $guru['id']; ?>_<?php echo $kelas['id']; ?>">
                                                                <label class="form-check-label" for="kelas_<?php echo $guru['id']; ?>_<?php echo $kelas['id']; ?>">
                                                                    <?php echo escape($kelas['nama_kelas']); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($guru_list)): ?>
                                <p class="text-muted">Tidak ada guru tersedia</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <small class="text-muted">
                        <strong>Catatan:</strong> Sistem menggunakan guru mata pelajaran (bukan guru kelas). 
                        Satu guru bisa mengajar beberapa mata pelajaran, dan satu mata pelajaran bisa diajar oleh beberapa guru. 
                        Setiap guru bisa di-assign ke kelas-kelas tertentu untuk mata pelajaran ini.
                        Contoh: Guru A mengajar kelas VII A, VII B, VII C; Guru B mengajar kelas VII D, VII E.
                    </small>
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
const kelasList = <?php echo json_encode($kelas_list); ?>;

function editMapel(mapel) {
    document.getElementById('edit_id').value = mapel.id;
    document.getElementById('edit_kode_mapel').value = mapel.kode_mapel;
    document.getElementById('edit_nama_mapel').value = mapel.nama_mapel;
    document.getElementById('edit_deskripsi').value = mapel.deskripsi || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editMapelModal'));
    modal.show();
}

function assignTingkat(mapelId, assignedTingkat) {
    // Set mapel ID
    document.getElementById('assign_tingkat_id_mapel').value = mapelId;
    
    // Clear all checkboxes
    document.querySelectorAll('#assignTingkatModal input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Check assigned tingkat
    if (assignedTingkat && assignedTingkat.length > 0) {
        assignedTingkat.forEach(function(tingkat) {
            const checkbox = document.getElementById('tingkat_' + tingkat);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('assignTingkatModal'));
    modal.show();
}

function assignGuru(mapelId, assignedGurus) {
    // Set mapel ID
    document.getElementById('assign_id_mapel').value = mapelId;
    
    // Clear all checkboxes
    document.querySelectorAll('#assignGuruModal input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    
    // Hide all kelas containers
    document.querySelectorAll('.kelas-container').forEach(container => {
        container.style.display = 'none';
    });
    
    // Check assigned gurus and their kelas
    if (assignedGurus && assignedGurus.length > 0) {
        assignedGurus.forEach(function(guru) {
            const guruCheckbox = document.getElementById('guru_' + guru.id);
            if (guruCheckbox) {
                guruCheckbox.checked = true;
                toggleGuruKelas(guru.id);
                
                // Check kelas checkboxes
                if (guru.kelas && guru.kelas.length > 0) {
                    guru.kelas.forEach(function(kelas) {
                        const kelasCheckbox = document.getElementById('kelas_' + guru.id + '_' + kelas.id);
                        if (kelasCheckbox) {
                            kelasCheckbox.checked = true;
                        }
                    });
                }
            }
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('assignGuruModal'));
    modal.show();
}

function toggleGuruKelas(guruId) {
    const guruCheckbox = document.getElementById('guru_' + guruId);
    const kelasContainer = document.getElementById('kelas_' + guruId);
    
    if (guruCheckbox && kelasContainer) {
        if (guruCheckbox.checked) {
            kelasContainer.style.display = 'block';
        } else {
            kelasContainer.style.display = 'none';
            // Uncheck all kelas for this guru
            document.querySelectorAll('.kelas-checkbox[data-guru-id="' + guruId + '"]').forEach(cb => {
                cb.checked = false;
            });
        }
    }
}

function validateAssignForm() {
    const assignments = [];
    const checkedGurus = document.querySelectorAll('.guru-checkbox:checked');
    
    if (checkedGurus.length === 0) {
        alert('Pilih minimal satu guru');
        return false;
    }
    
    let isValid = true;
    checkedGurus.forEach(function(guruCheckbox) {
        if (!isValid) return;
        
        const guruId = parseInt(guruCheckbox.dataset.guruId);
        const checkedKelas = document.querySelectorAll('.kelas-checkbox[data-guru-id="' + guruId + '"]:checked');
        
        if (checkedKelas.length === 0) {
            const guruLabel = document.querySelector('label[for="guru_' + guruId + '"]');
            const guruName = guruLabel ? guruLabel.textContent.trim().replace('👤', '').trim() : 'Guru';
            alert(guruName + ' harus memiliki minimal satu kelas yang dipilih');
            isValid = false;
            return;
        }
        
        const kelasIds = [];
        checkedKelas.forEach(function(kelasCheckbox) {
            kelasIds.push(parseInt(kelasCheckbox.dataset.kelasId));
        });
        
        assignments.push({
            guru_id: guruId,
            kelas_ids: kelasIds
        });
    });
    
    if (!isValid) {
        return false;
    }
    
    if (assignments.length === 0) {
        alert('Pilih minimal satu guru dengan kelas');
        return false;
    }
    
    // Set assignments to hidden input
    document.getElementById('assignments_input').value = JSON.stringify(assignments);
    
    return true;
}

// Handle form submit
document.getElementById('assignGuruForm').addEventListener('submit', function(e) {
    if (!validateAssignForm()) {
        e.preventDefault();
        return false;
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
