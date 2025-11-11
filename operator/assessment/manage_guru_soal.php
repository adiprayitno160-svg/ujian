<?php
/**
 * Manage Guru Soal Permission - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator mengatur guru mana yang boleh membuat soal assessment
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

// Check if user has operator access
if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Kelola Permission Guru Soal';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');
    
    if ($action === 'update_permission') {
        $guru_id = intval($_POST['guru_id'] ?? 0);
        $can_create = isset($_POST['can_create']) ? 1 : 0;
        
        if ($guru_id) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET can_create_assessment_soal = ? WHERE id = ? AND role = 'guru'");
                $stmt->execute([$can_create, $guru_id]);
                
                if ($can_create) {
                    $success = 'Permission berhasil diberikan kepada guru';
                } else {
                    $success = 'Permission berhasil dicabut dari guru';
                }
                log_activity('update_assessment_soal_permission', 'users', $guru_id);
            } catch (PDOException $e) {
                error_log("Update permission error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengupdate permission';
            }
        }
    }
}

// Get all guru
$stmt = $pdo->query("SELECT u.id, u.username, u.nama, u.can_create_assessment_soal,
                     (SELECT COUNT(*) FROM ujian WHERE id_guru = u.id AND tipe_asesmen IS NOT NULL) as total_assessment
                     FROM users u
                     WHERE u.role = 'guru' AND u.status = 'active'
                     ORDER BY u.nama ASC");
$guru_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Kelola Permission Guru Soal Assessment</h2>
        <p class="text-muted">Atur guru mana yang diizinkan membuat soal untuk assessment tengah semester, semester, dan tahunan</p>
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
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-users"></i> Daftar Guru</h5>
    </div>
    <div class="card-body">
        <?php if (empty($guru_list)): ?>
            <p class="text-muted text-center">Tidak ada guru ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nama Guru</th>
                            <th>Username</th>
                            <th>Total Assessment</th>
                            <th>Permission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($guru_list as $guru): ?>
                        <tr>
                            <td><?php echo escape($guru['nama']); ?></td>
                            <td><?php echo escape($guru['username']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $guru['total_assessment']; ?></span>
                            </td>
                            <td>
                                <?php if ($guru['can_create_assessment_soal']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Diizinkan
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-times"></i> Tidak Diizinkan
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin <?php echo $guru['can_create_assessment_soal'] ? 'mencabut' : 'memberikan'; ?> permission ini?');">
                                    <input type="hidden" name="action" value="update_permission">
                                    <input type="hidden" name="guru_id" value="<?php echo $guru['id']; ?>">
                                    <input type="hidden" name="can_create" value="<?php echo $guru['can_create_assessment_soal'] ? '0' : '1'; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $guru['can_create_assessment_soal'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <i class="fas fa-<?php echo $guru['can_create_assessment_soal'] ? 'ban' : 'check'; ?>"></i>
                                        <?php echo $guru['can_create_assessment_soal'] ? 'Cabut Permission' : 'Berikan Permission'; ?>
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

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h5 class="card-title"><i class="fas fa-info-circle"></i> Informasi</h5>
        <ul class="mb-0">
            <li>Hanya guru yang memiliki permission yang dapat membuat soal untuk assessment tengah semester, semester, dan tahunan</li>
            <li>Guru tanpa permission tidak akan melihat tombol "Pembuatan Soal Assessment" di menu mereka</li>
            <li>Permission ini berbeda dengan permission membuat ujian biasa - guru tetap bisa membuat ujian biasa tanpa permission ini</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


