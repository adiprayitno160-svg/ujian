<?php
/**
 * Token Requests - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk melihat semua request token dari semua sesi assessment
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Request Token - Assessment';
$role_css = 'operator';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $sesi_id = intval($_POST['sesi_id'] ?? 0);
        $expires_hours = intval($_POST['expires_hours'] ?? 24);
        
        if ($request_id > 0 && $sesi_id > 0) {
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
                    
                    // Check if this is the first token for this sesi
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM token_ujian WHERE id_sesi = ? AND status = 'active'");
                    $stmt->execute([$sesi_id]);
                    $existing_tokens = $stmt->fetch()['count'];
                    $is_first_token = ($existing_tokens == 0);
                    
                    // For first token: set max_usage to NULL (unlimited) so all peserta can use it
                    // For subsequent tokens (from request): set max_usage to 1 (single use)
                    $final_max_usage = $is_first_token ? null : 1;
                    
                    // Create token
                    $stmt = $pdo->prepare("INSERT INTO token_ujian 
                                          (id_sesi, token, created_by, expires_at, max_usage, status) 
                                          VALUES (?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$sesi_id, $token, $_SESSION['user_id'], $expires_at, $final_max_usage]);
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

// Get all pending token requests from all assessment sessions
// Hanya untuk assessment yang dikelola operator (punya tipe_asesmen)
$filter_mapel = intval($_GET['mapel'] ?? 0);
$filter_tingkat = sanitize($_GET['tingkat'] ?? '');

$sql = "SELECT tr.*, 
        u.nama as siswa_name, 
        u.username as siswa_username,
        s.nama_sesi,
        s.id as sesi_id,
        uj.judul as judul_ujian,
        uj.tipe_asesmen,
        uj.tingkat_kelas,
        uj.semester,
        uj.tahun_ajaran,
        m.nama_mapel,
        m.id as id_mapel,
        n.status as status_ujian,
        n.waktu_mulai as waktu_mulai_ujian
        FROM token_request tr
        INNER JOIN users u ON tr.id_siswa = u.id
        INNER JOIN sesi_ujian s ON tr.id_sesi = s.id
        INNER JOIN ujian uj ON s.id_ujian = uj.id
        LEFT JOIN mapel m ON uj.id_mapel = m.id
        LEFT JOIN nilai n ON (n.id_sesi = s.id AND n.id_siswa = u.id AND n.status = 'sedang_mengerjakan')
        WHERE tr.status = 'pending'
        AND uj.tipe_asesmen IS NOT NULL AND uj.tipe_asesmen != ''";

$params = [];

if ($filter_mapel > 0) {
    $sql .= " AND uj.id_mapel = ?";
    $params[] = $filter_mapel;
}

if ($filter_tingkat) {
    $sql .= " AND uj.tingkat_kelas = ?";
    $params[] = $filter_tingkat;
}

$sql .= " ORDER BY tr.requested_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$token_requests = $stmt->fetchAll();

// Get mapel for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get tingkat kelas
$stmt = $pdo->query("SELECT DISTINCT tingkat_kelas FROM ujian WHERE tingkat_kelas IS NOT NULL AND tingkat_kelas != '' ORDER BY tingkat_kelas ASC");
$tingkat_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold"><i class="fas fa-bell me-2"></i>Request Token - Assessment</h2>
            <a href="<?php echo base_url('operator/sesi/list.php'); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
        <p class="text-muted">
            Semua request token dari siswa untuk assessment yang sedang berlangsung. 
            Setiap assessment (mata pelajaran, tingkat) memiliki token yang berbeda.
        </p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
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
            <div class="col-md-4">
                <label for="mapel" class="form-label">Mata Pelajaran</label>
                <select class="form-select" id="mapel" name="mapel">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="tingkat" class="form-label">Tingkat Kelas</label>
                <select class="form-select" id="tingkat" name="tingkat">
                    <option value="">Semua Tingkat</option>
                    <?php foreach ($tingkat_list as $tingkat): ?>
                        <option value="<?php echo escape($tingkat); ?>" <?php echo $filter_tingkat == $tingkat ? 'selected' : ''; ?>>
                            Kelas <?php echo escape($tingkat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Token Requests -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">
            <i class="fas fa-bell me-2"></i> 
            Request Token dari Siswa (<?php echo count($token_requests); ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($token_requests)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada request token yang sedang menunggu persetujuan.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
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
                                    <br><small class="text-muted">
                                        <i class="fas fa-calendar"></i> Sesi: <?php echo escape($req['nama_sesi']); ?>
                                    </small>
                                </div>
                            </td>
                            <td><?php echo format_date($req['requested_at']); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-success" onclick="approveRequest(<?php echo $req['id']; ?>, <?php echo $req['sesi_id']; ?>)">
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
        <?php endif; ?>
    </div>
</div>

<script>
// Approve token request
function approveRequest(requestId, sesiId) {
    const expiresHours = prompt('Masukkan waktu expired token (jam, default: 24):', '24');
    if (expiresHours === null) return;
    
    const hours = parseInt(expiresHours) || 24;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="approve_request">
        <input type="hidden" name="request_id" value="${requestId}">
        <input type="hidden" name="sesi_id" value="${sesiId}">
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
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

