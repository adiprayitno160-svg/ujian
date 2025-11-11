<?php
/**
 * Create Tugas - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Operator dapat membuat tugas untuk semua mata pelajaran dan kelas
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tugas_functions.php';

require_role('operator');
check_session_timeout();

global $pdo;

$error = '';
$success = '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $id_guru = intval($_POST['id_guru'] ?? 0); // Operator can assign to any guru
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    $poin_maksimal = floatval($_POST['poin_maksimal'] ?? 100);
    $tipe_tugas = sanitize($_POST['tipe_tugas'] ?? 'individu');
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $max_files = intval($_POST['max_files'] ?? 5);
    $max_file_size_mb = intval($_POST['max_file_size'] ?? 10);
    $max_file_size = $max_file_size_mb * 1048576; // Convert MB to bytes
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
    
    // Operator can create tugas for any mapel and assign to any guru
    // No mapel restriction for operator
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // If no guru selected, use current operator as creator (but id_guru can be null or assigned to another guru)
            $guru_id_to_assign = $id_guru > 0 ? $id_guru : $_SESSION['user_id'];
            
            // Insert Tugas
            $stmt = $pdo->prepare("INSERT INTO tugas 
                                  (judul, deskripsi, id_mapel, id_guru, deadline, poin_maksimal, 
                                   tipe_tugas, allow_late_submission, max_files, max_file_size, 
                                   allowed_extensions, allow_edit_after_submit, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $judul, $deskripsi, $id_mapel, $guru_id_to_assign, $deadline, $poin_maksimal,
                $tipe_tugas, $allow_late_submission, $max_files, $max_file_size,
                $allowed_extensions, $allow_edit_after_submit, $status
            ]);
            $tugas_id = $pdo->lastInsertId();
            
            // Assign to kelas
            foreach ($kelas_ids as $kelas_id) {
                $kelas_id = intval($kelas_id);
                $stmt = $pdo->prepare("INSERT INTO tugas_kelas (id_tugas, id_kelas) VALUES (?, ?)");
                $stmt->execute([$tugas_id, $kelas_id]);
            }
            
            // Handle file attachments
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = UPLOAD_PR; // Using same directory as PR
                $files = $_FILES['attachments'];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        
                        $upload_result = upload_file($file, $upload_dir, ALLOWED_DOC_TYPES);
                        if ($upload_result['success']) {
                            $stmt = $pdo->prepare("INSERT INTO tugas_attachment 
                                                  (id_tugas, nama_file, file_path, file_size, file_type, urutan) 
                                                  VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $tugas_id,
                                $upload_result['name'],
                                $upload_result['path'],
                                $upload_result['size'],
                                $upload_result['type'],
                                $i + 1
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Tugas berhasil dibuat';
            redirect('operator-tugas-list');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error creating tugas: ' . $e->getMessage();
            error_log("Create tugas error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get all mapel (operator can see all)
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get all guru (operator can assign to any guru)
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'guru' AND status = 'active' ORDER BY nama ASC");
$guru_list = $stmt->fetchAll();

// Get all kelas (operator can assign to any kelas)
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

$page_title = 'Buat Tugas Baru - Operator';
$role_css = 'operator';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Tugas Baru</h2>
        <p class="text-muted">Operator dapat membuat tugas untuk semua mata pelajaran dan kelas.</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
    </div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="judul" name="judul" 
                               value="<?php echo escape($_POST['judul'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="5"><?php echo escape($_POST['deskripsi'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                                <select class="form-select" id="id_mapel" name="id_mapel" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php foreach ($mapel_list as $mapel): ?>
                                        <option value="<?php echo $mapel['id']; ?>" 
                                                <?php echo (isset($_POST['id_mapel']) && $_POST['id_mapel'] == $mapel['id']) ? 'selected' : ''; ?>>
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
                                                <?php echo (isset($_POST['id_guru']) && $_POST['id_guru'] == $guru['id']) ? 'selected' : ''; ?>>
                                            <?php echo escape($guru['nama']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Jika tidak dipilih, tugas akan diassign ke operator yang membuat</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="deadline" name="deadline" 
                                       value="<?php echo escape($_POST['deadline'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="poin_maksimal" class="form-label">Poin Maksimal</label>
                                <input type="number" class="form-control" id="poin_maksimal" name="poin_maksimal" 
                                       value="<?php echo escape($_POST['poin_maksimal'] ?? 100); ?>" 
                                       min="0" max="100" step="0.01">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tipe_tugas" class="form-label">Tipe Tugas</label>
                        <select class="form-select" id="tipe_tugas" name="tipe_tugas">
                            <option value="individu" <?php echo (isset($_POST['tipe_tugas']) && $_POST['tipe_tugas'] === 'individu') ? 'selected' : 'selected'; ?>>Individu</option>
                            <option value="kelompok" <?php echo (isset($_POST['tipe_tugas']) && $_POST['tipe_tugas'] === 'kelompok') ? 'selected' : ''; ?>>Kelompok</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] === 'published') ? 'selected' : 'selected'; ?>>Published</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Pengaturan File</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_files" class="form-label">Maksimal File</label>
                                <input type="number" class="form-control" id="max_files" name="max_files" 
                                       value="<?php echo escape($_POST['max_files'] ?? 5); ?>" min="1" max="20">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_file_size" class="form-label">Maksimal Ukuran File (MB)</label>
                                <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                       value="<?php echo escape($_POST['max_file_size'] ?? 10); ?>" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="allowed_extensions" class="form-label">Ekstensi File Diizinkan</label>
                                <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                                       value="<?php echo escape($_POST['allowed_extensions'] ?? 'pdf,doc,docx,zip,rar,ppt,pptx'); ?>" 
                                       placeholder="pdf,doc,docx">
                                <small class="text-muted">Pisahkan dengan koma</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_late_submission" 
                                   name="allow_late_submission" value="1" <?php echo (isset($_POST['allow_late_submission'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_late_submission">
                                Izinkan submit setelah deadline
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_edit_after_submit" 
                                   name="allow_edit_after_submit" value="1" checked>
                            <label class="form-check-label" for="allow_edit_after_submit">
                                Izinkan edit setelah submit (sebelum deadline)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">File Lampiran (opsional)</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                               accept=".pdf,.doc,.docx,.zip,.rar,.ppt,.pptx">
                        <small class="text-muted">Bisa upload multiple files</small>
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
                                   <?php echo (isset($_POST['kelas_ids']) && in_array($kelas['id'], $_POST['kelas_ids'])) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Pilih satu atau lebih kelas</small>
                </div>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Tugas
                </button>
                <a href="<?php echo base_url('operator-tugas-list'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Batal
                </a>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

