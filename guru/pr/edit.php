<?php
/**
 * Edit PR - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$error = '';
$success = '';

$pr_id = intval($_GET['id'] ?? 0);

// Get PR data
$stmt = $pdo->prepare("SELECT * FROM pr WHERE id = ? AND id_guru = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    header("Location: " . base_url('guru/pr/list.php'));
    exit;
}

// Get current kelas assignments
$stmt = $pdo->prepare("SELECT id_kelas FROM pr_kelas WHERE id_pr = ?");
$stmt->execute([$pr_id]);
$current_kelas = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Process POST request BEFORE including header to avoid headers already sent error
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    
    if (empty($judul) || !$id_mapel || empty($deadline) || empty($kelas_ids)) {
        $error = 'Judul, mata pelajaran, deadline, dan kelas harus diisi';
    } else {
        // Validasi: Guru hanya bisa mengedit PR untuk mata pelajaran yang dia ajar
        // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
        if (!guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
            $error = 'Anda tidak diizinkan mengedit PR untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Handle file upload
                $file_lampiran = $pr['file_lampiran']; // Keep existing file by default
                if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_file($_FILES['file_lampiran'], UPLOAD_PR, ALLOWED_DOC_TYPES);
                    if ($upload_result['success']) {
                        // Delete old file if exists
                        if ($pr['file_lampiran']) {
                            $old_file = UPLOAD_PR . '/' . $pr['file_lampiran'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                        $file_lampiran = $upload_result['filename'];
                    }
                }
                
                // Update PR
                $stmt = $pdo->prepare("UPDATE pr 
                                      SET judul = ?, deskripsi = ?, id_mapel = ?, deadline = ?, file_lampiran = ?
                                      WHERE id = ? AND id_guru = ?");
                $stmt->execute([$judul, $deskripsi, $id_mapel, $deadline, $file_lampiran, $pr_id, $_SESSION['user_id']]);
                
                // Update kelas assignments
                // Delete old assignments
                $stmt = $pdo->prepare("DELETE FROM pr_kelas WHERE id_pr = ?");
                $stmt->execute([$pr_id]);
                
                // Insert new assignments
                foreach ($kelas_ids as $kelas_id) {
                    $kelas_id = intval($kelas_id);
                    $stmt = $pdo->prepare("INSERT INTO pr_kelas (id_pr, id_kelas) VALUES (?, ?)");
                    $stmt->execute([$pr_id, $kelas_id]);
                }
                
                $pdo->commit();
                $success = 'PR berhasil diupdate';
                log_activity('update_pr', 'pr', $pr_id);
                
                // Redirect to list BEFORE any HTML output
                header("Location: " . base_url('guru/pr/list.php'));
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Update PR error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengupdate PR';
            }
        }
    }
}

// Get mapel for this guru (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Get kelas
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

$page_title = 'Edit PR';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Edit PR</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (empty($mapel_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Anda belum di-assign ke mata pelajaran.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul PR <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" value="<?php echo escape($pr['judul']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"><?php echo escape($pr['deskripsi']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    
                    <?php if (count($mapel_list) == 1): ?>
                        <?php $single_mapel = $mapel_list[0]; ?>
                        <!-- Jika hanya 1 mata pelajaran, auto-select dan tampilkan sebagai info -->
                        <div class="alert alert-info d-flex align-items-center mb-2">
                            <i class="fas fa-book me-2"></i>
                            <strong>Mata Pelajaran:</strong> 
                            <span class="ms-2 badge bg-primary"><?php echo escape($single_mapel['nama_mapel']); ?></span>
                        </div>
                        <input type="hidden" name="id_mapel" value="<?php echo $single_mapel['id']; ?>">
                    <?php else: ?>
                        <!-- Jika lebih dari 1 mata pelajaran, tampilkan info semua mata pelajaran yang diampu -->
                        <div class="mb-2">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Mata pelajaran yang Anda ampu:</strong>
                            </small>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($mapel_list as $mapel): ?>
                                    <span class="badge bg-info"><?php echo escape($mapel['nama_mapel']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <select class="form-select" id="id_mapel" name="id_mapel" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo ($mapel['id'] == $pr['id_mapel']) ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($pr['deadline'])); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="file_lampiran" class="form-label">File Lampiran (opsional)</label>
                    <?php if ($pr['file_lampiran']): ?>
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-file"></i> File saat ini: 
                                <a href="<?php echo asset_url('uploads/pr/' . $pr['file_lampiran']); ?>" target="_blank">
                                    <?php echo escape($pr['file_lampiran']); ?>
                                </a>
                            </small>
                        </div>
                    <?php endif; ?>
                    <input type="file" class="form-control" id="file_lampiran" name="file_lampiran" accept=".pdf,.doc,.docx,.zip">
                    <small class="text-muted">Format: PDF, DOC, DOCX, ZIP. Max: 10MB. Kosongkan jika tidak ingin mengubah file.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Assign ke Kelas <span class="text-danger">*</span></label>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                        <?php foreach ($kelas_list as $kelas): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                                   value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>"
                                   <?php echo in_array($kelas['id'], $current_kelas) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                    <a href="<?php echo base_url('guru/pr/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

