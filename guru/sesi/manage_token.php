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

// Support both 'id' (for guru) and 'sesi_id' (for operator) parameters
$id = intval($_GET['id'] ?? $_GET['sesi_id'] ?? 0);
$sesi = get_sesi($id);

if (!$sesi) {
    // Redirect based on role
    if (has_operator_access()) {
        redirect('operator/sesi/list.php');
    } else {
        redirect('guru/sesi/list.php');
    }
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
    } elseif ($action === 'release_all') {
        // Release all active tokens (revoke them)
        try {
            $stmt = $pdo->prepare("UPDATE token_ujian SET status = 'revoked' WHERE id_sesi = ? AND status = 'active'");
            $stmt->execute([$id]);
            $success = 'Semua token berhasil di-release';
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } elseif ($action === 'auto_generate') {
        // Auto-generate token with 15 minutes expiration for operators
        if (has_operator_access()) {
            try {
                // Revoke all active tokens first
                $stmt = $pdo->prepare("UPDATE token_ujian SET status = 'revoked' WHERE id_sesi = ? AND status = 'active'");
                $stmt->execute([$id]);
                
                // Generate new token (expires in 15 minutes)
                do {
                    $token = generate_token();
                    $stmt = $pdo->prepare("SELECT id FROM token_ujian WHERE token = ?");
                    $stmt->execute([$token]);
                } while ($stmt->fetch());
                
                $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                      (id_sesi, token, created_by, expires_at, max_usage, status) 
                                      VALUES (?, ?, ?, ?, NULL, 'active')");
                $stmt->execute([$id, $token, $_SESSION['user_id'], $expires_at]);
                
                $success = "Token baru berhasil dibuat: <strong>$token</strong> (Expires in 15 minutes)";
                log_activity('auto_generate_token', 'token_ujian', $pdo->lastInsertId());
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
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
            <a href="<?php echo has_operator_access() ? base_url('operator/sesi/manage.php?id=' . $id) : base_url('guru/sesi/manage.php?id=' . $id); ?>" class="btn btn-secondary">
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
                <?php if (has_operator_access()): ?>
                    <div class="mb-3">
                        <form method="POST" id="autoGenerateForm">
                            <input type="hidden" name="action" value="auto_generate">
                            <button type="submit" class="btn btn-success w-100 mb-2">
                                <i class="fas fa-sync-alt"></i> Generate & Release (15 menit)
                            </button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Yakin release semua token aktif?');">
                            <input type="hidden" name="action" value="release_all">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-ban"></i> Release Semua Token
                            </button>
                        </form>
                        <hr>
                        <small class="text-muted">Auto-generate akan membuat token baru setiap 15 menit dan me-revoke token lama</small>
                    </div>
                <?php endif; ?>
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
        <?php if (has_operator_access()): ?>
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-clock"></i> Auto-Generate (Operator)</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted">Token akan otomatis di-generate setiap 15 menit. Klik tombol di atas untuk memulai.</p>
                <div id="autoGenerateStatus" class="alert alert-info d-none">
                    <small>Auto-generate aktif. Token berikutnya akan dibuat dalam: <span id="countdown">15:00</span></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
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

<?php if (has_operator_access()): ?>
<script>
// Auto-generate token every 15 minutes for operators
let autoGenerateInterval = null;
let countdownInterval = null;
let countdownSeconds = 900; // 15 minutes in seconds

function startAutoGenerate() {
    // Clear existing intervals
    if (autoGenerateInterval) clearInterval(autoGenerateInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    
    // Show status
    const statusEl = document.getElementById('autoGenerateStatus');
    if (statusEl) {
        statusEl.classList.remove('d-none');
    }
    
    // Start countdown
    function updateCountdown() {
        const countdownEl = document.getElementById('countdown');
        if (countdownEl) {
            const minutes = Math.floor(countdownSeconds / 60);
            const seconds = countdownSeconds % 60;
            countdownEl.textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
        
        if (countdownSeconds <= 0) {
            countdownSeconds = 900; // Reset to 15 minutes
        } else {
            countdownSeconds--;
        }
    }
    
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
    
    // Auto-generate token every 15 minutes
    autoGenerateInterval = setInterval(function() {
        // Submit the auto-generate form
        const form = document.getElementById('autoGenerateForm');
        if (form) {
            form.submit();
        }
    }, 900000); // 15 minutes = 900000 milliseconds
}

// Start auto-generate if operator just enabled it
document.addEventListener('DOMContentLoaded', function() {
    <?php if (has_operator_access() && isset($_POST['action']) && $_POST['action'] === 'auto_generate'): ?>
    startAutoGenerate();
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
