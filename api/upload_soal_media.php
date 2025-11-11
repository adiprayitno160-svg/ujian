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
    $error_message = 'Tidak ada file yang diupload';
    if (isset($_FILES['media']['error'])) {
        switch ($_FILES['media']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message = 'File terlalu besar. Ukuran file melebihi batas yang diizinkan oleh server. Maksimal: 500KB untuk gambar soal, 100KB untuk gambar opsi jawaban.';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File terlalu besar. Ukuran file melebihi batas yang diizinkan. Maksimal: 500KB untuk gambar soal, 100KB untuk gambar opsi jawaban.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File hanya terupload sebagian. Pastikan koneksi internet stabil dan coba lagi.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'Tidak ada file yang dipilih. Silakan pilih file gambar (JPG, PNG, GIF, atau WebP) untuk diupload.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Kesalahan server: Folder temporary tidak ditemukan. Silakan hubungi administrator.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Gagal menulis file ke server. Pastikan folder upload memiliki izin menulis. Silakan hubungi administrator.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'Upload dihentikan oleh extension PHP. Silakan hubungi administrator.';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

$file = $_FILES['media'];

// Check if this is for option image (gambar opsi jawaban)
$is_option_image = isset($_POST['is_option_image']) && $_POST['is_option_image'] === '1';

// Validate file type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

$media_type = null;
$max_size = MAX_FILE_SIZE;

// Check if it's an image
if (in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
    $media_type = 'gambar';
    // If this is for option image, use 100KB limit
    if ($is_option_image) {
        $max_size = 102400; // 100KB for option images
    } else {
        $max_size = MAX_FILE_SIZE; // 500KB for soal media
    }
}
// Videos are disabled - reject video uploads
elseif (in_array($mime_type, ALLOWED_VIDEO_TYPES)) {
    $max_size_display = $is_option_image ? '100KB' : '500KB';
    echo json_encode([
        'success' => false, 
        'message' => 'Video tidak diizinkan. Hanya format gambar yang diizinkan: JPG, JPEG, PNG, GIF, atau WebP. Ukuran maksimal: ' . $max_size_display . ($is_option_image ? ' untuk gambar opsi jawaban' : ' untuk gambar soal')
    ]);
    exit;
}
else {
    $max_size_display = $is_option_image ? '100KB' : '500KB';
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    echo json_encode([
        'success' => false, 
        'message' => 'Format file tidak didukung. File yang Anda upload: ' . htmlspecialchars($file['name']) . ' (.' . $file_extension . '). Format yang diizinkan: JPG, JPEG, PNG, GIF, atau WebP. Ukuran maksimal: ' . $max_size_display . ($is_option_image ? ' untuk gambar opsi jawaban' : ' untuk gambar soal')
    ]);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    $file_size_kb = round($file['size'] / 1024, 2);
    $max_size_kb = round($max_size / 1024, 0);
    
    if ($is_option_image) {
        echo json_encode([
            'success' => false, 
            'message' => "Ukuran file terlalu besar: {$file_size_kb} KB. Maksimal: {$max_size_kb} KB (100 KB) untuk gambar opsi jawaban. Silakan kompres gambar atau gunakan gambar yang lebih kecil."
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Ukuran file terlalu besar: {$file_size_kb} KB. Maksimal: {$max_size_kb} KB (500 KB) untuk gambar soal. Silakan kompres gambar atau gunakan gambar yang lebih kecil."
        ]);
    }
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

