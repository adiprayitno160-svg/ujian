<?php
/**
 * SUMATIP Assessment Helper Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/functions.php';

/**
 * Get SUMATIP list with filters
 */
function get_sumatip_list($filters = []) {
    global $pdo;
    
    $sql = "SELECT u.*, m.nama_mapel, u2.nama as nama_guru,
            (SELECT COUNT(*) FROM soal WHERE id_ujian = u.id) as total_soal,
            (SELECT COUNT(*) FROM sesi_ujian WHERE id_ujian = u.id) as total_sesi
            FROM ujian u
            INNER JOIN mapel m ON u.id_mapel = m.id
            INNER JOIN users u2 ON u.id_guru = u2.id
            WHERE u.tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun')";
    
    $params = [];
    
    if (!empty($filters['tipe_asesmen'])) {
        $sql .= " AND u.tipe_asesmen = ?";
        $params[] = $filters['tipe_asesmen'];
    }
    
    if (!empty($filters['tahun_ajaran'])) {
        $sql .= " AND u.tahun_ajaran = ?";
        $params[] = $filters['tahun_ajaran'];
    }
    
    if (!empty($filters['semester'])) {
        $sql .= " AND u.semester = ?";
        $params[] = $filters['semester'];
    }
    
    if (!empty($filters['id_mapel'])) {
        $sql .= " AND u.id_mapel = ?";
        $params[] = $filters['id_mapel'];
    }
    
    if (!empty($filters['id_guru'])) {
        $sql .= " AND u.id_guru = ?";
        $params[] = $filters['id_guru'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND u.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['tingkat_kelas'])) {
        $sql .= " AND u.tingkat_kelas = ?";
        $params[] = $filters['tingkat_kelas'];
    }
    
    $sql .= " ORDER BY u.created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . intval($filters['limit']);
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get SUMATIP list error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get SUMATIP by ID
 */
function get_sumatip($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, m.nama_mapel, u2.nama as nama_guru
                              FROM ujian u
                              INNER JOIN mapel m ON u.id_mapel = m.id
                              INNER JOIN users u2 ON u.id_guru = u2.id
                              WHERE u.id = ? AND u.tipe_asesmen IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun')");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get SUMATIP error: " . $e->getMessage());
        return null;
    }
}

/**
 * Create SUMATIP
 */
function create_sumatip($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Validate duplicate SUMATIP
        if (!empty($data['tahun_ajaran']) && !empty($data['semester']) && !empty($data['tipe_asesmen']) && !empty($data['id_mapel'])) {
            $stmt = $pdo->prepare("SELECT id FROM ujian 
                                  WHERE tipe_asesmen = ? 
                                  AND tahun_ajaran = ? 
                                  AND semester = ? 
                                  AND id_mapel = ?
                                  AND status != 'completed'");
            $stmt->execute([$data['tipe_asesmen'], $data['tahun_ajaran'], $data['semester'], $data['id_mapel']]);
            if ($stmt->fetch()) {
                throw new Exception("SUMATIP dengan jenis, tahun ajaran, semester, dan mata pelajaran yang sama sudah ada");
            }
        }
        
        // Generate periode_sumatip
        $periode = '';
        if (!empty($data['tahun_ajaran']) && !empty($data['semester'])) {
            $jenis_label = [
                'sumatip_tengah_semester' => 'SUMATIP Tengah Semester',
                'sumatip_akhir_semester' => 'SUMATIP Akhir Semester',
                'sumatip_akhir_tahun' => 'SUMATIP Akhir Tahun'
            ];
            $jenis = $jenis_label[$data['tipe_asesmen']] ?? 'SUMATIP';
            $semester_label = ucfirst($data['semester']);
            $periode = "$jenis - Semester $semester_label {$data['tahun_ajaran']}";
        }
        
        // Insert ujian (with AI correction enabled by default)
        $stmt = $pdo->prepare("INSERT INTO ujian 
                              (judul, deskripsi, id_mapel, id_guru, durasi, tipe_asesmen, tahun_ajaran, semester, 
                               periode_sumatip, is_mandatory, id_template_sumatip, tingkat_kelas, ai_correction_enabled, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'draft')");
        $stmt->execute([
            $data['judul'],
            $data['deskripsi'] ?? null,
            $data['id_mapel'],
            $data['id_guru'],
            $data['durasi'],
            $data['tipe_asesmen'],
            $data['tahun_ajaran'] ?? null,
            $data['semester'] ?? null,
            $periode,
            $data['is_mandatory'] ?? 0,
            $data['id_template_sumatip'] ?? null,
            $data['tingkat_kelas'] ?? null,
        ]);
        
        $ujian_id = $pdo->lastInsertId();
        
        // Insert kelas target if provided
        if (!empty($data['id_kelas']) && is_array($data['id_kelas'])) {
            $stmt_kelas = $pdo->prepare("INSERT INTO sumatip_kelas_target (id_ujian, id_kelas) VALUES (?, ?)");
            foreach ($data['id_kelas'] as $id_kelas) {
                $stmt_kelas->execute([$ujian_id, $id_kelas]);
            }
        }
        
        // Log SUMATIP creation
        $stmt_log = $pdo->prepare("INSERT INTO sumatip_log (id_ujian, action, keterangan, created_by) VALUES (?, 'create', ?, ?)");
        $stmt_log->execute([$ujian_id, "SUMATIP created: {$data['judul']}", $data['id_guru']]);
        
        $pdo->commit();
        return $ujian_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create SUMATIP error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get bank soal with filters
 */
function get_bank_soal($filters = []) {
    global $pdo;
    
    $sql = "SELECT bs.*, s.pertanyaan, s.tipe_soal, s.bobot, s.tingkat_kesulitan,
            m.nama_mapel, u.judul as judul_ujian, u2.nama as nama_guru
            FROM bank_soal bs
            INNER JOIN soal s ON bs.id_soal = s.id
            INNER JOIN ujian u ON s.id_ujian = u.id
            INNER JOIN mapel m ON bs.id_mapel = m.id
            INNER JOIN users u2 ON u.id_guru = u2.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['id_mapel'])) {
        $sql .= " AND bs.id_mapel = ?";
        $params[] = $filters['id_mapel'];
    }
    
    if (!empty($filters['tingkat_kelas'])) {
        $sql .= " AND bs.tingkat_kelas = ?";
        $params[] = $filters['tingkat_kelas'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND bs.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['tipe_soal'])) {
        $sql .= " AND s.tipe_soal = ?";
        $params[] = $filters['tipe_soal'];
    }
    
    $sql .= " ORDER BY bs.created_at DESC";
    
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . intval($filters['limit']);
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get bank soal error: " . $e->getMessage());
        return [];
    }
}

