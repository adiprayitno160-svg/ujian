<?php
/**
 * Take Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * UNBK Style - One Question Per Page
 */

// Enable error reporting FIRST, before any other code
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Display error
        echo "<!DOCTYPE html><html><head><title>Fatal Error</title><meta charset='UTF-8'>";
        echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
        echo ".error{background:#f8d7da;color:#721c24;padding:20px;border-radius:5px;max-width:800px;margin:0 auto;}";
        echo "pre{background:#fff;padding:10px;border-radius:3px;overflow-x:auto;margin-top:10px;}</style></head><body>";
        echo "<div class='error'>";
        echo "<h2>Fatal Error</h2>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($error['file']) . " (Line: " . $error['line'] . ")</p>";
        echo "<p><strong>Type:</strong> " . $error['type'] . "</p>";
        echo "</div></body></html>";
        exit;
    }
});

// Try to load required files with error handling
try {
    require_once __DIR__ . '/../../config/config.php';
} catch (Throwable $e) {
    die("Error loading config: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    require_once __DIR__ . '/../../includes/auth.php';
} catch (Throwable $e) {
    die("Error loading auth: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    require_once __DIR__ . '/../../includes/functions.php';
} catch (Throwable $e) {
    die("Error loading functions: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    require_once __DIR__ . '/../../includes/functions_sumatip.php';
} catch (Throwable $e) {
    die("Error loading functions_sumatip: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

try {
    require_once __DIR__ . '/../../includes/security.php';
} catch (Throwable $e) {
    die("Error loading security: " . htmlspecialchars($e->getMessage()) . " in " . $e->getFile() . " on line " . $e->getLine());
}

require_role('siswa');
check_session_timeout();

global $pdo;

// Check if PDO is available
if (!isset($pdo) || $pdo === null) {
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Error</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Database Connection Error</h2>
            <p>Database connection is not available. Please check your database configuration.</p>
        </div>
    </body>
    </html>
    ");
}

$sesi_id = intval($_GET['id'] ?? 0);
$current_soal = intval($_GET['soal'] ?? 1); // Current question number (1-based)

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

// Check if waktu_mulai has arrived
try {
    $now = new DateTime();
    $waktu_mulai = new DateTime($sesi['waktu_mulai']);
    if ($now < $waktu_mulai) {
        $_SESSION['error_message'] = 'Ujian belum dimulai. Waktu mulai: ' . format_date($sesi['waktu_mulai']);
        redirect('siswa-ujian-list');
    }
} catch (Exception $e) {
    error_log("Error checking waktu_mulai: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error memeriksa waktu ujian: ' . $e->getMessage();
    redirect('siswa-ujian-list');
}

// Validate session
try {
    $validation = validate_exam_session($sesi_id, $_SESSION['user_id']);
    if (!$validation['valid']) {
        $_SESSION['error_message'] = $validation['message'];
        redirect('siswa-ujian-list');
    }
} catch (Exception $e) {
    error_log("Error validating exam session: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error validasi sesi ujian: ' . $e->getMessage();
    redirect('siswa-ujian-list');
}

$ujian = get_ujian($sesi['id_ujian']);

if (!$ujian) {
    $_SESSION['error_message'] = 'Ujian tidak ditemukan';
    redirect('siswa-ujian-list');
}

// Check if this is an assessment (dikelola operator)
$is_assessment = !empty($ujian['tipe_asesmen']); // Assessment jika tipe_asesmen tidak NULL/empty

// Check if already started
try {
    $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
    $stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
    $nilai = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching nilai: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat memuat data ujian: ' . $e->getMessage();
    redirect('siswa-ujian-list');
}

if (!$nilai) {
    // First time starting exam - create nilai record
    try {
        $stmt = $pdo->prepare("INSERT INTO nilai (id_sesi, id_ujian, id_siswa, status, waktu_mulai, device_info, ip_address) 
                              VALUES (?, ?, ?, 'sedang_mengerjakan', NOW(), ?, ?)");
        $stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id'], get_device_info(), get_client_ip()]);
        
        // Set exam mode in session - LOCK student from accessing other pages
        set_exam_mode($sesi_id, $sesi['id_ujian']);
        
        // Auto-absensi: create absensi record
        if (function_exists('create_absensi')) {
            try {
                create_absensi($sesi_id, $_SESSION['user_id'], null, 'hadir', 'auto', null);
            } catch (Exception $e) {
                error_log("Error creating absensi: " . $e->getMessage());
                // Don't fail the whole process if absensi creation fails
            }
        }
        
        $stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
        $stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            $_SESSION['error_message'] = 'Gagal membuat record ujian';
            clear_exam_mode();
            redirect('siswa-ujian-list');
        }
    } catch (Exception $e) {
        error_log("Error creating nilai record: " . $e->getMessage());
        $_SESSION['error_message'] = 'Terjadi kesalahan saat memulai ujian: ' . $e->getMessage();
        clear_exam_mode();
        redirect('siswa-ujian-list');
    }
} elseif ($nilai['status'] === 'selesai') {
    // Exam finished - clear exam mode
    clear_exam_mode();
    redirect('siswa-ujian-hasil?id=' . $sesi_id);
} else {
    // Exam already started - check if requires_token
    // Jika requires_token = true, student perlu token untuk melanjutkan (kecuali fraud)
    $requires_token = isset($nilai['requires_token']) && ($nilai['requires_token'] == 1 || $nilai['requires_token'] === '1');
    
    // Check token requirement (for assessment with token_required setting)
    $token_required_setting_temp = isset($sesi['token_required']) && ($sesi['token_required'] == 1 || $sesi['token_required'] === '1');
    
    // If requires_token is set OR (assessment with token_required and answers_locked), need token verification
    if ($requires_token || ($is_assessment && $token_required_setting_temp && 
        isset($nilai['answers_locked']) && $nilai['answers_locked'])) {
        // Normal disruption - need token to resume
        // Check if token is already verified
        $token_verified_key = 'token_verified_' . $sesi_id;
        if (!isset($_SESSION[$token_verified_key]) || !$_SESSION[$token_verified_key]) {
            // Token not verified yet - will be handled by token verification below
            // This will show token request form
        }
    }
    
    // Set exam mode - LOCK student from accessing other pages
    set_exam_mode($sesi_id, $sesi['id_ujian']);
}

// Get soal
try {
    $order_by = $ujian['acak_soal'] ? "RAND()" : "urutan ASC, id ASC";
    $stmt = $pdo->prepare("SELECT * FROM soal WHERE id_ujian = ? ORDER BY " . $order_by);
    $stmt->execute([$sesi['id_ujian']]);
    $soal_list = $stmt->fetchAll();
    
    $total_soal = count($soal_list);
    
    if ($total_soal === 0) {
        $_SESSION['error_message'] = 'Tidak ada soal yang tersedia untuk ujian ini';
        redirect('siswa-ujian-list');
    }
    
    if ($current_soal < 1) $current_soal = 1;
    if ($current_soal > $total_soal) $current_soal = $total_soal;
} catch (PDOException $e) {
    error_log("Error fetching soal: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat memuat soal: ' . $e->getMessage();
    redirect('siswa-ujian-list');
} catch (Exception $e) {
    error_log("Error fetching soal: " . $e->getMessage());
    $_SESSION['error_message'] = 'Terjadi kesalahan saat memuat soal: ' . $e->getMessage();
    redirect('siswa-ujian-list');
}

// Get saved answers (including locked status - but locked only after submit)
$stmt = $pdo->prepare("SELECT id_soal, jawaban, jawaban_json, is_ragu, is_locked FROM jawaban_siswa 
                      WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $sesi['id_ujian'], $_SESSION['user_id']]);
$saved_answers = [];
$ragu_soal = [];
$locked_answers = [];
foreach ($stmt->fetchAll() as $ans) {
    $saved_answers[$ans['id_soal']] = $ans;
    if ($ans['is_ragu']) {
        $ragu_soal[] = $ans['id_soal'];
    }
    if ($ans['is_locked'] && $nilai && $nilai['status'] === 'selesai') {
        // Only consider locked if exam is finished
        $locked_answers[] = $ans['id_soal'];
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

// Calculate elapsed time (waktu yang sudah berlalu sejak mulai ujian)
$elapsed_seconds = $now->getTimestamp() - $waktu_mulai->getTimestamp();
$elapsed_seconds = max(0, $elapsed_seconds);

// Minimum submit time restriction removed - students can finish anytime
// Finish button is always available (only restricted by time limit)
$can_submit_now = true;
$min_submit_seconds = 0;
$seconds_until_submit_enabled = 0;

// Get current soal
$current_soal_data = $soal_list[$current_soal - 1] ?? null;
if (!$current_soal_data) {
    $_SESSION['error_message'] = 'Soal tidak ditemukan';
    redirect('siswa-ujian-list');
}

// Check authentication (token only) - before header
$token_verified_key = 'token_verified_' . $sesi_id;
$token_id_key = 'token_id_' . $sesi_id;
$need_auth = false;
$token_error = '';
$need_token_contact_admin = false; // Flag untuk non-assessment yang butuh token tapi token belum tersedia

// Determine if token is required
// Token diperlukan jika:
// 1. Ujian adalah assessment yang dikelola operator (punya tipe_asesmen) DAN token_required = 1 di sesi_ujian, ATAU
// 2. requires_token = 1 di nilai table (set untuk semua jenis ujian jika ada masalah)
// Note: $is_assessment sudah didefinisikan di baris 148, $requires_token sudah didefinisikan di baris 220
$token_required_setting = isset($sesi['token_required']) && ($sesi['token_required'] == 1 || $sesi['token_required'] === '1');
$token_required = ($is_assessment && $token_required_setting) || $requires_token; // Aktif untuk assessment dengan token_required atau jika requires_token di set

// Ensure token_usage table has sesi_id column (check once at the beginning)
try {
    $check_col = $pdo->query("SHOW COLUMNS FROM token_usage LIKE 'sesi_id'");
    if ($check_col->rowCount() == 0) {
        $pdo->exec("ALTER TABLE token_usage ADD COLUMN sesi_id INT DEFAULT NULL AFTER id_user");
    }
} catch (PDOException $e) {
    // Column might already exist or table doesn't exist yet, ignore error
    error_log("Note: sesi_id column check in token_usage: " . $e->getMessage());
}

// Process authentication form submission (token only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_auth'])) {
    $token_input = $_POST['token'] ?? '';
    
    // Validate token if required
    if ($token_required) {
        if (empty($token_input)) {
            $token_error = 'Token harus diisi';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM token_ujian 
                                  WHERE id_sesi = ? AND token = ? AND status = 'active' 
                                  AND expires_at > NOW()");
            $stmt->execute([$sesi_id, $token_input]);
            $token = $stmt->fetch();
            
            if (!$token) {
                $token_error = 'Token tidak valid atau sudah expired';
            } elseif ($token['max_usage'] && $token['current_usage'] >= $token['max_usage']) {
                $token_error = 'Token sudah mencapai batas penggunaan';
            } else {
                // Check if this user already used a token for this session
                // Use id DESC instead of created_at (column might not exist)
                $stmt = $pdo->prepare("SELECT id_token FROM token_usage 
                                      WHERE id_user = ? AND sesi_id = ? 
                                      ORDER BY id DESC LIMIT 1");
                $stmt->execute([$_SESSION['user_id'], $sesi_id]);
                $existing_usage = $stmt->fetch();
                
                // Check if token has max_usage limit
                $token_has_limit = ($token['max_usage'] !== null && $token['max_usage'] > 0);
                
                if ($token_has_limit) {
                    // Token has usage limit - check if already used by someone else
                    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as user_count 
                                          FROM token_usage 
                                          WHERE id_token = ? AND sesi_id = ?");
                    $stmt->execute([$token['id'], $sesi_id]);
                    $token_users = $stmt->fetch();
                    
                    if ($token_users && $token_users['user_count'] > 0) {
                        // Token already used by someone else - prevent reuse
                        $token_error = 'Token ini sudah digunakan. Setiap siswa harus menggunakan token yang berbeda.';
                    } else {
                        // Token not used yet - record usage
                        if (!$existing_usage || $existing_usage['id_token'] != $token['id']) {
                            $stmt = $pdo->prepare("INSERT INTO token_usage (id_token, id_user, sesi_id, ip_address, device_info) 
                                                  VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$token['id'], $_SESSION['user_id'], $sesi_id, get_client_ip(), get_device_info()]);
                            
                            $stmt = $pdo->prepare("UPDATE token_ujian SET current_usage = current_usage + 1 WHERE id = ?");
                            $stmt->execute([$token['id']]);
                        }
                        
                        // Store verification in session and bind to user
                        $_SESSION[$token_verified_key] = true;
                        $_SESSION[$token_id_key] = $token['id'];
                        
                        // Redirect to same page to continue
                        redirect('siswa-ujian-take?id=' . $sesi_id);
                    }
                } else {
                    // Token has no limit (max_usage is NULL) - allow all peserta to use same token
                    if (!$existing_usage || $existing_usage['id_token'] != $token['id']) {
                        // New token usage for this user
                        $stmt = $pdo->prepare("INSERT INTO token_usage (id_token, id_user, sesi_id, ip_address, device_info) 
                                              VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$token['id'], $_SESSION['user_id'], $sesi_id, get_client_ip(), get_device_info()]);
                        
                        $stmt = $pdo->prepare("UPDATE token_ujian SET current_usage = current_usage + 1 WHERE id = ?");
                        $stmt->execute([$token['id']]);
                    }
                    
                    // Store verification in session and bind to user
                    $_SESSION[$token_verified_key] = true;
                    $_SESSION[$token_id_key] = $token['id'];
                    
                    // Redirect to same page to continue
                    redirect('siswa-ujian-take?id=' . $sesi_id);
                }
            }
        }
    }
}

// Check if authentication is needed (token only)
// If token is not required, automatically mark as verified
if (!$token_required) {
    $_SESSION[$token_verified_key] = true;
    $token_verified = true;
} else {
    // Check if token was verified in session
    $token_verified = isset($_SESSION[$token_verified_key]) && $_SESSION[$token_verified_key];
    
    // If verified in session, verify token is still valid in database
    if ($token_verified && isset($_SESSION[$token_id_key])) {
        $stored_token_id = $_SESSION[$token_id_key];
        $stmt = $pdo->prepare("SELECT * FROM token_ujian 
                              WHERE id = ? AND id_sesi = ? AND status = 'active' 
                              AND expires_at > NOW()");
        $stmt->execute([$stored_token_id, $sesi_id]);
        $token_still_valid = $stmt->fetch();
        
        if (!$token_still_valid) {
            // Token is no longer valid - clear verification
            unset($_SESSION[$token_verified_key]);
            unset($_SESSION[$token_id_key]);
            $token_verified = false;
        } else {
            // Also check if student has a valid token_usage record for this sesi
            // This ensures token is bound to user and sesi
            $stmt = $pdo->prepare("SELECT id FROM token_usage 
                                  WHERE id_token = ? AND id_user = ? 
                                  AND (sesi_id IS NULL OR sesi_id = ?)");
            $stmt->execute([$stored_token_id, $_SESSION['user_id'], $sesi_id]);
            $usage_record = $stmt->fetch();
            
            if (!$usage_record) {
                // No usage record - require re-authentication
                // This prevents token sharing between users
                unset($_SESSION[$token_verified_key]);
                unset($_SESSION[$token_id_key]);
                $token_verified = false;
            } else {
                // Additional check: ensure token was used by this specific user
                // Prevent token sharing by checking token_usage records
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_user) as user_count 
                                      FROM token_usage 
                                      WHERE id_token = ? AND sesi_id = ?");
                $stmt->execute([$stored_token_id, $sesi_id]);
                $token_usage_count = $stmt->fetch();
                
                // If token was used by multiple users, it's suspicious
                if ($token_usage_count && $token_usage_count['user_count'] > 1) {
                    log_security_event($_SESSION['user_id'], $sesi_id, 'token_sharing_detected', 
                        'Token used by multiple users', true);
                    // Still allow but log the event
                }
            }
        }
    }
}

// Get active token for this sesi (to display in form)
$active_token_for_sesi = null;
if ($token_required) {
    try {
        $stmt = $pdo->prepare("SELECT token FROM token_ujian 
                              WHERE id_sesi = ? AND status = 'active' 
                              AND expires_at > NOW()
                              ORDER BY id DESC LIMIT 1");
        $stmt->execute([$sesi_id]);
        $token_result = $stmt->fetch();
        if ($token_result) {
            $active_token_for_sesi = $token_result['token'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching active token: " . $e->getMessage());
    }
}

// Only need auth if token is required and not yet verified
if ($token_required && !$token_verified) {
    $need_auth = true;
    
    // If requires_token is set but it's not an assessment, check if token exists
    if ($requires_token && !$is_assessment) {
        // For non-assessments with requires_token, check if token exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as token_count FROM token_ujian 
                              WHERE id_sesi = ? AND status = 'active' AND expires_at > NOW()");
        $stmt->execute([$sesi_id]);
        $token_count = $stmt->fetchColumn();
        
        if ($token_count == 0) {
            // No token available - show blocking message
            // We'll show this in the auth form as a special case
            $need_token_contact_admin = true;
        } else {
            // Token exists - show token input form
            $need_token_contact_admin = false;
        }
    } else {
        $need_token_contact_admin = false;
    }
} else {
    $need_token_contact_admin = false;
}

// Validate that we have all required data before including header
if (!isset($sesi) || !$sesi) {
    $_SESSION['error_message'] = 'Sesi tidak ditemukan';
    redirect('siswa-ujian-list');
}

if (!isset($ujian) || !$ujian) {
    $_SESSION['error_message'] = 'Ujian tidak ditemukan';
    redirect('siswa-ujian-list');
}

if (!isset($nilai) || !$nilai) {
    $_SESSION['error_message'] = 'Data ujian tidak ditemukan';
    redirect('siswa-ujian-list');
}

if (!isset($soal_list) || empty($soal_list)) {
    $_SESSION['error_message'] = 'Tidak ada soal yang tersedia';
    redirect('siswa-ujian-list');
}

if (!isset($current_soal_data) || !$current_soal_data) {
    $_SESSION['error_message'] = 'Soal tidak ditemukan';
    redirect('siswa-ujian-list');
}

$page_title = 'Kerjakan Ujian';
$role_css = 'siswa';
$custom_js = ['auto_save', 'ragu_ragu']; // DISABLED: exam_security removed (Anti Contek feature removed)
$hide_navbar = true; // Hide sidebar for fullscreen exam
$fullscreen_exam = true; // Flag for fullscreen exam

// Try to include header, catch any errors
try {
    include __DIR__ . '/../../includes/header.php';
} catch (Throwable $e) {
    // Clear output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error Loading Header</title>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
            .error { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
            pre { background: #fff; padding: 10px; border-radius: 3px; overflow-x: auto; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class='error'>
            <h2>Error Loading Header</h2>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line: " . $e->getLine() . ")</p>
            <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
        </div>
    </body>
    </html>
    ");
}

// Show authentication form (token only) if needed
if ($need_auth) {
    // Close the main tag from header.php before showing auth form
    if (isset($hide_navbar) && $hide_navbar) {
        echo '</main>';
    }
    ?>
    <style>
        /* Override all exam styles for auth page */
        html, body.hide-navbar {
            height: 100% !important;
            width: 100% !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            position: relative !important;
        }
        
        /* Hide all navigation elements */
        body.hide-navbar .app-wrapper,
        body.hide-navbar .sidebar,
        body.hide-navbar .main-content,
        body.hide-navbar .content-header,
        body.hide-navbar .content-body,
        body.hide-navbar .sidebar-overlay,
        body.hide-navbar .sidebar-toggle {
            display: none !important;
        }
        
        /* Auth overlay container - covers entire screen */
        #auth-overlay {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 99999 !important;
            background: linear-gradient(135deg, #e6f2ff 0%, #cce5ff 100%) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 102, 204, 0.2);
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }
        .auth-header {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            color: white;
            padding: 20px 25px;
            text-align: center;
            border-radius: 12px 12px 0 0;
        }
        .auth-header h3 {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .auth-header p {
            margin-top: 8px;
            margin-bottom: 0;
            font-size: 0.875rem;
            opacity: 0.95;
        }
        .auth-body {
            padding: 25px;
        }
        .auth-container {
            padding: 30px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        @media (min-width: 768px) {
            .auth-container {
                padding: 50px 20px;
            }
        }
        @media (max-width: 767px) {
            .auth-container {
                padding: 20px 15px;
            }
        }
        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 6px;
            display: block;
            font-size: 0.9rem;
        }
        .form-control {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 0.95rem;
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
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            margin-top: 8px;
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
            padding: 10px 14px;
            margin-bottom: 12px;
            font-size: 0.875rem;
        }
        .text-muted {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 4px;
            display: block;
        }
    </style>
    <!-- Auth Page Content -->
    <div id="auth-overlay">
        <div class="auth-container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-6 col-lg-5 col-xl-4">
                    <div class="auth-card">
                    <div class="auth-header">
                        <h3><i class="fas fa-shield-alt me-2"></i>Verifikasi Token</h3>
                        <p style="opacity: 0.95;">
                            Masukkan token ujian untuk melanjutkan
                        </p>
                    </div>
                    <div class="auth-body">
                        <!-- Aturan Pengerjaan dan Sangsi -->
                        <div class="alert alert-warning mb-3" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                            <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Aturan Pengerjaan dan Sangsi</h5>
                            <div style="font-size: 0.9rem; line-height: 1.6;">
                                <p class="mb-2"><strong>Aturan Pengerjaan:</strong></p>
                                <ul class="mb-3" style="padding-left: 20px;">
                                    <li>Dilarang beralih tab atau window browser selama ujian</li>
                                    <li>Dilarang menggunakan aplikasi lain atau membuka aplikasi lain</li>
                                    <li>Dilarang copy-paste atau screenshot</li>
                                    <li>Dilarang menggunakan developer tools atau inspect element</li>
                                    <li>Dilarang menggunakan aplikasi remote desktop</li>
                                </ul>
                                
                                <p class="mb-2"><strong>Sangsi Pelanggaran:</strong></p>
                                <ul style="padding-left: 20px; margin-bottom: 0;">
                                    <li><strong>Beralih Tab/Window:</strong> Semua jawaban akan di-reset otomatis setiap kali terdeteksi beralih tab/window</li>
                                    <li><strong>Pelanggaran Berulang:</strong> Jika terdeteksi beralih tab/window lebih dari 10 kali, akun akan di-logout dan harus login ulang</li>
                                    <li><strong>Pelanggaran Lainnya:</strong> Akan dicatat sebagai fraud dan dapat mengakibatkan pengurangan nilai atau diskualifikasi</li>
                                </ul>
                            </div>
                        </div>
                        
                        <?php if ($token_error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo escape($token_error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Active Token for All Participants (First Token) -->
                        <?php if ($active_token_for_sesi): ?>
                            <div class="alert alert-success mb-3" style="background: #d1e7dd; border-left: 4px solid #198754;">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-key fa-2x me-3 text-success"></i>
                                    <div>
                                        <strong>Token Ujian Aktif</strong><br>
                                        <span class="fs-4 fw-bold text-success" style="letter-spacing: 0.3rem;">
                                            <?php echo escape($active_token_for_sesi); ?>
                                        </span><br>
                                        <small class="text-muted">Token ini digunakan oleh semua peserta ujian di sesi ini</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Token Request Status (hanya untuk assessment) -->
                        <?php if ($is_assessment && $token_required_setting): ?>
                        <div id="tokenRequestStatus" class="alert d-none mb-3"></div>
                        
                        <!-- Check existing token request (hanya untuk assessment) -->
                        <?php
                        $existing_request = null;
                        try {
                            $stmt = $pdo->prepare("SELECT tr.*, t.token, u.nama as approved_by_name
                                                  FROM token_request tr
                                                  LEFT JOIN token_ujian t ON tr.id_token = t.id
                                                  LEFT JOIN users u ON tr.approved_by = u.id
                                                  WHERE tr.id_sesi = ? AND tr.id_siswa = ?
                                                  ORDER BY tr.requested_at DESC LIMIT 1");
                            $stmt->execute([$sesi_id, $_SESSION['user_id']]);
                            $existing_request = $stmt->fetch();
                        } catch (PDOException $e) {
                            error_log("Error fetching token request: " . $e->getMessage());
                        }
                        
                        if ($existing_request):
                            if ($existing_request['status'] === 'pending'):
                        ?>
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Request Token Dikirim</strong><br>
                                <small>Request token Anda sedang menunggu persetujuan operator. Silakan tunggu atau hubungi operator.</small>
                            </div>
                        <?php elseif ($existing_request['status'] === 'approved' && $existing_request['token']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Token Tersedia!</strong><br>
                                <small>Token Anda: <strong class="fs-5"><?php echo escape($existing_request['token']); ?></strong></small><br>
                                <?php if ($existing_request['approved_by_name']): ?>
                                    <small>Disetujui oleh: <?php echo escape($existing_request['approved_by_name']); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($existing_request['status'] === 'rejected'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-times-circle me-2"></i>
                                <strong>Request Ditolak</strong><br>
                                <?php if ($existing_request['notes']): ?>
                                    <small><?php echo escape($existing_request['notes']); ?></small>
                                <?php else: ?>
                                    <small>Request token Anda ditolak. Silakan request token baru.</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php endif; ?>
                    
                        <?php if ($need_token_contact_admin): ?>
                            <!-- For non-assessments with requires_token but no token available -->
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Token Diperlukan</strong><br>
                                <p class="mb-2">Untuk melanjutkan ujian, Anda perlu token dari administrator.</p>
                                <p class="mb-0"><strong>Silakan hubungi administrator atau pengawas ujian untuk mendapatkan token.</strong></p>
                            </div>
                            <div class="text-center mt-3">
                                <a href="<?php echo base_url('siswa-ujian-list'); ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Kembali ke Daftar Ujian
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Normal token input form -->
                            <form method="POST" id="tokenForm">
                                <input type="hidden" name="id" value="<?php echo $sesi_id; ?>">
                                
                                <div class="mb-3">
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
                                           style="font-size: 1.1rem; letter-spacing: 0.4rem; padding: 10px;"
                                           value="<?php 
                                           echo isset($_POST['token']) ? escape($_POST['token']) : 
                                                ($active_token_for_sesi ? escape($active_token_for_sesi) : 
                                                (isset($existing_request) && $existing_request && isset($existing_request['token']) ? escape($existing_request['token']) : '')); 
                                           ?>"
                                           required 
                                           autofocus>
                                    <small class="text-muted" style="font-size: 0.8rem;">Token 6 digit yang diberikan oleh pengawas</small>
                                </div>
                                
                                <button type="submit" name="verify_auth" class="btn btn-verify mt-2 w-100">
                                    <i class="fas fa-check-circle me-2"></i>Verifikasi & Lanjutkan Ujian
                                </button>
                                
                                <?php 
                                // Hanya tampilkan button Request Token jika ini adalah assessment yang dikelola operator
                                if ($is_assessment && $token_required_setting && (!isset($existing_request) || !$existing_request || $existing_request['status'] === 'rejected')): 
                                ?>
                                <button type="button" class="btn btn-outline-info mt-2 w-100" id="btnRequestToken" onclick="requestToken()">
                                    <i class="fas fa-paper-plane me-2"></i>Request Token Baru
                                </button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    // Close body and html tags manually for auth form
    echo '</body></html>';
    exit;
}

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

// Shuffle opsi if enabled and create mapping for answer key conversion
$shuffle_map = []; // Maps shuffled key -> original key (e.g., ['C' => 'A'] means shuffled C is original A)
$reverse_shuffle_map = []; // Maps original key -> shuffled key (e.g., ['A' => 'C'] means original A is now at C)

if ($ujian['acak_opsi'] && is_array($opsi) && $current_soal_data['tipe_soal'] === 'pilihan_ganda') {
    // Get or create shuffle mapping for this soal for this student
    // IMPORTANT: Include student ID to ensure each student gets different shuffle
    $shuffle_key = 'shuffle_map_' . $sesi_id . '_' . $current_soal_data['id'] . '_' . $_SESSION['user_id'];
    
    if (isset($_SESSION[$shuffle_key])) {
        // Use existing mapping (consistent for this student during exam session)
        $shuffle_map = $_SESSION[$shuffle_key];
        $reverse_shuffle_map = array_flip($shuffle_map);
        
        // Recreate shuffled opsi based on saved mapping
        $shuffled_opsi = [];
        foreach ($shuffle_map as $shuffled_key => $original_key) {
            if (isset($opsi[$original_key])) {
                $shuffled_opsi[$shuffled_key] = $opsi[$original_key];
            }
        }
        $opsi = $shuffled_opsi;
    } else {
        // Create new shuffle mapping - each student gets different random order
        // Use student ID as seed to ensure different shuffle per student
        $keys = array_keys($opsi);
        $original_keys = $keys; // Keep original order
        
        // Seed random with student ID + soal ID to get consistent but different shuffle per student
        mt_srand(crc32($_SESSION['user_id'] . '_' . $current_soal_data['id'] . '_' . $sesi_id));
        shuffle($keys); // Shuffle the keys
        mt_srand(); // Reset seed
        
        // Create mapping: shuffled position -> original key
        // Example: If original A is now at position C, then shuffle_map['C'] = 'A'
        foreach ($keys as $idx => $shuffled_key) {
            $original_key = $original_keys[$idx];
            $shuffle_map[$shuffled_key] = $original_key;
            $reverse_shuffle_map[$original_key] = $shuffled_key;
        }
        
        // Save mapping to session (per student, per soal, per sesi)
        $_SESSION[$shuffle_key] = $shuffle_map;
        
        // Create shuffled opsi for display
        $shuffled_opsi = [];
        foreach ($keys as $key) {
            $shuffled_opsi[$key] = $opsi[$key];
        }
        $opsi = $shuffled_opsi;
    }
}

// Get matching items if tipe_soal is matching
$matching_items = [];
$matching_options = [];
$saved_matching = [];
if ($current_soal_data['tipe_soal'] === 'matching') {
    try {
        $stmt_match = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan");
        $stmt_match->execute([$current_soal_data['id']]);
        $matching_items = $stmt_match->fetchAll();
        
        // Get unique options for matching (item_kanan)
        foreach ($matching_items as $item) {
            if (!in_array($item['item_kanan'], $matching_options)) {
                $matching_options[] = $item['item_kanan'];
            }
        }
        
        // Shuffle options if acak_opsi is enabled
        if ($ujian['acak_opsi'] && is_array($matching_options)) {
            shuffle($matching_options);
        }
        
        // Get saved answers
        if ($saved && !empty($saved['jawaban_json'])) {
            $saved_matching = json_decode($saved['jawaban_json'], true);
            if (!is_array($saved_matching)) {
                $saved_matching = [];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching matching items: " . $e->getMessage());
        $matching_items = [];
    }
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
        background: #fff;
        overflow-x: hidden;
    }
    
    body {
        position: relative;
        min-height: 100vh;
    }
    
    /* Hide all navigation and headers when fullscreen */
    body.hide-navbar .app-wrapper,
    body.hide-navbar .sidebar,
    body.hide-navbar .main-content:not(.exam-main),
    body.hide-navbar .content-header,
    body.hide-navbar .content-body:not(.exam-main),
    body.hide-navbar .sidebar-overlay,
    body.hide-navbar .sidebar-toggle,
    body.hide-navbar main.container:not(.exam-wrapper) {
        display: none !important;
    }
    
    /* Fullscreen exam wrapper - ALWAYS VISIBLE */
    body.hide-navbar {
        overflow: hidden;
        margin: 0;
        padding: 0;
        background: #fff !important;
    }
    
    body.hide-navbar html {
        overflow: hidden;
        background: #fff !important;
    }
    
    .exam-wrapper {
        display: flex !important;
        height: 100vh !important;
        width: 100vw !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        z-index: 10000 !important;
        background: #fff !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    body.hide-navbar .exam-wrapper {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
    
    .exam-main {
        flex: 1;
        padding: 0;
        max-width: 100%;
        margin: 0;
        overflow-y: auto;
        transition: margin-right 0.3s ease;
        background: #fff;
        position: relative;
        display: flex;
        flex-direction: column;
    }
    
    .exam-main > form {
        padding: 20px;
        flex: 1;
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
        margin: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 1000;
        width: 100%;
        box-sizing: border-box;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        flex-shrink: 0;
    }
    
    .exam-header > div:first-child {
        flex: 1;
        min-width: 0;
        padding-right: 15px;
    }
    
    .exam-header > div:last-child {
        flex-shrink: 0;
        margin-left: auto;
        display: flex;
        align-items: center;
    }
    
    .exam-header .exam-timer,
    .exam-main .exam-header .exam-timer,
    #examTimer {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        bottom: auto !important;
        left: auto !important;
        background: rgba(255, 255, 255, 0.2) !important;
        color: white !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-weight: bold !important;
        font-size: 1.1rem !important;
        border: 2px solid rgba(255, 255, 255, 0.3) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        visibility: visible !important;
        opacity: 1 !important;
        white-space: nowrap !important;
        margin-left: 20px !important;
        margin-bottom: 0 !important;
        box-shadow: none !important;
        z-index: 1001 !important;
        transform: none !important;
    }
    
    .exam-header .exam-timer #timerDisplay,
    .exam-main .exam-header .exam-timer #timerDisplay,
    #examTimer #timerDisplay {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
        color: white !important;
        font-weight: bold !important;
    }
    
    .exam-header .exam-timer.danger,
    .exam-main .exam-header .exam-timer.danger,
    #examTimer.danger {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        background: #dc3545 !important;
        border-color: #dc3545 !important;
        color: white !important;
        animation: pulse 1s infinite;
    }
    
    .exam-header .exam-timer.danger #timerDisplay,
    .exam-main .exam-header .exam-timer.danger #timerDisplay,
    #examTimer.danger #timerDisplay {
        color: white !important;
    }
    
    .exam-header .exam-timer.warning,
    .exam-main .exam-header .exam-timer.warning,
    #examTimer.warning {
        position: relative !important;
        top: auto !important;
        right: auto !important;
        background: #ffc107 !important;
        color: #000 !important;
        border-color: #ffc107 !important;
    }
    
    .exam-header .exam-timer.warning #timerDisplay,
    .exam-main .exam-header .exam-timer.warning #timerDisplay,
    #examTimer.warning #timerDisplay {
        color: #000 !important;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .nav-toggle-btn {
        position: fixed;
        right: 24px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1002;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        width: 56px;
        height: 56px;
        border-radius: 16px;
        cursor: pointer;
        box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4), 
                    0 4px 12px rgba(0, 0, 0, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    
    .nav-toggle-btn:hover {
        background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        transform: translateY(-50%) scale(1.08) translateY(-2px);
        box-shadow: 0 12px 32px rgba(102, 126, 234, 0.5), 
                    0 6px 16px rgba(0, 0, 0, 0.2),
                    inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
    
    .nav-toggle-btn:active {
        transform: translateY(-50%) scale(0.95);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3), 
                    0 2px 6px rgba(0, 0, 0, 0.15);
    }
    
    .nav-toggle-btn.sidebar-open {
        right: 324px;
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        box-shadow: 0 8px 24px rgba(245, 87, 108, 0.4), 
                    0 4px 12px rgba(0, 0, 0, 0.15),
                    inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }
    
    .nav-toggle-btn.sidebar-open:hover {
        background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
        box-shadow: 0 12px 32px rgba(245, 87, 108, 0.5), 
                    0 6px 16px rgba(0, 0, 0, 0.2),
                    inset 0 1px 0 rgba(255, 255, 255, 0.3);
    }
    
    .nav-toggle-btn i {
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-block;
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
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: white;
        border-top: 1px solid #dee2e6;
        position: sticky;
        bottom: 0;
        margin: 0;
        z-index: 999;
        flex-shrink: 0;
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
            right: 24px;
        }
    }
    
    @media (max-width: 768px) {
        .exam-header {
            flex-wrap: wrap;
            padding: 12px 15px;
        }
        
        .exam-header > div:first-child {
            width: 100%;
            margin-bottom: 10px;
        }
        
        .exam-header > div:last-child {
            width: 100%;
            margin-left: 0;
            display: flex;
            justify-content: flex-end;
        }
        
        .exam-header .exam-timer,
        .exam-main .exam-header .exam-timer,
        #examTimer {
            position: relative !important;
            top: auto !important;
            right: auto !important;
            margin-left: 0 !important;
            margin-bottom: 0 !important;
            font-size: 1rem !important;
            padding: 6px 12px !important;
        }
    }
</style>

<?php
// Close the main tag from header.php if hide_navbar is true
// This allows exam-wrapper to be at body level, not inside main
if (isset($hide_navbar) && $hide_navbar) {
    echo '</main>';
}
?>

<div class="exam-wrapper" data-sesi-id="<?php echo $sesi_id; ?>" data-ujian-id="<?php echo $sesi['id_ujian']; ?>" data-student-name="<?php echo escape($_SESSION['nama'] ?? 'Siswa'); ?>" data-student-id="<?php echo $_SESSION['user_id']; ?>" style="display: flex !important; visibility: visible !important; opacity: 1 !important;">
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
                
                <?php if ($current_soal_data['tipe_soal'] === 'pilihan_ganda'): 
                    // For display: check if saved answer needs conversion from original to shuffled
                    $display_answer = null;
                    if ($saved && !empty($saved['jawaban'])) {
                        $original_answer = $saved['jawaban'];
                        // If we have shuffle map, convert original answer to shuffled position for display
                        if (!empty($reverse_shuffle_map) && isset($reverse_shuffle_map[$original_answer])) {
                            $display_answer = $reverse_shuffle_map[$original_answer];
                        } else {
                            // No shuffle or answer already in correct format
                            $display_answer = $original_answer;
                        }
                    }
                ?>
                    <div class="options-container">
                        <?php foreach ($opsi as $key => $value): 
                            // Check if this is the selected answer (considering shuffle mapping)
                            $is_selected = false;
                            if ($display_answer !== null) {
                                $is_selected = ($display_answer === $key);
                            } elseif ($saved && !empty($saved['jawaban'])) {
                                // Fallback: direct comparison
                                $is_selected = ($saved['jawaban'] === $key);
                            }
                            
                            // Get original key for this shuffled position
                            $original_key = isset($shuffle_map[$key]) ? $shuffle_map[$key] : $key;
                            
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
                                   data-original-key="<?php echo $original_key; ?>"
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
                <?php elseif ($current_soal_data['tipe_soal'] === 'matching'): ?>
                    <div class="mb-3">
                        <p class="text-muted mb-3">
                            <i class="fas fa-info-circle"></i> 
                            Pilih jawaban yang tepat untuk setiap item di kolom kiri dengan memilih dari kolom kanan.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="50%">Item Kiri</th>
                                        <th width="50%">Pilih Jawaban</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matching_items as $idx => $item): 
                                        $saved_value = $saved_matching[$idx] ?? '';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo ($idx + 1); ?>.</strong> <?php echo escape($item['item_kiri']); ?>
                                        </td>
                                        <td>
                                            <select class="form-select matching-select" 
                                                    name="jawaban[<?php echo $idx; ?>]" 
                                                    data-index="<?php echo $idx; ?>"
                                                    onchange="saveMatchingAnswer()">
                                                <option value="">-- Pilih Jawaban --</option>
                                                <?php foreach ($matching_options as $option): 
                                                    $is_selected = ($saved_value === $option);
                                                ?>
                                                <option value="<?php echo escape($option); ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                    <?php echo escape($option); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                <!-- Submit info removed - finish button always available -->
                
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
                    <button type="button" class="btn btn-success" id="submitBtn" <?php echo $can_submit_now ? '' : 'disabled'; ?> onclick="submitExam()">
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
            <a href="<?php echo base_url('siswa/ujian/review.php?id=' . $sesi_id); ?>" class="btn btn-info btn-sm w-100 mb-2">
                <i class="fas fa-list-check"></i> Review Jawaban
            </a>
            <button type="button" class="btn btn-danger btn-sm w-100" id="submitBtnSidebar" onclick="submitExam()">
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
const MIN_SUBMIT_SECONDS = 0; // Minimum submit time restriction removed - finish button always available
let elapsedSeconds = <?php echo $elapsed_seconds; ?>; // Waktu yang sudah berlalu sejak mulai ujian
let canSubmit = true; // Finish button always available (no time restriction)
let secondsUntilSubmit = 0; // No countdown needed

// Initialize timer display function
function initializeTimer() {
    const timerDisplay = document.getElementById('timerDisplay');
    const timerEl = document.getElementById('examTimer');
    
    if (!timerDisplay || !timerEl) {
        console.error('Timer elements not found');
        return;
    }
    
    // Calculate initial display
    const hours = Math.floor(sisaWaktu / 3600);
    const minutes = Math.floor((sisaWaktu % 3600) / 60);
    const seconds = sisaWaktu % 60;
    
    let display = '';
    if (hours > 0) {
        display = `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    } else {
        display = `${minutes}:${String(seconds).padStart(2, '0')}`;
    }
    
    timerDisplay.textContent = display;
    
    // Update timer color based on remaining time
    if (sisaWaktu <= 300) { // 5 minutes
        timerEl.classList.add('danger');
        timerEl.classList.remove('warning');
    } else if (sisaWaktu <= 600) { // 10 minutes
        timerEl.classList.add('warning');
        timerEl.classList.remove('danger');
    } else {
        timerEl.classList.remove('danger', 'warning');
    }
    
    // Force timer visibility and ensure it's in header (not fixed position)
    timerEl.style.cssText = 'position: relative !important; top: auto !important; right: auto !important; bottom: auto !important; left: auto !important; display: flex !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important; background: rgba(255, 255, 255, 0.2) !important; color: white !important; padding: 8px 16px !important; border-radius: 6px !important; font-weight: bold !important; font-size: 1.1rem !important; border: 2px solid rgba(255, 255, 255, 0.3) !important; z-index: 1001 !important; white-space: nowrap !important; margin-left: 20px !important; margin-bottom: 0 !important; box-shadow: none !important; transform: none !important;';
    timerDisplay.style.cssText = 'display: inline-block !important; visibility: visible !important; opacity: 1 !important; color: white !important; font-weight: bold !important; font-size: 1.1rem !important;';
    
    // Ensure header is at top and timer is inside header
    const headerEl = document.querySelector('.exam-header');
    if (headerEl) {
        headerEl.style.position = 'sticky';
        headerEl.style.top = '0';
        headerEl.style.zIndex = '1000';
        // Ensure timer is a child of header
        if (timerEl.parentElement !== headerEl && headerEl.querySelector('.exam-timer') === null) {
            // Timer should already be in header, but just in case
            const timerParent = timerEl.parentElement;
            if (timerParent && timerParent !== headerEl) {
                headerEl.appendChild(timerEl);
            }
        }
    }
}

// Request fullscreen on load and initialize timer
document.addEventListener('DOMContentLoaded', () => {
    // Ensure body has hide-navbar class
    document.body.classList.add('hide-navbar');
    
    // Initialize timer display
    initializeTimer();
    
    // Also initialize after a short delay to ensure DOM is fully ready
    setTimeout(function() {
        initializeTimer();
    }, 100);
    
    // Initialize submit info
    updateSubmitCountdown();
});

// Function to update submit countdown - removed minimum time restriction
function updateSubmitCountdown() {
    // Finish button is always available - no countdown needed
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnSidebar = document.getElementById('submitBtnSidebar');
    const submitInfo = document.getElementById('submitInfo');
    
    // Always enable submit buttons
    if (submitBtn) {
        submitBtn.disabled = false;
    }
    if (submitBtnSidebar) {
        submitBtnSidebar.disabled = false;
    }
    // Hide submit info if it exists (no countdown needed)
    if (submitInfo) {
        submitInfo.classList.add('hidden');
    }
}

// Timer - update every second
const timerInterval = setInterval(() => {
    sisaWaktu--;
    elapsedSeconds++; // Increment elapsed time
    
    if (sisaWaktu <= 0) {
        clearInterval(timerInterval);
        submitExam();
        return;
    }
    
    const timerDisplay = document.getElementById('timerDisplay');
    const timerEl = document.getElementById('examTimer');
    
    if (!timerDisplay || !timerEl) {
        return; // Elements not found, skip update
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
    
    // Update display
    timerDisplay.textContent = display;
    
    // Ensure timer is visible and in header (not fixed position)
    timerEl.style.cssText = 'position: relative !important; top: auto !important; right: auto !important; bottom: auto !important; left: auto !important; display: flex !important; align-items: center !important; justify-content: center !important; visibility: visible !important; opacity: 1 !important; z-index: 1001 !important; white-space: nowrap !important; margin-left: 20px !important; margin-bottom: 0 !important; box-shadow: none !important; transform: none !important;';
    timerDisplay.style.cssText = 'display: inline-block !important; visibility: visible !important; opacity: 1 !important;';
    
    // Ensure header stays at top
    const headerEl = document.querySelector('.exam-header');
    if (headerEl) {
        headerEl.style.position = 'sticky';
        headerEl.style.top = '0';
        headerEl.style.zIndex = '1000';
    }
    
    // Update submit button status based on elapsed time
    updateSubmitCountdown();
    
    // Change timer color based on remaining time
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
    
    // Smooth icon transition
    if (sidebar.classList.contains('show')) {
        toggleIcon.style.transform = 'rotate(180deg)';
        setTimeout(() => {
            toggleIcon.classList.remove('fa-list');
            toggleIcon.classList.add('fa-times');
            toggleIcon.style.transform = 'rotate(0deg)';
        }, 200);
    } else {
        toggleIcon.style.transform = 'rotate(180deg)';
        setTimeout(() => {
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-list');
            toggleIcon.style.transform = 'rotate(0deg)';
        }, 200);
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
        // Trigger change event to ensure saveAnswer is called with proper conversion
        radio.dispatchEvent(new Event('change'));
    }
}

// Update selected state on page load
document.addEventListener('DOMContentLoaded', () => {
    // Update selected option
    const checked = document.querySelector('input[name="jawaban"]:checked');
    if (checked) {
        checked.closest('.option-item').classList.add('selected');
    }
});

function saveAnswer() {
    const form = document.getElementById('examForm');
    const formData = new FormData(form);
    formData.append('action', 'save');
    
    // Track time since page load for server-side analysis
    const pageLoadTime = performance.timing.navigationStart || performance.timeOrigin;
    const timeSincePageLoad = (Date.now() - pageLoadTime) / 1000; // Convert to seconds
    formData.append('time_since_page_load', timeSincePageLoad);
    
    // Handle pilihan ganda with shuffled options - convert shuffled key to original key
    const jawabanInput = form.querySelector('input[name="jawaban"]:checked');
    if (jawabanInput && jawabanInput.hasAttribute('data-original-key')) {
        const shuffledKey = jawabanInput.value;
        const originalKey = jawabanInput.getAttribute('data-original-key');
        // Replace with original key
        formData.set('jawaban', originalKey);
    }
    
    // Handle matching answers - convert to JSON array if matching question
    const matchingSelects = document.querySelectorAll('.matching-select');
    if (matchingSelects.length > 0) {
        const matchingAnswers = [];
        let hasAnswer = false;
        matchingSelects.forEach(select => {
            const index = parseInt(select.getAttribute('data-index'));
            const value = select.value;
            matchingAnswers[index] = value;
            if (value) {
                hasAnswer = true;
            }
        });
        // Remove jawaban field from form and add as JSON array
        formData.delete('jawaban');
        if (hasAnswer) {
            // Send as array for API to handle as JSON
            matchingAnswers.forEach((value, index) => {
                formData.append(`jawaban[${index}]`, value);
            });
        }
    }
    
    fetch('<?php echo base_url('api/save_answer.php'); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNavigation();
        } else if (data.rate_limit) {
            alert('Terlalu banyak request. Silakan tunggu sebentar.');
        } else {
            console.error('Error saving answer:', data.message);
        }
    })
    .catch(error => {
        console.error('Error saving answer:', error);
    });
}

function saveMatchingAnswer() {
    // Save matching answers as JSON array
    const matchingSelects = document.querySelectorAll('.matching-select');
    const matchingAnswers = [];
    matchingSelects.forEach(select => {
        const index = parseInt(select.getAttribute('data-index'));
        matchingAnswers[index] = select.value;
    });
    
    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('sesi_id', sesiId);
    formData.append('ujian_id', ujianId);
    formData.append('soal_id', soalId);
    
    // Track time since page load for server-side analysis
    const pageLoadTime = performance.timing.navigationStart || performance.timeOrigin;
    const timeSincePageLoad = (Date.now() - pageLoadTime) / 1000; // Convert to seconds
    formData.append('time_since_page_load', timeSincePageLoad);
    
    // Send as array format for API to handle as JSON
    matchingAnswers.forEach((value, index) => {
        formData.append(`jawaban[${index}]`, value);
    });
    
    fetch('<?php echo base_url('api/save_answer.php'); ?>', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNavigation();
        } else if (data.rate_limit) {
            alert('Terlalu banyak request. Silakan tunggu sebentar.');
        } else {
            console.error('Error saving answer:', data.message);
        }
    })
    .catch(error => {
        console.error('Error saving answer:', error);
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
    if (!canSubmit) {
        const remaining = MIN_SUBMIT_SECONDS - elapsedSeconds;
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        alert(`Anda harus menunggu ${minutes > 0 ? minutes + ' menit ' : ''}${seconds} detik lagi sebelum bisa menyelesaikan ujian.`);
        return;
    }
    
    if (confirm('Apakah Anda yakin ingin menyelesaikan ujian? Pastikan semua jawaban sudah diperiksa.')) {
        // Allow navigation to submit page
        if (typeof ExamSecurity !== 'undefined' && ExamSecurity.allowSubmit) {
            ExamSecurity.allowSubmit();
        }
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

// Request token function
function requestToken() {
    const btn = document.getElementById('btnRequestToken');
    const statusDiv = document.getElementById('tokenRequestStatus');
    
    if (!btn || !statusDiv) return;
    
    // Disable button
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim Request...';
    
    const formData = new FormData();
    formData.append('action', 'request');
    formData.append('sesi_id', sesiId);
    
    fetch('<?php echo base_url('api/request_token.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            statusDiv.className = 'alert alert-success mb-3';
            statusDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
            statusDiv.classList.remove('d-none');
            
            // Hide button and reload page after 2 seconds
            btn.style.display = 'none';
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            statusDiv.className = 'alert alert-danger mb-3';
            statusDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + data.message;
            statusDiv.classList.remove('d-none');
            
            // Re-enable button
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Request Token Baru';
        }
    })
    .catch(error => {
        console.error('Error requesting token:', error);
        statusDiv.className = 'alert alert-danger mb-3';
        statusDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Terjadi kesalahan saat mengirim request';
        statusDiv.classList.remove('d-none');
        
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Request Token Baru';
    });
}
</script>

<?php 
// For fullscreen exam, close exam-wrapper and add minimal scripts
if (isset($hide_navbar) && $hide_navbar): 
?>
    </div>
    <!-- Minimal scripts for exam page -->
    <!-- Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Load Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Load main.js FIRST to define UJAN object - MUST be before custom_js -->
    <script src="<?php echo asset_url('js/main.js'); ?>"></script>
    
    <!-- Verify UJAN is loaded -->
    <script>
        if (typeof UJAN === 'undefined') {
            console.error('UJAN object is not defined! main.js may not have loaded correctly.');
        } else {
            console.log('UJAN object loaded successfully');
        }
    </script>
    
    <?php if (isset($custom_js)): ?>
        <?php foreach ($custom_js as $js): ?>
            <script src="<?php echo asset_url('js/' . $js . '.js'); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php else: ?>
    </div>
    <?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php endif; ?>
