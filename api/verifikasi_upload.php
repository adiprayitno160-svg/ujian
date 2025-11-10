<?php
/**
 * Verifikasi Upload API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * API endpoint untuk upload dan save dokumen verifikasi
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ocr_functions.php';
require_once __DIR__ . '/../includes/verifikasi_functions.php';

global $pdo;

// Check authentication
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if student
if ($_SESSION['role'] !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Only students can upload documents']);
    exit;
}

// Check if student is in class IX
$id_siswa = $_SESSION['user_id'];
if (!is_siswa_kelas_IX($id_siswa)) {
    echo json_encode(['success' => false, 'message' => 'Fitur ini hanya untuk siswa kelas IX']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$action = $_POST['action'] ?? '';
$jenis_dokumen = $_POST['jenis_dokumen'] ?? '';
$file = $_FILES['file'] ?? null;
$ocr_data = isset($_POST['ocr_data']) ? json_decode($_POST['ocr_data'], true) : null;
$is_upload_ulang = isset($_POST['is_upload_ulang']) && $_POST['is_upload_ulang'] === '1';

// Validate action
if (!in_array($action, ['upload', 'upload_ulang'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Validate jenis dokumen
if (!in_array($jenis_dokumen, ['ijazah', 'kk', 'akte'])) {
    echo json_encode(['success' => false, 'message' => 'Jenis dokumen tidak valid']);
    exit;
}

// Check if upload ulang is allowed
if ($is_upload_ulang) {
    $stmt = $pdo->prepare("SELECT jumlah_upload_ulang FROM verifikasi_data_siswa WHERE id_siswa = ?");
    $stmt->execute([$id_siswa]);
    $verifikasi = $stmt->fetch();
    
    if ($verifikasi && $verifikasi['jumlah_upload_ulang'] >= VERIFIKASI_MAX_UPLOAD_ULANG) {
        echo json_encode(['success' => false, 'message' => 'Anda sudah mencapai batas upload ulang']);
        exit;
    }
}

// Validate file
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);
$allowed_types = VERIFIKASI_ALLOWED_TYPES;

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan']);
    exit;
}

// Validate file size
if ($file['size'] > VERIFIKASI_MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar']);
    exit;
}

// Validate OCR data
if (!$ocr_data || !isset($ocr_data['nama_anak'])) {
    echo json_encode(['success' => false, 'message' => 'Data OCR tidak valid']);
    exit;
}

try {
    // Generate filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'verifikasi_' . $id_siswa . '_' . $jenis_dokumen . '_' . time() . '.' . $file_ext;
    $filepath = UPLOAD_VERIFIKASI . '/' . $filename;
    
    // Create directory if not exists
    if (!is_dir(UPLOAD_VERIFIKASI)) {
        mkdir(UPLOAD_VERIFIKASI, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
        exit;
    }
    
    // Determine file type
    $file_type = 'pdf';
    if (in_array($mime_type, ['image/jpeg', 'image/jpg', 'image/png'])) {
        $file_type = str_replace('image/', '', $mime_type);
        if ($file_type === 'jpg') $file_type = 'jpeg';
    }
    
    // Get existing document (if any)
    $stmt = $pdo->prepare("SELECT * FROM siswa_dokumen_verifikasi WHERE id_siswa = ? AND jenis_dokumen = ?");
    $stmt->execute([$id_siswa, $jenis_dokumen]);
    $existing = $stmt->fetch();
    
    // Delete old file if exists
    if ($existing && file_exists(UPLOAD_VERIFIKASI . '/' . $existing['file_path'])) {
        @unlink(UPLOAD_VERIFIKASI . '/' . $existing['file_path']);
    }
    
    // Prepare data
    $data = [
        'id_siswa' => $id_siswa,
        'jenis_dokumen' => $jenis_dokumen,
        'file_path' => $filename,
        'file_type' => $file_type,
        'file_size' => $file['size'],
        'nama_anak' => $ocr_data['nama_anak'] ?? null,
        'nama_ayah' => $ocr_data['nama_ayah'] ?? null,
        'nama_ibu' => $ocr_data['nama_ibu'] ?? null,
        'nik' => $ocr_data['nik'] ?? null,
        'tempat_lahir' => $ocr_data['tempat_lahir'] ?? null,
        'tanggal_lahir' => $ocr_data['tanggal_lahir'] ?? null,
        'data_ekstrak_lainnya' => json_encode($ocr_data['data_lainnya'] ?? []),
        'status_ocr' => 'success',
        'status_verifikasi' => 'belum',
        'ocr_text' => $ocr_data['raw_text'] ?? null,
        'ocr_confidence' => $ocr_data['confidence'] ?? 100
    ];
    
    if ($is_upload_ulang) {
        $data['jumlah_upload_ulang'] = ($existing['jumlah_upload_ulang'] ?? 0) + 1;
        $data['status_verifikasi'] = 'belum';
    }
    
    if ($existing) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE siswa_dokumen_verifikasi SET 
            file_path = :file_path,
            file_type = :file_type,
            file_size = :file_size,
            nama_anak = :nama_anak,
            nama_ayah = :nama_ayah,
            nama_ibu = :nama_ibu,
            nik = :nik,
            tempat_lahir = :tempat_lahir,
            tanggal_lahir = :tanggal_lahir,
            data_ekstrak_lainnya = :data_ekstrak_lainnya,
            status_ocr = :status_ocr,
            status_verifikasi = :status_verifikasi,
            ocr_text = :ocr_text,
            ocr_confidence = :ocr_confidence,
            jumlah_upload_ulang = :jumlah_upload_ulang,
            updated_at = NOW()
            WHERE id_siswa = :id_siswa AND jenis_dokumen = :jenis_dokumen");
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO siswa_dokumen_verifikasi (
            id_siswa, jenis_dokumen, file_path, file_type, file_size,
            nama_anak, nama_ayah, nama_ibu, nik, tempat_lahir, tanggal_lahir,
            data_ekstrak_lainnya, status_ocr, status_verifikasi,
            ocr_text, ocr_confidence, jumlah_upload_ulang
        ) VALUES (
            :id_siswa, :jenis_dokumen, :file_path, :file_type, :file_size,
            :nama_anak, :nama_ayah, :nama_ibu, :nik, :tempat_lahir, :tanggal_lahir,
            :data_ekstrak_lainnya, :status_ocr, :status_verifikasi,
            :ocr_text, :ocr_confidence, :jumlah_upload_ulang
        )");
    }
    
    $stmt->execute($data);
    
    // Update verifikasi data siswa
    update_verifikasi_data_siswa($id_siswa);
    
    // Get verifikasi data
    $stmt = $pdo->prepare("SELECT id FROM verifikasi_data_siswa WHERE id_siswa = ?");
    $stmt->execute([$id_siswa]);
    $verifikasi_data = $stmt->fetch();
    
    // Log history
    if ($verifikasi_data) {
        $action_type = $is_upload_ulang ? 'upload_ulang' : 'upload';
        log_verifikasi_history(
            $verifikasi_data['id'],
            $id_siswa,
            $action_type,
            $existing ? $existing['status_verifikasi'] : 'belum',
            'belum',
            $id_siswa,
            'siswa',
            $existing ? $existing : null,
            $data,
            $is_upload_ulang ? 'Upload ulang dokumen' : 'Upload dokumen baru'
        );
    }
    
    // Validate all documents
    $validation = validate_all_dokumen($id_siswa);
    
    // Create notification
    if ($validation['valid']) {
        create_notifikasi_verifikasi(
            $id_siswa,
            $verifikasi_data['id'] ?? null,
            'upload_berhasil',
            'Dokumen Berhasil Diupload',
            'Semua dokumen telah diupload dan nama sesuai. Menunggu verifikasi admin.'
        );
    } else {
        create_notifikasi_verifikasi(
            $id_siswa,
            $verifikasi_data['id'] ?? null,
            'upload_berhasil',
            'Dokumen Berhasil Diupload',
            'Dokumen telah diupload. Terdapat ketidaksesuaian nama. Silakan periksa dokumen Anda.'
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Dokumen berhasil diupload',
        'validation' => $validation
    ]);
    
} catch (PDOException $e) {
    error_log("Verifikasi upload error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

