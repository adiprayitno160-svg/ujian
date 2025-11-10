<?php
/**
 * Edit Tugas - Guru
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

$tugas_id = intval($_GET['id'] ?? 0);

// Get Tugas data
$tugas = get_tugas($tugas_id);

if (!$tugas || $tugas['id_guru'] != $_SESSION['user_id']) {
    header("Location: " . base_url('guru/tugas/list.php'));
    exit;
}

// Get current kelas assignments
$current_kelas = get_tugas_kelas($tugas_id);
$current_kelas_ids = array_column($current_kelas, 'id');

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    $poin_maksimal = floatval($_POST['poin_maksimal'] ?? 100);
    $tipe_tugas = sanitize($_POST['tipe_tugas'] ?? 'individu');
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $max_files = intval($_POST['max_files'] ?? 5);
    $max_file_size_mb = intval($_POST['max_file_size'] ?? 10);
    $max_file_size = $max_file_size_mb * 1048576; // Convert MB to bytes
    $allowed_extensions = sanitize($_POST['allowed_extensions'] ?? 'pdf,doc,docx,zip,rar');
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
    
    // Validasi: Guru hanya bisa mengedit tugas untuk mata pelajaran yang dia ajar
    // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
    if (empty($errors) && !guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
        $errors[] = 'Anda tidak diizinkan mengedit tugas untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Update Tugas
            $stmt = $pdo->prepare("UPDATE tugas 
                                  SET judul = ?, deskripsi = ?, id_mapel = ?, deadline = ?, 
                                      poin_maksimal = ?, tipe_tugas = ?, allow_late_submission = ?,
                                      max_files = ?, max_file_size = ?, allowed_extensions = ?,
                                      allow_edit_after_submit = ?, status = ?
                                  WHERE id = ? AND id_guru = ?");
            $stmt->execute([
                $judul, $deskripsi, $id_mapel, $deadline, $poin_maksimal,
                $tipe_tugas, $allow_late_submission, $max_files, $max_file_size,
                $allowed_extensions, $allow_edit_after_submit, $status,
                $tugas_id, $_SESSION['user_id']
            ]);
            
            // Update kelas assignments
            $stmt = $pdo->prepare("DELETE FROM tugas_kelas WHERE id_tugas = ?");
            $stmt->execute([$tugas_id]);
            
            foreach ($kelas_ids as $kelas_id) {
                $kelas_id = intval($kelas_id);
                $stmt = $pdo->prepare("INSERT INTO tugas_kelas (id_tugas, id_kelas) VALUES (?, ?)");
                $stmt->execute([$tugas_id, $kelas_id]);
            }
            
            // Handle new file attachments
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = UPLOAD_PR;
                $files = $_FILES['attachments'];
                $file_count = count($files['name']);
                
                // Get current max urutan
                $stmt = $pdo->prepare("SELECT MAX(urutan) as max_urutan FROM tugas_attachment WHERE id_tugas = ?");
                $stmt->execute([$tugas_id]);
                $max_urutan = $stmt->fetch()['max_urutan'] ?? 0;
                
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
                                $file['name'],
                                $upload_result['filename'],
                                $file['size'],
                                $file['type'],
                                $max_urutan + $i + 1
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Tugas berhasil diupdate';
            log_activity('update_tugas', 'tugas', $tugas_id);
            
            header("Location: " . base_url('guru/tugas/list.php'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Tugas error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat mengupdate Tugas';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get mapel for this guru (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Get kelas
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

// Get attachments
$attachments = get_tugas_attachments($tugas_id);

$page_title = 'Edit Tugas';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Edit Tugas</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="judul" name="judul" 
                       value="<?php echo escape($tugas['judul']); ?>" required>
            </div>
            
            <div class="mb-3">
                <label for="deskripsi" class="form-label">Deskripsi</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"><?php echo escape($tugas['deskripsi'] ?? ''); ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-6">
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
                                    <option value="<?php echo $mapel['id']; ?>" 
                                            <?php echo $mapel['id'] == $tugas['id_mapel'] ? 'selected' : ''; ?>>
                                        <?php echo escape($mapel['nama_mapel']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" id="deadline" name="deadline" 
                               value="<?php echo date('Y-m-d\TH:i', strtotime($tugas['deadline'])); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="poin_maksimal" class="form-label">Poin Maksimal</label>
                        <input type="number" class="form-control" id="poin_maksimal" name="poin_maksimal" 
                               value="<?php echo $tugas['poin_maksimal']; ?>" min="0" max="100" step="0.1">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="tipe_tugas" class="form-label">Tipe Tugas</label>
                        <select class="form-select" id="tipe_tugas" name="tipe_tugas">
                            <option value="individu" <?php echo $tugas['tipe_tugas'] === 'individu' ? 'selected' : ''; ?>>Individu</option>
                            <option value="kelompok" <?php echo $tugas['tipe_tugas'] === 'kelompok' ? 'selected' : ''; ?>>Kelompok</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="published" <?php echo $tugas['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $tugas['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="archived" <?php echo $tugas['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="max_files" class="form-label">Maksimal File</label>
                        <input type="number" class="form-control" id="max_files" name="max_files" 
                               value="<?php echo $tugas['max_files']; ?>" min="1" max="20">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="max_file_size" class="form-label">Maksimal Ukuran File (MB)</label>
                        <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                               value="<?php echo round($tugas['max_file_size'] / 1048576); ?>" min="1" max="100">
                        <small class="text-muted">Nilai dalam MB akan dikonversi ke bytes</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="allowed_extensions" class="form-label">Ekstensi File Diizinkan</label>
                        <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                               value="<?php echo escape($tugas['allowed_extensions']); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="allow_late_submission" 
                           name="allow_late_submission" value="1" 
                           <?php echo $tugas['allow_late_submission'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_late_submission">
                        Izinkan submit setelah deadline
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="allow_edit_after_submit" 
                           name="allow_edit_after_submit" value="1" 
                           <?php echo $tugas['allow_edit_after_submit'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="allow_edit_after_submit">
                        Izinkan edit setelah submit (sebelum deadline)
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">File Lampiran Saat Ini</label>
                <?php if (!empty($attachments)): ?>
                    <div class="list-group mb-2">
                        <?php foreach ($attachments as $att): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file"></i> 
                                <a href="<?php echo asset_url('uploads/pr/' . $att['file_path']); ?>" target="_blank">
                                    <?php echo escape($att['nama_file']); ?>
                                </a>
                                <small class="text-muted">(<?php echo format_file_size($att['file_size']); ?>)</small>
                            </div>
                            <a href="<?php echo base_url('guru/tugas/delete_attachment.php?id=' . $att['id'] . '&tugas_id=' . $tugas_id); ?>" 
                               class="btn btn-sm btn-danger" onclick="return confirm('Hapus file ini?');">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Tidak ada file lampiran</p>
                <?php endif; ?>
                <label for="attachments" class="form-label">Tambah File Lampiran Baru</label>
                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                       accept=".pdf,.doc,.docx,.zip,.rar,.ppt,.pptx">
                <small class="text-muted">Bisa upload multiple files</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Assign ke Kelas <span class="text-danger">*</span></label>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                    <?php foreach ($kelas_list as $kelas): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                               value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>"
                               <?php echo in_array($kelas['id'], $current_kelas_ids) ? 'checked' : ''; ?>>
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
                <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

