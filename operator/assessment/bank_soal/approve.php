<?php
/**
 * Approve/Reject Bank Soal - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id_soal = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'approve'; // approve or reject

if (!$id_soal) {
    redirect('operator-assessment-bank-soal-list');
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $reason = sanitize($_POST['reason'] ?? '');
    
    if (approve_bank_soal($id_soal, $action, $_SESSION['user_id'], $reason)) {
        log_activity('approve_bank_soal', 'bank_soal', $id_soal);
        redirect('operator-assessment-bank-soal-list?success=' . $action);
    } else {
        redirect('operator-assessment-bank-soal-list?error=approve_failed');
    }
}

// Get soal info
$stmt = $pdo->prepare("SELECT bs.*, s.pertanyaan, s.tipe_soal, m.nama_mapel 
                      FROM bank_soal bs
                      INNER JOIN soal s ON bs.id_soal = s.id
                      INNER JOIN mapel m ON bs.id_mapel = m.id
                      WHERE bs.id_soal = ?");
$stmt->execute([$id_soal]);
$soal = $stmt->fetch();

if (!$soal) {
    redirect('operator-assessment-bank-soal-list');
}

$page_title = $action === 'approve' ? 'Approve Soal' : 'Reject Soal';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold"><?php echo $action === 'approve' ? 'Approve' : 'Reject'; ?> Soal</h2>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5>Detail Soal</h5>
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Pertanyaan</th>
                        <td><?php echo escape($soal['pertanyaan']); ?></td>
                    </tr>
                    <tr>
                        <th>Mata Pelajaran</th>
                        <td><?php echo escape($soal['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Tingkat Kelas</th>
                        <td><?php echo escape($soal['tingkat_kelas'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Tipe Soal</th>
                        <td><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-warning"><?php echo ucfirst($soal['status']); ?></span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    
                    <?php if ($action === 'reject'): ?>
                    <div class="mb-3">
                        <label class="form-label">Alasan Reject</label>
                        <textarea class="form-control" name="reason" rows="3" required></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-<?php echo $action === 'approve' ? 'success' : 'danger'; ?>">
                            <i class="fas fa-<?php echo $action === 'approve' ? 'check' : 'times'; ?>"></i>
                            <?php echo $action === 'approve' ? 'Approve' : 'Reject'; ?>
                        </button>
                        <a href="<?php echo base_url('operator-assessment-bank-soal-list'); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



