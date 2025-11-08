<?php
/**
 * Manage Token - Guru/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

$page_title = 'Kelola Token';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$sesi = get_sesi($id);

if (!$sesi) {
    redirect('guru/sesi/list.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate') {
        $expires_hours = intval($_POST['expires_hours'] ?? 24);
        $max_usage = intval($_POST['max_usage'] ?? 0);
        
        try {
            // Generate unique token (6 digits)
            do {
                $token = generate_token();
                $stmt = $pdo->prepare("SELECT id FROM token_ujian WHERE token = ?");
                $stmt->execute([$token]);
            } while ($stmt->fetch());
            
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_hours hours"));
            
            $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                  (id_sesi, token, created_by, expires_at, max_usage, status) 
                                  VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$id, $token, $_SESSION['user_id'], $expires_at, $max_usage ?: null]);
            
            $success = "Token berhasil dibuat: <strong>$token</strong>";
            log_activity('generate_token', 'token_ujian', $pdo->lastInsertId());
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } elseif ($action === 'revoke') {
        $token_id = intval($_POST['token_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE token_ujian SET status = 'revoked' WHERE id = ?");
            $stmt->execute([$token_id]);
            $success = 'Token berhasil di-revoke';
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get tokens
$stmt = $pdo->prepare("SELECT t.*, u.nama as created_by_name,
                      (SELECT COUNT(*) FROM token_usage WHERE id_token = t.id) as usage_count
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
            <h2 class="fw-bold">Kelola Token: <?php echo escape($sesi['nama_sesi']); ?></h2>
            <a href="<?php echo base_url('guru/sesi/manage.php?id=' . $id); ?>" class="btn btn-secondary">
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
    <div class="alert alert-success" role="alert" data-auto-hide="5000">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-key"></i> Generate Token</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="mb-3">
                        <label for="expires_hours" class="form-label">Expires (jam)</label>
                        <input type="number" class="form-control" id="expires_hours" name="expires_hours" 
                               value="24" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="max_usage" class="form-label">Max Usage (kosongkan untuk unlimited)</label>
                        <input type="number" class="form-control" id="max_usage" name="max_usage" min="1">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Generate Token
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Token</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Token</th>
                                <th>Created</th>
                                <th>Expires</th>
                                <th>Usage</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tokens)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Belum ada token</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tokens as $token): ?>
                                <tr>
                                    <td><code class="fs-5"><?php echo escape($token['token']); ?></code></td>
                                    <td><?php echo format_date($token['created_at']); ?></td>
                                    <td><?php echo format_date($token['expires_at']); ?></td>
                                    <td><?php echo $token['usage_count']; ?>/<?php echo $token['max_usage'] ?? '∞'; ?></td>
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
                                                <input type="hidden" name="action" value="revoke">
                                                <input type="hidden" name="token_id" value="<?php echo $token['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-ban"></i> Revoke
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
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
