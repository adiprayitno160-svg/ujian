<?php
/**
 * Review PR - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Review PR';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ? AND p.id_guru = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('guru/pr/list.php');
}

$error = '';
$success = '';

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $nilai = floatval($_POST['nilai'] ?? 0);
    $feedback = sanitize($_POST['feedback'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE pr_submission 
                              SET nilai = ?, feedback = ?, status = 'dinilai', waktu_dinilai = NOW() 
                              WHERE id = ? AND id_pr = ?");
        $stmt->execute([$nilai, $feedback, $submission_id, $pr_id]);
        $success = 'Nilai berhasil disimpan';
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Get submissions
$stmt = $pdo->prepare("SELECT ps.*, u.nama as nama_siswa, u.username
                      FROM pr_submission ps
                      INNER JOIN users u ON ps.id_siswa = u.id
                      WHERE ps.id_pr = ?
                      ORDER BY ps.waktu_submit DESC");
$stmt->execute([$pr_id]);
$submissions = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Review PR: <?php echo escape($pr['judul']); ?></h2>
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="200">Mata Pelajaran</th>
                <td><?php echo escape($pr['nama_mapel']); ?></td>
            </tr>
            <tr>
                <th>Deadline</th>
                <td><?php echo format_date($pr['deadline']); ?></td>
            </tr>
            <tr>
                <th>Deskripsi</th>
                <td><?php echo escape($pr['deskripsi'] ?? '-'); ?></td>
            </tr>
            <tr>
                <th>Tipe PR</th>
                <td>
                    <span class="badge bg-<?php 
                        $tipe_badge = [
                            'file_upload' => 'secondary',
                            'online' => 'primary',
                            'hybrid' => 'info'
                        ];
                        echo $tipe_badge[$pr['tipe_pr']] ?? 'secondary';
                    ?>">
                        <?php 
                        $tipe_label = [
                            'file_upload' => 'File Upload',
                            'online' => 'Online',
                            'hybrid' => 'Hybrid'
                        ];
                        echo $tipe_label[$pr['tipe_pr']] ?? 'File Upload';
                        ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Submission</h5>
    </div>
    <div class="card-body">
        <?php if (empty($submissions)): ?>
            <p class="text-muted text-center">Belum ada submission</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>File</th>
                            <th>Jawaban Online</th>
                            <th>Komentar</th>
                            <th>Waktu Submit</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): 
                            // Get online answers if PR is online/hybrid
                            $has_online_answers = false;
                            $online_answers_count = 0;
                            if (in_array($pr['tipe_pr'], ['online', 'hybrid'])) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr_jawaban 
                                                      WHERE id_pr = ? AND id_siswa = ? AND status = 'submitted'");
                                $stmt->execute([$pr_id, $sub['id_siswa']]);
                                $answer_count = $stmt->fetch();
                                $online_answers_count = $answer_count['total'] ?? 0;
                                $has_online_answers = $online_answers_count > 0;
                            }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($sub['nama_siswa']); ?></strong><br>
                                <small class="text-muted"><?php echo escape($sub['username']); ?></small>
                            </td>
                            <td>
                                <?php if ($sub['file_jawaban']): ?>
                                    <a href="<?php echo asset_url('uploads/pr/' . $sub['file_jawaban']); ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($has_online_answers): ?>
                                    <a href="<?php echo base_url('guru/pr/review_detail.php?id=' . $pr_id . '&siswa_id=' . $sub['id_siswa']); ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye"></i> Lihat (<?php echo $online_answers_count; ?>)
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($sub['komentar'] ?? '-'); ?></td>
                            <td><?php echo $sub['waktu_submit'] ? format_date($sub['waktu_submit']) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $sub['status'] === 'dinilai' ? 'success' : 
                                        ($sub['status'] === 'sudah_dikumpulkan' || $sub['status'] === 'draft' ? 'info' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $sub['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sub['nilai'] !== null): ?>
                                    <strong><?php echo number_format($sub['nilai'], 2); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" 
                                        onclick="gradeSubmission(<?php echo htmlspecialchars(json_encode($sub)); ?>)">
                                    <i class="fas fa-edit"></i> Nilai
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Grade Modal -->
<div class="modal fade" id="gradeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Beri Nilai</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="grade">
                    <input type="hidden" name="submission_id" id="grade_submission_id">
                    <div class="mb-3">
                        <label class="form-label">Nilai (0-100)</label>
                        <input type="number" class="form-control" name="nilai" id="grade_nilai" 
                               min="0" max="100" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Feedback</label>
                        <textarea class="form-control" name="feedback" id="grade_feedback" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function gradeSubmission(sub) {
    document.getElementById('grade_submission_id').value = sub.id;
    document.getElementById('grade_nilai').value = sub.nilai || '';
    document.getElementById('grade_feedback').value = sub.feedback || '';
    
    const modal = new bootstrap.Modal(document.getElementById('gradeModal'));
    modal.show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
