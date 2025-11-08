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
    
    if (empty($judul) || !$id_mapel || empty($deadline) || empty($kelas_ids)) {
        $error = 'Judul, mata pelajaran, deadline, dan kelas harus diisi';
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
                                    'D' => sanitize($soal['opsi_d'] ?? ''),
                                    'E' => sanitize($soal['opsi_e'] ?? '')
                                ];
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
            $error = 'Terjadi kesalahan saat membuat PR';
        }
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
        <i class="fas fa-exclamation-triangle"></i> Anda belum di-assign ke mata pelajaran.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul PR <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" required>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4"></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_mapel" name="id_mapel" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id']; ?>">
                                <?php echo escape($mapel['nama_mapel']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="tipe_pr" class="form-label">Tipe PR <span class="text-danger">*</span></label>
                    <select class="form-select" id="tipe_pr" name="tipe_pr" required onchange="toggleTipeFields()">
                        <option value="file_upload">File Upload (Upload file jawaban)</option>
                        <option value="online">Online (Dikerjakan langsung di sistem)</option>
                        <option value="hybrid">Hybrid (Online + Upload file)</option>
                    </select>
                    <small class="text-muted">Pilih tipe PR yang ingin dibuat</small>
                </div>
                
                <div class="mb-3">
                    <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                </div>
                
                <div id="timer_settings" style="display:none;">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="timer_enabled" name="timer_enabled" onchange="toggleTimerFields()">
                            <label class="form-check-label" for="timer_enabled">
                                Aktifkan Timer
                            </label>
                        </div>
                    </div>
                    <div id="timer_fields" style="display:none;">
                        <div class="mb-3">
                            <label for="timer_minutes" class="form-label">Durasi Timer (menit)</label>
                            <input type="number" class="form-control" id="timer_minutes" name="timer_minutes" min="1" placeholder="Contoh: 60">
                        </div>
                    </div>
                </div>
                
                <div id="pr_settings" style="display:none;">
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_edit_after_submit" name="allow_edit_after_submit" checked>
                            <label class="form-check-label" for="allow_edit_after_submit">
                                Izinkan edit setelah submit (sebelum deadline)
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="max_attempts" class="form-label">Maksimal Percobaan (kosongkan untuk unlimited)</label>
                        <input type="number" class="form-control" id="max_attempts" name="max_attempts" min="1" placeholder="Contoh: 3">
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
