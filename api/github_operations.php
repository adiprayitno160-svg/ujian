<?php
/**
 * GitHub Operations API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Handle: Git pull, push, status, backup
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== ROLE_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function to execute shell commands safely
function exec_safe($command, $cwd = null) {
    $cwd = $cwd ?: __DIR__ . '/..';
    $output = [];
    $return_var = 0;
    
    // Change to project directory
    $old_cwd = getcwd();
    chdir($cwd);
    
    // Execute command
    exec($command . ' 2>&1', $output, $return_var);
    
    // Restore directory
    chdir($old_cwd);
    
    return [
        'success' => $return_var === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_var
    ];
}

// Helper function to backup database
function backup_database() {
    global $pdo;
    
    try {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_His') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Get database config
        $host = DB_HOST;
        $user = DB_USER;
        $pass = DB_PASS;
        $db = DB_NAME;
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h %s -u %s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass ? '-p' . escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($filepath)
        );
        
        exec($command . ' 2>&1', $output, $return_var);
        
        if ($return_var === 0 && file_exists($filepath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to create backup: ' . implode("\n", $output)
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Handle actions
try {
    switch ($action) {
        case 'git_status':
            // Get Git status
            $status = exec_safe('git status --porcelain');
            $branch = exec_safe('git rev-parse --abbrev-ref HEAD');
            $last_commit = exec_safe('git log -1 --format="%h - %s (%ar)"');
            
            $is_clean = empty(trim($status['output']));
            $changes = $is_clean ? null : $status['output'];
            
            echo json_encode([
                'success' => true,
                'branch' => trim($branch['output']),
                'last_commit' => trim($last_commit['output']),
                'is_clean' => $is_clean,
                'changes' => $changes
            ]);
            break;
            
        case 'pull':
            // Pull from GitHub
            $branch = $_POST['branch'] ?? 'main';
            $auto_backup = isset($_POST['auto_backup']) && $_POST['auto_backup'] == 1;
            
            $backup_file = null;
            if ($auto_backup) {
                $backup_result = backup_database();
                if ($backup_result['success']) {
                    $backup_file = $backup_result['filename'];
                }
            }
            
            // Check if git is initialized
            $git_check = exec_safe('git rev-parse --git-dir');
            if (!$git_check['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Git repository belum diinisialisasi. Jalankan: git init && git remote add origin https://github.com/adiprayitno160-svg/ujian.git'
                ]);
                break;
            }
            
            // Fetch and pull
            $fetch = exec_safe('git fetch origin');
            $pull = exec_safe("git pull origin $branch");
            
            if ($pull['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Update berhasil di-pull dari GitHub',
                    'output' => $pull['output'],
                    'backup_file' => $backup_file
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal pull dari GitHub: ' . $pull['output']
                ]);
            }
            break;
            
        case 'push':
            // Push to GitHub
            $branch = $_POST['branch'] ?? 'main';
            $commit_message = $_POST['commit_message'] ?? 'Update sistem';
            $include_database = isset($_POST['include_database']) && $_POST['include_database'] == 1;
            
            // Backup database if requested
            if ($include_database) {
                $backup_result = backup_database();
                if ($backup_result['success']) {
                    // Add backup to git
                    exec_safe('git add backups/' . $backup_result['filename']);
                }
            }
            
            // Add all changes
            $add = exec_safe('git add .');
            
            // Check if there are changes
            $status = exec_safe('git status --porcelain');
            if (empty(trim($status['output']))) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tidak ada perubahan untuk di-commit'
                ]);
                break;
            }
            
            // Commit
            $commit = exec_safe('git commit -m ' . escapeshellarg($commit_message));
            
            if (!$commit['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal commit: ' . $commit['output']
                ]);
                break;
            }
            
            // Push
            $push = exec_safe("git push origin $branch");
            
            if ($push['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Berhasil push ke GitHub',
                    'output' => $push['output']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal push ke GitHub: ' . $push['output'] . 
                                '. Pastikan remote repository sudah dikonfigurasi dan credentials sudah benar.'
                ]);
            }
            break;
            
        case 'backup_database':
            // Create database backup
            $result = backup_database();
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'filename' => $result['filename'],
                    'message' => 'Backup database berhasil dibuat',
                    'size' => $result['size']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]);
            }
            break;
            
        case 'upload_database':
            // Export and push database to GitHub
            $result = backup_database();
            
            if (!$result['success']) {
                echo json_encode([
                    'success' => false,
                    'message' => $result['message']
                ]);
                break;
            }
            
            // Add to git
            $add = exec_safe('git add backups/' . $result['filename']);
            
            // Commit
            $commit = exec_safe('git commit -m "Database backup: ' . date('Y-m-d H:i:s') . '"');
            
            // Push
            $branch = $_POST['branch'] ?? 'main';
            $push = exec_safe("git push origin $branch");
            
            if ($push['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Database berhasil di-upload ke GitHub',
                    'filename' => $result['filename']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Gagal push ke GitHub: ' . $push['output']
                ]);
            }
            break;
            
        case 'list_backups':
            // List backup files
            $backup_dir = __DIR__ . '/../backups';
            $backups = [];
            
            if (is_dir($backup_dir)) {
                $files = glob($backup_dir . '/*.sql');
                foreach ($files as $file) {
                    $backups[] = [
                        'filename' => basename($file),
                        'size' => formatFileSize(filesize($file)),
                        'date' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
                
                // Sort by date descending
                usort($backups, function($a, $b) {
                    return strcmp($b['date'], $a['date']);
                });
            }
            
            echo json_encode([
                'success' => true,
                'backups' => $backups
            ]);
            break;
            
        case 'download_backup':
            // Download backup file
            $filename = $_GET['file'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'Filename required']);
                exit;
            }
            
            $filepath = __DIR__ . '/../backups/' . basename($filename);
            
            if (!file_exists($filepath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File not found']);
                exit;
            }
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
            
        case 'delete_backup':
            // Delete backup file
            $filename = $_POST['filename'] ?? '';
            if (empty($filename)) {
                echo json_encode(['success' => false, 'message' => 'Filename required']);
                exit;
            }
            
            $filepath = __DIR__ . '/../backups/' . basename($filename);
            
            if (file_exists($filepath) && unlink($filepath)) {
                echo json_encode(['success' => true, 'message' => 'Backup berhasil dihapus']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus backup']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("GitHub operations error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    ]);
}

// Helper function to format file size
function formatFileSize($bytes) {
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

