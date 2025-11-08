<?php
/**
 * GitHub Sync API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Fitur: Update dari GitHub, Upload ke GitHub
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Only admin can access
if (!is_logged_in() || $_SESSION['role'] !== ROLE_ADMIN) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// GitHub repository info
$github_repo = 'adiprayitno160-svg/ujian';
$github_url = 'https://github.com/' . $github_repo;
$repo_path = __DIR__ . '/..';

// Check if git is available
function checkGitAvailable() {
    $output = [];
    $return_var = 0;
    exec('git --version 2>&1', $output, $return_var);
    return $return_var === 0;
}

// Get git status
function getGitStatus($repo_path) {
    $output = [];
    $return_var = 0;
    chdir($repo_path);
    exec('git status --porcelain 2>&1', $output, $return_var);
    return [
        'has_changes' => count($output) > 0,
        'changes' => $output,
        'success' => $return_var === 0
    ];
}

// Get current branch and commit
function getGitInfo($repo_path) {
    $info = [
        'branch' => 'unknown',
        'commit' => 'unknown',
        'remote' => 'unknown',
        'is_repo' => false
    ];
    
    if (!is_dir($repo_path . '/.git')) {
        return $info;
    }
    
    chdir($repo_path);
    
    // Get branch
    $output = [];
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $output, $return_var);
    if ($return_var === 0 && !empty($output)) {
        $info['branch'] = trim($output[0]);
    }
    
    // Get commit hash
    $output = [];
    exec('git rev-parse --short HEAD 2>&1', $output, $return_var);
    if ($return_var === 0 && !empty($output)) {
        $info['commit'] = trim($output[0]);
    }
    
    // Get remote URL
    $output = [];
    exec('git config --get remote.origin.url 2>&1', $output, $return_var);
    if ($return_var === 0 && !empty($output)) {
        $info['remote'] = trim($output[0]);
    }
    
    $info['is_repo'] = true;
    return $info;
}

// Initialize git repository if not exists
function initGitRepo($repo_path, $github_repo) {
    $output = [];
    $return_var = 0;
    chdir($repo_path);
    
    // Check if already a git repo
    if (is_dir($repo_path . '/.git')) {
        return ['success' => true, 'message' => 'Repository already initialized'];
    }
    
    // Initialize git
    exec('git init 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        return ['success' => false, 'message' => 'Failed to initialize git: ' . implode("\n", $output)];
    }
    
    // Add remote
    exec('git remote add origin https://github.com/' . $github_repo . '.git 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        return ['success' => false, 'message' => 'Failed to add remote: ' . implode("\n", $output)];
    }
    
    return ['success' => true, 'message' => 'Repository initialized successfully'];
}

// Pull from GitHub
function pullFromGitHub($repo_path) {
    $output = [];
    $return_var = 0;
    chdir($repo_path);
    
    // Fetch latest changes
    exec('git fetch origin 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        return ['success' => false, 'message' => 'Failed to fetch: ' . implode("\n", $output)];
    }
    
    // Pull changes
    $output = [];
    exec('git pull origin main 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        // Try master branch
        exec('git pull origin master 2>&1', $output, $return_var);
        if ($return_var !== 0) {
            return ['success' => false, 'message' => 'Failed to pull: ' . implode("\n", $output)];
        }
    }
    
    return ['success' => true, 'message' => 'Successfully pulled from GitHub', 'output' => $output];
}

// Push to GitHub
function pushToGitHub($repo_path, $commit_message = 'Update from system') {
    $output = [];
    $return_var = 0;
    chdir($repo_path);
    
    // Get current branch
    $branch_output = [];
    exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_output, $branch_return);
    $branch = ($branch_return === 0 && !empty($branch_output)) ? trim($branch_output[0]) : 'main';
    
    // Add all changes
    exec('git add . 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        return ['success' => false, 'message' => 'Failed to add files: ' . implode("\n", $output)];
    }
    
    // Commit
    $commit_message_escaped = escapeshellarg($commit_message);
    exec('git commit -m ' . $commit_message_escaped . ' 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        // Check if there are changes to commit
        if (strpos(implode("\n", $output), 'nothing to commit') !== false) {
            return ['success' => true, 'message' => 'No changes to commit', 'output' => $output];
        }
        return ['success' => false, 'message' => 'Failed to commit: ' . implode("\n", $output)];
    }
    
    // Push
    $output = [];
    exec('git push origin ' . $branch . ' 2>&1', $output, $return_var);
    if ($return_var !== 0) {
        return ['success' => false, 'message' => 'Failed to push: ' . implode("\n", $output)];
    }
    
    return ['success' => true, 'message' => 'Successfully pushed to GitHub', 'output' => $output];
}

// Backup database before operations
function backupDatabase() {
    global $pdo;
    
    try {
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . '/' . $filename;
        
        // Get database config
        $db_host = DB_HOST;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        $db_name = DB_NAME;
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h%s -u%s %s %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            !empty($db_pass) ? '-p' . escapeshellarg($db_pass) : '',
            escapeshellarg($db_name),
            escapeshellarg($filepath)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0 && file_exists($filepath)) {
            return ['success' => true, 'filepath' => $filepath, 'filename' => $filename];
        } else {
            return ['success' => false, 'message' => 'Failed to backup database: ' . implode("\n", $output)];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Handle actions
try {
    switch ($action) {
        case 'status':
            $git_available = checkGitAvailable();
            $git_info = getGitInfo($repo_path);
            $git_status = getGitStatus($repo_path);
            
            echo json_encode([
                'success' => true,
                'git_available' => $git_available,
                'git_info' => $git_info,
                'git_status' => $git_status,
                'github_url' => $github_url
            ]);
            break;
            
        case 'init':
            $result = initGitRepo($repo_path, $github_repo);
            echo json_encode($result);
            break;
            
        case 'pull':
            // Backup database first
            $backup = backupDatabase();
            
            $result = pullFromGitHub($repo_path);
            $result['backup'] = $backup;
            
            echo json_encode($result);
            break;
            
        case 'push':
            $commit_message = sanitize($_POST['commit_message'] ?? 'Update from system');
            $include_database = isset($_POST['include_database']) && $_POST['include_database'] === '1';
            
            // Backup database if requested
            $backup = null;
            if ($include_database) {
                $backup = backupDatabase();
                if ($backup['success']) {
                    // Add backup to git (optional, you might want to exclude it)
                    // For security, we'll exclude database backups from git
                }
            }
            
            $result = pushToGitHub($repo_path, $commit_message);
            $result['backup'] = $backup;
            
            echo json_encode($result);
            break;
            
        case 'backup_db':
            $result = backupDatabase();
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("GitHub sync error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

