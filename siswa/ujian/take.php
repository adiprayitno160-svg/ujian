<?php
/**
 * Take Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * UNBK Style - One Question Per Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$sesi_id = intval($_GET['id'] ?? 0);
$current_soal = intval($_GET['soal'] ?? 1); // Current question number (1-based)
$sesi = get_sesi($sesi_id);

if (!$sesi) {
    redirect('siswa/ujian/list.php');
}

// Check if waktu_mulai has arrived
$now = new DateTime();
$waktu_mulai = new DateTime($sesi['waktu_mulai']);
if ($now < $waktu_mulai) {
    $_SESSION['error_message'] = 'Ujian belum dimulai. Waktu mulai: ' . format_date($sesi['waktu_mulai']);
    redirect('siswa/ujian/list.php');
}

// Validate session
$validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
if (!$validation['valid']) {
    // Set error message and redirect or show error page
    $_SESSION['error_message'] = $validation['message'];
    redirect('siswa/ujian/list.php');
}

$ujian = get_ujian($sesi['id_ujian']);

// Check if already started
$stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
$nilai = $stmt->fetch();

if (!$nilai) {
    $stmt = $pdo->prepare("INSERT INTO nilai (id_sesi, id_ujian, id_siswa, status, waktu_mulai, device_info, ip_address) 
                          VALUES (?, ?, ?, 'sedang_mengerjakan', NOW(), ?, ?)");
    $stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id'], get_device_info(), get_client_ip()]);
    
    $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
    $stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
    $nilai = $stmt->fetch();
} elseif ($nilai['status'] === 'selesai') {
    redirect('siswa/ujian/hasil.php?id=' . $sesi_id);
}

// Get soal
$stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY " . ($ujian['acak_soal'] ? "RAND()" : "urutan ASC, id ASC"));
$stmt->execute([$sesi['id_ujian']]);
$soal_list = $stmt->fetchAll();

$total_soal = count($soal_list);
if ($current_soal < 1) $current_soal = 1;
if ($current_soal > $total_soal) $current_soal = $total_soal;

// Get saved answers
$stmt = $pdo->prepare("SELECT id_soal, jawaban, jawaban_json, is_ragu FROM jawaban_siswa 
                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
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

// Calculate time
$waktu_mulai = new DateTime($nilai['waktu_mulai']);
$durasi_menit = $sesi['durasi'];
$waktu_selesai = clone $waktu_mulai;
$waktu_selesai->modify("+$durasi_menit minutes");
$now = new DateTime();
$sisa_waktu = max(0, $waktu_selesai->getTimestamp() - $now->getTimestamp());

// Get current soal
$current_soal_data = $soal_list[$current_soal - 1] ?? null;
if (!$current_soal_data) {
    redirect('siswa/ujian/list.php');
}

// Check authentication (token + tanggal lahir) - before header
$tanggal_lahir_verified_key = 'tanggal_lahir_verified_' . $sesi_id;
$token_verified_key = 'token_verified_' . $sesi_id;
$need_auth = false;
$token_error = '';
$tanggal_lahir_error = '';

// Process authentication form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_auth'])) {
    $token_input = $_POST['token'] ?? '';
    $tanggal_lahir_input = $_POST['tanggal_lahir'] ?? '';
    $all_valid = true;
    
    // Validate token if required
    if ($sesi['token_required']) {
        if (empty($token_input)) {
            $token_error = 'Token harus diisi';
            $all_valid = false;
        } else {
            $stmt = $pdo->prepare("SELECT * FROM token_ujian 
                                  WHERE id_sesi = ? AND token = ? AND status = 'active' 
                                  AND expires_at > NOW()");
            $stmt->execute([$sesi_id, $token_input]);
            $token = $stmt->fetch();
            
            if (!$token) {
                $token_error = 'Token tidak valid atau sudah expired';
                $all_valid = false;
            } elseif ($token['max_usage'] && $token['current_usage'] >= $token['max_usage']) {
                $token_error = 'Token sudah mencapai batas penggunaan';
                $all_valid = false;
            } else {
                // Token valid - record usage
                $stmt = $pdo->prepare("INSERT INTO token_usage (id_token, id_user, ip_address, device_info) VALUES (?, ?, ?, ?)");
                $stmt->execute([$token['id'], $_SESSION['user_id'], get_client_ip(), get_device_info()]);
                
                $stmt = $pdo->prepare("UPDATE token_ujian SET current_usage = current_usage + 1 WHERE id = ?");
                $stmt->execute([$token['id']]);
                
                $_SESSION[$token_verified_key] = true;
            }
        }
    } else {
        // Token not required, mark as verified
        $_SESSION[$token_verified_key] = true;
    }
    
    // Validate tanggal lahir (always required)
    if (empty($tanggal_lahir_input)) {
        $tanggal_lahir_error = 'Tanggal lahir harus diisi';
        $all_valid = false;
    } else {
        // Get user data to verify tanggal lahir
        $stmt = $pdo->prepare("SELECT tanggal_lahir FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['tanggal_lahir'])) {
            // Compare tanggal lahir
            $db_tanggal_lahir = date('Y-m-d', strtotime($user['tanggal_lahir']));
            $input_tanggal_lahir = date('Y-m-d', strtotime($tanggal_lahir_input));
            
            if ($db_tanggal_lahir === $input_tanggal_lahir) {
                // Verified - save to session
                $_SESSION[$tanggal_lahir_verified_key] = true;
                $_SESSION[$tanggal_lahir_verified_key . '_time'] = time();
            } else {
                $tanggal_lahir_error = 'Tanggal lahir tidak sesuai';
                $all_valid = false;
            }
        } else {
            // If no tanggal lahir in database, accept the input and save to session
            // This allows first-time setup
            $_SESSION[$tanggal_lahir_verified_key] = true;
            $_SESSION[$tanggal_lahir_verified_key . '_time'] = time();
            $_SESSION[$tanggal_lahir_verified_key . '_value'] = $tanggal_lahir_input;
        }
    }
    
    // If all valid, redirect to reload page (to continue to exam)
    if ($all_valid && $_SESSION[$tanggal_lahir_verified_key] && $_SESSION[$token_verified_key]) {
        // Redirect to same page to continue
        redirect('siswa/ujian/take.php?id=' . $sesi_id);
    }
}

// Check if authentication is needed
$token_verified = isset($_SESSION[$token_verified_key]) && $_SESSION[$token_verified_key];
$tanggal_lahir_verified = isset($_SESSION[$tanggal_lahir_verified_key]) && $_SESSION[$tanggal_lahir_verified_key];

if ($sesi['token_required'] && !$token_verified) {
    $need_auth = true;
}
if (!$tanggal_lahir_verified) {
    $need_auth = true;
}

$page_title = 'Kerjakan Ujian';
$role_css = 'siswa';
$custom_js = ['auto_save', 'ragu_ragu', 'exam_security'];
$hide_navbar = true; // Hide sidebar for fullscreen exam
include __DIR__ . '/../../includes/header.php';

// Show authentication form (token + tanggal lahir) if needed
if ($need_auth) {
    ?>
    <style>
        body {
            background: linear-gradient(135deg, #e6f2ff 0%, #cce5ff 100%) !important;
            min-height: 100vh;
        }
        main.container {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh;
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100% !important;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 102, 204, 0.2);
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        .auth-header {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .auth-header h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }
        .auth-body {
            padding: 35px;
        }
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            display: block;
        }
        .form-control {
            border: 1px solid #dee2e6;
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
        }
        .form-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
            outline: none;
        }
        .btn-verify {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            border: none;
            color: white;
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .btn-verify:hover {
            background: linear-gradient(135deg, #0052a3 0%, #004080 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 102, 204, 0.4);
            color: white;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: none;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 15px;
        }
        .text-muted {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 5px;
            display: block;
        }
    </style>
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5">
            <div class="auth-card">
                <div class="auth-header">
                    <h3><i class="fas fa-shield-alt me-2"></i>Verifikasi Identitas</h3>
                    <p class="mb-0 mt-2" style="opacity: 0.95; font-size: 0.9rem;">
                        <?php if ($sesi['token_required']): ?>
                            Masukkan token dan tanggal lahir untuk melanjutkan
                        <?php else: ?>
                            Masukkan tanggal lahir Anda untuk melanjutkan ujian
                        <?php endif; ?>
                    </p>
                </div>
                <div class="auth-body">
                    <?php if ($token_error || $tanggal_lahir_error): ?>
                        <?php if ($token_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo escape($token_error); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($tanggal_lahir_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo escape($tanggal_lahir_error); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $sesi_id; ?>">
                        
                        <?php if ($sesi['token_required']): ?>
                        <div class="mb-4">
                            <label for="token" class="form-label">
                                <i class="fas fa-key me-1"></i> Token Ujian
                            </label>
                            <input type="text" 
                                   class="form-control text-center" 
                                   id="token" 
                                   name="token" 
                                   maxlength="6" 
                                   pattern="[0-9]{6}"
                                   placeholder="Masukkan 6 digit token"
                                   style="font-size: 1.2rem; letter-spacing: 0.5rem;"
                                   value="<?php echo isset($_POST['token']) ? escape($_POST['token']) : ''; ?>"
                                   required 
                                   autofocus>
                            <small class="text-muted">Token 6 digit yang diberikan oleh pengawas</small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <label for="tanggal_lahir" class="form-label">
                                <i class="fas fa-birthday-cake me-1"></i> Tanggal Lahir
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   id="tanggal_lahir" 
                                   name="tanggal_lahir" 
                                   value="<?php echo isset($_POST['tanggal_lahir']) ? escape($_POST['tanggal_lahir']) : ''; ?>"
                                   required
                                   <?php echo !$sesi['token_required'] ? 'autofocus' : ''; ?>>
                            <small class="text-muted">Masukkan tanggal lahir Anda (Format: DD/MM/YYYY)</small>
                        </div>
                        
                        <button type="submit" name="verify_auth" class="btn btn-verify">
                            <i class="fas fa-check-circle me-2"></i>Verifikasi & Lanjutkan
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

$saved = $saved_answers[$current_soal_data['id']] ?? null;
$opsi = $current_soal_data['opsi_json'] ? json_decode($current_soal_data['opsi_json'], true) : [];

// Shuffle opsi if enabled
if ($ujian['acak_opsi'] && is_array($opsi)) {
    $keys = array_keys($opsi);
    shuffle($keys);
    $shuffled_opsi = [];
    foreach ($keys as $key) {
        $shuffled_opsi[$key] = $opsi[$key];
    }
    $opsi = $shuffled_opsi;
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
    
    .exam-wrapper {
        display: flex;
        height: 100vh;
        width: 100vw;
        background: #fff;
        position: relative;
    }
    
    .exam-main {
        flex: 1;
        padding: 20px;
        max-width: 100%;
        margin: 0 auto;
        overflow-y: auto;
        transition: margin-right 0.3s ease;
    }
    
    .exam-main.sidebar-open {
        margin-right: 300px;
    }
    
    .exam-sidebar {
        width: 300px;
        background: #f8f9fa;
        border-left: 1px solid #dee2e6;
        padding: 20px;
        position: fixed;
        right: -300px;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        transition: right 0.3s ease;
        z-index: 1000;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
    }
    
    .exam-sidebar.show {
        right: 0;
    }
    
    .exam-header {
        background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
        color: white;
        padding: 15px 20px;
        margin: -20px -20px 20px -20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .exam-timer {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: bold;
        font-size: 1.1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }
    
    .exam-timer.danger {
        background: #dc3545;
        border-color: #dc3545;
        animation: pulse 1s infinite;
    }
    
    .exam-timer.warning {
        background: #ffc107;
        color: #000;
        border-color: #ffc107;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .nav-toggle-btn {
        position: fixed;
        right: 20px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1001;
        background: #0066cc;
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: all 0.3s ease;
    }
    
    .nav-toggle-btn:hover {
        background: #0052a3;
        transform: translateY(-50%) scale(1.1);
    }
    
    .nav-toggle-btn.sidebar-open {
        right: 320px;
    }
    
    .question-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 20px;
    }
    
    .question-number {
        display: inline-block;
        width: 40px;
        height: 40px;
        background: #0066cc;
        color: white;
        border-radius: 50%;
        text-align: center;
        line-height: 40px;
        font-weight: bold;
        margin-right: 15px;
    }
    
    .question-text {
        font-size: 1.1rem;
        line-height: 1.6;
        margin: 20px 0;
    }
    
    .option-item {
        padding: 12px;
        margin: 8px 0;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }
    
    .option-item:hover {
        border-color: #0066cc;
        background: #e6f2ff;
    }
    
    .option-item.selected {
        border-color: #0066cc;
        background: #e6f2ff;
    }
    
    .exam-navigation {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: white;
        border-top: 1px solid #dee2e6;
        position: sticky;
        bottom: 0;
        margin: 20px -20px -20px -20px;
        z-index: 99;
    }
    
    .submit-info {
        background: #fff3cd;
        border: 1px solid #ffc107;
        color: #856404;
        padding: 10px 15px;
        border-radius: 6px;
        margin-bottom: 15px;
        text-align: center;
        font-size: 0.9rem;
    }
    
    .submit-info.hidden {
        display: none;
    }
    
    .soal-nav-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
        margin-top: 15px;
    }
    
    .soal-nav-item {
        width: 100%;
        aspect-ratio: 1;
        border: 2px solid #dee2e6;
        border-radius: 6px;
        background: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        transition: all 0.2s;
    }
    
    .soal-nav-item:hover {
        border-color: #0066cc;
        background: #e6f2ff;
    }
    
    .soal-nav-item.active {
        border-color: #0066cc;
        background: #0066cc;
        color: white;
    }
    
    .soal-nav-item.answered {
        background: #d4edda;
        border-color: #28a745;
    }
    
    .soal-nav-item.ragu {
        background: #fff3cd;
        border-color: #ffc107;
    }
    
    .soal-nav-item.answered.ragu {
        background: #ffeaa7;
        border-color: #fdcb6e;
    }
    
    .legend {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        margin: 8px 0;
        font-size: 0.9rem;
    }
    
    .legend-box {
        width: 20px;
        height: 20px;
        border: 2px solid #dee2e6;
        border-radius: 4px;
        margin-right: 10px;
    }
    
    @media (max-width: 1200px) {
        .exam-sidebar {
            width: 100%;
            right: -100%;
        }
        
        .exam-sidebar.show {
            right: 0;
        }
        
        .exam-main.sidebar-open {
            margin-right: 0;
        }
        
        .nav-toggle-btn.sidebar-open {
            right: 20px;
        }
    }
</style>

<div class="exam-wrapper exam-container" data-sesi-id="<?php echo $sesi_id; ?>" data-ujian-id="<?php echo $sesi['id_ujian']; ?>">
    <!-- Toggle Navigation Button -->
    <button class="nav-toggle-btn" id="navToggleBtn" onclick="toggleNavigation()" title="Toggle Navigasi Soal">
        <i class="fas fa-list" id="navToggleIcon"></i>
    </button>
    
    <div class="exam-main" id="examMain">
        <div class="exam-header">
            <div>
                <h4 class="mb-0"><?php echo escape($ujian['judul']); ?></h4>
                <small><?php echo escape($ujian['nama_mapel']); ?> - <?php echo escape($sesi['nama_sesi']); ?></small>
            </div>
            <div class="exam-timer" id="examTimer">
                <i class="fas fa-clock me-2"></i>
                <span id="timerDisplay">--:--</span>
            </div>
        </div>
        
        <form id="examForm" method="POST" action="<?php echo base_url('api/save_answer.php'); ?>">
            <input type="hidden" name="sesi_id" value="<?php echo $sesi_id; ?>">
            <input type="hidden" name="ujian_id" value="<?php echo $sesi['id_ujian']; ?>">
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
                
                <?php if ($current_soal_data['tipe_soal'] === 'pilihan_ganda'): ?>
                    <div class="options-container">
                        <?php foreach ($opsi as $key => $value): 
                            $is_selected = $saved && $saved['jawaban'] === $key;
                        ?>
                        <div class="option-item <?php echo $is_selected ? 'selected' : ''; ?>" 
                             onclick="selectOption('<?php echo $key; ?>')">
                            <input type="radio" name="jawaban" id="opt_<?php echo $key; ?>" 
                                   value="<?php echo $key; ?>" 
                                   <?php echo $is_selected ? 'checked' : ''; ?>
                                   onchange="saveAnswer()">
                            <label for="opt_<?php echo $key; ?>" style="cursor: pointer; margin-left: 10px; flex: 1;">
                                <strong><?php echo $key; ?>.</strong> <?php echo escape($value); ?>
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
                        <textarea class="form-control" name="jawaban" rows="8"
                                  placeholder="Tulis jawaban Anda di sini..."
                                  onchange="saveAnswer()"><?php echo escape($saved['jawaban'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="exam-navigation">
                <?php if ($current_soal >= $total_soal): ?>
                <div class="submit-info hidden" id="submitInfo">
                    <i class="fas fa-info-circle"></i> 
                    Tombol "Selesai" akan aktif dalam <strong id="minTimeRemaining">-</strong> menit
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center w-100">
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
                    <button type="button" class="btn btn-success" id="submitBtn" disabled onclick="submitExam()">
                        <i class="fas fa-check"></i> Selesai
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    
    <div class="exam-sidebar" id="examSidebar">
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
                <div class="legend-box" style="background: #0066cc; border-color: #0066cc;"></div>
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
        
        <div class="mt-3 text-center">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="submitExam()">
                <i class="fas fa-stop"></i> Selesai Ujian
            </button>
        </div>
    </div>
</div>

<script>
// Base URL helper function
function base_url(path) {
    return '<?php echo base_url(''); ?>' + path;
}

let sisaWaktu = <?php echo $sisa_waktu; ?>;
let currentSoal = <?php echo $current_soal; ?>;
let totalSoal = <?php echo $total_soal; ?>;
let sesiId = <?php echo $sesi_id; ?>;
let ujianId = <?php echo $sesi['id_ujian']; ?>;
let soalId = <?php echo $current_soal_data['id']; ?>;
let isRagu = <?php echo ($saved && $saved['is_ragu']) ? 'true' : 'false'; ?>;
const MIN_SUBMIT_MINUTES = 3; // 3 menit sebelum waktu habis
let canSubmit = false;

// Request fullscreen on load
document.addEventListener('DOMContentLoaded', () => {
    // Try to enter fullscreen
    if (document.documentElement.requestFullscreen) {
        document.documentElement.requestFullscreen().catch(() => {
            console.log('Fullscreen not available');
        });
    } else if (document.documentElement.webkitRequestFullscreen) {
        document.documentElement.webkitRequestFullscreen();
    } else if (document.documentElement.mozRequestFullScreen) {
        document.documentElement.mozRequestFullScreen();
    } else if (document.documentElement.msRequestFullscreen) {
        document.documentElement.msRequestFullscreen();
    }
    
    // Initialize submit info
    const submitBtn = document.getElementById('submitBtn');
    const submitInfo = document.getElementById('submitInfo');
    if (submitBtn && submitInfo) {
        const minutesRemaining = Math.floor(sisaWaktu / 60);
        if (minutesRemaining > MIN_SUBMIT_MINUTES) {
            const minsUntilEnable = minutesRemaining - MIN_SUBMIT_MINUTES;
            submitInfo.classList.remove('hidden');
            document.getElementById('minTimeRemaining').textContent = minsUntilEnable;
        } else {
            submitBtn.disabled = false;
            canSubmit = true;
        }
    }
});

// Timer
const timerInterval = setInterval(() => {
    sisaWaktu--;
    
    if (sisaWaktu <= 0) {
        clearInterval(timerInterval);
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
    
    const timerEl = document.getElementById('examTimer');
    const submitBtn = document.getElementById('submitBtn');
    const submitInfo = document.getElementById('submitInfo');
    
    // Enable submit button 3 minutes before time ends
    const minutesRemaining = Math.floor(sisaWaktu / 60);
    if (minutesRemaining <= MIN_SUBMIT_MINUTES) {
        if (!canSubmit) {
            canSubmit = true;
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            if (submitInfo) {
                submitInfo.classList.add('hidden');
            }
        }
    } else {
        if (submitInfo && submitBtn && submitBtn.disabled) {
            const minsUntilEnable = minutesRemaining - MIN_SUBMIT_MINUTES;
            submitInfo.classList.remove('hidden');
            document.getElementById('minTimeRemaining').textContent = minsUntilEnable;
        }
    }
    
    // Change timer color
    if (sisaWaktu <= 300) { // 5 minutes
        timerEl.classList.add('danger');
        timerEl.classList.remove('warning');
    } else if (sisaWaktu <= 600) { // 10 minutes
        timerEl.classList.add('warning');
        timerEl.classList.remove('danger');
    } else {
        timerEl.classList.remove('danger', 'warning');
    }
}, 1000);

// Toggle Navigation
function toggleNavigation() {
    const sidebar = document.getElementById('examSidebar');
    const main = document.getElementById('examMain');
    const toggleBtn = document.getElementById('navToggleBtn');
    const toggleIcon = document.getElementById('navToggleIcon');
    
    sidebar.classList.toggle('show');
    main.classList.toggle('sidebar-open');
    toggleBtn.classList.toggle('sidebar-open');
    
    if (sidebar.classList.contains('show')) {
        toggleIcon.classList.remove('fa-list');
        toggleIcon.classList.add('fa-times');
    } else {
        toggleIcon.classList.remove('fa-times');
        toggleIcon.classList.add('fa-list');
    }
}

function selectOption(value) {
    // Remove selected class from all options
    document.querySelectorAll('.option-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Check the radio button
    const radio = document.querySelector(`input[value="${value}"]`);
    if (radio) {
        radio.checked = true;
        radio.closest('.option-item').classList.add('selected');
        saveAnswer();
    }
}

// Update selected state on page load
document.addEventListener('DOMContentLoaded', () => {
    const checked = document.querySelector('input[name="jawaban"]:checked');
    if (checked) {
        checked.closest('.option-item').classList.add('selected');
    }
});

function saveAnswer() {
    const form = document.getElementById('examForm');
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    fetch('<?php echo base_url('api/save_answer.php'); ?>', {
        method: 'POST',
        body: formData
    }).then(() => {
        updateNavigation();
    });
}

function toggleRagu() {
    isRagu = !isRagu;
    const formData = new FormData();
    formData.append('action', 'toggle_ragu');
    formData.append('sesi_id', sesiId);
    formData.append('ujian_id', ujianId);
    formData.append('soal_id', soalId);
    formData.append('is_ragu', isRagu ? '1' : '0');
    
    fetch('<?php echo base_url('api/save_answer.php'); ?>', {
        method: 'POST',
        body: formData
    }).then(() => {
        document.getElementById('raguText').textContent = isRagu ? 'Batal Ragu-ragu' : 'Ragu-ragu';
        updateNavigation();
    });
}

function goToSoal(num) {
    if (num < 1 || num > totalSoal) return;
    window.location.href = `?id=<?php echo $sesi_id; ?>&soal=${num}`;
}

function updateNavigation() {
    // Update navigation will be done on page reload
}

function submitExam() {
    if (confirm('Apakah Anda yakin ingin menyelesaikan ujian? Pastikan semua jawaban sudah diperiksa.')) {
        window.location.href = '<?php echo base_url('siswa/ujian/submit.php'); ?>?sesi_id=<?php echo $sesi_id; ?>';
    }
}

// Keyboard navigation
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft' && currentSoal > 1) {
        goToSoal(currentSoal - 1);
    } else if (e.key === 'ArrowRight' && currentSoal < totalSoal) {
        goToSoal(currentSoal + 1);
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
