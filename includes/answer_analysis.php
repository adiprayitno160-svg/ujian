<?php
/**
 * Answer Analysis Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Server-side analysis for answer timing and patterns
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Track answer submission timing
 */
function track_answer_timing($sesi_id, $ujian_id, $siswa_id, $soal_id, $time_taken_seconds) {
    global $pdo;
    
    try {
        // Create answer_timing table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS answer_timing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_sesi INT NOT NULL,
            id_ujian INT NOT NULL,
            id_siswa INT NOT NULL,
            id_soal INT NOT NULL,
            time_taken_seconds INT NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sesi_ujian_siswa (id_sesi, id_ujian, id_siswa),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->prepare("INSERT INTO answer_timing 
                              (id_sesi, id_ujian, id_siswa, id_soal, time_taken_seconds) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$sesi_id, $ujian_id, $siswa_id, $soal_id, $time_taken_seconds]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Track answer timing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Analyze answer timing for suspicious patterns
 */
function analyze_answer_timing($sesi_id, $ujian_id, $siswa_id) {
    global $pdo;
    
    try {
        // Get all answer timings for this student
        $stmt = $pdo->prepare("SELECT * FROM answer_timing 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? 
                              ORDER BY timestamp ASC");
        $stmt->execute([$sesi_id, $ujian_id, $siswa_id]);
        $timings = $stmt->fetchAll();
        
        if (count($timings) < 3) {
            return ['suspicious' => false, 'reason' => 'Insufficient data'];
        }
        
        $suspicious_patterns = [];
        
        // Check for too fast answers (less than 5 seconds average)
        $avg_time = array_sum(array_column($timings, 'time_taken_seconds')) / count($timings);
        if ($avg_time < 5) {
            $suspicious_patterns[] = 'answers_too_fast';
        }
        
        // Check for uniform timing (suspicious if all answers take similar time)
        $times = array_column($timings, 'time_taken_seconds');
        $variance = variance($times);
        $mean = $avg_time;
        $coefficient_of_variation = $mean > 0 ? sqrt($variance) / $mean : 0;
        
        if ($coefficient_of_variation < 0.1 && count($timings) > 5) {
            $suspicious_patterns[] = 'uniform_timing';
        }
        
        // Check for extremely fast individual answers (less than 2 seconds)
        $too_fast_count = 0;
        foreach ($times as $time) {
            if ($time < 2) {
                $too_fast_count++;
            }
        }
        if ($too_fast_count > count($timings) * 0.3) {
            $suspicious_patterns[] = 'many_fast_answers';
        }
        
        return [
            'suspicious' => !empty($suspicious_patterns),
            'patterns' => $suspicious_patterns,
            'avg_time' => $avg_time,
            'variance' => $variance,
            'coefficient_of_variation' => $coefficient_of_variation
        ];
    } catch (PDOException $e) {
        error_log("Analyze answer timing error: " . $e->getMessage());
        return ['suspicious' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Calculate variance
 */
function variance($array) {
    $count = count($array);
    if ($count < 2) return 0;
    
    $mean = array_sum($array) / $count;
    $sum_squared_diff = 0;
    
    foreach ($array as $value) {
        $sum_squared_diff += pow($value - $mean, 2);
    }
    
    return $sum_squared_diff / $count;
}

/**
 * Track answer changes (audit trail)
 */
function track_answer_change($sesi_id, $ujian_id, $siswa_id, $soal_id, $old_answer, $new_answer, $changed_by = 'user') {
    global $pdo;
    
    try {
        // Create answer_history table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS answer_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_sesi INT NOT NULL,
            id_ujian INT NOT NULL,
            id_siswa INT NOT NULL,
            id_soal INT NOT NULL,
            old_answer TEXT,
            new_answer TEXT,
            old_answer_json TEXT,
            new_answer_json TEXT,
            changed_by VARCHAR(50) DEFAULT 'user',
            ip_address VARCHAR(45),
            device_info TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sesi_ujian_siswa (id_sesi, id_ujian, id_siswa),
            INDEX idx_timestamp (timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $stmt = $pdo->prepare("INSERT INTO answer_history 
                              (id_sesi, id_ujian, id_siswa, id_soal, old_answer, new_answer, 
                               old_answer_json, new_answer_json, changed_by, ip_address, device_info) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $sesi_id,
            $ujian_id,
            $siswa_id,
            $soal_id,
            is_array($old_answer) ? null : $old_answer,
            is_array($new_answer) ? null : $new_answer,
            is_array($old_answer) ? json_encode($old_answer) : null,
            is_array($new_answer) ? json_encode($new_answer) : null,
            $changed_by,
            get_client_ip(),
            get_device_info()
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Track answer change error: " . $e->getMessage());
        return false;
    }
}

/**
 * Analyze answer change patterns
 */
function analyze_answer_changes($sesi_id, $ujian_id, $siswa_id) {
    global $pdo;
    
    try {
        // Get answer change history
        $stmt = $pdo->prepare("SELECT * FROM answer_history 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? 
                              ORDER BY timestamp ASC");
        $stmt->execute([$sesi_id, $ujian_id, $siswa_id]);
        $changes = $stmt->fetchAll();
        
        if (empty($changes)) {
            return ['suspicious' => false, 'reason' => 'No changes'];
        }
        
        $suspicious_patterns = [];
        
        // Check for too many changes
        if (count($changes) > 20) {
            $suspicious_patterns[] = 'too_many_changes';
        }
        
        // Check for rapid changes (multiple changes in short time)
        $rapid_changes = 0;
        for ($i = 1; $i < count($changes); $i++) {
            $time_diff = strtotime($changes[$i]['timestamp']) - strtotime($changes[$i-1]['timestamp']);
            if ($time_diff < 5) { // Less than 5 seconds between changes
                $rapid_changes++;
            }
        }
        if ($rapid_changes > 5) {
            $suspicious_patterns[] = 'rapid_changes';
        }
        
        // Check for changes near end of exam (possible last-minute cheating)
        $stmt = $pdo->prepare("SELECT waktu_selesai FROM sesi_ujian WHERE id = ?");
        $stmt->execute([$sesi_id]);
        $sesi = $stmt->fetch();
        
        if ($sesi) {
            $waktu_selesai = strtotime($sesi['waktu_selesai']);
            $last_minute_changes = 0;
            foreach ($changes as $change) {
                $change_time = strtotime($change['timestamp']);
                if ($waktu_selesai - $change_time < 300) { // Last 5 minutes
                    $last_minute_changes++;
                }
            }
            if ($last_minute_changes > 10) {
                $suspicious_patterns[] = 'last_minute_changes';
            }
        }
        
        return [
            'suspicious' => !empty($suspicious_patterns),
            'patterns' => $suspicious_patterns,
            'total_changes' => count($changes),
            'rapid_changes' => $rapid_changes
        ];
    } catch (PDOException $e) {
        error_log("Analyze answer changes error: " . $e->getMessage());
        return ['suspicious' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Validate answer submission (server-side)
 */
function validate_answer_submission($sesi_id, $ujian_id, $siswa_id, $soal_id, $time_since_page_load = null) {
    global $pdo;
    
    try {
        // Check if student is in exam mode
        $stmt = $pdo->prepare("SELECT * FROM nilai 
                              WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ? 
                              AND status = 'sedang_mengerjakan'");
        $stmt->execute([$sesi_id, $ujian_id, $siswa_id]);
        $nilai = $stmt->fetch();
        
        if (!$nilai) {
            return ['valid' => false, 'message' => 'Not in exam mode'];
        }
        
        // Check if answer is being submitted too quickly (less than 2 seconds)
        if ($time_since_page_load !== null && $time_since_page_load < 2) {
            log_security_event($siswa_id, $sesi_id, 'suspicious_fast_answer', 
                "Answer submitted too quickly: {$time_since_page_load} seconds", true);
            return ['valid' => false, 'message' => 'Answer submitted too quickly', 'suspicious' => true];
        }
        
        // Check if soal exists and is part of this ujian
        $stmt = $pdo->prepare("SELECT * FROM soal WHERE id = ? AND id_ujian = ?");
        $stmt->execute([$soal_id, $ujian_id]);
        $soal = $stmt->fetch();
        
        if (!$soal) {
            return ['valid' => false, 'message' => 'Invalid soal'];
        }
        
        // Check session time
        $stmt = $pdo->prepare("SELECT * FROM sesi_ujian WHERE id = ?");
        $stmt->execute([$sesi_id]);
        $sesi = $stmt->fetch();
        
        if (!$sesi || $sesi['status'] !== 'aktif') {
            return ['valid' => false, 'message' => 'Session not active'];
        }
        
        $now = new DateTime();
        $waktu_selesai = new DateTime($sesi['waktu_selesai']);
        
        if ($now > $waktu_selesai) {
            return ['valid' => false, 'message' => 'Session expired'];
        }
        
        return ['valid' => true];
    } catch (PDOException $e) {
        error_log("Validate answer submission error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'Validation error'];
    }
}