/**
 * Approve/reject soal in bank
 */
function approve_bank_soal($id_soal, $action, $user_id, $reason = null) {
    global $pdo;
    
    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE bank_soal SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id_soal = ?");
            $stmt->execute([$user_id, $id_soal]);
        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE bank_soal SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id_soal = ?");
            $stmt->execute([$user_id, $reason, $id_soal]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Approve bank soal error: " . $e->getMessage());
        return false;
    }
}

/**
 * Add soal to bank (auto when guru creates soal)
 */
function add_soal_to_bank($id_soal, $id_mapel, $tingkat_kelas = null) {
    global $pdo;
    
    try {
        // Check if already exists
        $stmt = $pdo->prepare("SELECT id FROM bank_soal WHERE id_soal = ?");
        $stmt->execute([$id_soal]);
        if ($stmt->fetch()) {
            return true; // Already exists
        }
        
        // Get tingkat from ujian if not provided
        if (empty($tingkat_kelas)) {
            $stmt = $pdo->prepare("SELECT u.tingkat_kelas FROM soal s INNER JOIN ujian u ON s.id_ujian = u.id WHERE s.id = ?");
            $stmt->execute([$id_soal]);
            $result = $stmt->fetch();
            $tingkat_kelas = $result['tingkat_kelas'] ?? null;
        }
        
        // Insert to bank_soal
        $stmt = $pdo->prepare("INSERT INTO bank_soal (id_soal, id_mapel, tingkat_kelas, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$id_soal, $id_mapel, $tingkat_kelas]);
        return true;
    } catch (PDOException $e) {
        error_log("Add soal to bank error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get nilai semua mapel per siswa
 */
function get_nilai_semua_mapel($filters = []) {
    global $pdo;
    
    $sql = "SELECT nsm.*, u.nama as nama_siswa, u.username, m.nama_mapel, m.kode_mapel
            FROM nilai_semua_mapel nsm
            INNER JOIN users u ON nsm.id_siswa = u.id
            INNER JOIN mapel m ON nsm.id_mapel = m.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['id_siswa'])) {
        $sql .= " AND nsm.id_siswa = ?";
        $params[] = $filters['id_siswa'];
    }
    
    if (!empty($filters['tahun_ajaran'])) {
        $sql .= " AND nsm.tahun_ajaran = ?";
        $params[] = $filters['tahun_ajaran'];
    }
    
    if (!empty($filters['semester'])) {
        $sql .= " AND nsm.semester = ?";
        $params[] = $filters['semester'];
    }
    
    if (!empty($filters['id_mapel'])) {
        $sql .= " AND nsm.id_mapel = ?";
        $params[] = $filters['id_mapel'];
    }
    
    if (!empty($filters['is_sumatip'])) {
        $sql .= " AND nsm.is_sumatip = ?";
        $params[] = $filters['is_sumatip'];
    }
    
    $sql .= " ORDER BY u.nama ASC, m.nama_mapel ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get nilai semua mapel error: " . $e->getMessage());
        return [];
    }
}

