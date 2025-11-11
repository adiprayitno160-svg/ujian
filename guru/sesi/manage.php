<?php
/**
 * Manage Sesi - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['guru', 'operator']);
check_session_timeout();

$page_title = 'Kelola Sesi';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$sesi = get_sesi($id);

if (!$sesi) {
    redirect('guru/sesi/list.php');
}

// Check permission
$ujian = get_ujian($sesi['id_ujian']);
if ($ujian['id_guru'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin' && !has_operator_access()) {
    redirect('guru/sesi/list.php');
}

$error = '';
$success = '';

// Handle status change and delete participant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'aktif' WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Sesi diaktifkan';
        $sesi = get_sesi($id);
    } elseif ($action === 'complete') {
        $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'selesai' WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Sesi diselesaikan';
        $sesi = get_sesi($id);
    } elseif ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE sesi_ujian SET status = 'dibatalkan' WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Sesi dibatalkan';
        $sesi = get_sesi($id);
    } elseif ($action === 'delete_peserta') {
        $peserta_id = intval($_POST['peserta_id'] ?? 0);
        $tipe_assign = sanitize($_POST['tipe_assign'] ?? '');
        if ($peserta_id > 0 && $tipe_assign) {
            try {
                if ($tipe_assign === 'individual') {
                    $stmt = $pdo->prepare("DELETE FROM sesi_peserta WHERE id_sesi = ? AND id_user = ? AND tipe_assign = 'individual'");
                    $stmt->execute([$id, $peserta_id]);
                } elseif ($tipe_assign === 'kelas') {
                    $stmt = $pdo->prepare("DELETE FROM sesi_peserta WHERE id_sesi = ? AND id_kelas = ? AND tipe_assign = 'kelas'");
                    $stmt->execute([$id, $peserta_id]);
                }
                $success = 'Peserta berhasil dihapus';
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get peserta
$stmt = $pdo->prepare("SELECT sp.*, 
                      u.nama as nama_user, u.username,
                      k.nama_kelas
                      FROM sesi_peserta sp
                      LEFT JOIN users u ON sp.id_user = u.id
                      LEFT JOIN kelas k ON sp.id_kelas = k.id
                      WHERE sp.id_sesi = ?");
$stmt->execute([$id]);
$peserta_list = $stmt->fetchAll();

// Get tokens
$stmt = $pdo->prepare("SELECT t.*, u.nama as created_by_name
                      FROM token_ujian t
                      LEFT JOIN users u ON t.created_by = u.id
                      WHERE t.id_sesi = ?
                      ORDER BY t.created_at DESC");
$stmt->execute([$id]);
$tokens = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Kelola Sesi: <?php echo escape($sesi['nama_sesi']); ?></h2>
            <a href="<?php echo base_url('guru/sesi/list.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
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

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sesi</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Ujian</th>
                        <td><?php echo escape($sesi['judul_ujian']); ?></td>
                    </tr>
                    <tr>
                        <th>Waktu Mulai</th>
                        <td><?php echo format_date($sesi['waktu_mulai']); ?></td>
                    </tr>
                    <tr>
                        <th>Waktu Selesai</th>
                        <td><?php echo format_date($sesi['waktu_selesai']); ?></td>
                    </tr>
                    <tr>
                        <th>Durasi</th>
                        <td><?php echo $sesi['durasi']; ?> menit</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $sesi['status'] === 'aktif' ? 'success' : 
                                    ($sesi['status'] === 'selesai' ? 'info' : 'secondary'); 
                            ?>">
                                <?php echo ucfirst($sesi['status']); ?>
                            </span>
                        </td>
                    </tr>
                </table>
                
                <div class="mt-3">
                    <form method="POST" style="display:inline;">
                        <?php if ($sesi['status'] === 'draft'): ?>
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-play"></i> Aktifkan Sesi
                            </button>
                        <?php elseif ($sesi['status'] === 'aktif'): ?>
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="btn btn-info" onclick="return confirm('Selesaikan sesi ini?');">
                                <i class="fas fa-check"></i> Selesaikan
                            </button>
                            <input type="hidden" name="action" value="cancel">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Batalkan sesi ini?');">
                                <i class="fas fa-times"></i> Batalkan
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-users"></i> Peserta</h5>
            </div>
            <div class="card-body">
                <h3 class="mb-0"><?php echo count($peserta_list); ?></h3>
                <p class="text-muted mb-3">Total peserta terdaftar</p>
                <a href="<?php echo base_url('guru/sesi/assign_peserta.php?id=' . $id); ?>" class="btn btn-primary w-100">
                    <i class="fas fa-user-plus"></i> Assign Peserta
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Daftar Peserta</h5>
                    <a href="<?php echo base_url('guru/sesi/assign_peserta.php?id=' . $id); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> Tambah
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($peserta_list)): ?>
                    <p class="text-muted text-center">Belum ada peserta</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Tipe</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($peserta_list as $p): ?>
                                <tr>
                                    <td>
                                        <?php if ($p['tipe_assign'] === 'individual'): ?>
                                            <?php echo escape($p['nama_user']); ?><br>
                                            <small class="text-muted"><?php echo escape($p['username']); ?></small>
                                        <?php else: ?>
                                            <i class="fas fa-users"></i> <?php echo escape($p['nama_kelas']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $p['tipe_assign'] === 'individual' ? 'primary' : 'info'; ?>">
                                            <?php echo ucfirst($p['tipe_assign']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin hapus peserta ini?');">
                                            <input type="hidden" name="action" value="delete_peserta">
                                            <input type="hidden" name="peserta_id" value="<?php echo $p['tipe_assign'] === 'individual' ? $p['id_user'] : $p['id_kelas']; ?>">
                                            <input type="hidden" name="tipe_assign" value="<?php echo escape($p['tipe_assign']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Hapus
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
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-key"></i> Token</h5>
                    <a href="<?php echo base_url('guru/sesi/manage_token.php?id=' . $id); ?>" class="btn btn-sm btn-light">
                        <i class="fas fa-plus"></i> Generate
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($tokens)): ?>
                    <p class="text-muted text-center">Belum ada token</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>Expires</th>
                                    <th>Usage</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><code><?php echo escape($token['token']); ?></code></td>
                                    <td><?php echo format_date($token['expires_at']); ?></td>
                                    <td><?php echo $token['current_usage']; ?>/<?php echo $token['max_usage'] ?? '∞'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $token['status'] === 'active' ? 'success' : 
                                                ($token['status'] === 'expired' ? 'warning' : 'secondary'); 
                                        ?>">
                                            <?php echo ucfirst($token['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($token['status'] === 'active'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Revoke token ini?');">
                                                <input type="hidden" name="action" value="revoke_token">
                                                <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
