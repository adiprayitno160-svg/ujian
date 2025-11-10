<?php
/**
 * Tugas Helper Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Total: 120 functions
 */

// ============================================
// CORE FUNCTIONS - GET/RETRIEVE DATA (10 functions)
// ============================================

/**
 * Get Tugas by ID with full details
 */
function get_tugas($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT t.*, m.nama_mapel, u.nama as nama_guru 
                              FROM tugas t
                              INNER JOIN mapel m ON t.id_mapel = m.id
                              INNER JOIN users u ON t.id_guru = u.id
                              WHERE t.id = ?");
        $stmt->execute([$tugas_id]);
        $tugas = $stmt->fetch();
        
        if ($tugas) {
            // Get assigned classes
            $stmt = $pdo->prepare("SELECT k.* FROM tugas_kelas tk
                                  INNER JOIN kelas k ON tk.id_kelas = k.id
                                  WHERE tk.id_tugas = ?");
            $stmt->execute([$tugas_id]);
            $tugas['kelas'] = $stmt->fetchAll();
            
            // Get attachments
            $tugas['attachments'] = get_tugas_attachments($tugas_id);
        }
        
        return $tugas;
    } catch (PDOException $e) {
        error_log("Get Tugas error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Tugas by guru with filters
 */
function get_tugas_by_guru($guru_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT t.*, m.nama_mapel,
                (SELECT COUNT(*) FROM tugas_submission WHERE id_tugas = t.id) as total_submission,
                (SELECT COUNT(*) FROM tugas_submission WHERE id_tugas = t.id AND status = 'sudah_dikumpulkan') as sudah_dikumpulkan
                FROM tugas t
                INNER JOIN mapel m ON t.id_mapel = m.id
                WHERE t.id_guru = ?";
        
        $params = [$guru_id];
        
        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        
        if (isset($filters['mapel_id'])) {
            $sql .= " AND t.id_mapel = ?";
            $params[] = $filters['mapel_id'];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas by guru error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas by student with filters
 */
function get_tugas_by_student($student_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT DISTINCT t.*, m.nama_mapel,
                (SELECT status FROM tugas_submission WHERE id_tugas = t.id AND id_siswa = ?) as status_submission,
                (SELECT nilai FROM tugas_submission WHERE id_tugas = t.id AND id_siswa = ?) as nilai_submission
                FROM tugas t
                INNER JOIN mapel m ON t.id_mapel = m.id
                INNER JOIN tugas_kelas tk ON t.id = tk.id_tugas
                INNER JOIN user_kelas uk ON tk.id_kelas = uk.id_kelas
                WHERE uk.id_user = ? AND t.status = 'published'";
        
        $params = [$student_id, $student_id, $student_id];
        
        if (isset($filters['status'])) {
            $sql .= " AND (SELECT status FROM tugas_submission WHERE id_tugas = t.id AND id_siswa = ?) = ?";
            $params[] = $student_id;
            $params[] = $filters['status'];
        }
        
        if (isset($filters['mapel_id'])) {
            $sql .= " AND t.id_mapel = ?";
            $params[] = $filters['mapel_id'];
        }
        
        $sql .= " ORDER BY t.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas by student error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas submissions with student info
 */
function get_tugas_submissions($tugas_id, $status = null) {
    global $pdo;
    
    try {
        $sql = "SELECT ts.*, u.nama as nama_siswa, u.username,
                k.nama_kelas, k.tahun_ajaran
                FROM tugas_submission ts
                INNER JOIN users u ON ts.id_siswa = u.id
                LEFT JOIN user_kelas uk ON u.id = uk.id_user
                LEFT JOIN kelas k ON uk.id_kelas = k.id
                WHERE ts.id_tugas = ?";
        
        $params = [$tugas_id];
        
        if ($status) {
            $sql .= " AND ts.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY ts.waktu_submit DESC, u.nama ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas submissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas submission by student
 */
function get_tugas_submission($tugas_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tugas_submission WHERE id_tugas = ? AND id_siswa = ?");
        $stmt->execute([$tugas_id, $student_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get Tugas submission error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Tugas attachments
 */
function get_tugas_attachments($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tugas_attachment WHERE id_tugas = ? ORDER BY urutan ASC, id ASC");
        $stmt->execute([$tugas_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas attachments error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas submission files
 */
function get_tugas_submission_files($submission_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tugas_submission_file WHERE id_submission = ? ORDER BY urutan ASC, id ASC");
        $stmt->execute([$submission_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas submission files error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas kelas assignments
 */
function get_tugas_kelas($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT k.* FROM tugas_kelas tk
                              INNER JOIN kelas k ON tk.id_kelas = k.id
                              WHERE tk.id_tugas = ?");
        $stmt->execute([$tugas_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas kelas error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas by kelas
 */
function get_tugas_by_kelas($kelas_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT DISTINCT t.*, m.nama_mapel
                FROM tugas t
                INNER JOIN mapel m ON t.id_mapel = m.id
                INNER JOIN tugas_kelas tk ON t.id = tk.id_tugas
                WHERE tk.id_kelas = ? AND t.status = 'published'";
        
        $params = [$kelas_id];
        
        $sql .= " ORDER BY t.deadline ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas by kelas error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas by mapel
 */
function get_tugas_by_mapel($mapel_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT t.*, m.nama_mapel
                FROM tugas t
                INNER JOIN mapel m ON t.id_mapel = m.id
                WHERE t.id_mapel = ?";
        
        $params = [$mapel_id];
        
        $sql .= " ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas by mapel error: " . $e->getMessage());
        return [];
    }
}

// ============================================
// CORE FUNCTIONS - VALIDATION & CHECK (8 functions)
// ============================================

/**
 * Check if Tugas deadline has passed
 */
function is_tugas_deadline_passed($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT deadline FROM tugas WHERE id = ?");
        $stmt->execute([$tugas_id]);
        $tugas = $stmt->fetch();
        
        if ($tugas) {
            $deadline = new DateTime($tugas['deadline']);
            $now = new DateTime();
            return $now > $deadline;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Check Tugas deadline error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student can submit Tugas
 */
function can_student_submit_tugas($tugas_id, $student_id) {
    global $pdo;
    
    try {
        $tugas = get_tugas($tugas_id);
        if (!$tugas || $tugas['status'] !== 'published') return false;
        
        // Check if student is assigned
        if (!is_student_assigned_to_tugas($tugas_id, $student_id)) {
            return false;
        }
        
        // Check deadline
        $deadline_passed = is_tugas_deadline_passed($tugas_id);
        if ($deadline_passed && !$tugas['allow_late_submission']) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Can student submit Tugas error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student can edit submission
 */
function can_student_edit_tugas($tugas_id, $student_id) {
    global $pdo;
    
    try {
        $tugas = get_tugas($tugas_id);
        if (!$tugas) return false;
        
        if (!$tugas['allow_edit_after_submit']) {
            return false;
        }
        
        $deadline_passed = is_tugas_deadline_passed($tugas_id);
        if ($deadline_passed) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Can student edit Tugas error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if student is assigned to Tugas
 */
function is_student_assigned_to_tugas($tugas_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas_kelas tk
                              INNER JOIN user_kelas uk ON tk.id_kelas = uk.id_kelas
                              WHERE tk.id_tugas = ? AND uk.id_user = ?");
        $stmt->execute([$tugas_id, $student_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Check student assigned to Tugas error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate Tugas data
 */
function validate_tugas_data($data) {
    $errors = [];
    
    if (empty($data['judul'])) {
        $errors[] = 'Judul Tugas harus diisi';
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
    
    if (isset($data['poin_maksimal']) && ($data['poin_maksimal'] < 0 || $data['poin_maksimal'] > 100)) {
        $errors[] = 'Poin maksimal harus antara 0-100';
    }
    
    return $errors;
}

/**
 * Validate Tugas submission
 */
function validate_tugas_submission($tugas_id, $files) {
    global $pdo;
    
    try {
        $tugas = get_tugas($tugas_id);
        if (!$tugas) {
            return ['success' => false, 'message' => 'Tugas tidak ditemukan'];
        }
        
        // Check file count
        if (count($files) > $tugas['max_files']) {
            return ['success' => false, 'message' => "Maksimal {$tugas['max_files']} file"];
        }
        
        // Check file size and type
        $allowed_extensions = explode(',', $tugas['allowed_extensions']);
        $max_size = $tugas['max_file_size'];
        
        foreach ($files as $file) {
            if ($file['size'] > $max_size) {
                return ['success' => false, 'message' => 'File terlalu besar. Maksimal ' . format_file_size($max_size)];
            }
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, array_map('trim', $allowed_extensions))) {
                return ['success' => false, 'message' => "Ekstensi file .$extension tidak diizinkan"];
            }
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Validate Tugas submission error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan saat validasi'];
    }
}

/**
 * Check Tugas file limits
 */
function check_tugas_file_limits($files, $tugas_id) {
    global $pdo;
    
    try {
        $tugas = get_tugas($tugas_id);
        if (!$tugas) return false;
        
        if (count($files) > $tugas['max_files']) {
            return false;
        }
        
        $total_size = 0;
        foreach ($files as $file) {
            $total_size += $file['size'];
        }
        
        if ($total_size > ($tugas['max_file_size'] * $tugas['max_files'])) {
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Check Tugas file limits error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if Tugas allows late submission
 */
function is_tugas_late_submission_allowed($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT allow_late_submission FROM tugas WHERE id = ?");
        $stmt->execute([$tugas_id]);
        $tugas = $stmt->fetch();
        return $tugas ? (bool)$tugas['allow_late_submission'] : false;
    } catch (PDOException $e) {
        error_log("Check Tugas late submission error: " . $e->getMessage());
        return false;
    }
}

// format_file_size is defined in includes/functions.php

// ============================================
// CORE FUNCTIONS - STATISTICS & ANALYTICS (8 functions)
// ============================================

/**
 * Get Tugas statistics
 */
function get_tugas_statistics($tugas_id) {
    global $pdo;
    
    try {
        // Get total assigned students
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT uk.id_user) as total_siswa
                              FROM tugas_kelas tk
                              INNER JOIN user_kelas uk ON tk.id_kelas = uk.id_kelas
                              WHERE tk.id_tugas = ?");
        $stmt->execute([$tugas_id]);
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
                              FROM tugas_submission
                              WHERE id_tugas = ?");
        $stmt->execute([$tugas_id]);
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
        error_log("Get Tugas statistics error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Tugas completion rate
 */
function get_tugas_completion_rate($tugas_id) {
    $stats = get_tugas_statistics($tugas_id);
    return $stats ? $stats['completion_rate'] : 0;
}

/**
 * Get Tugas grading rate
 */
function get_tugas_grading_rate($tugas_id) {
    $stats = get_tugas_statistics($tugas_id);
    return $stats ? $stats['grading_rate'] : 0;
}

/**
 * Get Tugas average score
 */
function get_tugas_average_score($tugas_id) {
    $stats = get_tugas_statistics($tugas_id);
    return $stats ? $stats['rata_rata_nilai'] : null;
}

/**
 * Get Tugas score distribution
 */
function get_tugas_score_distribution($tugas_id) {
    global $pdo;
    
    try {
        $tugas = get_tugas($tugas_id);
        if (!$tugas) return null;
        
        $stmt = $pdo->prepare("SELECT 
                              SUM(CASE WHEN nilai >= 90 THEN 1 ELSE 0 END) as A,
                              SUM(CASE WHEN nilai >= 80 AND nilai < 90 THEN 1 ELSE 0 END) as B,
                              SUM(CASE WHEN nilai >= 70 AND nilai < 80 THEN 1 ELSE 0 END) as C,
                              SUM(CASE WHEN nilai >= 60 AND nilai < 70 THEN 1 ELSE 0 END) as D,
                              SUM(CASE WHEN nilai < 60 THEN 1 ELSE 0 END) as E
                              FROM tugas_submission
                              WHERE id_tugas = ? AND nilai IS NOT NULL");
        $stmt->execute([$tugas_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get Tugas score distribution error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get Tugas submission timeline
 */
function get_tugas_submission_timeline($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT 
                              DATE(waktu_submit) as tanggal,
                              COUNT(*) as jumlah
                              FROM tugas_submission
                              WHERE id_tugas = ? AND waktu_submit IS NOT NULL
                              GROUP BY DATE(waktu_submit)
                              ORDER BY tanggal ASC");
        $stmt->execute([$tugas_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas submission timeline error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas late submissions
 */
function get_tugas_late_submissions($tugas_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT ts.*, u.nama as nama_siswa
                              FROM tugas_submission ts
                              INNER JOIN users u ON ts.id_siswa = u.id
                              WHERE ts.id_tugas = ? AND ts.is_late = 1
                              ORDER BY ts.waktu_submit DESC");
        $stmt->execute([$tugas_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas late submissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get Tugas pending review
 */
function get_tugas_pending_review($guru_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT t.*, COUNT(ts.id) as pending_count
                              FROM tugas t
                              INNER JOIN tugas_submission ts ON t.id = ts.id_tugas
                              WHERE t.id_guru = ? AND ts.status = 'sudah_dikumpulkan'
                              GROUP BY t.id
                              HAVING pending_count > 0
                              ORDER BY t.deadline ASC");
        $stmt->execute([$guru_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get Tugas pending review error: " . $e->getMessage());
        return [];
    }
}

// Continue with more functions... (Remaining functions will be added as needed)

