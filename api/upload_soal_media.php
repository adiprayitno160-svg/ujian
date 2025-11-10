<?php
/**
 * API: Upload Media for Soal (Image/Video)
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'No file uploaded';
    if (isset($_FILES['media']['error'])) {
        switch ($_FILES['media']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File terlalu besar';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File hanya terupload sebagian';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'Tidak ada file yang diupload';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Folder temporary tidak ditemukan';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Gagal menulis file ke disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'Upload dihentikan oleh extension';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

$file = $_FILES['media'];

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

$media_type = null;
$max_size = MAX_FILE_SIZE;

// Check if it's an image
if (in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
    $media_type = 'gambar';
    $max_size = MAX_FILE_SIZE;
}
// Check if it's a video
elseif (in_array($mime_type, ALLOWED_VIDEO_TYPES)) {
    $media_type = 'video';
    $max_size = MAX_VIDEO_SIZE;
}
else {
    echo json_encode([
        'success' => false, 
        'message' => 'Tipe file tidak diizinkan. Hanya gambar (JPG, PNG, GIF, WebP) dan video (MP4, WebM, OGG) yang diizinkan.'
    ]);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    $max_size_mb = round($max_size / 1048576, 1);
    echo json_encode([
        'success' => false, 
        'message' => "Ukuran file terlalu besar. Maksimal: {$max_size_mb}MB"
    ]);
    exit;
}

// Get file extension
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Generate unique filename
$filename = 'soal_' . uniqid() . '_' . time() . '.' . $extension;
$upload_dir = UPLOAD_SOAL;
$filepath = $upload_dir . '/' . $filename;

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'File berhasil diupload',
        'filename' => $filename,
        'path' => $filename, // Relative path for database
        'media_type' => $media_type,
        'url' => UPLOAD_URL . '/soal/' . $filename,
        'size' => $file['size'],
        'mime_type' => $mime_type
    ]);
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Gagal mengupload file. Pastikan folder upload dapat ditulis.'
    ]);
}

