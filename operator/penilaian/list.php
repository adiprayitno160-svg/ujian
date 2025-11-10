<?php
/**
 * Penilaian Manual - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator melihat dan mengumpulkan nilai dari semua guru
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Ledger Nilai Manual';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';
$id_kelas = intval($_GET['id_kelas'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$aktif_filter = $_GET['aktif'] ?? '';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Handle approve action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    $id_penilaian = intval($_POST['id_penilaian'] ?? 0);
    if ($id_penilaian) {
        try {
            $stmt = $pdo->prepare("UPDATE penilaian_manual 
                                  SET status = 'approved', 
                                      approved_by = ?, 
                                      approved_at = NOW(),
                                      updated_at = NOW()
                                  WHERE id = ? AND status = 'submitted'");
            $stmt->execute([$_SESSION['user_id'], $id_penilaian]);
            $success = 'Penilaian berhasil disetujui.';
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Handle activate/deactivate action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'activate' || $_POST['action'] === 'deactivate')) {
    $id_penilaian = intval($_POST['id_penilaian'] ?? 0);
    $action_type = $_POST['action'];
    if ($id_penilaian) {
        try {
            if ($action_type === 'activate') {
                $stmt = $pdo->prepare("UPDATE penilaian_manual 
                                      SET aktif = 1, 
                                          activated_by = ?, 
                                          activated_at = NOW(),
                                          updated_at = NOW()
                                      WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $id_penilaian]);
                $success = 'Nilai manual berhasil diaktifkan.';
            } else {
                $stmt = $pdo->prepare("UPDATE penilaian_manual 
                                      SET aktif = 0, 
                                          activated_by = NULL, 
                                          activated_at = NULL,
                                          updated_at = NOW()
                                      WHERE id = ?");
                $stmt->execute([$id_penilaian]);
                $success = 'Nilai manual berhasil dinonaktifkan.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get kelas list
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? AND status = 'active' ORDER BY nama_kelas ASC");
$stmt->execute([$tahun_ajaran]);
$kelas_list = $stmt->fetchAll();

// Check if aktif column exists (for backward compatibility)
$aktif_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Column doesn't exist or table doesn't exist
    error_log("Check aktif column: " . $e->getMessage());
}

// Build query - Get all manual grades from all teachers (ledger view)
if ($aktif_column_exists) {
    $sql = "SELECT pm.*, 
            g.nama as nama_guru, 
            s.nama as nama_siswa, 
            s.username as nis, 
            m.nama_mapel, 
            k.nama_kelas,
            u_approved.nama as approved_by_name,
            u_activated.nama as activated_by_name
            FROM penilaian_manual pm
            INNER JOIN users g ON pm.id_guru = g.id
            INNER JOIN users s ON pm.id_siswa = s.id
            INNER JOIN mapel m ON pm.id_mapel = m.id
            INNER JOIN kelas k ON pm.id_kelas = k.id
            LEFT JOIN users u_approved ON pm.approved_by = u_approved.id
            LEFT JOIN users u_activated ON pm.activated_by = u_activated.id
            WHERE pm.tahun_ajaran = ? AND pm.semester = ?";
} else {
    // Fallback if aktif column doesn't exist yet
    $sql = "SELECT pm.*, 
            0 as aktif,
            NULL as activated_by,
            NULL as activated_at,
            g.nama as nama_guru, 
            s.nama as nama_siswa, 
            s.username as nis, 
            m.nama_mapel, 
            k.nama_kelas,
            u_approved.nama as approved_by_name,
            NULL as activated_by_name
            FROM penilaian_manual pm
            INNER JOIN users g ON pm.id_guru = g.id
            INNER JOIN users s ON pm.id_siswa = s.id
            INNER JOIN mapel m ON pm.id_mapel = m.id
            INNER JOIN kelas k ON pm.id_kelas = k.id
            LEFT JOIN users u_approved ON pm.approved_by = u_approved.id
            WHERE pm.tahun_ajaran = ? AND pm.semester = ?";
}
$params = [$tahun_ajaran, $semester];

if ($id_kelas) {
    $sql .= " AND pm.id_kelas = ?";
    $params[] = $id_kelas;
}

if ($status_filter) {
    $sql .= " AND pm.status = ?";
    $params[] = $status_filter;
}

if ($aktif_filter !== '' && $aktif_column_exists) {
    $sql .= " AND pm.aktif = ?";
    $params[] = intval($aktif_filter);
}

$sql .= " ORDER BY k.nama_kelas ASC, s.nama ASC, m.nama_mapel ASC, g.nama ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$penilaian_list = $stmt->fetchAll();

// Statistics
$stats = [
    'total' => count($penilaian_list),
    'draft' => count(array_filter($penilaian_list, fn($p) => ($p['status'] ?? 'draft') == 'draft')),
    'submitted' => count(array_filter($penilaian_list, fn($p) => ($p['status'] ?? '') == 'submitted')),
    'approved' => count(array_filter($penilaian_list, fn($p) => ($p['status'] ?? '') == 'approved')),
    'aktif' => $aktif_column_exists ? count(array_filter($penilaian_list, fn($p) => ($p['aktif'] ?? 0) == 1)) : 0,
    'tidak_aktif' => $aktif_column_exists ? count(array_filter($penilaian_list, fn($p) => ($p['aktif'] ?? 0) == 0)) : count($penilaian_list)
];
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Ledger Nilai Manual</h2>
        <p class="text-muted">Ledger nilai manual dari semua guru mata pelajaran. Operator dapat mengaktifkan atau menonaktifkan nilai manual yang telah dikumpulkan.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-clipboard-list fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Total Penilaian</h6>
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
                        <div class="bg-secondary bg-opacity-10 rounded p-3">
                            <i class="fas fa-edit fa-2x text-secondary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Draft</h6>
                        <h3 class="mb-0"><?php echo $stats['draft']; ?></h3>
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
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-paper-plane fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Menunggu Approval</h6>
                        <h3 class="mb-0"><?php echo $stats['submitted']; ?></h3>
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
                        <h6 class="text-muted mb-0">Disetujui</h6>
                        <h3 class="mb-0"><?php echo $stats['approved']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($aktif_column_exists): ?>
<!-- Activation Statistics -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-toggle-on fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Aktif</h6>
                        <h3 class="mb-0"><?php echo $stats['aktif']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-secondary bg-opacity-10 rounded p-3">
                            <i class="fas fa-toggle-off fa-2x text-secondary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Tidak Aktif</h6>
                        <h3 class="mb-0"><?php echo $stats['tidak_aktif']; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning mb-4">
    <i class="fas fa-exclamation-triangle"></i> 
    <strong>Perhatian:</strong> Kolom aktivasi belum tersedia di database. Silakan refresh halaman untuk menjalankan migration otomatis, atau hubungi administrator.
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <input type="text" class="form-control" value="<?php echo escape($tahun_ajaran); ?>" disabled>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="ganjil" <?php echo $semester == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester == 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $id_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua Status</option>
                    <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="submitted" <?php echo $status_filter == 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                </select>
            </div>
            <?php if ($aktif_column_exists): ?>
            <div class="col-md-2">
                <label class="form-label">Status Aktif</label>
                <select class="form-select" name="aktif">
                    <option value="">Semua</option>
                    <option value="1" <?php echo $aktif_filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?php echo $aktif_filter === '0' ? 'selected' : ''; ?>>Tidak Aktif</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($penilaian_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada data penilaian.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Tugas</th>
                            <th>UTS</th>
                            <th>UAS</th>
                            <th>Akhir</th>
                            <th>Predikat</th>
                            <th>Status</th>
                            <?php if ($aktif_column_exists): ?>
                            <th>Aktif</th>
                            <?php endif; ?>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($penilaian_list as $index => $penilaian): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo escape($penilaian['nis']); ?></strong></td>
                                <td><?php echo escape($penilaian['nama_siswa']); ?></td>
                                <td><?php echo escape($penilaian['nama_kelas']); ?></td>
                                <td><?php echo escape($penilaian['nama_mapel']); ?></td>
                                <td><?php echo escape($penilaian['nama_guru']); ?></td>
                                <td><?php echo $penilaian['nilai_tugas'] !== null ? number_format($penilaian['nilai_tugas'], 2) : '-'; ?></td>
                                <td><?php echo $penilaian['nilai_uts'] !== null ? number_format($penilaian['nilai_uts'], 2) : '-'; ?></td>
                                <td><?php echo $penilaian['nilai_uas'] !== null ? number_format($penilaian['nilai_uas'], 2) : '-'; ?></td>
                                <td>
                                    <strong><?php echo $penilaian['nilai_akhir'] !== null ? number_format($penilaian['nilai_akhir'], 2) : '-'; ?></strong>
                                </td>
                                <td>
                                    <?php if ($penilaian['predikat']): ?>
                                        <span class="badge bg-info"><?php echo escape($penilaian['predikat']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_badge = [
                                        'draft' => ['bg' => 'secondary', 'icon' => 'edit', 'text' => 'Draft'],
                                        'submitted' => ['bg' => 'warning', 'icon' => 'paper-plane', 'text' => 'Submitted'],
                                        'approved' => ['bg' => 'success', 'icon' => 'check-circle', 'text' => 'Approved']
                                    ];
                                    $status_info = $status_badge[$penilaian['status']] ?? $status_badge['draft'];
                                    ?>
                                    <span class="badge bg-<?php echo $status_info['bg']; ?>">
                                        <i class="fas fa-<?php echo $status_info['icon']; ?>"></i> <?php echo $status_info['text']; ?>
                                    </span>
                                </td>
                                <?php if ($aktif_column_exists): ?>
                                <td class="text-center">
                                    <?php if (($penilaian['aktif'] ?? 0) == 1): ?>
                                        <span class="badge bg-success" title="Aktif">
                                            <i class="fas fa-toggle-on"></i> Aktif
                                        </span>
                                        <?php if (!empty($penilaian['activated_by_name'])): ?>
                                            <br><small class="text-muted" title="Diaktifkan oleh: <?php echo escape($penilaian['activated_by_name']); ?>">
                                                <?php echo format_date($penilaian['activated_at'] ?? '', 'd/m/Y H:i'); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Tidak Aktif">
                                            <i class="fas fa-toggle-off"></i> Tidak Aktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div class="btn-group-vertical" role="group">
                                        <?php if ($aktif_column_exists && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')): ?>
                                            <?php if (($penilaian['aktif'] ?? 0) == 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_penilaian" value="<?php echo $penilaian['id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="btn btn-sm btn-success mb-1" onclick="return confirm('Apakah Anda yakin ingin mengaktifkan nilai manual ini?');">
                                                        <i class="fas fa-toggle-on"></i> Aktifkan
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id_penilaian" value="<?php echo $penilaian['id']; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="btn btn-sm btn-warning mb-1" onclick="return confirm('Apakah Anda yakin ingin menonaktifkan nilai manual ini?');">
                                                        <i class="fas fa-toggle-off"></i> Nonaktifkan
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($penilaian['status'] == 'submitted'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="id_penilaian" value="<?php echo $penilaian['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-info" onclick="return confirm('Apakah Anda yakin ingin menyetujui penilaian ini?');">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                        <?php elseif ($penilaian['status'] == 'approved' && $penilaian['approved_by_name']): ?>
                                            <small class="text-muted">
                                                Disetujui oleh:<br>
                                                <?php echo escape($penilaian['approved_by_name']); ?><br>
                                                <small><?php echo format_date($penilaian['approved_at'], 'd/m/Y H:i'); ?></small>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if ($penilaian['keterangan']): ?>
                                <tr>
                                    <td colspan="<?php echo $aktif_column_exists ? '14' : '13'; ?>" class="bg-light">
                                        <small><strong>Keterangan:</strong> <?php echo escape($penilaian['keterangan']); ?></small>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

