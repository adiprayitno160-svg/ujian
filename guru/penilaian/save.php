<?php
/**
 * Save Penilaian Manual - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$guru_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'save';
$id_mapel = intval($_POST['id_mapel'] ?? 0);
$id_kelas = intval($_POST['id_kelas'] ?? 0);
$tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
$semester = sanitize($_POST['semester'] ?? 'ganjil');
$penilaian_data = $_POST['penilaian'] ?? [];

if (!$id_mapel || !$id_kelas || !$tahun_ajaran || empty($penilaian_data)) {
    redirect('guru/penilaian/list.php');
}

// Verify that guru teaches this mapel
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM guru_mapel WHERE id_guru = ? AND id_mapel = ?");
$stmt->execute([$guru_id, $id_mapel]);
$can_teach = $stmt->fetch()['count'] > 0;

if (!$can_teach) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mata pelajaran ini.';
    redirect('guru/penilaian/list.php');
}

try {
    $pdo->beginTransaction();
    
    $status = ($action == 'submit') ? 'submitted' : 'draft';
    $submitted_at = ($action == 'submit') ? date('Y-m-d H:i:s') : null;
    
    foreach ($penilaian_data as $siswa_id => $data) {
        $siswa_id = intval($siswa_id);
        // Manual grading is only for UTS - set tugas and UAS to null
        $nilai_tugas = null; // Not manually graded - should come from tugas submissions
        $nilai_uts = !empty($data['nilai_uts']) ? floatval($data['nilai_uts']) : null;
        $nilai_uas = null; // Not manually graded - should come from UAS exam results
        $nilai_akhir = !empty($data['nilai_akhir']) ? floatval($data['nilai_akhir']) : null;
        $predikat = !empty($data['predikat']) ? sanitize($data['predikat']) : null;
        $keterangan = !empty($data['keterangan']) ? sanitize($data['keterangan']) : null;
        
        // Check if penilaian already exists
        $stmt = $pdo->prepare("SELECT id, status FROM penilaian_manual 
                              WHERE id_guru = ? 
                              AND id_siswa = ? 
                              AND id_mapel = ? 
                              AND id_kelas = ? 
                              AND tahun_ajaran = ? 
                              AND semester = ?");
        $stmt->execute([$guru_id, $siswa_id, $id_mapel, $id_kelas, $tahun_ajaran, $semester]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Don't allow editing if already submitted or approved
            if ($existing['status'] == 'submitted' || $existing['status'] == 'approved') {
                continue; // Skip this record
            }
            
            // Update existing
            $stmt = $pdo->prepare("UPDATE penilaian_manual 
                                  SET nilai_tugas = ?, 
                                      nilai_uts = ?, 
                                      nilai_uas = ?, 
                                      nilai_akhir = ?, 
                                      predikat = ?, 
                                      keterangan = ?, 
                                      status = ?, 
                                      submitted_at = ?,
                                      updated_at = NOW()
                                  WHERE id = ?");
            $stmt->execute([
                $nilai_tugas, $nilai_uts, $nilai_uas, $nilai_akhir, 
                $predikat, $keterangan, $status, $submitted_at, $existing['id']
            ]);
        } else {
            // Insert new
            $stmt = $pdo->prepare("INSERT INTO penilaian_manual 
                                  (id_guru, id_siswa, id_mapel, id_kelas, tahun_ajaran, semester,
                                   nilai_tugas, nilai_uts, nilai_uas, nilai_akhir, predikat, keterangan,
                                   status, submitted_at, created_at, updated_at)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $guru_id, $siswa_id, $id_mapel, $id_kelas, $tahun_ajaran, $semester,
                $nilai_tugas, $nilai_uts, $nilai_uas, $nilai_akhir, $predikat, $keterangan,
                $status, $submitted_at
            ]);
        }
    }
    
    $pdo->commit();
    
    if ($action == 'submit') {
        $_SESSION['success'] = 'Nilai berhasil dikumpulkan ke operator.';
    } else {
        $_SESSION['success'] = 'Nilai berhasil disimpan.';
    }
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Save penilaian manual error: " . $e->getMessage());
    $_SESSION['error'] = 'Terjadi kesalahan saat menyimpan nilai: ' . $e->getMessage();
}

redirect('guru/penilaian/list.php?id_mapel=' . $id_mapel . '&id_kelas=' . $id_kelas . '&semester=' . $semester);

