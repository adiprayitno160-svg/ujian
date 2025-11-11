<?php
/**
 * Submit Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ai_correction.php';
require_once __DIR__ . '/../../includes/notification_functions.php';

// Define constant to indicate we're on exam page (prevents redirect loops)
if (!defined('ON_EXAM_PAGE')) {
    define('ON_EXAM_PAGE', true);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['on_exam_page'] = true;
}

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

// Minimum submit time restriction has been removed - students can finish anytime
// Only restriction is time limit (waktu habis) or manual finish button click

try {
    $pdo->beginTransaction();
    
    // Save all answers and lock them (after verification/submit)
    foreach ($jawaban as $soal_id => $jawaban_value) {
        $soal_id = intval($soal_id);
        
        if (is_array($jawaban_value)) {
            $jawaban_json = json_encode($jawaban_value);
            $jawaban_value = null;
        } else {
            $jawaban_json = null;
            $jawaban_value = sanitize($jawaban_value);
        }
        
        // Save answer and lock it (only if not already locked)
        $stmt = $pdo->prepare("INSERT INTO jawaban_siswa 
                              (id_sesi, id_ujian, id_soal, id_siswa, jawaban, jawaban_json, waktu_submit, is_locked, locked_at) 
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, NOW())
                              ON DUPLICATE KEY UPDATE 
                              jawaban = VALUES(jawaban), 
                              jawaban_json = VALUES(jawaban_json),
                              waktu_submit = NOW(),
                              is_locked = 1,
                              locked_at = NOW()");
        $stmt->execute([$sesi_id, $ujian_id, $soal_id, $_SESSION['user_id'], $jawaban_value, $jawaban_json]);
    }
    
    // Mark answers as locked in nilai table
    $stmt = $pdo->prepare("UPDATE nilai SET answers_locked = 1 WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
    $stmt->execute([$sesi_id, $ujian_id, $_SESSION['user_id']]);
    
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
            // Isian singkat: try exact match first, if no match and AI enabled, try AI
            $kunci_list = array_map('trim', explode(',', $kunci));
            $jawaban_trim = strtolower(trim($jawaban_siswa));
            $found_match = false;
            
            foreach ($kunci_list as $k) {
                if (strtolower(trim($k)) === $jawaban_trim) {
                    $score = $bobot;
                    $found_match = true;
                    break;
                }
            }
            
            // If no exact match and AI is enabled, try AI correction for more flexible matching
            if (!$found_match && is_ai_correction_enabled($ujian_id) && !empty(trim($jawaban_siswa))) {
                $stmt_jawaban = $pdo->prepare("SELECT id FROM jawaban_siswa 
                                              WHERE id_sesi = ? AND id_ujian = ? AND id_soal = ? AND id_siswa = ?");
                $stmt_jawaban->execute([$sesi_id, $ujian_id, $soal['id'], $_SESSION['user_id']]);
                $jawaban_data = $stmt_jawaban->fetch();
                
                if ($jawaban_data) {
                    $api_key = get_ai_api_key($ujian_id);
                    $ai_result = correct_answer_ai($ujian_id, $soal['id'], $jawaban_data['id'], $api_key, 'isian_singkat');
                    
                    if ($ai_result['success'] && $ai_result['nilai'] !== null) {
                        // Use AI score if it's high enough (>= 70% similarity)
                        if ($ai_result['nilai'] >= 70) {
                            $score = ($ai_result['nilai'] / 100) * $bobot;
                        }
                    }
                }
            }
        } elseif ($soal['tipe_soal'] === 'matching') {
            // Matching logic - use jawaban_json from jawaban_siswa (from JOIN), not from soal
            // $soal['jawaban_json'] here is from jawaban_siswa table because of the JOIN
            $jawaban_siswa_json = $soal['jawaban_json'] ?? null;
            if ($jawaban_siswa_json) {
                $jawaban_array = json_decode($jawaban_siswa_json, true);
                if (is_array($jawaban_array)) {
                    $stmt_match = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ? ORDER BY urutan");
                    $stmt_match->execute([$soal['id']]);
                    $matches = $stmt_match->fetchAll();
                    
                    $correct = 0;
                    $total_match = count($matches);
                    
                    // Compare jawaban siswa dengan kunci jawaban (item_kanan)
                    // Jawaban siswa bisa dalam format array dengan index atau object
                    foreach ($matches as $idx => $match) {
                        if (isset($jawaban_array[$idx])) {
                            // Handle both array and object formats
                            $jawaban_item = is_array($jawaban_array[$idx]) 
                                ? ($jawaban_array[$idx]['jawaban'] ?? $jawaban_array[$idx]['item_kanan'] ?? $jawaban_array[$idx]) 
                                : $jawaban_array[$idx];
                            
                            // Case-insensitive comparison
                            if (trim(strtolower($jawaban_item)) === trim(strtolower($match['item_kanan']))) {
                                $correct++;
                            }
                        }
                    }
                    
                    if ($total_match > 0) {
                        $score = ($correct / $total_match) * $bobot;
                    }
                }
            }
        } elseif (requires_ai_correction($soal['tipe_soal'])) {
            // Soal yang memerlukan AI correction (esai, uraian singkat, rangkuman, cerita, dll)
            // Check if AI correction is enabled for this exam
            $ai_enabled = is_ai_correction_enabled($ujian_id);
            
            if ($ai_enabled && !empty(trim($jawaban_siswa ?? ''))) {
                // Get jawaban_id
                $stmt_jawaban = $pdo->prepare("SELECT id FROM jawaban_siswa 
                                              WHERE id_sesi = ? AND id_ujian = ? AND id_soal = ? AND id_siswa = ?");
                $stmt_jawaban->execute([$sesi_id, $ujian_id, $soal['id'], $_SESSION['user_id']]);
                $jawaban_data = $stmt_jawaban->fetch();
                
                if ($jawaban_data) {
                    // Call AI correction
                    $api_key = get_ai_api_key($ujian_id);
                    $ai_result = correct_answer_ai($ujian_id, $soal['id'], $jawaban_data['id'], $api_key, $soal['tipe_soal']);
                    
                    if ($ai_result['success'] && $ai_result['nilai'] !== null) {
                        // Convert AI score (0-100 scale) to bobot scale
                        $score = ($ai_result['nilai'] / 100) * $bobot;
                        
                        // Save AI feedback to jawaban_siswa (if field exists) or create feedback record
                        try {
                            // Try to update jawaban_siswa with AI feedback
                            // Note: This assumes there might be fields for AI feedback, if not, we'll log it separately
                            $feedback_json = json_encode([
                                'ai_feedback' => $ai_result['feedback'],
                                'ai_kekuatan' => $ai_result['kekuatan'],
                                'ai_kelemahan' => $ai_result['kelemahan'],
                                'ai_saran' => $ai_result['saran'],
                                'ai_score' => $ai_result['nilai']
                            ]);
                            
                            // Store in a comment or separate field if available
                            // For now, we'll rely on ai_correction_log for feedback retrieval
                        } catch (Exception $e) {
                            error_log("Error saving AI feedback: " . $e->getMessage());
                        }
                    } else {
                        // AI correction failed, set score to 0 (will be graded manually later)
                        $score = 0;
                        error_log("AI correction failed for soal_id: " . $soal['id'] . ", error: " . ($ai_result['message'] ?? 'Unknown'));
                    }
                } else {
                    $score = 0;
                }
            } else {
                // AI correction not enabled or empty answer - will be graded manually
                $score = 0;
            }
        } else {
            // Unknown question type
            $score = 0;
        }
        
        $total_score += $score;
    }
    
    $nilai_akhir = $total_bobot > 0 ? ($total_score / $total_bobot) * 100 : 0;
    
    // Check if AI correction was used
    $ai_corrected = false;
    foreach ($soal_list as $soal) {
        if (requires_ai_correction($soal['tipe_soal']) && is_ai_correction_enabled($ujian_id)) {
            $ai_corrected = true;
            break;
        }
    }
    
    // Update nilai
    $stmt = $pdo->prepare("UPDATE nilai SET 
                          status = 'selesai', 
                          nilai = ?, 
                          waktu_selesai = NOW(),
                          ai_corrected = ?
                          WHERE id = ?");
    $stmt->execute([$nilai_akhir, $ai_corrected ? 1 : 0, $nilai['id']]);
    
    $pdo->commit();
    
    // Create notification for nilai keluar (if notification functions available)
    if (function_exists('create_nilai_notification')) {
        try {
            create_nilai_notification($nilai['id']);
        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            // Don't fail the submit if notification fails
        }
    }
    
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
