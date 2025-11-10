<?php
/**
 * Create Tugas - Guru
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
    
    // Validasi: Guru hanya bisa membuat tugas untuk mata pelajaran yang dia ajar
    // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
    if (empty($errors) && !guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
        $errors[] = 'Anda tidak diizinkan membuat tugas untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert Tugas
            $stmt = $pdo->prepare("INSERT INTO tugas 
                                  (judul, deskripsi, id_mapel, id_guru, deadline, poin_maksimal, 
                                   tipe_tugas, allow_late_submission, max_files, max_file_size, 
                                   allowed_extensions, allow_edit_after_submit, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $deadline, $poin_maksimal,
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
                                $file['name'],
                                $upload_result['filename'],
                                $file['size'],
                                $file['type'],
                                $i + 1
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Tugas berhasil dibuat';
            log_activity('create_tugas', 'tugas', $tugas_id);
            
            header("Location: " . base_url('guru/tugas/list.php'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Create Tugas error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat Tugas';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get mapel for this guru
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();

// Get kelas
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

$page_title = 'Buat Tugas Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Tugas Baru</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (empty($mapel_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Anda belum di-assign ke mata pelajaran.</strong><br>
        Sistem menggunakan guru mata pelajaran (bukan guru kelas). 
        Hubungi admin untuk assign mata pelajaran yang akan Anda ajarkan.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" required>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"></textarea>
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
                                        <option value="<?php echo $mapel['id']; ?>">
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
                            <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="poin_maksimal" class="form-label">Poin Maksimal</label>
                            <input type="number" class="form-control" id="poin_maksimal" name="poin_maksimal" 
                                   value="100" min="0" max="100" step="0.1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="tipe_tugas" class="form-label">Tipe Tugas</label>
                            <select class="form-select" id="tipe_tugas" name="tipe_tugas">
                                <option value="individu">Individu</option>
                                <option value="kelompok">Kelompok</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="max_files" class="form-label">Maksimal File</label>
                            <input type="number" class="form-control" id="max_files" name="max_files" 
                                   value="5" min="1" max="20">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="max_file_size" class="form-label">Maksimal Ukuran File (MB)</label>
                            <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                   value="10" min="1" max="100">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="allowed_extensions" class="form-label">Ekstensi File Diizinkan</label>
                            <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                                   value="pdf,doc,docx,zip,rar,ppt,pptx" 
                                   placeholder="pdf,doc,docx">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="allow_late_submission" 
                               name="allow_late_submission" value="1">
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
                
                <div class="mb-3">
                    <label class="form-label">Assign ke Kelas <span class="text-danger">*</span></label>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                        <?php foreach ($kelas_list as $kelas): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                                   value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>">
                            <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat Tugas
                    </button>
                    <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
// Convert max_file_size from MB to bytes for display
document.getElementById('max_file_size').addEventListener('change', function() {
    // Value is in MB, no conversion needed for display
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

