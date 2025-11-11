<?php
/**
 * Review Tugas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tugas_functions.php';

require_role('operator');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas) {
    redirect('operator-tugas-list');
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

$page_title = 'Review Tugas - Operator';
$role_css = 'operator';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Review Tugas</h2>
                <p class="text-muted mb-0"><?php echo escape($tugas['judul']); ?> - <?php echo escape($tugas['nama_mapel']); ?></p>
            </div>
            <div>
                <a href="<?php echo base_url('operator-tugas-detail?id=' . $tugas_id); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($submissions)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada submission
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa</th>
                            <th>Username</th>
                            <th>Kelas</th>
                            <th>Waktu Submit</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $index => $sub): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($sub['nama_siswa']); ?></td>
                            <td><?php echo escape($sub['username']); ?></td>
                            <td><?php echo escape($sub['nama_kelas'] ?? '-'); ?></td>
                            <td><?php echo $sub['waktu_submit'] ? format_date($sub['waktu_submit']) : '-'; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $sub['status'] === 'dinilai' ? 'success' : 
                                        ($sub['status'] === 'sudah_dikumpulkan' ? 'info' : 'warning'); 
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
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" 
                                        data-bs-target="#gradeModal<?php echo $sub['id']; ?>">
                                    <i class="fas fa-edit"></i> Grade
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Grade Modal -->
                        <div class="modal fade" id="gradeModal<?php echo $sub['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Grade Submission - <?php echo escape($sub['nama_siswa']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="grade">
                                            <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                            
                                            <div class="mb-3">
                                                <label for="nilai<?php echo $sub['id']; ?>" class="form-label">Nilai</label>
                                                <input type="number" class="form-control" id="nilai<?php echo $sub['id']; ?>" 
                                                       name="nilai" value="<?php echo $sub['nilai'] ?? ''; ?>" 
                                                       min="0" max="<?php echo $tugas['poin_maksimal']; ?>" step="0.01" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="feedback<?php echo $sub['id']; ?>" class="form-label">Feedback</label>
                                                <textarea class="form-control" id="feedback<?php echo $sub['id']; ?>" 
                                                          name="feedback" rows="4"><?php echo escape($sub['feedback'] ?? ''); ?></textarea>
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
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

