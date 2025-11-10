<?php
/**
 * List Tugas - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Daftar Tugas';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$filter_status = $_GET['status'] ?? '';
$filter_mapel = $_GET['mapel_id'] ?? '';

// Get Tugas for this student's classes
$filters = [];
if ($filter_status) $filters['status'] = $filter_status;
if ($filter_mapel) $filters['mapel_id'] = $filter_mapel;

$tugas_list = get_tugas_by_student($_SESSION['user_id'], $filters);

// Get mapel for filter
$stmt = $pdo->prepare("SELECT DISTINCT m.* FROM mapel m
                      INNER JOIN tugas t ON m.id = t.id_mapel
                      INNER JOIN tugas_kelas tk ON t.id = tk.id_tugas
                      INNER JOIN user_kelas uk ON tk.id_kelas = uk.id_kelas
                      WHERE uk.id_user = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Daftar Tugas</h2>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua Status</option>
                    <option value="belum_dikumpulkan" <?php echo $filter_status === 'belum_dikumpulkan' ? 'selected' : ''; ?>>Belum Dikumpulkan</option>
                    <option value="sudah_dikumpulkan" <?php echo $filter_status === 'sudah_dikumpulkan' ? 'selected' : ''; ?>>Sudah Dikumpulkan</option>
                    <option value="dinilai" <?php echo $filter_status === 'dinilai' ? 'selected' : ''; ?>>Sudah Dinilai</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="mapel_id" class="form-label">Mata Pelajaran</label>
                <select class="form-select" id="mapel_id" name="mapel_id">
                    <option value="">Semua Mata Pelajaran</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">&nbsp;</label>
                <div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <?php if (empty($tugas_list)): ?>
        <div class="col-12">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada Tugas yang tersedia
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($tugas_list as $tugas): 
            $now = new DateTime();
            $deadline = new DateTime($tugas['deadline']);
            $is_overdue = $now > $deadline;
            $status = $tugas['status_submission'] ?? 'belum_dikumpulkan';
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 <?php echo $is_overdue && $status !== 'sudah_dikumpulkan' ? 'border-danger' : ''; ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo escape($tugas['judul']); ?></h5>
                    <p class="text-muted mb-2">
                        <i class="fas fa-book"></i> <?php echo escape($tugas['nama_mapel']); ?>
                    </p>
                    <p class="mb-2">
                        <i class="fas fa-calendar"></i> 
                        <strong>Deadline:</strong> 
                        <span class="<?php echo $is_overdue && $status !== 'sudah_dikumpulkan' ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo format_date($tugas['deadline']); ?>
                        </span>
                    </p>
                    <p class="text-muted small mb-2">
                        <i class="fas fa-star"></i> Poin: <?php echo number_format($tugas['poin_maksimal'], 0); ?>
                    </p>
                    <?php if ($tugas['deskripsi']): ?>
                    <p class="text-muted small mb-3"><?php echo escape(substr($tugas['deskripsi'], 0, 100)); ?>...</p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <span class="badge bg-<?php 
                            echo $status === 'dinilai' ? 'success' : 
                                ($status === 'sudah_dikumpulkan' || $status === 'draft' ? 'info' : 'warning'); 
                        ?>">
                            <?php 
                            echo $status === 'dinilai' ? 'Sudah Dinilai' : 
                                ($status === 'sudah_dikumpulkan' ? 'Sudah Dikumpulkan' : 
                                ($status === 'draft' ? 'Draft' : 'Belum Dikumpulkan')); 
                            ?>
                        </span>
                        <?php if ($tugas['nilai_submission'] !== null): ?>
                            <span class="badge bg-primary ms-2">Nilai: <?php echo number_format($tugas['nilai_submission'], 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="<?php echo base_url('siswa/tugas/detail.php?id=' . $tugas['id']); ?>" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> Lihat Detail
                        </a>
                        <?php if ($status === 'belum_dikumpulkan' || $status === null || $status === 'draft'): ?>
                            <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas['id']); ?>" 
                               class="btn btn-sm btn-success">
                                <i class="fas fa-upload"></i> Submit Tugas
                            </a>
                        <?php else: ?>
                            <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas['id']); ?>" 
                               class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> Lihat Submission
                            </a>
                            <?php if (can_student_edit_tugas($tugas['id'], $_SESSION['user_id'])): ?>
                                <a href="<?php echo base_url('siswa/tugas/submit.php?id=' . $tugas['id']); ?>" 
                                   class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit Submission
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



