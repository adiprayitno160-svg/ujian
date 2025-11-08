<?php
/**
 * Migrasi Kelas - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Migrasi Kelas';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    
    if ($action === 'approve' && $id) {
        try {
            $pdo->beginTransaction();
            
            // Get migrasi data
            $stmt = $pdo->prepare("SELECT * FROM migrasi_kelas WHERE id = ?");
            $stmt->execute([$id]);
            $migrasi = $stmt->fetch();
            
            if ($migrasi && $migrasi['status'] === 'pending') {
                // Update user_kelas
                $stmt = $pdo->prepare("UPDATE user_kelas SET id_kelas = ?, tahun_ajaran = ? 
                                      WHERE id_user = ? AND id_kelas = ?");
                $stmt->execute([
                    $migrasi['id_kelas_baru'],
                    $migrasi['tahun_ajaran_baru'],
                    $migrasi['id_user'],
                    $migrasi['id_kelas_lama']
                ]);
                
                // Insert to history
                $stmt = $pdo->prepare("INSERT INTO migrasi_history 
                                      (id_user, id_kelas_lama, id_kelas_baru, tahun_ajaran, semester) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $migrasi['id_user'],
                    $migrasi['id_kelas_lama'],
                    $migrasi['id_kelas_baru'],
                    $migrasi['tahun_ajaran_baru'],
                    $migrasi['semester']
                ]);
                
                // Update migrasi status
                $stmt = $pdo->prepare("UPDATE migrasi_kelas SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $id]);
                
                $pdo->commit();
                $success = 'Migrasi kelas berhasil disetujui';
                log_activity('approve_migrasi', 'migrasi_kelas', $id);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Approve migrasi error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat menyetujui migrasi';
        }
    } elseif ($action === 'reject' && $id) {
        try {
            $stmt = $pdo->prepare("UPDATE migrasi_kelas SET status = 'rejected', approved_by = ?, approved_at = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
            $success = 'Migrasi kelas ditolak';
            log_activity('reject_migrasi', 'migrasi_kelas', $id);
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get pending migrasi
$stmt = $pdo->prepare("SELECT m.*, 
                      u1.nama as nama_siswa, u1.username as username_siswa,
                      k1.nama_kelas as kelas_lama, k2.nama_kelas as kelas_baru,
                      u2.nama as created_by_name
                      FROM migrasi_kelas m
                      INNER JOIN users u1 ON m.id_user = u1.id
                      INNER JOIN kelas k1 ON m.id_kelas_lama = k1.id
                      INNER JOIN kelas k2 ON m.id_kelas_baru = k2.id
                      LEFT JOIN users u2 ON m.created_by = u2.id
                      WHERE m.status = 'pending'
                      ORDER BY m.created_at DESC");
$stmt->execute();
$pending_migrasi = $stmt->fetchAll();

// Get history
$stmt = $pdo->query("SELECT m.*, 
                    u.nama as nama_siswa,
                    k1.nama_kelas as kelas_lama, k2.nama_kelas as kelas_baru
                    FROM migrasi_history m
                    INNER JOIN users u ON m.id_user = u.id
                    INNER JOIN kelas k1 ON m.id_kelas_lama = k1.id
                    INNER JOIN kelas k2 ON m.id_kelas_baru = k2.id
                    ORDER BY m.created_at DESC
                    LIMIT 50");
$history = $stmt->fetchAll();
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

<!-- Pending Migrasi -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0"><i class="fas fa-clock"></i> Menunggu Persetujuan</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas Lama</th>
                        <th>Kelas Baru</th>
                        <th>Tahun Ajaran</th>
                        <th>Dibuat Oleh</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_migrasi)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Tidak ada migrasi yang menunggu persetujuan</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_migrasi as $m): ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($m['nama_siswa']); ?></strong><br>
                                <small class="text-muted"><?php echo escape($m['username_siswa']); ?></small>
                            </td>
                            <td><?php echo escape($m['kelas_lama']); ?></td>
                            <td><?php echo escape($m['kelas_baru']); ?></td>
                            <td><?php echo escape($m['tahun_ajaran_baru']); ?></td>
                            <td><?php echo escape($m['created_by_name'] ?? '-'); ?></td>
                            <td><?php echo format_date($m['created_at']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Setujui migrasi ini?');">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Tolak migrasi ini?');">
                                        <i class="fas fa-times"></i> Tolak
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

<!-- History -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Riwayat Migrasi</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas Lama</th>
                        <th>Kelas Baru</th>
                        <th>Tahun Ajaran</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Tidak ada riwayat</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo escape($h['nama_siswa']); ?></td>
                            <td><?php echo escape($h['kelas_lama']); ?></td>
                            <td><?php echo escape($h['kelas_baru']); ?></td>
                            <td><?php echo escape($h['tahun_ajaran']); ?></td>
                            <td><?php echo format_date($h['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
