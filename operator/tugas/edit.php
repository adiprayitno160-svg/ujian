<?php
/**
 * Edit Tugas - Operator
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

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $id_guru = intval($_POST['id_guru'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    $poin_maksimal = floatval($_POST['poin_maksimal'] ?? 100);
    $tipe_tugas = sanitize($_POST['tipe_tugas'] ?? 'individu');
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $max_files = intval($_POST['max_files'] ?? 5);
    $max_file_size_mb = intval($_POST['max_file_size'] ?? 10);
    $max_file_size = $max_file_size_mb * 1048576;
    $allowed_extensions = sanitize($_POST['allowed_extensions'] ?? 'pdf,doc,docx,zip,rar,ppt,pptx');
    $allow_edit_after_submit = isset($_POST['allow_edit_after_submit']) ? 1 : 0;
    $status = sanitize($_POST['status'] ?? 'published');
    
    // Validate
    $data = [
        'judul' => $judul,
        'id_mapel' => $id_mapel,
        'deadline' => $deadline,
        'kelas_ids' => $kelas_ids,
        'poin_maksimal' => $poin_maksimal
    ];
    
    $errors = validate_tugas_data($data);
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $guru_id_to_assign = $id_guru > 0 ? $id_guru : $tugas['id_guru'];
            
            // Update Tugas
            $stmt = $pdo->prepare("UPDATE tugas 
                                  SET judul = ?, deskripsi = ?, id_mapel = ?, id_guru = ?, deadline = ?, 
                                      poin_maksimal = ?, tipe_tugas = ?, allow_late_submission = ?,
                                      max_files = ?, max_file_size = ?, allowed_extensions = ?,
                                      allow_edit_after_submit = ?, status = ?
                                  WHERE id = ?");
            $stmt->execute([
                $judul, $deskripsi, $id_mapel, $guru_id_to_assign, $deadline, $poin_maksimal,
                $tipe_tugas, $allow_late_submission, $max_files, $max_file_size,
                $allowed_extensions, $allow_edit_after_submit, $status,
                $tugas_id
            ]);
            
            // Update kelas assignments
            $stmt = $pdo->prepare("DELETE FROM tugas_kelas WHERE id_tugas = ?");
            $stmt->execute([$tugas_id]);
            
            foreach ($kelas_ids as $kelas_id) {
                $kelas_id = intval($kelas_id);
                $stmt = $pdo->prepare("INSERT INTO tugas_kelas (id_tugas, id_kelas) VALUES (?, ?)");
                $stmt->execute([$tugas_id, $kelas_id]);
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Tugas berhasil diperbarui';
            redirect('operator-tugas-list');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error updating tugas: ' . $e->getMessage();
            error_log("Update tugas error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get all mapel
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get all guru
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'guru' AND status = 'active' ORDER BY nama ASC");
$guru_list = $stmt->fetchAll();

// Get all kelas
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

// Get assigned kelas
$assigned_kelas = get_tugas_kelas($tugas_id);
$assigned_kelas_ids = array_column($assigned_kelas, 'id');

$page_title = 'Edit Tugas - Operator';
$role_css = 'operator';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Edit Tugas</h2>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo escape($tugas['judul']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="5"><?php echo escape($tugas['deskripsi'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_mapel" name="id_mapel" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php foreach ($mapel_list as $mapel): ?>
                                        <option value="<?php echo $mapel['id']; ?>" 
                                                <?php echo $tugas['id_mapel'] == $mapel['id'] ? 'selected' : ''; ?>>
                                            <?php echo escape($mapel['nama_mapel']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_guru" class="form-label">Assign ke Guru</label>
                                <select class="form-select" id="id_guru" name="id_guru">
                                    <option value="">Pilih Guru (opsional)</option>
                                    <?php foreach ($guru_list as $guru): ?>
                                        <option value="<?php echo $guru['id']; ?>" 
                                                <?php echo $tugas['id_guru'] == $guru['id'] ? 'selected' : ''; ?>>
                                            <?php echo escape($guru['nama']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="deadline" name="deadline" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($tugas['deadline'])); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="poin_maksimal" class="form-label">Poin Maksimal</label>
                                <input type="number" class="form-control" id="poin_maksimal" name="poin_maksimal" 
                                       value="<?php echo $tugas['poin_maksimal']; ?>" 
                                       min="0" max="100" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo $tugas['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo $tugas['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Assign ke Kelas</h5>
                </div>
                <div class="card-body">
                    <div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                        <?php foreach ($kelas_list as $kelas): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                                   value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>"
                                   <?php echo in_array($kelas['id'], $assigned_kelas_ids) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
                <a href="<?php echo base_url('operator-tugas-list'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

