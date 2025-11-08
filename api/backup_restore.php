<?php
/**
 * Backup & Restore API Handler
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Only allow admin
require_role(ROLE_ADMIN);

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'backup':
            $include_sourcecode = isset($_POST['include_sourcecode']) && $_POST['include_sourcecode'] === '1';
            $result = backup_database($include_sourcecode);
            
            if ($result['success']) {
                log_activity('backup_' . ($include_sourcecode ? 'full' : 'database'), 'backups', null);
            }
            
            echo json_encode($result);
            break;
            
        case 'restore':
            if (!isset($_FILES['backup_file'])) {
                echo json_encode(['success' => false, 'message' => 'File backup tidak ditemukan']);
                exit;
            }
            
            $file = $_FILES['backup_file'];
            
            // Check if it's a zip file (full backup) or sql file (database only)
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'zip') {
                if (!class_exists('ZipArchive')) {
                    echo json_encode(['success' => false, 'message' => 'Extension ZipArchive tidak tersedia. Install php-zip extension.']);
                    exit;
                }
                
                // Extract zip file
                $backup_dir = BASE_PATH . '/backups';
                $temp_dir = $backup_dir . '/temp_' . time();
                mkdir($temp_dir, 0755, true);
                
                $zip = new ZipArchive();
                if ($zip->open($file['tmp_name']) === TRUE) {
                    $zip->extractTo($temp_dir);
                    $zip->close();
                    
                    // Find SQL file in extracted files
                    $sql_file = null;
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::SELF_FIRST
                    );
                    
                    foreach ($iterator as $file_item) {
                        if ($file_item->isFile() && $file_item->getExtension() === 'sql') {
                            $sql_file = $file_item->getPathname();
                            break;
                        }
                    }
                    
                    if ($sql_file) {
                        $result = restore_database($sql_file);
                    } else {
                        $result = ['success' => false, 'message' => 'File SQL tidak ditemukan dalam backup'];
                    }
                    
                    // Cleanup temp directory
                    array_map('unlink', glob("$temp_dir/**/*"));
                    array_map('rmdir', glob("$temp_dir/**"));
                    rmdir($temp_dir);
                } else {
                    $result = ['success' => false, 'message' => 'Gagal mengekstrak file ZIP'];
                }
            } elseif ($file_ext === 'sql') {
                // Direct SQL file
                $upload_result = upload_file($file, BASE_PATH . '/backups', ['application/sql', 'text/plain', 'text/x-sql'], 100 * 1024 * 1024); // 100MB max
                
                if ($upload_result['success']) {
                    $result = restore_database($upload_result['path']);
                    // Delete uploaded file after restore
                    if (file_exists($upload_result['path'])) {
                        unlink($upload_result['path']);
                    }
                } else {
                    $result = $upload_result;
                }
            } else {
                $result = ['success' => false, 'message' => 'Format file tidak didukung. Gunakan file .sql atau .zip'];
            }
            
            if ($result['success']) {
                log_activity('restore_database', 'backups', null);
            }
            
            echo json_encode($result);
            break;
            
        case 'list':
            $files = get_backup_files();
            $formatted_files = array_map(function($file) {
                return [
                    'filename' => $file['filename'],
                    'size' => $file['size'],
                    'size_formatted' => format_file_size($file['size']),
                    'modified' => date('Y-m-d H:i:s', $file['modified']),
                    'type' => $file['type']
                ];
            }, $files);
            
            echo json_encode(['success' => true, 'files' => $formatted_files]);
            break;
            
        case 'download':
            $filename = $_GET['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'Filename tidak ditemukan']);
                exit;
            }
            
            $backup_dir = BASE_PATH . '/backups';
            $file_path = $backup_dir . '/' . basename($filename);
            
            if (!file_exists($file_path)) {
                echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
                exit;
            }
            
            // Security: only allow files from backup directory
            if (strpos(realpath($file_path), realpath($backup_dir)) !== 0) {
                echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan']);
                exit;
            }
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
            
        case 'delete':
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'Filename tidak ditemukan']);
                exit;
            }
            
            $backup_dir = BASE_PATH . '/backups';
            $file_path = $backup_dir . '/' . basename($filename);
            
            if (!file_exists($file_path)) {
                echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
                exit;
            }
            
            // Security: only allow files from backup directory
            if (strpos(realpath($file_path), realpath($backup_dir)) !== 0) {
                echo json_encode(['success' => false, 'message' => 'Akses tidak diizinkan']);
                exit;
            }
            
            if (unlink($file_path)) {
                log_activity('delete_backup', 'backups', $filename);
                echo json_encode(['success' => true, 'message' => 'File backup berhasil dihapus']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus file']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

/**
 * Format file size
 */
function format_file_size($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

