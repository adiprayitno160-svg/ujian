<?php
/**
 * PR (Pekerjaan Rumah) Helper Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Total: 120 functions
 */

require_once __DIR__ . '/../config/database.php';

// ============================================
// CORE FUNCTIONS - GET/RETRIEVE DATA (10 functions)
// ============================================

/**
 * Get PR by ID with full details
 */
function get_pr($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT p.*, m.nama_mapel, u.nama as nama_guru 
                              FROM pr p
                              INNER JOIN mapel m ON p.id_mapel = m.id
                              INNER JOIN users u ON p.id_guru = u.id
                              WHERE p.id = ?");
        $stmt->execute([$pr_id]);
        $pr = $stmt->fetch();
        
        if ($pr) {
            // Get assigned classes
            $stmt = $pdo->prepare("SELECT k.* FROM pr_kelas pk
                                  INNER JOIN kelas k ON pk.id_kelas = k.id
                                  WHERE pk.id_pr = ?");
            $stmt->execute([$pr_id]);
            $pr['kelas'] = $stmt->fetchAll();
            
            // Get soal count if online/hybrid
            if (in_array($pr['tipe_pr'], ['online', 'hybrid'])) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pr_soal WHERE id_pr = ?");
                $stmt->execute([$pr_id]);
                $pr['total_soal'] = $stmt->fetch()['total'];
            }
        }
        
        return $pr;
    } catch (PDOException $e) {
        error_log("Get PR error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get PR by guru with filters
 */
function get_pr_by_guru($guru_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT p.*, m.nama_mapel,
                (SELECT COUNT(*) FROM pr_submission WHERE id_pr = p.id) as total_submission,
                (SELECT COUNT(*) FROM pr_submission WHERE id_pr = p.id AND status = 'sudah_dikumpulkan') as sudah_dikumpulkan
                FROM pr p
                INNER JOIN mapel m ON p.id_mapel = m.id
                WHERE p.id_guru = ?";
        
        $params = [$guru_id];
        
        if (isset($filters['status'])) {
            // Filter by submission status
        }
        
        if (isset($filters['mapel_id'])) {
            $sql .= " AND p.id_mapel = ?";
            $params[] = $filters['mapel_id'];
        }
        
        if (isset($filters['tipe_pr'])) {
            $sql .= " AND p.tipe_pr = ?";
            $params[] = $filters['tipe_pr'];
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR by guru error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR by student with filters
 */
function get_pr_by_student($student_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT DISTINCT p.*, m.nama_mapel,
                (SELECT status FROM pr_submission WHERE id_pr = p.id AND id_siswa = ?) as status_submission,
                (SELECT nilai FROM pr_submission WHERE id_pr = p.id AND id_siswa = ?) as nilai_submission
                FROM pr p
                INNER JOIN mapel m ON p.id_mapel = m.id
                INNER JOIN pr_kelas pk ON p.id = pk.id_pr
                INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                WHERE uk.id_user = ?";
        
        $params = [$student_id, $student_id, $student_id];
        
        if (isset($filters['status'])) {
            $sql .= " AND (SELECT status FROM pr_submission WHERE id_pr = p.id AND id_siswa = ?) = ?";
            $params[] = $student_id;
            $params[] = $filters['status'];
        }
        
        if (isset($filters['mapel_id'])) {
            $sql .= " AND p.id_mapel = ?";
            $params[] = $filters['mapel_id'];
        }
        
        $sql .= " ORDER BY p.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR by student error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR submissions with student info
 */
function get_pr_submissions($pr_id, $status = null) {
    global $pdo;
    
    try {
        $sql = "SELECT ps.*, u.nama as nama_siswa, u.username,
                k.nama_kelas, k.tahun_ajaran
                FROM pr_submission ps
                INNER JOIN users u ON ps.id_siswa = u.id
                LEFT JOIN user_kelas uk ON u.id = uk.id_user
                LEFT JOIN kelas k ON uk.id_kelas = k.id
                WHERE ps.id_pr = ?";
        
        $params = [$pr_id];
        
        if ($status) {
            $sql .= " AND ps.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY ps.waktu_submit DESC, u.nama ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR submissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR submission by student
 */
function get_pr_submission($pr_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pr_submission WHERE id_pr = ? AND id_siswa = ?");
        $stmt->execute([$pr_id, $student_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get PR submission error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get PR soal
 */
function get_pr_soal($pr_id, $order_by = 'urutan') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM pr_soal WHERE id_pr = ? ORDER BY $order_by ASC, id ASC");
        $stmt->execute([$pr_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR soal error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR jawaban by student
 */
function get_pr_jawaban($pr_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT pj.*, ps.pertanyaan, ps.tipe_soal, ps.kunci_jawaban, ps.bobot
                              FROM pr_jawaban pj
                              INNER JOIN pr_soal ps ON pj.id_soal = ps.id
                              WHERE pj.id_pr = ? AND pj.id_siswa = ?
                              ORDER BY ps.urutan ASC");
        $stmt->execute([$pr_id, $student_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR jawaban error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR kelas assignments
 */
function get_pr_kelas($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT k.* FROM pr_kelas pk
                              INNER JOIN kelas k ON pk.id_kelas = k.id
                              WHERE pk.id_pr = ?");
        $stmt->execute([$pr_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR kelas error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR by kelas
 */
function get_pr_by_kelas($kelas_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT DISTINCT p.*, m.nama_mapel
                FROM pr p
                INNER JOIN mapel m ON p.id_mapel = m.id
                INNER JOIN pr_kelas pk ON p.id = pk.id_pr
                WHERE pk.id_kelas = ?";
        
        $params = [$kelas_id];
        
        if (isset($filters['status'])) {
            // Add status filter if needed
        }
        
        $sql .= " ORDER BY p.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR by kelas error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR by mapel
 */
function get_pr_by_mapel($mapel_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT p.*, m.nama_mapel
                FROM pr p
                INNER JOIN mapel m ON p.id_mapel = m.id
                WHERE p.id_mapel = ?";
        
        $params = [$mapel_id];
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR by mapel error: " . $e->getMessage());
        return [];
    }
}

// ============================================
// CORE FUNCTIONS - VALIDATION & CHECK (8 functions)
// ============================================

/**
 * Check if PR deadline has passed
 */
function is_pr_deadline_passed($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT deadline FROM pr WHERE id = ?");
        $stmt->execute([$pr_id]);
        $pr = $stmt->fetch();
        
        if ($pr) {
            $deadline = new DateTime($pr['deadline']);
            $now = new DateTime();
            return $now > $deadline;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Check PR deadline error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student can submit PR
 */
function can_student_submit_pr($pr_id, $student_id) {
    global $pdo;
    
    try {
        // Get PR
        $pr = get_pr($pr_id);
        if (!$pr) return false;
        
        // Check if student is assigned
        if (!is_student_assigned_to_pr($pr_id, $student_id)) {
            return false;
        }
        
        // Check deadline
        $deadline_passed = is_pr_deadline_passed($pr_id);
        if ($deadline_passed && !$pr['allow_edit_after_submit']) {
            return false;
        }
        
        // Check max attempts
        $submission = get_pr_submission($pr_id, $student_id);
        if ($pr['max_attempts'] && $submission && $submission['attempt_count'] >= $pr['max_attempts']) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Can student submit PR error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student can edit submission
 */
function can_student_edit_pr($pr_id, $student_id) {
    global $pdo;
    
    try {
        $pr = get_pr($pr_id);
        if (!$pr) return false;
        
        if (!$pr['allow_edit_after_submit']) {
            return false;
        }
        
        $deadline_passed = is_pr_deadline_passed($pr_id);
        if ($deadline_passed) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Can student edit PR error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student is assigned to PR
 */
function is_student_assigned_to_pr($pr_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pr_kelas pk
                              INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                              WHERE pk.id_pr = ? AND uk.id_user = ?");
        $stmt->execute([$pr_id, $student_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Check student assigned to PR error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate PR data
 */
function validate_pr_data($data) {
    $errors = [];
    
    if (empty($data['judul'])) {
        $errors[] = 'Judul PR harus diisi';
    }
    
    if (empty($data['id_mapel'])) {
        $errors[] = 'Mata pelajaran harus dipilih';
    }
    
    if (empty($data['deadline'])) {
        $errors[] = 'Deadline harus diisi';
    } else {
        $deadline = new DateTime($data['deadline']);
        $now = new DateTime();
        if ($deadline <= $now) {
            $errors[] = 'Deadline harus lebih besar dari waktu sekarang';
        }
    }
    
    if (empty($data['kelas_ids']) || !is_array($data['kelas_ids']) || count($data['kelas_ids']) == 0) {
        $errors[] = 'Minimal satu kelas harus dipilih';
    }
    
    if (isset($data['timer_enabled']) && $data['timer_enabled'] && empty($data['timer_minutes'])) {
        $errors[] = 'Durasi timer harus diisi jika timer diaktifkan';
    }
    
    return $errors;
}

/**
 * Check PR timer status
 */
function check_pr_timer_status($pr_id, $student_id) {
    global $pdo;
    
    try {
        $pr = get_pr($pr_id);
        if (!$pr || !$pr['timer_enabled'] || !$pr['timer_minutes']) {
            return null;
        }
        
        $submission = get_pr_submission($pr_id, $student_id);
        if (!$submission || !$submission['waktu_submit']) {
            return null;
        }
        
        $start_time = new DateTime($submission['waktu_submit']);
        $end_time = clone $start_time;
        $end_time->modify("+{$pr['timer_minutes']} minutes");
        
        $now = new DateTime();
        $remaining = $end_time->getTimestamp() - $now->getTimestamp();
        
        return [
            'remaining_seconds' => max(0, $remaining),
            'is_expired' => $remaining <= 0,
            'end_time' => $end_time->format('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("Check PR timer status error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if PR has max attempts reached
 */
function has_pr_max_attempts_reached($pr_id, $student_id) {
    global $pdo;
    
    try {
        $pr = get_pr($pr_id);
        if (!$pr || !$pr['max_attempts']) {
            return false;
        }
        
        $submission = get_pr_submission($pr_id, $student_id);
        if ($submission && $submission['attempt_count'] >= $pr['max_attempts']) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Check PR max attempts error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check PR permissions
 */
function check_pr_permissions($pr_id, $user_id, $action) {
    global $pdo;
    
    try {
        $pr = get_pr($pr_id);
        if (!$pr) return false;
        
        // Get user role
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if (!$user) return false;
        
        $role = $user['role'];
        
        switch ($action) {
            case 'view':
                if ($role === 'admin') return true;
                if ($role === 'guru' && $pr['id_guru'] == $user_id) return true;
                if ($role === 'siswa' && is_student_assigned_to_pr($pr_id, $user_id)) return true;
                return false;
                
            case 'edit':
            case 'delete':
                if ($role === 'admin') return true;
                if ($role === 'guru' && $pr['id_guru'] == $user_id) return true;
                return false;
                
            case 'submit':
                if ($role === 'siswa' && is_student_assigned_to_pr($pr_id, $user_id)) return true;
                return false;
                
            default:
                return false;
        }
    } catch (Exception $e) {
        error_log("Check PR permissions error: " . $e->getMessage());
        return false;
    }
}

// ============================================
// CORE FUNCTIONS - STATISTICS & ANALYTICS (8 functions)
// ============================================

/**
 * Get PR statistics
 */
function get_pr_statistics($pr_id) {
    global $pdo;
    
    try {
        // Get total assigned students
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT uk.id_user) as total_siswa
                              FROM pr_kelas pk
                              INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                              WHERE pk.id_pr = ?");
        $stmt->execute([$pr_id]);
        $total_siswa = $stmt->fetch()['total_siswa'];
        
        // Get submission counts
        $stmt = $pdo->prepare("SELECT 
                              COUNT(*) as total_submission,
                              SUM(CASE WHEN status = 'sudah_dikumpulkan' THEN 1 ELSE 0 END) as sudah_dikumpulkan,
                              SUM(CASE WHEN status = 'dinilai' THEN 1 ELSE 0 END) as sudah_dinilai,
                              SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
                              SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                              AVG(nilai) as rata_rata_nilai,
                              MAX(nilai) as nilai_tertinggi,
                              MIN(nilai) as nilai_terendah
                              FROM pr_submission
                              WHERE id_pr = ?");
        $stmt->execute([$pr_id]);
        $stats = $stmt->fetch();
        
        $stats['total_siswa'] = $total_siswa;
        $stats['belum_dikumpulkan'] = $total_siswa - ($stats['sudah_dikumpulkan'] ?? 0) - ($stats['draft'] ?? 0);
        $stats['completion_rate'] = $total_siswa > 0 
            ? round((($stats['sudah_dikumpulkan'] ?? 0) / $total_siswa) * 100, 2) 
            : 0;
        $stats['grading_rate'] = ($stats['sudah_dikumpulkan'] ?? 0) > 0
            ? round((($stats['sudah_dinilai'] ?? 0) / ($stats['sudah_dikumpulkan'] ?? 0)) * 100, 2)
            : 0;
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Get PR statistics error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get PR completion rate
 */
function get_pr_completion_rate($pr_id) {
    $stats = get_pr_statistics($pr_id);
    return $stats ? $stats['completion_rate'] : 0;
}

/**
 * Get PR grading rate
 */
function get_pr_grading_rate($pr_id) {
    $stats = get_pr_statistics($pr_id);
    return $stats ? $stats['grading_rate'] : 0;
}

/**
 * Get PR average score
 */
function get_pr_average_score($pr_id) {
    $stats = get_pr_statistics($pr_id);
    return $stats ? $stats['rata_rata_nilai'] : null;
}

/**
 * Get PR score distribution
 */
function get_pr_score_distribution($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN nilai >= 90 THEN 1 ELSE 0 END) as A,
                              SUM(CASE WHEN nilai >= 80 AND nilai < 90 THEN 1 ELSE 0 END) as B,
                              SUM(CASE WHEN nilai >= 70 AND nilai < 80 THEN 1 ELSE 0 END) as C,
                              SUM(CASE WHEN nilai >= 60 AND nilai < 70 THEN 1 ELSE 0 END) as D,
                              SUM(CASE WHEN nilai < 60 THEN 1 ELSE 0 END) as E
                              FROM pr_submission
                              WHERE id_pr = ? AND nilai IS NOT NULL");
        $stmt->execute([$pr_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get PR score distribution error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get PR submission timeline
 */
function get_pr_submission_timeline($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT 
                              DATE(waktu_submit) as tanggal,
                              COUNT(*) as jumlah
                              FROM pr_submission
                              WHERE id_pr = ? AND waktu_submit IS NOT NULL
                              GROUP BY DATE(waktu_submit)
                              ORDER BY tanggal ASC");
        $stmt->execute([$pr_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR submission timeline error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR late submissions
 */
function get_pr_late_submissions($pr_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT ps.*, u.nama as nama_siswa
                              FROM pr_submission ps
                              INNER JOIN users u ON ps.id_siswa = u.id
                              WHERE ps.id_pr = ? AND ps.status = 'terlambat'
                              ORDER BY ps.waktu_submit DESC");
        $stmt->execute([$pr_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR late submissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get PR pending review
 */
function get_pr_pending_review($guru_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT p.*, COUNT(ps.id) as pending_count
                              FROM pr p
                              INNER JOIN pr_submission ps ON p.id = ps.id_pr
                              WHERE p.id_guru = ? AND ps.status = 'sudah_dikumpulkan'
                              GROUP BY p.id
                              HAVING pending_count > 0
                              ORDER BY p.deadline ASC");
        $stmt->execute([$guru_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get PR pending review error: " . $e->getMessage());
        return [];
    }
}

// Continue with more functions... (Remaining functions will be added as needed)

