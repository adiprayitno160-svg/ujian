<?php
/**
 * Reset Fraud Lock - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator mereset lock akun siswa yang terkunci karena fraud
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    redirect('');
}

$page_title = 'Reset Fraud Lock';
$role_css = 'operator';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'reset_lock') {
        $id_siswa = intval($_POST['id_siswa'] ?? 0);
        $id_sesi = intval($_POST['id_sesi'] ?? 0);
        $id_ujian = intval($_POST['id_ujian'] ?? 0);
        
        if ($id_siswa && $id_sesi && $id_ujian) {
            try {
                $pdo->beginTransaction();
                
                // Reset fraud flags (same logic as automatic reset in siswa/login.php)
                $stmt = $pdo->prepare("UPDATE nilai 
                                      SET is_fraud = 0,
                                          requires_relogin = 0,
                                          fraud_reason = NULL,
                                          fraud_detected_at = NULL,
                                          warning_count = 0
                                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
                $stmt->execute([$id_sesi, $id_ujian, $id_siswa]);
                
                // Log activity
                log_activity('reset_fraud_lock', 'nilai', $id_siswa);
                
                $pdo->commit();
                $success = 'Fraud lock berhasil direset. Siswa dapat login kembali.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Reset fraud lock error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mereset fraud lock: ' . $e->getMessage();
            }
        } else {
            $error = 'Parameter tidak valid';
        }
    } elseif ($action === 'reset_all_locks') {
        $id_siswa = intval($_POST['id_siswa'] ?? 0);
        
        if ($id_siswa) {
            try {
                $pdo->beginTransaction();
                
                // Reset all fraud flags for this siswa
                $stmt = $pdo->prepare("UPDATE nilai 
                                      SET is_fraud = 0,
                                          requires_relogin = 0,
                                          fraud_reason = NULL,
                                          fraud_detected_at = NULL,
                                          warning_count = 0
                                      WHERE id_siswa = ?");
                $stmt->execute([$id_siswa]);
                
                // Log activity
                log_activity('reset_all_fraud_locks', 'nilai', $id_siswa);
                
                $pdo->commit();
                $success = 'Semua fraud lock untuk siswa ini berhasil direset.';
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Reset all fraud locks error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mereset fraud lock: ' . $e->getMessage();
            }
        } else {
            $error = 'Parameter tidak valid';
        }
    }
}

// Get filter
$search = sanitize($_GET['search'] ?? '');
$status_filter = sanitize($_GET['status'] ?? 'all'); // all, locked, fraud

// Build query to get locked/fraud siswa ONLY (not all siswa)
$where = "(n.is_fraud = 1 OR n.requires_relogin = 1)";
$params = [];

if ($search) {
    $where .= " AND (u.nama LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter === 'locked') {
    $where .= " AND n.requires_relogin = 1 AND n.is_fraud = 0";
} elseif ($status_filter === 'fraud') {
    $where .= " AND n.is_fraud = 1";
}

$stmt = $pdo->prepare("SELECT DISTINCT
                      n.id_siswa,
                      n.id_sesi,
                      n.id_ujian,
                      n.is_fraud,
                      n.requires_relogin,
                      n.fraud_reason,
                      n.fraud_detected_at,
                      n.warning_count,
                      u.nama as nama_siswa,
                      u.username as nis,
                      s.nama_sesi,
                      s.waktu_mulai,
                      uj.judul as judul_ujian,
                      m.nama_mapel
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      INNER JOIN sesi_ujian s ON n.id_sesi = s.id
                      INNER JOIN ujian uj ON n.id_ujian = uj.id
                      INNER JOIN mapel m ON uj.id_mapel = m.id
                      WHERE $where
                      ORDER BY n.fraud_detected_at DESC, n.id DESC");
$stmt->execute($params);
$locked_list = $stmt->fetchAll();

// Group by siswa for summary
$siswa_summary = [];
foreach ($locked_list as $item) {
    $siswa_id = $item['id_siswa'];
    if (!isset($siswa_summary[$siswa_id])) {
        $siswa_summary[$siswa_id] = [
            'id_siswa' => $siswa_id,
            'nama_siswa' => $item['nama_siswa'],
            'nis' => $item['nis'],
            'total_locked' => 0,
            'total_fraud' => 0,
            'latest_fraud' => null
        ];
    }
    $siswa_summary[$siswa_id]['total_locked']++;
    if ($item['is_fraud']) {
        $siswa_summary[$siswa_id]['total_fraud']++;
        if (!$siswa_summary[$siswa_id]['latest_fraud'] || 
            $item['fraud_detected_at'] > $siswa_summary[$siswa_id]['latest_fraud']) {
            $siswa_summary[$siswa_id]['latest_fraud'] = $item['fraud_detected_at'];
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Reset Fraud Lock</h2>
        </div>
        <p class="text-muted">
            Reset lock akun siswa yang terkunci karena fraud detection. 
            <br><small class="text-info">
                <i class="fas fa-info-circle"></i> 
                Sistem akan otomatis mereset saat siswa login ulang. Gunakan reset manual ini jika reset otomatis gagal atau untuk kasus khusus.
            </small>
        </p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-5">
                <input type="text" class="form-control" name="search" placeholder="Cari nama atau NIS siswa..." value="<?php echo escape($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="locked" <?php echo $status_filter === 'locked' ? 'selected' : ''; ?>>Terkunci (Requires Relogin)</option>
                    <option value="fraud" <?php echo $status_filter === 'fraud' ? 'selected' : ''; ?>>Fraud Terdeteksi</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
            <div class="col-md-2">
                <a href="<?php echo base_url('operator/reset_fraud_lock.php'); ?>" class="btn btn-outline-secondary w-100">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary by Siswa -->
<?php if (!empty($siswa_summary)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0"><i class="fas fa-users"></i> Ringkasan Siswa Terkunci</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama Siswa</th>
                        <th>Total Terkunci</th>
                        <th>Total Fraud</th>
                        <th>Fraud Terakhir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswa_summary as $siswa): ?>
                    <tr>
                        <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                        <td><?php echo escape($siswa['nama_siswa']); ?></td>
                        <td><span class="badge bg-warning"><?php echo $siswa['total_locked']; ?></span></td>
                        <td><span class="badge bg-danger"><?php echo $siswa['total_fraud']; ?></span></td>
                        <td><?php echo $siswa['latest_fraud'] ? format_date($siswa['latest_fraud']) : '-'; ?></td>
                        <td>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Reset semua fraud lock untuk siswa ini?');">
                                <input type="hidden" name="action" value="reset_all_locks">
                                <input type="hidden" name="id_siswa" value="<?php echo $siswa['id_siswa']; ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-unlock"></i> Reset Semua
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Detail Locked Records -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Detail Locked Records</h5>
    </div>
    <div class="card-body">
        <?php if (empty($locked_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada siswa yang terkunci karena fraud.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Ujian</th>
                            <th>Mata Pelajaran</th>
                            <th>Sesi</th>
                            <th>Status</th>
                            <th>Alasan Fraud</th>
                            <th>Waktu Deteksi</th>
                            <th>Warning Count</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locked_list as $item): ?>
                        <tr>
                            <td><strong><?php echo escape($item['nis']); ?></strong></td>
                            <td><?php echo escape($item['nama_siswa']); ?></td>
                            <td><?php echo escape($item['judul_ujian']); ?></td>
                            <td><?php echo escape($item['nama_mapel']); ?></td>
                            <td><?php echo escape($item['nama_sesi']); ?></td>
                            <td>
                                <?php if ($item['is_fraud']): ?>
                                    <span class="badge bg-danger">Fraud</span>
                                <?php endif; ?>
                                <?php if ($item['requires_relogin']): ?>
                                    <span class="badge bg-warning">Requires Relogin</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($item['fraud_reason'] ?? '-'); ?></td>
                            <td><?php echo $item['fraud_detected_at'] ? format_date($item['fraud_detected_at']) : '-'; ?></td>
                            <td><span class="badge bg-secondary"><?php echo $item['warning_count'] ?? 0; ?></span></td>
                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reset fraud lock untuk ujian ini? Siswa dapat login kembali.');">
                                    <input type="hidden" name="action" value="reset_lock">
                                    <input type="hidden" name="id_siswa" value="<?php echo $item['id_siswa']; ?>">
                                    <input type="hidden" name="id_sesi" value="<?php echo $item['id_sesi']; ?>">
                                    <input type="hidden" name="id_ujian" value="<?php echo $item['id_ujian']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-unlock"></i> Reset
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

