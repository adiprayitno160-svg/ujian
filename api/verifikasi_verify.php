<?php
/**
 * Verifikasi Verify API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * API endpoint untuk verifikasi dokumen oleh admin
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/verifikasi_functions.php';

global $pdo;

// Check authentication
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if admin
if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only admin can verify documents']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? '';
$id_siswa = intval($_POST['id_siswa'] ?? 0);
$status = $_POST['status'] ?? '';
$catatan = sanitize($_POST['catatan'] ?? '');

// Validate action
if (!in_array($action, ['verify', 'set_residu'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Validate status
if ($action === 'verify' && !in_array($status, ['valid', 'tidak_valid'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    // Get verifikasi data
    $stmt = $pdo->prepare("SELECT * FROM verifikasi_data_siswa WHERE id_siswa = ?");
    $stmt->execute([$id_siswa]);
    $verifikasi_data = $stmt->fetch();
    
    if (!$verifikasi_data) {
        echo json_encode(['success' => false, 'message' => 'Data verifikasi tidak ditemukan']);
        exit;
    }
    
    $status_sebelum = $verifikasi_data['status_overall'];
    $status_sesudah = $status;
    
    if ($action === 'set_residu') {
        $status_sesudah = 'residu';
    }
    
    // Update status
    $stmt = $pdo->prepare("UPDATE verifikasi_data_siswa SET 
        status_overall = ?,
        catatan_admin = ?,
        diverifikasi_oleh = ?,
        tanggal_verifikasi = NOW()
        WHERE id_siswa = ?");
    $stmt->execute([
        $status_sesudah,
        $catatan,
        $_SESSION['user_id'],
        $id_siswa
    ]);
    
    // Update documents status
    $stmt = $pdo->prepare("UPDATE siswa_dokumen_verifikasi SET 
        status_verifikasi = ?,
        keterangan_admin = ?,
        diverifikasi_oleh = ?,
        tanggal_verifikasi = NOW()
        WHERE id_siswa = ?");
    $stmt->execute([
        $status_sesudah,
        $catatan,
        $_SESSION['user_id'],
        $id_siswa
    ]);
    
    // Log history
    $action_type = $action === 'set_residu' ? 'set_residu' : 'verifikasi_' . $status;
    log_verifikasi_history(
        $verifikasi_data['id'],
        $id_siswa,
        $action_type,
        $status_sebelum,
        $status_sesudah,
        $_SESSION['user_id'],
        'admin',
        $verifikasi_data,
        ['status_overall' => $status_sesudah, 'catatan_admin' => $catatan],
        $catatan
    );
    
    // Create notification
    $jenis_notifikasi = $status_sesudah === 'valid' ? 'verifikasi_valid' : 
                       ($status_sesudah === 'residu' ? 'upload_ulang_diperlukan' : 'verifikasi_tidak_valid');
    $judul = $status_sesudah === 'valid' ? 'Dokumen Valid' : 
            ($status_sesudah === 'residu' ? 'Data Residu' : 'Dokumen Tidak Valid');
    $pesan = $status_sesudah === 'valid' ? 
            'Dokumen Anda telah diverifikasi dan dinyatakan VALID.' :
            ($status_sesudah === 'residu' ? 
            'Dokumen Anda masuk ke data residu. Silakan hubungi admin untuk penanganan lebih lanjut.' :
            'Dokumen Anda tidak valid. Silakan upload ulang dokumen yang benar.');
    
    if ($catatan) {
        $pesan .= "\n\nCatatan: " . $catatan;
    }
    
    create_notifikasi_verifikasi(
        $id_siswa,
        $verifikasi_data['id'],
        $jenis_notifikasi,
        $judul,
        $pesan
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Verifikasi berhasil disimpan'
    ]);
    
} catch (PDOException $e) {
    error_log("Verifikasi verify error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

