<?php
/**
 * Submit Tugas - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$tugas_id = intval($_GET['id'] ?? 0);
$tugas = get_tugas($tugas_id);

if (!$tugas) {
    redirect('siswa/tugas/list.php');
}

// Check if student is assigned
if (!is_student_assigned_to_tugas($tugas_id, $_SESSION['user_id'])) {
    redirect('siswa/tugas/list.php');
}

// Get existing submission
$submission = get_tugas_submission($tugas_id, $_SESSION['user_id']);

$error = '';
$success = '';

// Check if can submit
if (!can_student_submit_tugas($tugas_id, $_SESSION['user_id']) && !$submission) {
    $error = 'Tidak dapat submit tugas. Deadline sudah lewat atau tidak memiliki akses.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $komentar = sanitize($_POST['komentar'] ?? '');
    $tipe_submission = sanitize($_POST['tipe_submission'] ?? 'file');
    $jawaban_text = isset($_POST['jawaban_text']) ? $_POST['jawaban_text'] : ''; // Allow HTML from editor
    
    // Check deadline
    $deadline = new DateTime($tugas['deadline']);
    $now = new DateTime();
    $is_late = $now > $deadline;
    
    if ($is_late && !$tugas['allow_late_submission'] && !$submission) {
        $error = 'Deadline sudah lewat dan late submission tidak diizinkan';
    } else {
        try {
            $pdo->beginTransaction();
            
            $uploaded_files = [];
            $has_file = false;
            $has_text = false;
            
            // Handle file uploads (if file type is selected)
            if ($tipe_submission === 'file' || $tipe_submission === 'both') {
                if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                    $files = $_FILES['files'];
                    $file_count = count($files['name']);
                    
                    // Validate files
                    $file_array = [];
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file_array[] = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                        }
                    }
                    
                    if (!empty($file_array)) {
                        $validation = validate_tugas_submission($tugas_id, $file_array);
                        if (!$validation['success']) {
                            throw new Exception($validation['message']);
                        }
                        
                        // Upload files
                        $upload_dir = UPLOAD_PR;
                        foreach ($file_array as $file) {
                            $upload_result = upload_file($file, $upload_dir, ALLOWED_DOC_TYPES);
                            if ($upload_result['success']) {
                                $uploaded_files[] = [
                                    'name' => $file['name'],
                                    'path' => $upload_result['filename'],
                                    'size' => $file['size'],
                                    'type' => $file['type']
                                ];
                                $has_file = true;
                            }
                        }
                    }
                }
                
                // Check if existing files
                if (!$has_file && $submission) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tugas_submission_file WHERE id_submission = ?");
                    $stmt->execute([$submission['id']]);
                    $file_count = $stmt->fetch()['count'];
                    if ($file_count > 0) {
                        $has_file = true;
                    }
                }
                
                // Require file if file type and no existing files
                if ($tipe_submission === 'file' && !$has_file && !$submission) {
                    $error = 'Minimal satu file harus diupload';
                }
            } elseif ($submission) {
                // Keep existing files if switching to text-only
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tugas_submission_file WHERE id_submission = ?");
                $stmt->execute([$submission['id']]);
                $file_count = $stmt->fetch()['count'];
                if ($file_count > 0) {
                    $has_file = true;
                }
            }
            
            // Handle text submission (if text type is selected)
            if ($tipe_submission === 'text' || $tipe_submission === 'both') {
                if (!empty(trim(strip_tags($jawaban_text)))) {
                    $has_text = true;
                } elseif ($tipe_submission === 'text' && (!$submission || empty($submission['jawaban_text']))) {
                    $error = 'Jawaban harus diisi';
                }
            } elseif ($submission && $submission['jawaban_text']) {
                // Keep existing text if switching to file-only
                $jawaban_text = $submission['jawaban_text'];
                $has_text = true;
            }
            
            // Determine actual submission type
            if ($has_file && $has_text) {
                $tipe_submission = 'both';
            } elseif ($has_file) {
                $tipe_submission = 'file';
            } elseif ($has_text) {
                $tipe_submission = 'text';
            } else {
                if (!$error) {
                    $error = 'Harus mengupload file atau mengisi jawaban';
                }
            }
            
            if (!$error) {
                $status = $is_late ? 'terlambat' : 'sudah_dikumpulkan';
                
                if ($submission) {
                    // Update submission
                    $stmt = $pdo->prepare("UPDATE tugas_submission SET 
                                          jawaban_text = ?, komentar = ?, tipe_submission = ?, status = ?, waktu_submit = NOW(), is_late = ?
                                          WHERE id = ?");
                    $stmt->execute([
                        $jawaban_text, 
                        $komentar, 
                        $tipe_submission, 
                        $status, 
                        $is_late ? 1 : 0, 
                        $submission['id']
                    ]);
                    $submission_id = $submission['id'];
                    
                    // Delete old files if updating with new files
                    if (!empty($uploaded_files)) {
                        $stmt = $pdo->prepare("SELECT * FROM tugas_submission_file WHERE id_submission = ?");
                        $stmt->execute([$submission_id]);
                        $old_files = $stmt->fetchAll();
                        foreach ($old_files as $old_file) {
                            $file_path = UPLOAD_PR . '/' . $old_file['file_path'];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM tugas_submission_file WHERE id_submission = ?");
                        $stmt->execute([$submission_id]);
                    }
                } else {
                    // Create new submission
                    $stmt = $pdo->prepare("INSERT INTO tugas_submission 
                                          (id_tugas, id_siswa, jawaban_text, komentar, tipe_submission, status, waktu_submit, is_late) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([
                        $tugas_id, 
                        $_SESSION['user_id'], 
                        $jawaban_text, 
                        $komentar, 
                        $tipe_submission, 
                        $status, 
                        $is_late ? 1 : 0
                    ]);
                    $submission_id = $pdo->lastInsertId();
                }
                
                // Insert uploaded files
                if (!empty($uploaded_files)) {
                    foreach ($uploaded_files as $idx => $file) {
                        $stmt = $pdo->prepare("INSERT INTO tugas_submission_file 
                                              (id_submission, nama_file, file_path, file_size, file_type, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $submission_id,
                            $file['name'],
                            $file['path'],
                            $file['size'],
                            $file['type'],
                            $idx + 1
                        ]);
                    }
                }
                
                $pdo->commit();
                $success = 'Tugas berhasil dikumpulkan';
                log_activity('submit_tugas', 'tugas_submission', $tugas_id);
                
                // Refresh submission data
                $submission = get_tugas_submission($tugas_id, $_SESSION['user_id']);
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Submit Tugas error: " . $e->getMessage());
            $error = $e->getMessage();
        }
    }
}

// Get submission files
$submission_files = [];
if ($submission) {
    $submission_files = get_tugas_submission_files($submission['id']);
}

$page_title = 'Submit Tugas';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

$now = new DateTime();
$deadline = new DateTime($tugas['deadline']);
$is_overdue = $now > $deadline;
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Submit Tugas: <?php echo escape($tugas['judul']); ?></h2>
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

<div class="row g-4">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h4>Informasi Tugas</h4>
                <table class="table table-borderless">
                    <tr>
                        <th width="150">Mata Pelajaran</th>
                        <td><?php echo escape($tugas['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <th>Deadline</th>
                        <td>
                            <span class="<?php echo $is_overdue ? 'text-danger fw-bold' : ''; ?>">
                                <?php echo format_date($tugas['deadline']); ?>
                            </span>
                            <?php if ($is_overdue): ?>
                                <span class="badge bg-danger ms-2">Terlambat</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Poin Maksimal</th>
                        <td><?php echo number_format($tugas['poin_maksimal'], 0); ?></td>
                    </tr>
                    <tr>
                        <th>Maksimal File</th>
                        <td><?php echo $tugas['max_files']; ?> file</td>
                    </tr>
                    <tr>
                        <th>Ukuran Maksimal</th>
                        <td><?php echo format_file_size($tugas['max_file_size']); ?> per file</td>
                    </tr>
                    <tr>
                        <th>Ekstensi Diizinkan</th>
                        <td><?php echo escape($tugas['allowed_extensions']); ?></td>
                    </tr>
                    <tr>
                        <th>Deskripsi</th>
                        <td><?php echo nl2br(escape($tugas['deskripsi'] ?? '-')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="submitForm">
                    <!-- Submission Type Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Pilih Tipe Submission <span class="text-danger">*</span></label>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card border-2 h-100 submission-type-option" data-type="file" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <input type="radio" name="tipe_submission" id="tipe_file" value="file" 
                                               class="form-check-input" 
                                               <?php echo (!$submission || ($submission['tipe_submission'] ?? 'file') === 'file') ? 'checked' : ''; ?>>
                                        <label for="tipe_file" class="form-check-label w-100" style="cursor: pointer;">
                                            <i class="fas fa-file-upload fa-2x text-primary mb-2"></i>
                                            <h6 class="mb-1">Upload File</h6>
                                            <small class="text-muted">Upload file jawaban</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-2 h-100 submission-type-option" data-type="text" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <input type="radio" name="tipe_submission" id="tipe_text" value="text" 
                                               class="form-check-input"
                                               <?php echo ($submission && ($submission['tipe_submission'] ?? '') === 'text') ? 'checked' : ''; ?>>
                                        <label for="tipe_text" class="form-check-label w-100" style="cursor: pointer;">
                                            <i class="fas fa-keyboard fa-2x text-success mb-2"></i>
                                            <h6 class="mb-1">Kerjakan Langsung</h6>
                                            <small class="text-muted">Tulis jawaban langsung di sistem</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-2 h-100 submission-type-option" data-type="both" style="cursor: pointer;">
                                    <div class="card-body text-center">
                                        <input type="radio" name="tipe_submission" id="tipe_both" value="both" 
                                               class="form-check-input"
                                               <?php echo ($submission && ($submission['tipe_submission'] ?? '') === 'both') ? 'checked' : ''; ?>>
                                        <label for="tipe_both" class="form-check-label w-100" style="cursor: pointer;">
                                            <i class="fas fa-file-alt fa-2x text-info mb-2"></i>
                                            <h6 class="mb-1">Kedua-duanya</h6>
                                            <small class="text-muted">Upload file dan tulis jawaban</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Upload Section -->
                    <div class="mb-3" id="file-upload-section">
                        <label for="files" class="form-label">
                            Upload File 
                            <?php if (!empty($submission_files)): ?>
                                <span class="badge bg-success"><?php echo count($submission_files); ?> file</span>
                            <?php else: ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <input type="file" class="form-control" id="files" name="files[]" multiple>
                        <small class="text-muted">
                            Maksimal <?php echo $tugas['max_files']; ?> file. 
                            Ukuran maksimal: <?php echo format_file_size($tugas['max_file_size']); ?> per file.
                            Ekstensi: <?php echo escape($tugas['allowed_extensions']); ?>
                        </small>
                        <?php if (!empty($submission_files)): ?>
                        <div class="mt-2">
                            <div class="alert alert-info">
                                <strong>File saat ini:</strong>
                                <ul class="list-unstyled mb-0 mt-2">
                                    <?php foreach ($submission_files as $file): ?>
                                    <li>
                                        <i class="fas fa-file"></i> 
                                        <a href="<?php echo asset_url('uploads/pr/' . $file['file_path']); ?>" target="_blank" class="fw-bold">
                                            <?php echo escape($file['nama_file']); ?>
                                        </a>
                                        (<?php echo format_file_size($file['file_size']); ?>)
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="text-muted d-block mt-2">Upload file baru akan mengganti file yang sudah ada</small>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Text Answer Section -->
                    <div class="mb-3" id="text-answer-section">
                        <label for="jawaban_text" class="form-label">
                            Jawaban 
                            <?php if ($submission && $submission['jawaban_text']): ?>
                                <span class="badge bg-success">Sudah ada jawaban</span>
                            <?php else: ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div id="editor-wrapper">
                            <textarea class="form-control" id="jawaban_text" name="jawaban_text" rows="10" style="min-height: 400px;"><?php echo htmlspecialchars($submission['jawaban_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <small class="text-muted">Tulis jawaban Anda di sini. Anda dapat menggunakan formatting seperti bold, italic, list, dll.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="komentar" class="form-label">Komentar (opsional)</label>
                        <textarea class="form-control" id="komentar" name="komentar" rows="4"><?php echo escape($submission['komentar'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> <?php echo $submission ? 'Update' : 'Submit'; ?> Tugas
                        </button>
                        <a href="<?php echo base_url('siswa/tugas/list.php'); ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <?php if ($submission): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Status Submission</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge bg-<?php 
                                echo $submission['status'] === 'dinilai' ? 'success' : 
                                    ($submission['status'] === 'sudah_dikumpulkan' ? 'info' : 'warning'); 
                            ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $submission['status'])); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ($submission['waktu_submit']): ?>
                    <tr>
                        <th>Waktu Submit</th>
                        <td><?php echo format_date($submission['waktu_submit']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission['nilai'] !== null): ?>
                    <tr>
                        <th>Nilai</th>
                        <td><strong><?php echo number_format($submission['nilai'], 2); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission['feedback']): ?>
                    <tr>
                        <th>Feedback</th>
                        <td><?php echo nl2br(escape($submission['feedback'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission && ($submission['tipe_submission'] ?? '')): ?>
                    <tr>
                        <th>Tipe Submission</th>
                        <td>
                            <span class="badge bg-info">
                                <?php 
                                echo $submission['tipe_submission'] === 'file' ? 'File' : 
                                    ($submission['tipe_submission'] === 'text' ? 'Text' : 'File & Text'); 
                                ?>
                            </span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($submission && $submission['jawaban_text']): ?>
                    <tr>
                        <th>Jawaban Text</th>
                        <td>
                            <div class="border p-3 bg-light rounded" style="max-height: 300px; overflow-y: auto;">
                                <?php echo $submission['jawaban_text']; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Include Quill Editor -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<script>
// Initialize Quill Editor
let quill = null;

// Handle submission type change
function updateSubmissionSections() {
    const selectedType = document.querySelector('input[name="tipe_submission"]:checked').value;
    const fileSection = document.getElementById('file-upload-section');
    const textSection = document.getElementById('text-answer-section');
    const fileInput = document.getElementById('files');
    
    // Update card borders
    document.querySelectorAll('.submission-type-option').forEach(card => {
        card.classList.remove('border-primary');
        card.style.borderWidth = '';
        card.style.borderColor = '';
    });
    const selectedCard = document.querySelector(`.submission-type-option[data-type="${selectedType}"]`);
    if (selectedCard) {
        selectedCard.classList.add('border-primary');
        selectedCard.style.borderWidth = '3px';
    }
    
    if (selectedType === 'file') {
        fileSection.style.display = 'block';
        textSection.style.display = 'none';
        fileInput.removeAttribute('required');
        <?php if (!empty($submission_files)): ?>
        fileInput.setAttribute('data-has-files', '1');
        <?php endif; ?>
    } else if (selectedType === 'text') {
        fileSection.style.display = 'none';
        textSection.style.display = 'block';
        fileInput.removeAttribute('required');
    } else if (selectedType === 'both') {
        fileSection.style.display = 'block';
        textSection.style.display = 'block';
        fileInput.removeAttribute('required');
    }
}

// Initialize Quill editor
function initQuillEditor() {
    const textSection = document.getElementById('text-answer-section');
    const textarea = document.getElementById('jawaban_text');
    
    if (textSection && textSection.style.display !== 'none' && !quill) {
        // Create editor container
        const editorContainer = document.createElement('div');
        editorContainer.id = 'editor-container';
        editorContainer.style.height = '400px';
        editorContainer.style.marginBottom = '10px';
        
        // Hide textarea and insert editor
        textarea.style.display = 'none';
        textarea.parentNode.insertBefore(editorContainer, textarea);
        
        // Initialize Quill
        quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'align': [] }],
                    ['link', 'image'],
                    ['clean']
                ]
            },
            placeholder: 'Tulis jawaban Anda di sini...'
        });
        
        // Set initial content if exists
        <?php if ($submission && $submission['jawaban_text']): ?>
        quill.root.innerHTML = <?php echo json_encode($submission['jawaban_text']); ?>;
        <?php endif; ?>
        
        // Update textarea on text change
        quill.on('text-change', function() {
            textarea.value = quill.root.innerHTML;
        });
        
        // Set initial value
        textarea.value = quill.root.innerHTML;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updateSubmissionSections();
    initQuillEditor();
    
    // Add event listeners to radio buttons
    document.querySelectorAll('input[name="tipe_submission"]').forEach(radio => {
        radio.addEventListener('change', function() {
            updateSubmissionSections();
            // Reinitialize editor if text section is shown
            setTimeout(function() {
                if (document.getElementById('text-answer-section').style.display !== 'none') {
                    if (!quill) {
                        initQuillEditor();
                    }
                } else {
                    // Destroy editor if hidden
                    if (quill) {
                        const editorContainer = document.getElementById('editor-container');
                        if (editorContainer) {
                            editorContainer.remove();
                            document.getElementById('jawaban_text').style.display = 'block';
                            quill = null;
                        }
                    }
                }
            }, 100);
        });
    });
    
    // Form validation
    document.getElementById('submitForm').addEventListener('submit', function(e) {
        const selectedType = document.querySelector('input[name="tipe_submission"]:checked').value;
        const fileInput = document.getElementById('files');
        const textarea = document.getElementById('jawaban_text');
        let textContent = '';
        
        if (quill) {
            textContent = quill.root.innerHTML;
            textarea.value = textContent; // Ensure textarea is updated
        } else {
            textContent = textarea.value;
        }
        
        let hasError = false;
        
        if (selectedType === 'file' || selectedType === 'both') {
            if (!fileInput.files.length && !fileInput.hasAttribute('data-has-files')) {
                alert('Harap upload file jawaban');
                hasError = true;
            }
        }
        
        if (selectedType === 'text' || selectedType === 'both') {
            const plainText = textContent.replace(/<[^>]*>/g, '').trim();
            if (!plainText || plainText === '') {
                alert('Harap isi jawaban');
                hasError = true;
            }
        }
        
        if (hasError) {
            e.preventDefault();
            return false;
        }
    });
});
</script>

<style>
.submission-type-option {
    transition: all 0.3s ease;
}
.submission-type-option:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.submission-type-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}
.submission-type-option.border-primary {
    border-color: #0d6efd !important;
    background-color: #f0f7ff;
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



