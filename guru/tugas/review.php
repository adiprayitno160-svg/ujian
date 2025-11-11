<?php
/**
 * Review Tugas - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas || $tugas['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/tugas/list.php');
}

$error = '';
$success = '';

// Handle grading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade') {
    $submission_id = intval($_POST['submission_id'] ?? 0);
    $nilai = floatval($_POST['nilai'] ?? 0);
    $feedback = sanitize($_POST['feedback'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE tugas_submission 
                              SET nilai = ?, feedback = ?, status = 'dinilai', waktu_dinilai = NOW() 
                              WHERE id = ? AND id_tugas = ?");
        $stmt->execute([$nilai, $feedback, $submission_id, $tugas_id]);
        $success = 'Nilai berhasil disimpan';
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Get submissions
$submissions = get_tugas_submissions($tugas_id);

$page_title = 'Review Tugas';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Review Tugas: <?php echo escape($tugas['judul']); ?></h2>
                <p class="text-muted mb-0"><?php echo escape($tugas['nama_mapel']); ?></p>
            </div>
            <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="200">Mata Pelajaran</th>
                <td><?php echo escape($tugas['nama_mapel']); ?></td>
            </tr>
            <tr>
                <th>Deadline</th>
                <td><?php echo format_date($tugas['deadline']); ?></td>
            </tr>
            <tr>
                <th>Poin Maksimal</th>
                <td><?php echo number_format($tugas['poin_maksimal'], 0); ?></td>
            </tr>
            <tr>
                <th>Deskripsi</th>
                <td><?php echo nl2br(escape($tugas['deskripsi'] ?? '-')); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Submission (<?php echo count($submissions); ?>)</h5>
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
                            <th>Komentar</th>
                            <th>Waktu Submit</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): 
                            $submission_files = get_tugas_submission_files($sub['id']);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo escape($sub['nama_siswa']); ?></strong><br>
                                <small class="text-muted"><?php echo escape($sub['username']); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($submission_files)): ?>
                                    <?php foreach ($submission_files as $file): ?>
                                        <a href="<?php echo asset_url('uploads/pr/' . $file['file_path']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary mb-1">
                                            <i class="fas fa-download"></i> <?php echo escape($file['nama_file']); ?>
                                        </a><br>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($sub['komentar'] ?? '-'); ?></td>
                            <td><?php echo $sub['waktu_submit'] ? format_date($sub['waktu_submit']) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $sub['status'] === 'dinilai' ? 'success' : 
                                        ($sub['status'] === 'sudah_dikumpulkan' || $sub['status'] === 'draft' ? 'info' : 
                                        ($sub['status'] === 'terlambat' ? 'warning' : 'secondary')); 
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
                        <label class="form-label">Nilai (0-<?php echo $tugas['poin_maksimal']; ?>)</label>
                        <input type="number" class="form-control" name="nilai" id="grade_nilai" 
                               min="0" max="<?php echo $tugas['poin_maksimal']; ?>" step="0.01" required>
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





