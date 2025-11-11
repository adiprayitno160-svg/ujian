<?php
/**
 * Export Ledger Nilai Manual - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Export nilai dari penilaian_manual ke Excel/PDF
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$format = $_GET['format'] ?? 'excel';
$tahun_ajaran = $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$semester = $_GET['semester'] ?? 'ganjil';
$id_kelas = intval($_GET['id_kelas'] ?? 0);
$tingkat = $_GET['tingkat'] ?? '';

// Get all mapel
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get kelas
$sql_kelas = "SELECT * FROM kelas WHERE tahun_ajaran = ?";
$params_kelas = [$tahun_ajaran];
if ($tingkat) {
    $sql_kelas .= " AND tingkat = ?";
    $params_kelas[] = $tingkat;
}
$sql_kelas .= " ORDER BY nama_kelas ASC";
$stmt = $pdo->prepare($sql_kelas);
$stmt->execute($params_kelas);
$kelas_list = $stmt->fetchAll();

// Get siswa based on filter
$sql_siswa = "SELECT DISTINCT u.id, u.nama, u.username, u.no_hp, k.nama_kelas
              FROM users u
              INNER JOIN user_kelas uk ON u.id = uk.id_user
              INNER JOIN kelas k ON uk.id_kelas = k.id
              WHERE u.role = 'siswa'
              AND uk.tahun_ajaran = ?";
$params_siswa = [$tahun_ajaran];

if ($id_kelas) {
    $sql_siswa .= " AND uk.id_kelas = ?";
    $params_siswa[] = $id_kelas;
}

if ($semester) {
    $sql_siswa .= " AND uk.semester = ?";
    $params_siswa[] = $semester;
}

$sql_siswa .= " ORDER BY k.nama_kelas ASC, u.nama ASC";

$stmt = $pdo->prepare($sql_siswa);
$stmt->execute($params_siswa);
$siswa_list = $stmt->fetchAll();

// Check if aktif column exists
try {
    $check_aktif = $pdo->query("SHOW COLUMNS FROM penilaian_manual LIKE 'aktif'");
    $aktif_column_exists = $check_aktif->rowCount() > 0;
} catch (PDOException $e) {
    $aktif_column_exists = false;
}

// Get nilai from penilaian_manual for all siswa and mapel
$nilai_data = [];
if (!empty($siswa_list) && !empty($mapel_list)) {
    foreach ($siswa_list as $siswa) {
        $nilai_data[$siswa['id']] = [];
        foreach ($mapel_list as $mapel) {
            // Get nilai from penilaian_manual
            // Only get approved nilai, prioritize aktif = 1 if column exists
            if ($aktif_column_exists) {
                // First try to get aktif = 1
                $stmt = $pdo->prepare("SELECT nilai_akhir 
                                      FROM penilaian_manual
                                      WHERE id_siswa = ? 
                                      AND id_mapel = ?
                                      AND tahun_ajaran = ?
                                      AND semester = ?
                                      AND status = 'approved'
                                      AND aktif = 1
                                      ORDER BY id DESC
                                      LIMIT 1");
                $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                $nilai = $stmt->fetch();
                
                // If no aktif = 1, get any approved
                if (!$nilai) {
                    $stmt = $pdo->prepare("SELECT nilai_akhir 
                                          FROM penilaian_manual
                                          WHERE id_siswa = ? 
                                          AND id_mapel = ?
                                          AND tahun_ajaran = ?
                                          AND semester = ?
                                          AND status = 'approved'
                                          ORDER BY id DESC
                                          LIMIT 1");
                    $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                    $nilai = $stmt->fetch();
                }
            } else {
                // If aktif column doesn't exist, just get approved
                $stmt = $pdo->prepare("SELECT nilai_akhir 
                                      FROM penilaian_manual
                                      WHERE id_siswa = ? 
                                      AND id_mapel = ?
                                      AND tahun_ajaran = ?
                                      AND semester = ?
                                      AND status = 'approved'
                                      ORDER BY id DESC
                                      LIMIT 1");
                $stmt->execute([$siswa['id'], $mapel['id'], $tahun_ajaran, $semester]);
                $nilai = $stmt->fetch();
            }
            
            $nilai_data[$siswa['id']][$mapel['id']] = $nilai ? $nilai['nilai_akhir'] : null;
        }
    }
}

if ($format === 'excel' || $format === 'csv') {
    // Export to Excel/CSV
    $filename = 'Ledger_Nilai_Manual_' . $tahun_ajaran . '_' . $semester . '_' . date('YmdHis') . '.csv';
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";
    
    // Header row
    $headers = ['No', 'Nama Siswa', 'NIS', 'Kelas'];
    foreach ($mapel_list as $mapel) {
        $headers[] = $mapel['nama_mapel'];
    }
    echo '"' . implode('","', $headers) . '"' . "\n";
    
    // Data rows
    $no = 1;
    foreach ($siswa_list as $siswa) {
        $row = [
            $no++,
            '"' . str_replace('"', '""', $siswa['nama']) . '"',
            '"' . str_replace('"', '""', $siswa['username']) . '"',
            '"' . str_replace('"', '""', $siswa['nama_kelas']) . '"'
        ];
        
        foreach ($mapel_list as $mapel) {
            $nilai = $nilai_data[$siswa['id']][$mapel['id']] ?? null;
            $row[] = $nilai !== null ? number_format($nilai, 2) : '-';
        }
        
        echo implode(',', $row) . "\n";
    }
    
    exit;
} else {
    // For PDF, redirect back or show message
    redirect('operator-ledger-nilai-manual?tahun_ajaran=' . $tahun_ajaran . '&semester=' . $semester . '&id_kelas=' . $id_kelas . '&tingkat=' . $tingkat);
}



