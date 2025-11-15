<?php
/**
 * Save Nilai Manual - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
    $semester = sanitize($_POST['semester'] ?? '');
    $id_kelas = intval($_POST['id_kelas'] ?? 0);
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $nilai_data = $_POST['nilai'] ?? [];
    
    if (empty($tahun_ajaran) || empty($semester) || !$id_kelas || !$id_mapel) {
        $error = 'Data tidak lengkap. Pastikan tahun ajaran, semester, kelas, dan mata pelajaran sudah dipilih.';
    } else {
        try {
            $pdo->beginTransaction();
            
            $saved_count = 0;
            $deleted_count = 0;
            
            // Get all siswa in the class
            $stmt = $pdo->prepare("SELECT DISTINCT u.id
                                  FROM users u
                                  INNER JOIN user_kelas uk ON u.id = uk.id_user
                                  WHERE u.role = 'siswa'
                                  AND uk.tahun_ajaran = ?
                                  AND uk.id_kelas = ?
                                  AND uk.semester = ?");
            $stmt->execute([$tahun_ajaran, $id_kelas, $semester]);
            $all_siswa = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($all_siswa as $id_siswa) {
                $nilai = isset($nilai_data[$id_siswa]) ? trim($nilai_data[$id_siswa]) : '';
                
                if ($nilai === '' || $nilai === null) {
                    // Delete existing nilai manual if empty
                    $stmt = $pdo->prepare("DELETE FROM nilai_semua_mapel 
                                          WHERE id_siswa = ? 
                                          AND id_mapel = ?
                                          AND tahun_ajaran = ?
                                          AND semester = ?
                                          AND (id_ujian IS NULL OR tipe_asesmen = 'manual')");
                    $stmt->execute([$id_siswa, $id_mapel, $tahun_ajaran, $semester]);
                    if ($stmt->rowCount() > 0) {
                        $deleted_count++;
                    }
                } else {
                    // Validate nilai
                    $nilai_float = floatval($nilai);
                    if ($nilai_float < 0 || $nilai_float > 100) {
                        continue; // Skip invalid nilai
                    }
                    
                    // Check if record exists
                    $stmt = $pdo->prepare("SELECT id FROM nilai_semua_mapel 
                                          WHERE id_siswa = ? 
                                          AND id_mapel = ?
                                          AND tahun_ajaran = ?
                                          AND semester = ?
                                          AND (id_ujian IS NULL OR tipe_asesmen = 'manual')
                                          LIMIT 1");
                    $stmt->execute([$id_siswa, $id_mapel, $tahun_ajaran, $semester]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing
                        $stmt = $pdo->prepare("UPDATE nilai_semua_mapel 
                                              SET nilai = ?,
                                                  tipe_asesmen = 'manual',
                                                  is_sumatip = 0,
                                                  updated_at = NOW()
                                              WHERE id = ?");
                        $stmt->execute([$nilai_float, $existing['id']]);
                    } else {
                        // Insert new
                        $stmt = $pdo->prepare("INSERT INTO nilai_semua_mapel 
                                              (id_siswa, tahun_ajaran, semester, id_mapel, id_ujian, tipe_asesmen, nilai, is_sumatip, created_at, updated_at)
                                              VALUES (?, ?, ?, ?, NULL, 'manual', ?, 0, NOW(), NOW())");
                        $stmt->execute([$id_siswa, $tahun_ajaran, $semester, $id_mapel, $nilai_float]);
                    }
                    $saved_count++;
                }
            }
            
            $pdo->commit();
            
            if ($saved_count > 0 || $deleted_count > 0) {
                $success = "Berhasil menyimpan $saved_count nilai";
                if ($deleted_count > 0) {
                    $success .= " dan menghapus $deleted_count nilai kosong";
                }
                $success .= ".";
                
                // Log activity
                log_activity('input_nilai_manual', 'nilai_semua_mapel', null, "Input nilai manual: $saved_count nilai untuk kelas $id_kelas, mapel $id_mapel");
            } else {
                $success = "Tidak ada perubahan nilai.";
            }
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage();
            error_log("Save nilai manual error: " . $e->getMessage());
        }
    }
    
    // Redirect back with message
    $_SESSION['error'] = $error;
    $_SESSION['success'] = $success;
    
    $redirect_url = base_url('operator-assessment-nilai-input') . 
                    '?tahun_ajaran=' . urlencode($tahun_ajaran) .
                    '&semester=' . urlencode($semester) .
                    '&id_kelas=' . $id_kelas .
                    '&id_mapel=' . $id_mapel;
    
    redirect($redirect_url);
} else {
    redirect('operator-assessment-nilai-input');
}






