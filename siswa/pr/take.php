<?php
/**
 * Take PR Online - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * UNBK Style - One Question Per Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$current_soal = intval($_GET['soal'] ?? 1); // Current question number (1-based)

// Get PR
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ?");
$stmt->execute([$pr_id]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('siswa/pr/list.php');
}

// Check if PR is online or hybrid type
if (!in_array($pr['tipe_pr'], ['online', 'hybrid'])) {
    redirect('siswa/pr/submit.php?id=' . $pr_id);
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

// Check deadline
$deadline = new DateTime($pr['deadline']);
$now = new DateTime();
if ($now > $deadline && !$pr['allow_edit_after_submit']) {
    $_SESSION['error_message'] = 'Deadline sudah lewat';
    redirect('siswa/pr/list.php');
}

// Get or create submission
$stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) {
    $stmt = $pdo->prepare("INSERT INTO pr_submission (id_pr, id_siswa, status) VALUES (?, ?, 'draft')");
    $stmt->execute([$pr_id, $_SESSION['user_id']]);
    
    // Auto-absensi: create absensi record for PR
    create_absensi(null, $_SESSION['user_id'], $pr_id, 'hadir', 'auto', null);
    
    $stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
    $stmt->execute([$pr_id, $_SESSION['user_id']]);
    $submission = $stmt->fetch();
}

// Check max attempts
if ($pr['max_attempts'] && $submission['attempt_count'] >= $pr['max_attempts']) {
    $_SESSION['error_message'] = 'Anda sudah mencapai batas maksimal percobaan';
    redirect('siswa/pr/list.php');
}

// Get soal
$stmt = $pdo->prepare("SELECT * FROM pr_soal WHERE id_pr = ? ORDER BY urutan ASC, id ASC");
$stmt->execute([$pr_id]);
$soal_list = $stmt->fetchAll();

$total_soal = count($soal_list);
if ($total_soal == 0) {
    $_SESSION['error_message'] = 'PR ini belum memiliki soal';
    redirect('siswa/pr/list.php');
}

if ($current_soal < 1) $current_soal = 1;
if ($current_soal > $total_soal) $current_soal = $total_soal;

// Get saved answers
$stmt = $pdo->prepare("SELECT id_soal, jawaban, is_ragu, status FROM pr_jawaban 
                      WHERE id_pr = ? AND id_siswa = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$saved_answers = [];
$ragu_soal = [];
foreach ($stmt->fetchAll() as $ans) {
    $saved_answers[$ans['id_soal']] = $ans;
    if ($ans['is_ragu']) {
        $ragu_soal[] = $ans['id_soal'];
    }
}

// Get answer status for all questions
$answer_status = [];
foreach ($soal_list as $soal) {
    $answer_status[$soal['id']] = isset($saved_answers[$soal['id']]) && !empty($saved_answers[$soal['id']]['jawaban']);
}

// Calculate timer if enabled
$timer_seconds = null;
if ($pr['timer_enabled'] && $pr['timer_minutes']) {
    // Get start time from submission or current time
    $start_time = $submission['waktu_submit'] ? new DateTime($submission['waktu_submit']) : new DateTime();
    $end_time = clone $start_time;
    $end_time->modify("+{$pr['timer_minutes']} minutes");
    $timer_seconds = max(0, $end_time->getTimestamp() - $now->getTimestamp());
}

// Get current soal
$current_soal_data = $soal_list[$current_soal - 1] ?? null;
if (!$current_soal_data) {
    redirect('siswa/pr/list.php');
}

$page_title = 'Kerjakan PR';
$role_css = 'siswa';
$custom_js = ['auto_save_pr', 'ragu_ragu_pr'];
$hide_navbar = true; // Hide sidebar for fullscreen
include __DIR__ . '/../../includes/header.php';

$saved = $saved_answers[$current_soal_data['id']] ?? null;
$opsi = $current_soal_data['opsi_json'] ? json_decode($current_soal_data['opsi_json'], true) : [];

// Filter opsi hanya A-D (remove E and above)
if (is_array($opsi)) {
    $filtered_opsi = [];
    $allowed_keys = ['A', 'B', 'C', 'D'];
    foreach ($allowed_keys as $key) {
        if (isset($opsi[$key]) && !empty($opsi[$key])) {
            $filtered_opsi[$key] = $opsi[$key];
        }
    }
    $opsi = $filtered_opsi;
}
?>

<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html, body {
        height: 100%;
        overflow: hidden;
        background: #fff;
    }
    
    body {
        position: fixed;
        width: 100%;
        height: 100%;
    }
    
    .pr-wrapper {
        display: flex;
        height: 100vh;
        background: #f5f5f5;
    }
    
    .pr-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
    }
    
    .pr-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .pr-timer {
        background: rgba(255,255,255,0.2);
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 1.1em;
    }
    
    .pr-content {
        flex: 1;
        overflow-y: auto;
        padding: 30px;
    }
    
    .question-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .question-number {
        display: inline-block;
        width: 40px;
        height: 40px;
        line-height: 40px;
        text-align: center;
        background: #667eea;
        color: white;
        border-radius: 50%;
        font-weight: bold;
        margin-right: 15px;
    }
    
    .question-text {
        font-size: 1.1em;
        line-height: 1.8;
        margin: 20px 0;
        color: #333;
    }
    
    .options-container {
        margin-top: 20px;
    }
    
    .option-item {
        padding: 15px;
        margin: 10px 0;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
    }
    
    .option-item:hover {
        border-color: #667eea;
        background: #f0f4ff;
    }
    
    .option-item.selected {
        border-color: #667eea;
        background: #e8edff;
    }
    
    .pr-navigation {
        padding: 20px 30px;
        background: white;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .pr-sidebar {
        width: 300px;
        background: white;
        border-left: 1px solid #e0e0e0;
        padding: 20px;
        overflow-y: auto;
        display: none;
    }
    
    .pr-sidebar.active {
        display: block;
    }
    
    .soal-nav-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .soal-nav-item {
        width: 40px;
        height: 40px;
        line-height: 40px;
        text-align: center;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }
    
    .soal-nav-item:hover {
        border-color: #667eea;
    }
    
    .soal-nav-item.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    
    .soal-nav-item.answered {
        background: #d4edda;
        border-color: #28a745;
    }
    
    .soal-nav-item.ragu {
        background: #fff3cd;
        border-color: #ffc107;
    }
    
    .legend {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .legend-box {
        width: 20px;
        height: 20px;
        border: 2px solid;
        border-radius: 4px;
        margin-right: 10px;
    }
    
    .deadline-info {
        background: #fff3cd;
        padding: 10px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #ffc107;
    }
    
    .deadline-info.danger {
        background: #f8d7da;
        border-left-color: #dc3545;
    }
</style>

<div class="pr-wrapper">
    <button class="btn btn-primary position-fixed" style="top: 10px; left: 10px; z-index: 1000;" onclick="toggleSidebar()">
        <i class="fas fa-list" id="navToggleIcon"></i>
    </button>
    
    <div class="pr-main" id="prMain">
        <div class="pr-header">
            <div>
                <h4 class="mb-0"><?php echo escape($pr['judul']); ?></h4>
                <small><?php echo escape($pr['nama_mapel']); ?></small>
            </div>
            <?php if ($timer_seconds !== null): ?>
            <div class="pr-timer" id="prTimer">
                <i class="fas fa-clock me-2"></i>
                <span id="timerDisplay">--:--</span>
            </div>
            <?php else: ?>
            <div class="pr-timer">
                <i class="fas fa-calendar me-2"></i>
                Deadline: <?php echo format_date($pr['deadline']); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="pr-content">
            <?php 
            $deadline_class = $now > $deadline ? 'danger' : '';
            ?>
            <div class="deadline-info <?php echo $deadline_class; ?>">
                <i class="fas fa-info-circle"></i> 
                <strong>Deadline:</strong> <?php echo format_date($pr['deadline']); ?>
                <?php if ($now > $deadline): ?>
                    <span class="text-danger">(Sudah lewat)</span>
                <?php else: ?>
                    <?php 
                    $diff = $now->diff($deadline);
                    echo " - Sisa: " . $diff->format('%d hari %h jam %i menit');
                    ?>
                <?php endif; ?>
            </div>
            
            <form id="prForm" method="POST" action="<?php echo base_url('api/save_pr_answer.php'); ?>">
                <input type="hidden" name="pr_id" value="<?php echo $pr_id; ?>">
                <input type="hidden" name="soal_id" value="<?php echo $current_soal_data['id']; ?>">
                <input type="hidden" name="current_soal" value="<?php echo $current_soal; ?>">
                
                <div class="question-card">
                    <div class="d-flex align-items-center mb-3">
                        <span class="question-number"><?php echo $current_soal; ?></span>
                        <h5 class="mb-0">Soal Nomor <?php echo $current_soal; ?> dari <?php echo $total_soal; ?></h5>
                    </div>
                    
                    <div class="question-text">
                        <?php echo nl2br(escape($current_soal_data['pertanyaan'])); ?>
                    </div>
                    
                    <?php if (!empty($current_soal_data['gambar'])): ?>
                        <div class="question-media mt-3 mb-3">
                            <?php 
                            $media_url = UPLOAD_URL . '/soal/' . $current_soal_data['gambar'];
                            $media_type = $current_soal_data['media_type'] ?? 'gambar';
                            if ($media_type === 'gambar'): 
                            ?>
                                <img src="<?php echo $media_url; ?>" 
                                     alt="Media Soal" 
                                     class="img-fluid rounded shadow-sm" 
                                     style="max-width: 100%; max-height: 500px; cursor: pointer;"
                                     onclick="openMediaModal('<?php echo $media_url; ?>', 'gambar')">
                            <?php else: ?>
                                <video controls class="w-100 rounded shadow-sm" style="max-width: 100%; max-height: 500px;">
                                    <source src="<?php echo $media_url; ?>" type="video/mp4">
                                    Browser Anda tidak mendukung video tag.
                                </video>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($current_soal_data['tipe_soal'] === 'pilihan_ganda'): ?>
                        <div class="options-container">
                            <?php foreach ($opsi as $key => $value): 
                                $is_selected = $saved && $saved['jawaban'] === $key;
                                
                                // Handle both old format (string) and new format (object with text and image)
                                $option_text = '';
                                $option_image = null;
                                
                                if (is_array($value)) {
                                    // New format: object with text and image
                                    $option_text = $value['text'] ?? '';
                                    $option_image = $value['image'] ?? null;
                                } else {
                                    // Old format: just text (backward compatible)
                                    $option_text = $value;
                                }
                            ?>
                            <div class="option-item <?php echo $is_selected ? 'selected' : ''; ?>" 
                                 onclick="selectOption('<?php echo $key; ?>')">
                                <input type="radio" name="jawaban" id="opt_<?php echo $key; ?>" 
                                       value="<?php echo $key; ?>" 
                                       <?php echo $is_selected ? 'checked' : ''; ?>
                                       onchange="saveAnswer()">
                                <label for="opt_<?php echo $key; ?>" style="cursor: pointer; margin-left: 10px; flex: 1;">
                                    <strong><?php echo $key; ?>.</strong> 
                                    <?php if (!empty($option_text)): ?>
                                        <?php echo escape($option_text); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($option_image)): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo UPLOAD_URL . '/soal/' . escape($option_image); ?>" 
                                                 alt="Gambar Opsi <?php echo $key; ?>" 
                                                 class="img-thumbnail" 
                                                 style="max-width: 300px; max-height: 200px; cursor: pointer;"
                                                 onclick="event.stopPropagation(); openMediaModal('<?php echo UPLOAD_URL . '/soal/' . escape($option_image); ?>', 'gambar');">
                                        </div>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($current_soal_data['tipe_soal'] === 'benar_salah'): 
                        $is_benar = $saved && $saved['jawaban'] === 'Benar';
                        $is_salah = $saved && $saved['jawaban'] === 'Salah';
                    ?>
                        <div class="options-container">
                            <div class="option-item <?php echo $is_benar ? 'selected' : ''; ?>" onclick="selectOption('Benar')">
                                <input type="radio" name="jawaban" id="opt_benar" value="Benar"
                                       <?php echo $is_benar ? 'checked' : ''; ?>
                                       onchange="saveAnswer()">
                                <label for="opt_benar" style="cursor: pointer; margin-left: 10px; flex: 1;">Benar</label>
                            </div>
                            <div class="option-item <?php echo $is_salah ? 'selected' : ''; ?>" onclick="selectOption('Salah')">
                                <input type="radio" name="jawaban" id="opt_salah" value="Salah"
                                       <?php echo $is_salah ? 'checked' : ''; ?>
                                       onchange="saveAnswer()">
                                <label for="opt_salah" style="cursor: pointer; margin-left: 10px; flex: 1;">Salah</label>
                            </div>
                        </div>
                    <?php elseif ($current_soal_data['tipe_soal'] === 'isian_singkat'): ?>
                        <div class="mb-3">
                            <input type="text" class="form-control form-control-lg" name="jawaban" 
                                   value="<?php echo escape($saved['jawaban'] ?? ''); ?>" 
                                   placeholder="Masukkan jawaban singkat"
                                   onchange="saveAnswer()">
                        </div>
                    <?php elseif ($current_soal_data['tipe_soal'] === 'esai'): ?>
                        <div class="mb-3">
                            <textarea class="form-control" name="jawaban" rows="10"
                                      placeholder="Tulis jawaban Anda di sini..."
                                      onchange="saveAnswer()"><?php echo escape($saved['jawaban'] ?? ''); ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="pr-navigation">
            <button type="button" class="btn btn-outline-secondary" 
                    onclick="goToSoal(<?php echo $current_soal - 1; ?>)"
                    <?php echo $current_soal <= 1 ? 'disabled' : ''; ?>>
                <i class="fas fa-chevron-left"></i> Sebelumnya
            </button>
            
            <button type="button" class="btn btn-warning" id="btnRagu" 
                    onclick="toggleRagu()">
                <i class="fas fa-question-circle"></i> 
                <span id="raguText"><?php echo ($saved && $saved['is_ragu']) ? 'Batal Ragu-ragu' : 'Ragu-ragu'; ?></span>
            </button>
            
            <?php if ($current_soal < $total_soal): ?>
            <button type="button" class="btn btn-primary" 
                    onclick="goToSoal(<?php echo $current_soal + 1; ?>)">
                Selanjutnya <i class="fas fa-chevron-right"></i>
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-success" onclick="submitPR()">
                <i class="fas fa-check"></i> Submit PR
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="pr-sidebar" id="prSidebar">
        <h6 class="fw-bold mb-3">Navigasi Soal</h6>
        <div class="soal-nav-grid">
            <?php foreach ($soal_list as $idx => $soal): 
                $soal_num = $idx + 1;
                $is_answered = isset($answer_status[$soal['id']]) && $answer_status[$soal['id']];
                $is_ragu = isset($saved_answers[$soal['id']]) && $saved_answers[$soal['id']]['is_ragu'];
                $is_active = $soal_num == $current_soal;
                
                $classes = 'soal-nav-item';
                if ($is_active) $classes .= ' active';
                if ($is_answered) $classes .= ' answered';
                if ($is_ragu) $classes .= ' ragu';
            ?>
            <div class="<?php echo $classes; ?>" 
                 onclick="goToSoal(<?php echo $soal_num; ?>)"
                 title="Soal <?php echo $soal_num; ?>">
                <?php echo $soal_num; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="legend">
            <div class="legend-item">
                <div class="legend-box" style="background: #667eea; border-color: #667eea;"></div>
                <span>Sedang Dikerjakan</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background: #d4edda; border-color: #28a745;"></div>
                <span>Sudah Dijawab</span>
            </div>
            <div class="legend-item">
                <div class="legend-box" style="background: #fff3cd; border-color: #ffc107;"></div>
                <span>Ragu-ragu</span>
            </div>
            <div class="legend-item">
                <div class="legend-box"></div>
                <span>Belum Dijawab</span>
            </div>
        </div>
    </div>
</div>

<script>
let sidebarOpen = false;

function toggleSidebar() {
    sidebarOpen = !sidebarOpen;
    document.getElementById('prSidebar').classList.toggle('active', sidebarOpen);
}

function goToSoal(num) {
    if (num < 1 || num > <?php echo $total_soal; ?>) return;
    window.location.href = '<?php echo base_url("siswa/pr/take.php?id=" . $pr_id . "&soal="); ?>' + num;
}

function selectOption(value) {
    document.querySelector(`input[value="${value}"]`).checked = true;
    document.querySelectorAll('.option-item').forEach(item => {
        item.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    saveAnswer();
}

function saveAnswer() {
    const form = document.getElementById('prForm');
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    fetch('<?php echo base_url("api/save_pr_answer.php"); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            updateAnswerStatus();
        }
    }).catch(error => {
        console.error('Error:', error);
    });
}

function toggleRagu() {
    const form = document.getElementById('prForm');
    const formData = new FormData(form);
    formData.append('action', 'toggle_ragu');
    
    fetch('<?php echo base_url("api/save_pr_answer.php"); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            const btn = document.getElementById('btnRagu');
            const text = document.getElementById('raguText');
            if (data.is_ragu) {
                text.textContent = 'Batal Ragu-ragu';
                btn.classList.add('active');
            } else {
                text.textContent = 'Ragu-ragu';
                btn.classList.remove('active');
            }
        }
    });
}

function submitPR() {
    if (!confirm('Apakah Anda yakin ingin submit PR ini? Pastikan semua jawaban sudah diisi dengan benar.')) {
        return;
    }
    
    const form = document.getElementById('prForm');
    const formData = new FormData(form);
    formData.append('action', 'submit');
    
    fetch('<?php echo base_url("api/save_pr_answer.php"); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('PR berhasil disubmit!');
            window.location.href = '<?php echo base_url("siswa/pr/list.php"); ?>';
        } else {
            alert('Error: ' + (data.message || 'Terjadi kesalahan'));
        }
    }).catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat submit PR');
    });
}

function updateAnswerStatus() {
    // Reload page to update status
    // Or use AJAX to update sidebar
}

<?php if ($timer_seconds !== null): ?>
// Timer countdown
let timerSeconds = <?php echo $timer_seconds; ?>;
const timerDisplay = document.getElementById('timerDisplay');

function updateTimer() {
    if (timerSeconds <= 0) {
        alert('Waktu habis! PR akan otomatis disubmit.');
        submitPR();
        return;
    }
    
    const hours = Math.floor(timerSeconds / 3600);
    const minutes = Math.floor((timerSeconds % 3600) / 60);
    const seconds = timerSeconds % 60;
    
    timerDisplay.textContent = 
        String(hours).padStart(2, '0') + ':' +
        String(minutes).padStart(2, '0') + ':' +
        String(seconds).padStart(2, '0');
    
    timerSeconds--;
}

setInterval(updateTimer, 1000);
updateTimer();
<?php endif; ?>

// Auto-save every 30 seconds
setInterval(saveAnswer, 30000);

// Media modal for image viewing
function openMediaModal(url, type) {
    if (type === 'gambar') {
        const modal = document.createElement('div');
        modal.className = 'media-modal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; display: flex; align-items: center; justify-content: center; cursor: pointer;';
        modal.onclick = function() { document.body.removeChild(modal); };
        
        const img = document.createElement('img');
        img.src = url;
        img.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain;';
        img.onclick = function(e) { e.stopPropagation(); };
        
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; background: none; border: none; cursor: pointer; z-index: 10000;';
        closeBtn.onclick = function() { document.body.removeChild(modal); };
        
        modal.appendChild(img);
        modal.appendChild(closeBtn);
        document.body.appendChild(modal);
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

