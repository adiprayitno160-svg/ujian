<?php
/**
 * Create PR - Guru
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

// Process POST request BEFORE including header to avoid headers already sent error
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    $tipe_pr = sanitize($_POST['tipe_pr'] ?? 'file_upload');
    $timer_enabled = isset($_POST['timer_enabled']) ? 1 : 0;
    $timer_minutes = !empty($_POST['timer_minutes']) ? intval($_POST['timer_minutes']) : null;
    $allow_edit_after_submit = isset($_POST['allow_edit_after_submit']) ? 1 : 0;
    $max_attempts = !empty($_POST['max_attempts']) ? intval($_POST['max_attempts']) : null;
    
    // Validasi input
    $validation_errors = [];
    if (empty($judul)) {
        $validation_errors[] = 'Judul PR harus diisi';
    }
    if (!$id_mapel) {
        $validation_errors[] = 'Mata pelajaran harus dipilih';
    }
    if (empty($deadline)) {
        $validation_errors[] = 'Deadline harus diisi';
    }
    if (empty($kelas_ids) || !is_array($kelas_ids) || count($kelas_ids) == 0) {
        $validation_errors[] = 'Minimal satu kelas harus dipilih';
    }
    
    if (!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        // Validasi: Guru hanya bisa membuat PR untuk mata pelajaran yang dia ajar
        // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
        if (!guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
            $error = 'Anda tidak diizinkan membuat PR untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Handle file upload
                $file_lampiran = null;
                if (isset($_FILES['file_lampiran']) && $_FILES['file_lampiran']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = upload_file($_FILES['file_lampiran'], UPLOAD_PR, ALLOWED_DOC_TYPES);
                    if ($upload_result['success']) {
                        $file_lampiran = $upload_result['filename'];
                    }
                }
                
                // Insert PR
                $stmt = $pdo->prepare("INSERT INTO pr 
                                      (judul, deskripsi, id_mapel, id_guru, deadline, file_lampiran, 
                                       tipe_pr, timer_enabled, timer_minutes, allow_edit_after_submit, max_attempts) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $deadline, $file_lampiran,
                              $tipe_pr, $timer_enabled, $timer_minutes, $allow_edit_after_submit, $max_attempts]);
                $pr_id = $pdo->lastInsertId();
                
                // Assign to kelas
                foreach ($kelas_ids as $kelas_id) {
                    $kelas_id = intval($kelas_id);
                    $stmt = $pdo->prepare("INSERT INTO pr_kelas (id_pr, id_kelas) VALUES (?, ?)");
                    $stmt->execute([$pr_id, $kelas_id]);
                }
                
                // Handle soal for online/hybrid PR
                if (in_array($tipe_pr, ['online', 'hybrid']) && isset($_POST['soal'])) {
                    $soal_data = json_decode($_POST['soal'], true);
                    if (is_array($soal_data)) {
                        foreach ($soal_data as $idx => $soal) {
                            $pertanyaan = sanitize($soal['pertanyaan'] ?? '');
                            $tipe_soal = sanitize($soal['tipe_soal'] ?? '');
                            $bobot = floatval($soal['bobot'] ?? 1.0);
                            $kunci_jawaban = sanitize($soal['kunci_jawaban'] ?? '');
                            
                            if (!empty($pertanyaan) && !empty($tipe_soal)) {
                                $opsi_json = null;
                                if ($tipe_soal === 'pilihan_ganda') {
                                    $opsi = [
                                        'A' => sanitize($soal['opsi_a'] ?? ''),
                                        'B' => sanitize($soal['opsi_b'] ?? ''),
                                        'C' => sanitize($soal['opsi_c'] ?? ''),
                                        'D' => sanitize($soal['opsi_d'] ?? '')
                                    ];
                                    // Remove empty options
                                    $opsi = array_filter($opsi, function($value) {
                                        return !empty($value);
                                    });
                                    $opsi_json = json_encode($opsi);
                                } elseif ($tipe_soal === 'benar_salah') {
                                    $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
                                }
                                
                                $stmt = $pdo->prepare("INSERT INTO pr_soal 
                                                      (id_pr, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$pr_id, $pertanyaan, $tipe_soal, $opsi_json, $kunci_jawaban, $bobot, $idx + 1]);
                                $soal_id = $pdo->lastInsertId();
                                
                                // Handle matching items
                                if ($tipe_soal === 'matching' && isset($soal['matching_items'])) {
                                    foreach ($soal['matching_items'] as $match_idx => $item) {
                                        if (!empty($item['kiri']) && !empty($item['kanan'])) {
                                            $stmt = $pdo->prepare("INSERT INTO pr_soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                                            $stmt->execute([$soal_id, sanitize($item['kiri']), sanitize($item['kanan']), $match_idx + 1]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                
                $pdo->commit();
                $success = 'PR berhasil dibuat';
                log_activity('create_pr', 'pr', $pr_id);
                
                // Redirect to add soal if online/hybrid, or to list
                if (in_array($tipe_pr, ['online', 'hybrid'])) {
                    header("Location: " . base_url('guru/pr/soal.php?id=' . $pr_id));
                } else {
                    header("Location: " . base_url('guru/pr/list.php'));
                }
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Create PR error: " . $e->getMessage());
                // Provide more specific error message
                if ($e->getCode() == 23000) {
                    $error = 'Terjadi kesalahan: Data duplikat atau constraint violation. Silakan coba lagi.';
                } else {
                    $error = 'Terjadi kesalahan saat membuat PR: ' . htmlspecialchars($e->getMessage());
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Create PR error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat membuat PR: ' . htmlspecialchars($e->getMessage());
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

$page_title = 'Buat PR Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat PR Baru</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
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
                    <label for="judul" class="form-label">Judul PR <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" value="<?php echo isset($_POST['judul']) ? escape($_POST['judul']) : ''; ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"><?php echo isset($_POST['deskripsi']) ? escape($_POST['deskripsi']) : ''; ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    
                    <?php if (count($mapel_list) == 1): ?>
                        <?php $single_mapel = $mapel_list[0]; ?>
                        <?php 
                        // Ensure we use the single mapel ID
                        $selected_mapel_id = $single_mapel['id'];
                        ?>
                        <!-- Jika hanya 1 mata pelajaran, tampilkan sebagai input readonly untuk visual clarity -->
                        <input type="text" class="form-control" value="<?php echo escape($single_mapel['nama_mapel']); ?>" readonly style="background-color: #e9ecef; cursor: not-allowed;">
                        <input type="hidden" name="id_mapel" id="id_mapel" value="<?php echo $single_mapel['id']; ?>">
                        <small class="text-muted d-block mt-1">
                            <i class="fas fa-info-circle"></i> 
                            Mata pelajaran yang Anda ampu: <strong><?php echo escape($single_mapel['nama_mapel']); ?></strong>
                        </small>
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
                            <?php 
                            $selected_mapel_id = isset($_POST['id_mapel']) ? intval($_POST['id_mapel']) : 0;
                            foreach ($mapel_list as $mapel): 
                            ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo $selected_mapel_id == $mapel['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="tipe_pr" class="form-label">Tipe PR <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipe_pr" name="tipe_pr" required onchange="toggleTipeFields()">
                        <?php 
                        $selected_tipe = isset($_POST['tipe_pr']) ? $_POST['tipe_pr'] : 'file_upload';
                        ?>
                        <option value="file_upload" <?php echo $selected_tipe == 'file_upload' ? 'selected' : ''; ?>>File Upload (Upload file jawaban)</option>
                        <option value="online" <?php echo $selected_tipe == 'online' ? 'selected' : ''; ?>>Online (Dikerjakan langsung di sistem)</option>
                        <option value="hybrid" <?php echo $selected_tipe == 'hybrid' ? 'selected' : ''; ?>>Hybrid (Online + Upload file)</option>
                    </select>
                    <small class="text-muted">Pilih tipe PR yang ingin dibuat</small>
                </div>
                
                <div class="mb-3">
                    <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" value="<?php echo isset($_POST['deadline']) ? escape($_POST['deadline']) : ''; ?>" required>
                </div>
                
                <div id="timer_settings" style="display:none;">
                    <div class="mb-3">
                        <div class="form-check">
                            <?php 
                            $timer_enabled_checked = isset($_POST['timer_enabled']) ? (int)$_POST['timer_enabled'] : 0;
                            ?>
                            <input class="form-check-input" type="checkbox" id="timer_enabled" name="timer_enabled" <?php echo $timer_enabled_checked ? 'checked' : ''; ?> onchange="toggleTimerFields()">
                            <label class="form-check-label" for="timer_enabled">
                                Aktifkan Timer
                            </label>
                        </div>
                    </div>
                    <div id="timer_fields" style="display:none;">
                        <div class="mb-3">
                            <label for="timer_minutes" class="form-label">Durasi Timer (menit)</label>
                            <input type="number" class="form-control" id="timer_minutes" name="timer_minutes" min="1" value="<?php echo isset($_POST['timer_minutes']) ? escape($_POST['timer_minutes']) : ''; ?>" placeholder="Contoh: 60">
                        </div>
                    </div>
                </div>
                
                <div id="pr_settings" style="display:none;">
                    <div class="mb-3">
                        <div class="form-check">
                            <?php 
                            $allow_edit = isset($_POST['allow_edit_after_submit']) ? (int)$_POST['allow_edit_after_submit'] : 1;
                            ?>
                            <input class="form-check-input" type="checkbox" id="allow_edit_after_submit" name="allow_edit_after_submit" <?php echo $allow_edit ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_edit_after_submit">
                                Izinkan edit setelah submit (sebelum deadline)
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_attempts" class="form-label">Maksimal Percobaan (kosongkan untuk unlimited)</label>
                        <input type="number" class="form-control" id="max_attempts" name="max_attempts" min="1" value="<?php echo isset($_POST['max_attempts']) ? escape($_POST['max_attempts']) : ''; ?>" placeholder="Contoh: 3">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="file_lampiran" class="form-label">File Lampiran (opsional)</label>
                    <input type="file" class="form-control" id="file_lampiran" name="file_lampiran" accept=".pdf,.doc,.docx,.zip">
                    <small class="text-muted">Format: PDF, DOC, DOCX, ZIP. Max: 10MB</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Assign ke Kelas <span class="text-danger">*</span></label>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                        <?php 
                        $selected_kelas_ids = isset($_POST['kelas_ids']) && is_array($_POST['kelas_ids']) ? array_map('intval', $_POST['kelas_ids']) : [];
                        foreach ($kelas_list as $kelas): 
                        ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                                   value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>"
                                   <?php echo in_array($kelas['id'], $selected_kelas_ids) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat PR
                    </button>
                    <a href="<?php echo base_url('guru/pr/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleTipeFields() {
    const tipePr = document.getElementById('tipe_pr').value;
    const timerSettings = document.getElementById('timer_settings');
    const prSettings = document.getElementById('pr_settings');
    
    if (tipePr === 'online' || tipePr === 'hybrid') {
        timerSettings.style.display = 'block';
        prSettings.style.display = 'block';
    } else {
        timerSettings.style.display = 'none';
        prSettings.style.display = 'none';
    }
}

function toggleTimerFields() {
    const timerEnabled = document.getElementById('timer_enabled').checked;
    const timerFields = document.getElementById('timer_fields');
    timerFields.style.display = timerEnabled ? 'block' : 'none';
}

// Initialize fields on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize based on current form state (in case of error redisplay)
    toggleTipeFields();
    
    // Check if timer_enabled checkbox exists and is checked, then show timer fields
    const timerEnabledCheckbox = document.getElementById('timer_enabled');
    if (timerEnabledCheckbox && timerEnabledCheckbox.checked) {
        toggleTimerFields();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
