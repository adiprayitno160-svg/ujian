<?php
/**
 * OCR Scan API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * API endpoint untuk scan dokumen dengan Gemini Vision API
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

// Check if student (only students can scan)
if ($_SESSION['role'] !== 'siswa') {
    echo json_encode(['success' => false, 'message' => 'Only students can scan documents']);
    exit;
}

// Check if student is in class IX
$id_siswa = $_SESSION['user_id'];
if (!is_siswa_kelas_IX($id_siswa)) {
    echo json_encode(['success' => false, 'message' => 'Fitur ini hanya untuk siswa kelas IX']);
    exit;
}

// Check if Gemini OCR is enabled
if (!is_gemini_ocr_enabled()) {
    echo json_encode(['success' => false, 'message' => 'Gemini OCR tidak diaktifkan. Silakan hubungi admin.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get parameters
$jenis_dokumen = $_POST['jenis_dokumen'] ?? '';
$file = $_FILES['file'] ?? null;

// Validate jenis dokumen
if (!in_array($jenis_dokumen, ['ijazah', 'kk', 'akte'])) {
    echo json_encode(['success' => false, 'message' => 'Jenis dokumen tidak valid']);
    exit;
}

// Validate file
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error: ' . ($file['error'] ?? 'No file')]);
    exit;
}

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);
$allowed_types = VERIFIKASI_ALLOWED_TYPES;

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Tipe file tidak diizinkan. Hanya PDF, JPG, PNG yang diizinkan.']);
    exit;
}

// Validate file size
if ($file['size'] > VERIFIKASI_MAX_FILE_SIZE) {
    $max_size_mb = round(VERIFIKASI_MAX_FILE_SIZE / 1048576, 1);
    echo json_encode(['success' => false, 'message' => "Ukuran file terlalu besar. Maksimal: {$max_size_mb}MB"]);
    exit;
}

// Save uploaded file temporarily
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$temp_filename = 'temp_' . uniqid() . '_' . time() . '.' . $file_ext;
$temp_filepath = UPLOAD_VERIFIKASI . '/' . $temp_filename;

// Create directory if not exists
if (!is_dir(UPLOAD_VERIFIKASI)) {
    mkdir(UPLOAD_VERIFIKASI, 0755, true);
}

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $temp_filepath)) {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
    exit;
}

try {
    // Scan document with Gemini
    $scan_result = scan_dokumen_with_gemini($temp_filepath, $jenis_dokumen);
    
    // Delete temp file
    @unlink($temp_filepath);
    
    if (!$scan_result['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal melakukan scan: ' . $scan_result['message'],
            'error' => $scan_result['error'] ?? null
        ]);
        exit;
    }
    
    // Return result
    echo json_encode([
        'success' => true,
        'message' => 'Scan berhasil',
        'data' => $scan_result['data'],
        'confidence' => $scan_result['confidence'] ?? 100,
        'raw_text' => $scan_result['raw_text'] ?? null
    ]);
    
} catch (Exception $e) {
    // Delete temp file on error
    @unlink($temp_filepath);
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