/**
 * Aggregate nilai and update nilai_semua_mapel
 */
function aggregate_nilai_semua_mapel($tahun_ajaran, $semester) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get all nilai from nilai table
        $sql = "SELECT n.id_siswa, u.id_mapel, n.id_ujian, u.tipe_asesmen, 
                AVG(n.nilai) as nilai_avg, MAX(n.nilai) as nilai_max
                FROM nilai n
                INNER JOIN ujian u ON n.id_ujian = u.id
                INNER JOIN sesi_ujian s ON n.id_sesi = s.id
                WHERE n.status = 'selesai'
                AND u.tahun_ajaran = ?
                AND u.semester = ?
                GROUP BY n.id_siswa, u.id_mapel, n.id_ujian";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tahun_ajaran, $semester]);
        $nilai_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insert/update nilai_semua_mapel
        $stmt_insert = $pdo->prepare("INSERT INTO nilai_semua_mapel 
                                     (id_siswa, tahun_ajaran, semester, id_mapel, id_ujian, tipe_asesmen, nilai, is_sumatip)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE 
                                     nilai = VALUES(nilai),
                                     updated_at = NOW()");
        
        foreach ($nilai_list as $nilai) {
            $is_sumatip = in_array($nilai['tipe_asesmen'], ['sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun']) ? 1 : 0;
            $stmt_insert->execute([
                $nilai['id_siswa'],
                $tahun_ajaran,
                $semester,
                $nilai['id_mapel'],
                $nilai['id_ujian'],
                $nilai['tipe_asesmen'],
                $nilai['nilai_avg'],
                $is_sumatip
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Aggregate nilai semua mapel error: " . $e->getMessage());
        return false;
    }
}

/**
 * Create jadwal assessment
 */
function create_jadwal_assessment($data) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Validate jadwal overlap
        $tanggal = $data['tanggal'];
        $waktu_mulai = $data['waktu_mulai'];
        $waktu_selesai = $data['waktu_selesai'];
        
        $stmt = $pdo->prepare("SELECT id FROM jadwal_assessment 
                              WHERE id_kelas = ? 
                              AND tanggal = ?
                              AND status = 'aktif'
                              AND (
                                  (waktu_mulai <= ? AND waktu_selesai >= ?)
                                  OR (waktu_mulai <= ? AND waktu_selesai >= ?)
                                  OR (waktu_mulai >= ? AND waktu_selesai <= ?)
                              )");
        $stmt->execute([
            $data['id_kelas'],
            $tanggal,
            $waktu_mulai, $waktu_mulai,
            $waktu_selesai, $waktu_selesai,
            $waktu_mulai, $waktu_selesai
        ]);
        
        if ($stmt->fetch()) {
            throw new Exception("Jadwal overlap dengan jadwal yang sudah ada");
        }
        
        // Get tingkat from kelas
        $stmt = $pdo->prepare("SELECT tingkat FROM kelas WHERE id = ?");
        $stmt->execute([$data['id_kelas']]);
        $kelas = $stmt->fetch();
        $tingkat = $kelas['tingkat'] ?? null;
        
        // Insert jadwal
        $stmt = $pdo->prepare("INSERT INTO jadwal_assessment 
                              (id_ujian, id_kelas, tingkat, tanggal, waktu_mulai, waktu_selesai, 
                               status, is_susulan, id_jadwal_utama, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, 'aktif', ?, ?, ?)");
        $stmt->execute([
            $data['id_ujian'],
            $data['id_kelas'],
            $tingkat,
            $tanggal,
            $waktu_mulai,
            $waktu_selesai,
            $data['is_susulan'] ?? 0,
            $data['id_jadwal_utama'] ?? null,
            $data['created_by']
        ]);
        
        $jadwal_id = $pdo->lastInsertId();
        
        // Create sesi if needed
        if (!empty($data['create_sesi'])) {
            $stmt_ujian = $pdo->prepare("SELECT durasi FROM ujian WHERE id = ?");
            $stmt_ujian->execute([$data['id_ujian']]);
            $ujian = $stmt_ujian->fetch();
            
            if ($ujian) {
                $datetime_mulai = $tanggal . ' ' . $waktu_mulai . ':00';
                $datetime_selesai = $tanggal . ' ' . $waktu_selesai . ':00';
                
                $stmt_sesi = $pdo->prepare("INSERT INTO sesi_ujian 
                                           (id_ujian, nama_sesi, waktu_mulai, waktu_selesai, durasi, status)
                                           VALUES (?, ?, ?, ?, ?, 'draft')");
                $nama_sesi = "Sesi " . date('d/m/Y H:i', strtotime($datetime_mulai));
                $stmt_sesi->execute([
                    $data['id_ujian'],
                    $nama_sesi,
                    $datetime_mulai,
                    $datetime_selesai,
                    $ujian['durasi']
                ]);
                
                $sesi_id = $pdo->lastInsertId();
                
                // Update jadwal dengan id_sesi
                $stmt_update = $pdo->prepare("UPDATE jadwal_assessment SET id_sesi = ? WHERE id = ?");
                $stmt_update->execute([$sesi_id, $jadwal_id]);
            }
        }
        
        $pdo->commit();
        return $jadwal_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Create jadwal assessment error: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get jadwal assessment
 */
function get_jadwal_assessment($filters = []) {
    global $pdo;
    
    $sql = "SELECT ja.*, u.judul as judul_ujian, m.nama_mapel, k.nama_kelas, k.tingkat,
            u2.nama as nama_creator
            FROM jadwal_assessment ja
            INNER JOIN ujian u ON ja.id_ujian = u.id
            INNER JOIN mapel m ON u.id_mapel = m.id
            INNER JOIN kelas k ON ja.id_kelas = k.id
            INNER JOIN users u2 ON ja.created_by = u2.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['id_ujian'])) {
        $sql .= " AND ja.id_ujian = ?";
        $params[] = $filters['id_ujian'];
    }
    
    if (!empty($filters['id_kelas'])) {
        $sql .= " AND ja.id_kelas = ?";
        $params[] = $filters['id_kelas'];
    }
    
    if (!empty($filters['tingkat'])) {
        $sql .= " AND ja.tingkat = ?";
        $params[] = $filters['tingkat'];
    }
    
    if (!empty($filters['tanggal'])) {
        $sql .= " AND ja.tanggal = ?";
        $params[] = $filters['tanggal'];
    }
    
    if (!empty($filters['status'])) {
        $sql .= " AND ja.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['is_susulan'])) {
        $sql .= " AND ja.is_susulan = ?";
        $params[] = $filters['is_susulan'];
    }
    
    if (!empty($filters['tahun_ajaran'])) {
        $sql .= " AND u.tahun_ajaran = ?";
        $params[] = $filters['tahun_ajaran'];
    }
    
    $sql .= " ORDER BY ja.tanggal ASC, ja.waktu_mulai ASC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get jadwal assessment error: " . $e->getMessage());
        return [];
    }
}

/**
 * Create absensi (auto)
 */
function create_absensi($id_sesi, $id_siswa, $id_pr = null, $status = 'hadir', $method = 'auto', $created_by = null) {
    global $pdo;
    
    try {
        if ($id_sesi) {
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM absensi_ujian WHERE id_sesi = ? AND id_siswa = ?");
            $stmt->execute([$id_sesi, $id_siswa]);
            if ($stmt->fetch()) {
                return true; // Already exists
            }
            
            $stmt = $pdo->prepare("INSERT INTO absensi_ujian 
                                  (id_sesi, id_siswa, status_absen, waktu_absen, metode_absen, created_by)
                                  VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$id_sesi, $id_siswa, $status, $method, $created_by]);
        } elseif ($id_pr) {
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM absensi_ujian WHERE id_pr = ? AND id_siswa = ?");
            $stmt->execute([$id_pr, $id_siswa]);
            if ($stmt->fetch()) {
                return true; // Already exists
            }
            
            $stmt = $pdo->prepare("INSERT INTO absensi_ujian 
                                  (id_pr, id_siswa, status_absen, waktu_absen, metode_absen, created_by)
                                  VALUES (?, ?, ?, NOW(), ?, ?)");
            $stmt->execute([$id_pr, $id_siswa, $status, $method, $created_by]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Create absensi error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get absensi report
 */
function get_absensi_report($filters = []) {
    global $pdo;
    
    $sql = "SELECT a.*, u.nama as nama_siswa, u.username, 
            s.nama_sesi, s.waktu_mulai, s.waktu_selesai,
            uj.judul as judul_ujian, m.nama_mapel, k.nama_kelas
            FROM absensi_ujian a
            INNER JOIN users u ON a.id_siswa = u.id
            LEFT JOIN sesi_ujian s ON a.id_sesi = s.id
            LEFT JOIN ujian uj ON s.id_ujian = uj.id
            LEFT JOIN mapel m ON uj.id_mapel = m.id
            LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
            LEFT JOIN kelas k ON uk.id_kelas = k.id
            WHERE 1=1";
    
    $params = [$filters['tahun_ajaran'] ?? get_tahun_ajaran_aktif()];
    
    // Filter untuk assessment saja (jika filter_assessment = true atau tidak ada filter khusus)
    // Untuk operator assessment, hanya tampilkan absensi dari assessment
    if (isset($filters['filter_assessment']) && $filters['filter_assessment']) {
        $sql .= " AND (uj.tipe_asesmen IS NOT NULL AND uj.tipe_asesmen != '')";
    }
    
    if (!empty($filters['id_sesi'])) {
        $sql .= " AND a.id_sesi = ?";
        $params[] = $filters['id_sesi'];
    }
    
    if (!empty($filters['id_kelas'])) {
        $sql .= " AND k.id = ?";
        $params[] = $filters['id_kelas'];
    }
    
    if (!empty($filters['status_absen'])) {
        $sql .= " AND a.status_absen = ?";
        $params[] = $filters['status_absen'];
    }
    
    if (!empty($filters['tanggal_mulai'])) {
        $sql .= " AND DATE(a.waktu_absen) >= ?";
        $params[] = $filters['tanggal_mulai'];
    }
    
    if (!empty($filters['tanggal_selesai'])) {
        $sql .= " AND DATE(a.waktu_absen) <= ?";
        $params[] = $filters['tanggal_selesai'];
    }
    
    $sql .= " ORDER BY a.waktu_absen DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get absensi report error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get SUMATIP badge class
 */
function get_sumatip_badge_class($tipe_asesmen) {
    $badges = [
        'sumatip_tengah_semester' => 'bg-primary',
        'sumatip_akhir_semester' => 'bg-success',
        'sumatip_akhir_tahun' => 'bg-danger',
        'sumatip' => 'bg-info'
    ];
    return $badges[$tipe_asesmen] ?? 'bg-secondary';
}

/**
 * Get SUMATIP badge label
 */
function get_sumatip_badge_label($tipe_asesmen) {
    $labels = [
        'sumatip_tengah_semester' => 'SUMATIP Tengah Semester',
        'sumatip_akhir_semester' => 'SUMATIP Akhir Semester',
        'sumatip_akhir_tahun' => 'SUMATIP Akhir Tahun',
        'sumatip' => 'SUMATIP'
    ];
    return $labels[$tipe_asesmen] ?? 'Regular';
}

/**
 * Validate SUMATIP duplicate
 */
function validate_sumatip_duplicate($tipe_asesmen, $tahun_ajaran, $semester, $id_mapel, $exclude_id = null) {
    global $pdo;
    
    try {
        $sql = "SELECT id FROM ujian 
                WHERE tipe_asesmen = ? 
                AND tahun_ajaran = ? 
                AND semester = ? 
                AND id_mapel = ?
                AND status != 'completed'";
        
        $params = [$tipe_asesmen, $tahun_ajaran, $semester, $id_mapel];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return !$stmt->fetch(); // Return true if no duplicate
    } catch (PDOException $e) {
        error_log("Validate SUMATIP duplicate error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get SUMATIP template
 */
function get_sumatip_template($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM sumatip_template WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get SUMATIP template error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get SUMATIP template list
 */
function get_sumatip_template_list($filters = []) {
    global $pdo;
    
    $sql = "SELECT st.*, m.nama_mapel, u.nama as nama_creator
            FROM sumatip_template st
            LEFT JOIN mapel m ON st.id_mapel = m.id
            INNER JOIN users u ON st.created_by = u.id
            WHERE st.is_active = 1";
    
    $params = [];
    
    if (!empty($filters['jenis_sumatip'])) {
        $sql .= " AND st.jenis_sumatip = ?";
        $params[] = $filters['jenis_sumatip'];
    }
    
    if (!empty($filters['id_mapel'])) {
        $sql .= " AND (st.id_mapel = ? OR st.id_mapel IS NULL)";
        $params[] = $filters['id_mapel'];
    }
    
    $sql .= " ORDER BY st.created_at DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get SUMATIP template list error: " . $e->getMessage());
        return [];
    }
}

