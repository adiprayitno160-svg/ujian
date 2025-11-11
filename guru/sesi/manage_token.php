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
            // Get sesi info to show tingkat and mapel
            $ujian = get_ujian($sesi['id_ujian']);
            $mapel_info = '';
            if ($ujian) {
                $stmt = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
                $stmt->execute([$ujian['id_mapel']]);
                $mapel = $stmt->fetch();
                $mapel_info = $mapel ? $mapel['nama_mapel'] : '';
            }
            
            // Check if this is the first token for this sesi
            // Token dibuat per sesi, dan setiap sesi terikat ke satu ujian dengan satu tingkat + pelajaran
            // Jadi token otomatis berbeda per (tingkat + pelajaran)
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM token_ujian WHERE id_sesi = ? AND status = 'active'");
            $stmt->execute([$id]);
            $existing_tokens = $stmt->fetch()['count'];
            $is_first_token = ($existing_tokens == 0);
            
            // Generate unique token (6 digits)
            do {
                $token = generate_token();
                $stmt = $pdo->prepare("SELECT id FROM token_ujian WHERE token = ?");
                $stmt->execute([$token]);
            } while ($stmt->fetch());
            
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_hours hours"));
            
            // For first token: set max_usage to NULL (unlimited) so all peserta can use it
            // For subsequent tokens: use provided max_usage
            // Note: Token ini hanya untuk sesi ini (yang terikat ke tingkat + pelajaran spesifik)
            $final_max_usage = $is_first_token ? null : ($max_usage ?: null);
            
            $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                  (id_sesi, token, created_by, expires_at, max_usage, status) 
                                  VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$id, $token, $_SESSION['user_id'], $expires_at, $final_max_usage]);
            
            $token_id = $pdo->lastInsertId();
            
            if ($is_first_token) {
                $success = "Token pertama berhasil dibuat: <strong>$token</strong><br>";
                $success .= "<small class='text-muted'>Token ini dapat digunakan oleh semua peserta ujian di sesi ini";
                if ($ujian && $ujian['tingkat_kelas'] && $mapel_info) {
                    $success .= " (Kelas " . escape($ujian['tingkat_kelas']) . " - " . escape($mapel_info) . ")";
                }
                $success .= ".</small>";
            } else {
                $success = "Token berhasil dibuat: <strong>$token</strong>";
            }
            
            log_activity('generate_token', 'token_ujian', $token_id);
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
    } elseif ($action === 'reset_token') {
        // Reset token: Revoke semua token aktif, lalu generate token baru
        // Untuk ulangan harian - guru bisa reset token dan beri tahu langsung ke siswa
        try {
            $pdo->beginTransaction();
            
            // Revoke all active tokens first
            $stmt = $pdo->prepare("UPDATE token_ujian SET status = 'revoked' WHERE id_sesi = ? AND status = 'active'");
            $stmt->execute([$id]);
            
            // Generate new token (set max_usage to NULL so all peserta can use it)
            do {
                $token = generate_token();
                $stmt = $pdo->prepare("SELECT id FROM token_ujian WHERE token = ?");
                $stmt->execute([$token]);
            } while ($stmt->fetch());
            
            $expires_hours = intval($_POST['expires_hours'] ?? 24);
            $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_hours hours"));
            
            $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                  (id_sesi, token, created_by, expires_at, max_usage, status) 
                                  VALUES (?, ?, ?, ?, NULL, 'active')");
            $stmt->execute([$id, $token, $_SESSION['user_id'], $expires_at]);
            
            $token_id = $pdo->lastInsertId();
            
            $pdo->commit();
            
            $success = "Token berhasil di-reset. Token baru: <strong>$token</strong> (Expires in $expires_hours hours)<br>";
            $success .= "<small class='text-muted'>Token ini dapat digunakan oleh semua peserta ujian di sesi ini.</small>";
            log_activity('reset_token', 'token_ujian', $token_id);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error resetting token: " . $e->getMessage());
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
                // Check if this is the first token for this sesi
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM token_ujian WHERE id_sesi = ? AND status = 'active'");
                $stmt->execute([$id]);
                $existing_tokens = $stmt->fetch()['count'];
                $is_first_token = ($existing_tokens == 0);
                
                // Revoke all active tokens first (if not first token)
                if (!$is_first_token) {
                    $stmt = $pdo->prepare("UPDATE token_ujian SET status = 'revoked' WHERE id_sesi = ? AND status = 'active'");
                    $stmt->execute([$id]);
                }
                
                // Generate new token (expires in 15 minutes)
                // For first token: set max_usage to NULL (unlimited) so all peserta can use it
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
                
                $token_id = $pdo->lastInsertId();
                
                if ($is_first_token) {
                    $success = "Token pertama berhasil dibuat: <strong>$token</strong> (Expires in 15 minutes)<br>";
                    $success .= "<small class='text-muted'>Token ini dapat digunakan oleh semua peserta ujian di sesi ini.</small>";
                } else {
                    $success = "Token baru berhasil dibuat: <strong>$token</strong> (Expires in 15 minutes)";
                }
                log_activity('auto_generate_token', 'token_ujian', $token_id);
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'approve_request') {
        // Approve token request and generate token
        $request_id = intval($_POST['request_id'] ?? 0);
        $expires_hours = intval($_POST['expires_hours'] ?? 24);
        
        if ($request_id > 0) {
            try {
                // Get request details
                $stmt = $pdo->prepare("SELECT * FROM token_request WHERE id = ? AND status = 'pending'");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch();
                
                if (!$request) {
                    $error = 'Request tidak ditemukan atau sudah diproses';
                } else {
                    // Generate unique token
                    do {
                        $token = generate_token();
                        $stmt = $pdo->prepare("SELECT id FROM token_ujian WHERE token = ?");
                        $stmt->execute([$token]);
                    } while ($stmt->fetch());
                    
                    $expires_at = date('Y-m-d H:i:s', strtotime("+$expires_hours hours"));
                    
                    // Create token
                    $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                          (id_sesi, token, created_by, expires_at, max_usage, status) 
                                          VALUES (?, ?, ?, ?, 1, 'active')");
                    $stmt->execute([$id, $token, $_SESSION['user_id'], $expires_at]);
                    $token_id = $pdo->lastInsertId();
                    
                    // Update request
                    $stmt = $pdo->prepare("UPDATE token_request 
                                          SET status = 'approved', 
                                              approved_by = ?, 
                                              approved_at = NOW(), 
                                              id_token = ?
                                          WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $token_id, $request_id]);
                    
                    $success = "Request disetujui. Token: <strong>$token</strong>";
                    log_activity('approve_token_request', 'token_request', $request_id);
                }
            } catch (PDOException $e) {
                error_log("Error approving token request: " . $e->getMessage());
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'reject_request') {
        // Reject token request
        $request_id = intval($_POST['request_id'] ?? 0);
        $notes = sanitize($_POST['notes'] ?? '');
        
        if ($request_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE token_request 
                                      SET status = 'rejected', 
                                          rejected_at = NOW(),
                                          notes = ?
                                      WHERE id = ? AND status = 'pending'");
                $stmt->execute([$notes, $request_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success = 'Request ditolak';
                    log_activity('reject_token_request', 'token_request', $request_id);
                } else {
                    $error = 'Request tidak ditemukan atau sudah diproses';
                }
            } catch (PDOException $e) {
                error_log("Error rejecting token request: " . $e->getMessage());
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

// Get pending token requests (only for operators)
// Include informasi ujian yang sedang berlangsung (mata pelajaran, tingkat, dll)
$token_requests = [];
if (has_operator_access()) {
    try {
        $stmt = $pdo->prepare("SELECT tr.*, 
                              u.nama as siswa_name, 
                              u.username as siswa_username,
                              s.nama_sesi,
                              uj.judul as judul_ujian,
                              uj.tipe_asesmen,
                              uj.tingkat_kelas,
                              uj.semester,
                              uj.tahun_ajaran,
                              m.nama_mapel,
                              n.status as status_ujian,
                              n.waktu_mulai as waktu_mulai_ujian
                              FROM token_request tr
                              INNER JOIN users u ON tr.id_siswa = u.id
                              INNER JOIN sesi_ujian s ON tr.id_sesi = s.id
                              INNER JOIN ujian uj ON s.id_ujian = uj.id
                              LEFT JOIN mapel m ON uj.id_mapel = m.id
                              LEFT JOIN nilai n ON (n.id_sesi = s.id AND n.id_siswa = u.id AND n.status = 'sedang_mengerjakan')
                              WHERE tr.id_sesi = ? AND tr.status = 'pending'
                              ORDER BY tr.requested_at ASC");
        $stmt->execute([$id]);
        $token_requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching token requests: " . $e->getMessage());
    }
}
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
                <?php else: ?>
                    <!-- Untuk Guru (Ulangan Harian): Reset Token -->
                    <div class="mb-3">
                        <form method="POST" onsubmit="return confirm('Reset token akan me-revoke semua token aktif dan membuat token baru. Lanjutkan?');">
                            <input type="hidden" name="action" value="reset_token">
                            <div class="mb-2">
                                <label for="reset_expires_hours" class="form-label small">Expires (jam)</label>
                                <input type="number" class="form-control form-control-sm" id="reset_expires_hours" name="expires_hours" value="24" min="1" required>
                            </div>
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-redo"></i> Reset Token
                            </button>
                        </form>
                        <small class="text-muted d-block mt-2">
                            Reset token untuk ulangan harian. Token lama akan di-revoke dan token baru akan dibuat. 
                            Beri tahu token baru langsung ke siswa.
                        </small>
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
        <?php if (has_operator_access() && !empty($token_requests)): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-bell me-2"></i> Request Token dari Siswa (<?php echo count($token_requests); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Ujian yang Sedang Berlangsung</th>
                                <th>Waktu Request</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($token_requests as $req): ?>
                            <tr>
                                <td>
                                    <strong><?php echo escape($req['siswa_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo escape($req['siswa_username']); ?></small>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo escape($req['judul_ujian'] ?? '-'); ?></strong><br>
                                        <?php if ($req['nama_mapel']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-book"></i> <?php echo escape($req['nama_mapel']); ?>
                                            </small><br>
                                        <?php endif; ?>
                                        <?php if ($req['tingkat_kelas']): ?>
                                            <small class="text-muted">
                                                <i class="fas fa-graduation-cap"></i> Kelas <?php echo escape($req['tingkat_kelas']); ?>
                                            </small>
                                            <?php if ($req['semester']): ?>
                                                <small class="text-muted"> - Semester <?php echo escape($req['semester']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($req['tahun_ajaran']): ?>
                                                <small class="text-muted"> - <?php echo escape($req['tahun_ajaran']); ?></small>
                                            <?php endif; ?>
                                            <br>
                                        <?php endif; ?>
                                        <?php if ($req['tipe_asesmen']): ?>
                                            <span class="badge bg-info">
                                                <?php 
                                                $tipe_labels = [
                                                    'sumatip' => 'SUMATIP',
                                                    'sumatip_tengah_semester' => 'SUMATIP Tengah Semester',
                                                    'sumatip_akhir_semester' => 'SUMATIP Akhir Semester',
                                                    'sumatip_akhir_tahun' => 'SUMATIP Akhir Tahun'
                                                ];
                                                echo escape($tipe_labels[$req['tipe_asesmen']] ?? ucfirst($req['tipe_asesmen'])); 
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($req['status_ujian'] === 'sedang_mengerjakan' && $req['waktu_mulai_ujian']): ?>
                                            <br><small class="text-info">
                                                <i class="fas fa-clock"></i> Mulai: <?php echo format_date($req['waktu_mulai_ujian']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo format_date($req['requested_at']); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-success" onclick="approveRequest(<?php echo $req['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="rejectRequest(<?php echo $req['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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

// Approve token request
function approveRequest(requestId) {
    const expiresHours = prompt('Masukkan waktu expired token (jam, default: 24):', '24');
    if (expiresHours === null) return;
    
    const hours = parseInt(expiresHours) || 24;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="approve_request">
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="expires_hours" value="${hours}">
    `;
    document.body.appendChild(form);
    form.submit();
}

// Reject token request
function rejectRequest(requestId) {
    const notes = prompt('Alasan penolakan (opsional):', '');
    if (notes === null) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="reject_request">
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="notes" value="${notes}">
    `;
    document.body.appendChild(form);
    form.submit();
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
