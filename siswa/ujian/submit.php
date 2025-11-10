<?php
/**
 * Submit Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

global $pdo;

$sesi_id = intval($_POST['sesi_id'] ?? $_GET['sesi_id'] ?? 0);
$jawaban = $_POST['jawaban'] ?? [];

if (!$sesi_id) {
    redirect('siswa/ujian/list.php');
}

// Get sesi to get ujian_id
$sesi = get_sesi($sesi_id);
if (!$sesi) {
    redirect('siswa/ujian/list.php');
}

$ujian_id = $sesi['id_ujian'];

// Verify session
$stmt = $pdo->prepare("SELECT * FROM nilai WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
$stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
$nilai = $stmt->fetch();

if (!$nilai || $nilai['status'] === 'selesai') {
    redirect('siswa/ujian/list.php');
}

// Get ujian info
$ujian = get_ujian($ujian_id);

// Validate minimum submit time (server-side validation)
$min_submit_minutes = $ujian['min_submit_minutes'] ?? DEFAULT_MIN_SUBMIT_MINUTES;
if ($min_submit_minutes > 0 && $nilai['waktu_mulai']) {
    $waktu_mulai = new DateTime($nilai['waktu_mulai']);
    $now = new DateTime();
    $elapsed_seconds = $now->getTimestamp() - $waktu_mulai->getTimestamp();
    $min_submit_seconds = $min_submit_minutes * 60;
    
    if ($elapsed_seconds < $min_submit_seconds) {
        $remaining_seconds = $min_submit_seconds - $elapsed_seconds;
        $remaining_minutes = ceil($remaining_seconds / 60);
        $_SESSION['error_message'] = "Anda harus menunggu minimal {$min_submit_minutes} menit setelah mulai ujian sebelum bisa menyelesaikan. Silakan tunggu {$remaining_minutes} menit lagi.";
        redirect('siswa/ujian/take.php?id=' . $sesi_id);
    }
}

try {
    $pdo->beginTransaction();
    
    // Save all answers
    foreach ($jawaban as $soal_id => $jawaban_value) {
        $soal_id = intval($soal_id);
        
        if (is_array($jawaban_value)) {
            $jawaban_json = json_encode($jawaban_value);
            $jawaban_value = null;
        } else {
            $jawaban_json = null;
            $jawaban_value = sanitize($jawaban_value);
        }
        
        $stmt = $pdo->prepare("INSERT INTO jawaban_siswa 
                              (id_sesi, id_ujian, id_soal, id_siswa, jawaban, jawaban_json, waktu_submit) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE 
                              jawaban = VALUES(jawaban), 
                              jawaban_json = VALUES(jawaban_json),
                              waktu_submit = NOW()");
        $stmt->execute([$sesi_id, $ujian_id, $soal_id, $_SESSION['user_id'], $jawaban_value, $jawaban_json]);
    }
    
    // Calculate score
    $stmt = $pdo->prepare("SELECT s.*, js.jawaban, js.jawaban_json 
                          FROM soal s
                          LEFT JOIN jawaban_siswa js ON s.id = js.id_soal 
                          AND js.id_sesi = ? AND js.id_ujian = ? AND js.id_siswa = ?
                          WHERE s.id_ujian = ?");
    $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id'], $ujian_id]);
    $soal_list = $stmt->fetchAll();
    
    $total_score = 0;
    $total_bobot = 0;
    
    foreach ($soal_list as $soal) {
        $bobot = floatval($soal['bobot']);
        $total_bobot += $bobot;
        
        $jawaban_siswa = $soal['jawaban'] ?? '';
        $kunci = $soal['kunci_jawaban'] ?? '';
        
        $score = 0;
        
        if ($soal['tipe_soal'] === 'pilihan_ganda' || $soal['tipe_soal'] === 'benar_salah') {
            if (strtoupper(trim($jawaban_siswa)) === strtoupper(trim($kunci))) {
                $score = $bobot;
            }
        } elseif ($soal['tipe_soal'] === 'isian_singkat') {
            $kunci_list = array_map('trim', explode(',', $kunci));
            $jawaban_trim = strtolower(trim($jawaban_siswa));
            foreach ($kunci_list as $k) {
                if (strtolower(trim($k)) === $jawaban_trim) {
                    $score = $bobot;
                    break;
                }
            }
        } elseif ($soal['tipe_soal'] === 'matching') {
            // Matching logic
            $jawaban_json = json_decode($soal['jawaban_json'], true);
            if ($jawaban_json) {
                $stmt_match = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan");
                $stmt_match->execute([$soal['id']]);
                $matches = $stmt_match->fetchAll();
                
                $correct = 0;
                $total_match = count($matches);
                
                foreach ($matches as $idx => $match) {
                    if (isset($jawaban_json[$idx]) && $jawaban_json[$idx] === $match['item_kanan']) {
                        $correct++;
                    }
                }
                
                if ($total_match > 0) {
                    $score = ($correct / $total_match) * $bobot;
                }
            }
        } elseif ($soal['tipe_soal'] === 'esai') {
            // Esai will be graded manually or by AI later
            $score = 0;
        }
        
        $total_score += $score;
    }
    
    $nilai_akhir = $total_bobot > 0 ? ($total_score / $total_bobot) * 100 : 0;
    
    // Update nilai
    $stmt = $pdo->prepare("UPDATE nilai SET 
                          status = 'selesai', 
                          nilai = ?, 
                          waktu_selesai = NOW() 
                          WHERE id = ?");
    $stmt->execute([$nilai_akhir, $nilai['id']]);
    
    $pdo->commit();
    
    // Clear exam mode - exam is finished
    if (function_exists('clear_exam_mode')) {
        clear_exam_mode();
    }
    
    log_activity('submit_ujian', 'nilai', $nilai['id']);
    
    redirect('siswa/ujian/hasil.php?id=' . $sesi_id);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Submit ujian error: " . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan saat menyimpan jawaban';
    redirect('siswa/ujian/take.php?id=' . $sesi_id);
}
