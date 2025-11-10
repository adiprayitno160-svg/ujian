<?php
/**
 * Submit PR - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Kumpulkan PR';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ?");
$stmt->execute([$pr_id]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('siswa/pr/list.php');
}

// Check if student is in assigned class
$stmt = $pdo->prepare("SELECT * FROM pr_kelas pk
                      INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                      WHERE pk.id_pr = ? AND uk.id_user = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$is_assigned = $stmt->fetch();

if (!$is_assigned) {
    redirect('siswa/pr/list.php');
}

// Get existing submission
$stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $komentar = sanitize($_POST['komentar'] ?? '');
    $tipe_submission = sanitize($_POST['tipe_submission'] ?? 'file');
    $jawaban_text = isset($_POST['jawaban_text']) ? $_POST['jawaban_text'] : ''; // Allow HTML from editor
    
    // Check deadline
    $deadline = new DateTime($pr['deadline']);
    $now = new DateTime();
    
    if ($now > $deadline && !$submission) {
        $error = 'Deadline sudah lewat';
    } else {
        try {
            $file_jawaban = null;
            $has_file = false;
            $has_text = false;
            
            // Handle file upload (if file type is selected)
            if ($tipe_submission === 'file' || $tipe_submission === 'both') {
                if (isset($_FILES['file_jawaban']) && $_FILES['file_jawaban']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_file($_FILES['file_jawaban'], UPLOAD_PR, ALLOWED_DOC_TYPES);
                    if ($upload_result['success']) {
                        $file_jawaban = $upload_result['filename'];
                        $has_file = true;
                    } else {
                        $error = $upload_result['message'];
                    }
                } elseif (!$submission || ($submission && !$submission['file_jawaban'])) {
                    // Only require file if it's file type and no existing submission
                    if ($tipe_submission === 'file') {
                        $error = 'File jawaban harus diupload';
                    }
                } else {
                    // Keep existing file if updating
                    $file_jawaban = $submission['file_jawaban'];
                    $has_file = true;
                }
            } elseif ($submission && $submission['file_jawaban']) {
                // Keep existing file if switching to text-only
                $file_jawaban = $submission['file_jawaban'];
                $has_file = true;
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
                $error = 'Harus mengupload file atau mengisi jawaban';
            }
            
            if (!$error) {
                if ($submission) {
                    // Update
                    if ($file_jawaban && $file_jawaban !== $submission['file_jawaban']) {
                        // Delete old file if new one is uploaded
                        if ($submission['file_jawaban']) {
                            $old_file = UPLOAD_PR . '/' . $submission['file_jawaban'];
                            if (file_exists($old_file)) {
                                unlink($old_file);
                            }
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE pr_submission SET 
                                          file_jawaban = ?, jawaban_text = ?, komentar = ?, tipe_submission = ?, 
                                          waktu_submit = NOW(), status = 'sudah_dikumpulkan'
                                          WHERE id = ?");
                    $stmt->execute([
                        $file_jawaban, 
                        $jawaban_text, 
                        $komentar, 
                        $tipe_submission, 
                        $submission['id']
                    ]);
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO pr_submission 
                                          (id_pr, id_siswa, file_jawaban, jawaban_text, komentar, tipe_submission, status, waktu_submit) 
                                          VALUES (?, ?, ?, ?, ?, ?, 'sudah_dikumpulkan', NOW())");
                    $stmt->execute([
                        $pr_id, 
                        $_SESSION['user_id'], 
                        $file_jawaban, 
                        $jawaban_text, 
                        $komentar, 
                        $tipe_submission
                    ]);
                }
                
                $success = 'PR berhasil dikumpulkan';
                log_activity('submit_pr', 'pr_submission', $pr_id);
                
                // Refresh
                $stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
                $stmt->execute([$pr_id, $_SESSION['user_id']]);
                $submission = $stmt->fetch();
            }
        } catch (PDOException $e) {
            error_log("Submit PR error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat mengumpulkan PR';
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Kumpulkan PR</h2>
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
        <h4><?php echo escape($pr['judul']); ?></h4>
        <p class="text-muted"><?php echo escape($pr['nama_mapel']); ?></p>
        <table class="table table-borderless">
            <tr>
                <th width="150">Deadline</th>
                <td>
                    <?php 
                    $deadline = new DateTime($pr['deadline']);
                    $now = new DateTime();
                    $class = $now > $deadline ? 'text-danger' : '';
                    ?>
                    <span class="<?php echo $class; ?>">
                        <?php echo format_date($pr['deadline']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Deskripsi</th>
                <td><?php echo nl2br(escape($pr['deskripsi'] ?? '-')); ?></td>
            </tr>
            <?php if ($pr['file_lampiran']): ?>
            <tr>
                <th>File Lampiran</th>
                <td>
                    <a href="<?php echo asset_url('uploads/pr/' . $pr['file_lampiran']); ?>" 
                       target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> Download
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php if ($submission): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Status Submission</h5>
    </div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="150">Status</th>
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
            <?php if ($submission && $submission['tipe_submission']): ?>
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
            <?php if ($submission && $submission['file_jawaban']): ?>
            <tr>
                <th>File Jawaban</th>
                <td>
                    <a href="<?php echo asset_url('uploads/pr/' . $submission['file_jawaban']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download"></i> <?php echo escape($submission['file_jawaban']); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
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
                                    <small class="text-muted">Upload file jawaban (PDF, DOC, DOCX, ZIP)</small>
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
                <label for="file_jawaban" class="form-label">
                    File Jawaban 
                    <?php if ($submission && $submission['file_jawaban']): ?>
                        <span class="badge bg-success">Sudah ada file</span>
                    <?php else: ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <input type="file" class="form-control" id="file_jawaban" name="file_jawaban" 
                       accept=".pdf,.doc,.docx,.zip">
                <small class="text-muted">Format: PDF, DOC, DOCX, ZIP. Max: 10MB</small>
                <?php if ($submission && $submission['file_jawaban']): ?>
                <div class="mt-2">
                    <div class="alert alert-info">
                        <i class="fas fa-file"></i> File saat ini: 
                        <a href="<?php echo asset_url('uploads/pr/' . $submission['file_jawaban']); ?>" target="_blank" class="fw-bold">
                            <?php echo escape($submission['file_jawaban']); ?>
                        </a>
                        <br><small class="text-muted">Upload file baru untuk mengganti file yang sudah ada</small>
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
                <textarea class="form-control" id="komentar" name="komentar" rows="3"><?php echo escape($submission['komentar'] ?? ''); ?></textarea>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> <?php echo $submission ? 'Update' : 'Kumpulkan'; ?>
                </button>
                <a href="<?php echo base_url('siswa/pr/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
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
    const fileInput = document.getElementById('file_jawaban');
    const textEditor = document.getElementById('jawaban_text');
    
    // Update card borders
    document.querySelectorAll('.submission-type-option').forEach(card => {
        card.classList.remove('border-primary');
        card.style.borderColor = '';
    });
    document.querySelector(`.submission-type-option[data-type="${selectedType}"]`).classList.add('border-primary');
    document.querySelector(`.submission-type-option[data-type="${selectedType}"]`).style.borderWidth = '3px';
    
    if (selectedType === 'file') {
        fileSection.style.display = 'block';
        textSection.style.display = 'none';
        fileInput.removeAttribute('required');
        if (!fileInput.hasAttribute('data-has-file')) {
            fileInput.setAttribute('required', 'required');
        }
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
    
    if (textSection && textSection.style.display !== 'none') {
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
    
    // Check if file exists for required attribute
    <?php if ($submission && $submission['file_jawaban']): ?>
    document.getElementById('file_jawaban').setAttribute('data-has-file', '1');
    <?php endif; ?>
    
    // Form validation
    document.getElementById('submitForm').addEventListener('submit', function(e) {
        const selectedType = document.querySelector('input[name="tipe_submission"]:checked').value;
        const fileInput = document.getElementById('file_jawaban');
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
            if (!fileInput.files.length && !fileInput.hasAttribute('data-has-file')) {
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

