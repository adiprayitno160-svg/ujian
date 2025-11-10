<?php
/**
 * Review Mode - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk review semua jawaban sebelum submit
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';
require_once __DIR__ . '/../../includes/security.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$sesi_id = intval($_GET['id'] ?? 0);

// Validate sesi_id
if ($sesi_id <= 0) {
    $_SESSION['error_message'] = 'ID sesi tidak valid';
    redirect('siswa-ujian-list');
}

$sesi = get_sesi($sesi_id);

if (!$sesi) {
    $_SESSION['error_message'] = 'Sesi ujian tidak ditemukan';
    redirect('siswa-ujian-list');
}

$ujian = get_ujian($sesi['id_ujian']);

if (!$ujian) {
    $_SESSION['error_message'] = 'Ujian tidak ditemukan';
    redirect('siswa-ujian-list');
}

// Check if already started
$stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
$nilai = $stmt->fetch();

if (!$nilai || $nilai['status'] === 'selesai') {
    redirect('siswa-ujian-list');
}

// Check if review mode is enabled
$show_review_mode = $ujian['show_review_mode'] ?? 1;
if (!$show_review_mode) {
    redirect('siswa/ujian/take.php?id=' . $sesi_id);
}

// Get soal - for review, always show in original order (not shuffled)
// This makes it easier for students to review
try {
    $stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY urutan ASC, id ASC");
    $stmt->execute([$sesi['id_ujian']]);
    $soal_list = $stmt->fetchAll();
    
    $total_soal = count($soal_list);
    
    if ($total_soal === 0) {
        $_SESSION['error_message'] = 'Tidak ada soal yang tersedia untuk ujian ini';
        redirect('siswa-ujian-list');
    }
} catch (PDOException $e) {
    error_log("Error fetching soal: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat memuat soal';
    redirect('siswa-ujian-list');
}

// Get saved answers
$stmt = $pdo->prepare("SELECT id_soal, jawaban, jawaban_json, COALESCE(is_ragu, 0) as is_ragu FROM jawaban_siswa 
                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
$saved_answers = [];
$ragu_soal = [];
foreach ($stmt->fetchAll() as $ans) {
    $saved_answers[$ans['id_soal']] = $ans;
    if (isset($ans['is_ragu']) && intval($ans['is_ragu']) == 1) {
        $ragu_soal[] = $ans['id_soal'];
    }
}

// Calculate statistics
$stats = [
    'total' => $total_soal,
    'answered' => 0,
    'unanswered' => 0,
    'ragu' => count($ragu_soal)
];

foreach ($soal_list as $soal) {
    $saved = $saved_answers[$soal['id']] ?? null;
    // Check if answered (for matching, check jawaban_json; for others, check jawaban)
    $is_answered = false;
    if ($saved) {
        if ($soal['tipe_soal'] === 'matching') {
            $jawaban_json = $saved['jawaban_json'] ?? null;
            if ($jawaban_json) {
                $jawaban_array = json_decode($jawaban_json, true);
                $is_answered = is_array($jawaban_array) && count(array_filter($jawaban_array, function($v) { return !empty($v); })) > 0;
            }
        } else {
            $is_answered = !empty($saved['jawaban']);
        }
    }
    
    if ($is_answered) {
        $stats['answered']++;
    } else {
        $stats['unanswered']++;
    }
}

// Calculate time
$waktu_mulai = new DateTime($nilai['waktu_mulai']);
$durasi_menit = $sesi['durasi'];
$waktu_selesai = clone $waktu_mulai;
$waktu_selesai->modify("+$durasi_menit minutes");
$now = new DateTime();
$sisa_waktu = max(0, $waktu_selesai->getTimestamp() - $now->getTimestamp());

// Get min_submit_minutes
$min_submit_minutes = $ujian['min_submit_minutes'] ?? DEFAULT_MIN_SUBMIT_MINUTES;
$elapsed_seconds = $now->getTimestamp() - $waktu_mulai->getTimestamp();
$elapsed_seconds = max(0, $elapsed_seconds);
$min_submit_seconds = $min_submit_minutes * 60;
$can_submit_now = $elapsed_seconds >= $min_submit_seconds;

$page_title = 'Review Jawaban';
$role_css = 'siswa';
$custom_js = ['auto_save', 'ragu_ragu', 'exam_security'];
$hide_navbar = true;
$fullscreen_exam = true;

include __DIR__ . '/../../includes/header.php';

// Close main tag if hide_navbar
if (isset($hide_navbar) && $hide_navbar) {
    echo '</main>';
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
        width: 100%;
        margin: 0;
        padding: 0;
        background: #f8f9fa;
    }
    
    body.hide-navbar {
        overflow: auto;
        margin: 0;
        padding: 0;
    }
    
    .review-wrapper {
        min-height: 100vh;
        padding: 20px;
        background: #f8f9fa;
    }
    
    .review-header {
        background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
        color: white;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .review-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        text-align: center;
    }
    
    .stat-card .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .stat-card.answered .stat-number {
        color: #28a745;
    }
    
    .stat-card.unanswered .stat-number {
        color: #dc3545;
    }
    
    .stat-card.ragu .stat-number {
        color: #ffc107;
    }
    
    .stat-card.total .stat-number {
        color: #0066cc;
    }
    
    .review-filters {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #dee2e6;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .filter-btn:hover {
        border-color: #0066cc;
        background: #e6f2ff;
    }
    
    .filter-btn.active {
        border-color: #0066cc;
        background: #0066cc;
        color: white;
    }
    
    .soal-review-list {
        display: grid;
        gap: 15px;
    }
    
    .soal-review-item {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        border-left: 4px solid #dee2e6;
        transition: all 0.2s;
    }
    
    .soal-review-item:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .soal-review-item.answered {
        border-left-color: #28a745;
    }
    
    .soal-review-item.unanswered {
        border-left-color: #dc3545;
    }
    
    .soal-review-item.ragu {
        border-left-color: #ffc107;
    }
    
    .soal-review-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .soal-review-number {
        font-weight: bold;
        font-size: 1.1rem;
        color: #0066cc;
    }
    
    .soal-review-status {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .status-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    .status-badge.answered {
        background: #d4edda;
        color: #155724;
    }
    
    .status-badge.unanswered {
        background: #f8d7da;
        color: #721c24;
    }
    
    .status-badge.ragu {
        background: #fff3cd;
        color: #856404;
    }
    
    .soal-review-content {
        margin-top: 10px;
    }
    
    .soal-review-preview {
        color: #6c757d;
        font-size: 0.9rem;
        margin-top: 8px;
        line-height: 1.6;
    }
    
    .soal-review-answer {
        margin-top: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
        font-size: 0.9rem;
    }
    
    .review-actions {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 20px;
        border-top: 2px solid #dee2e6;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    
    .review-actions-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    @media (max-width: 768px) {
        .review-stats {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .soal-review-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .review-actions-buttons {
            flex-direction: column;
        }
        
        .review-actions-buttons .btn {
            width: 100%;
        }
    }
</style>

<div class="review-wrapper">
    <div class="review-header">
        <h2 class="mb-2">
            <i class="fas fa-list-check"></i> Review Jawaban
        </h2>
        <p class="mb-0"><?php echo escape($ujian['judul']); ?> - <?php echo escape($ujian['nama_mapel']); ?></p>
        <div class="mt-2">
            <i class="fas fa-clock"></i> Sisa Waktu: <span id="timerDisplay">--:--</span>
        </div>
    </div>
    
    <div class="review-stats">
        <div class="stat-card total">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Soal</div>
        </div>
        <div class="stat-card answered">
            <div class="stat-number"><?php echo $stats['answered']; ?></div>
            <div class="stat-label">Terjawab</div>
        </div>
        <div class="stat-card unanswered">
            <div class="stat-number"><?php echo $stats['unanswered']; ?></div>
            <div class="stat-label">Belum Dijawab</div>
        </div>
        <div class="stat-card ragu">
            <div class="stat-number"><?php echo $stats['ragu']; ?></div>
            <div class="stat-label">Ragu-ragu</div>
        </div>
    </div>
    
    <div class="review-filters">
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all" onclick="filterSoal('all')">
                <i class="fas fa-list"></i> Semua (<?php echo $stats['total']; ?>)
            </button>
            <button class="filter-btn" data-filter="answered" onclick="filterSoal('answered')">
                <i class="fas fa-check-circle"></i> Terjawab (<?php echo $stats['answered']; ?>)
            </button>
            <button class="filter-btn" data-filter="unanswered" onclick="filterSoal('unanswered')">
                <i class="fas fa-times-circle"></i> Belum (<?php echo $stats['unanswered']; ?>)
            </button>
            <button class="filter-btn" data-filter="ragu" onclick="filterSoal('ragu')">
                <i class="fas fa-question-circle"></i> Ragu-ragu (<?php echo $stats['ragu']; ?>)
            </button>
        </div>
    </div>
    
    <div class="soal-review-list" id="soalReviewList">
        <?php 
        $shuffle_maps = [];
        foreach ($soal_list as $idx => $soal): 
            $soal_num = $idx + 1;
            $saved = $saved_answers[$soal['id']] ?? null;
            $is_answered = $saved && (!empty($saved['jawaban']) || !empty($saved['jawaban_json']));
            $is_ragu = $saved && isset($saved['is_ragu']) && intval($saved['is_ragu']) == 1;
            
            // Get shuffle map for this soal if acak_opsi enabled
            $shuffle_map = [];
            $reverse_shuffle_map = [];
            if ($ujian['acak_opsi'] && $soal['tipe_soal'] === 'pilihan_ganda') {
                $shuffle_key = 'shuffle_map_' . $sesi_id . '_' . $soal['id'] . '_' . $_SESSION['user_id'];
                if (isset($_SESSION[$shuffle_key])) {
                    $shuffle_map = $_SESSION[$shuffle_key];
                    $reverse_shuffle_map = array_flip($shuffle_map);
                }
            }
            $shuffle_maps[$soal['id']] = ['map' => $shuffle_map, 'reverse' => $reverse_shuffle_map];
            
            // Determine status class
            $status_class = 'unanswered';
            if ($is_answered) {
                $status_class = $is_ragu ? 'ragu' : 'answered';
            }
            
            // Get answer preview
            $answer_preview = '';
            if ($is_answered && $saved) {
                if ($soal['tipe_soal'] === 'pilihan_ganda') {
                    $original_answer = $saved['jawaban'] ?? '';
                    if (!empty($original_answer)) {
                        // Convert to shuffled position for display if needed
                        if (!empty($reverse_shuffle_map) && isset($reverse_shuffle_map[$original_answer])) {
                            $display_answer = $reverse_shuffle_map[$original_answer];
                        } else {
                            $display_answer = $original_answer;
                        }
                        $answer_preview = "Jawaban: <strong>{$display_answer}</strong>";
                    } else {
                        $answer_preview = "<span class='text-warning'>Jawaban kosong</span>";
                    }
                } elseif ($soal['tipe_soal'] === 'benar_salah') {
                    $jawaban = $saved['jawaban'] ?? '';
                    $answer_preview = !empty($jawaban) ? "Jawaban: <strong>" . escape($jawaban) . "</strong>" : "<span class='text-warning'>Jawaban kosong</span>";
                } elseif ($soal['tipe_soal'] === 'isian_singkat') {
                    $jawaban = $saved['jawaban'] ?? '';
                    if (!empty($jawaban)) {
                        $answer_preview = "Jawaban: <strong>" . escape(substr($jawaban, 0, 50)) . (strlen($jawaban) > 50 ? '...' : '') . "</strong>";
                    } else {
                        $answer_preview = "<span class='text-warning'>Jawaban kosong</span>";
                    }
                } elseif ($soal['tipe_soal'] === 'matching') {
                    $jawaban_json = $saved['jawaban_json'] ?? null;
                    if ($jawaban_json) {
                        $jawaban_array = json_decode($jawaban_json, true);
                        $matched_count = is_array($jawaban_array) ? count(array_filter($jawaban_array, function($v) { return !empty($v); })) : 0;
                        $answer_preview = "Jawaban: <strong>Matching ({$matched_count} item dipilih)</strong>";
                    } else {
                        $answer_preview = "<span class='text-warning'>Jawaban kosong</span>";
                    }
                } elseif ($soal['tipe_soal'] === 'esai') {
                    $jawaban = $saved['jawaban'] ?? '';
                    if (!empty($jawaban)) {
                        $answer_preview = "Jawaban: <strong>" . escape(substr($jawaban, 0, 50)) . (strlen($jawaban) > 50 ? '...' : '') . "</strong>";
                    } else {
                        $answer_preview = "<span class='text-warning'>Jawaban kosong</span>";
                    }
                }
            } else {
                $answer_preview = "<span class='text-danger'>Belum dijawab</span>";
            }
        ?>
        <div class="soal-review-item <?php echo $status_class; ?>" data-status="<?php echo $status_class; ?>" data-soal-id="<?php echo $soal['id']; ?>">
            <div class="soal-review-header">
                <div>
                    <span class="soal-review-number">Soal <?php echo $soal_num; ?></span>
                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></span>
                </div>
                <div class="soal-review-status">
                    <?php if ($is_answered): ?>
                        <span class="status-badge answered">
                            <i class="fas fa-check"></i> Terjawab
                        </span>
                    <?php else: ?>
                        <span class="status-badge unanswered">
                            <i class="fas fa-times"></i> Belum
                        </span>
                    <?php endif; ?>
                    <?php if ($is_ragu): ?>
                        <span class="status-badge ragu">
                            <i class="fas fa-question-circle"></i> Ragu-ragu
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="soal-review-content">
                <div class="soal-review-preview">
                    <?php echo escape(substr($soal['pertanyaan'], 0, 150)); ?><?php echo strlen($soal['pertanyaan']) > 150 ? '...' : ''; ?>
                </div>
                <div class="soal-review-answer">
                    <?php echo $answer_preview; ?>
                </div>
                <div class="mt-3">
                    <a href="<?php echo base_url('siswa/ujian/take.php?id=' . $sesi_id . '&soal=' . $soal_num); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Lihat/Edit Jawaban
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="review-actions">
        <div class="review-actions-buttons">
            <a href="<?php echo base_url('siswa/ujian/take.php?id=' . $sesi_id); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Soal
            </a>
            <button type="button" class="btn btn-success" id="submitBtn" <?php echo $can_submit_now ? '' : 'disabled'; ?> onclick="submitExam()">
                <i class="fas fa-check"></i> Selesai & Submit
            </button>
        </div>
        <?php if (!$can_submit_now): ?>
            <div class="alert alert-warning mt-3 mb-0">
                <i class="fas fa-info-circle"></i> 
                Anda harus menunggu minimal <?php echo $min_submit_minutes; ?> menit setelah mulai ujian sebelum bisa submit.
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
let sisaWaktu = <?php echo $sisa_waktu; ?>;
const sesiId = <?php echo $sesi_id; ?>;
const canSubmit = <?php echo $can_submit_now ? 'true' : 'false'; ?>;

// Timer
function updateTimer() {
    if (sisaWaktu <= 0) {
        submitExam();
        return;
    }
    
    const hours = Math.floor(sisaWaktu / 3600);
    const minutes = Math.floor((sisaWaktu % 3600) / 60);
    const seconds = sisaWaktu % 60;
    
    let display = '';
    if (hours > 0) {
        display = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    } else {
        display = `${minutes}:${String(seconds).padStart(2, '0')}`;
    }
    
    document.getElementById('timerDisplay').textContent = display;
    sisaWaktu--;
}

setInterval(updateTimer, 1000);
updateTimer();

// Filter soal
function filterSoal(filter) {
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-filter') === filter) {
            btn.classList.add('active');
        }
    });
    
    // Filter items
    const items = document.querySelectorAll('.soal-review-item');
    items.forEach(item => {
        if (filter === 'all') {
            item.style.display = 'block';
        } else {
            const status = item.getAttribute('data-status');
            if (filter === 'answered' && status === 'answered') {
                item.style.display = 'block';
            } else if (filter === 'unanswered' && status === 'unanswered') {
                item.style.display = 'block';
            } else if (filter === 'ragu' && status === 'ragu') {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        }
    });
}

// Submit exam
function submitExam() {
    if (!canSubmit) {
        alert('Anda harus menunggu minimal waktu yang ditentukan sebelum bisa submit.');
        return;
    }
    
    if (confirm('Apakah Anda yakin ingin menyelesaikan ujian? Pastikan semua jawaban sudah diperiksa.')) {
        window.location.href = '<?php echo base_url('siswa/ujian/submit.php'); ?>?sesi_id=<?php echo $sesi_id; ?>';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

