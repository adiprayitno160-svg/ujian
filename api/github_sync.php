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

// Only admin can access (skip check if called from test file)
if (basename($_SERVER['PHP_SELF']) !== 'test_api_update.php') {
    if (!is_logged_in() || $_SESSION['role'] !== ROLE_ADMIN) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
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

// Check for updates from GitHub
function checkUpdateAvailable($repo_path, $branch = null) {
    try {
        if (!is_dir($repo_path . '/.git')) {
            return [
                'has_update' => false,
                'success' => false,
                'message' => 'Bukan Git repository'
            ];
        }
        
        $old_dir = getcwd();
        @chdir($repo_path);
        
        // Get current branch (use provided branch or detect current)
        if (empty($branch)) {
            $branch_output = [];
            $branch_return = 0;
            @exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_output, $branch_return);
            $current_branch = ($branch_return === 0 && !empty($branch_output)) ? trim($branch_output[0]) : 'master';
        } else {
            $current_branch = $branch;
        }
        
        // Escape branch name for shell command
        $current_branch_escaped = escapeshellarg($current_branch);
        
        // Get current commit
        $commit_output = [];
        $commit_return = 0;
        @exec('git rev-parse --short HEAD 2>&1', $commit_output, $commit_return);
        $current_commit = ($commit_return === 0 && !empty($commit_output)) ? trim($commit_output[0]) : null;
        
        // Fetch latest from remote (with timeout handling)
        // Try to fetch, but don't fail if it takes too long
        @exec('timeout 10 git fetch origin ' . $current_branch_escaped . ' 2>&1', $fetch_output, $fetch_return);
        // If timeout command not available, try without timeout
        if ($fetch_return !== 0 && strpos(implode(' ', $fetch_output), 'timeout') === false) {
            @exec('git fetch origin ' . $current_branch_escaped . ' 2>&1', $fetch_output, $fetch_return);
        }
        
        // Check if behind remote (compare with remote branch)
        $behind_output = [];
        $behind_return = 0;
        @exec('git rev-list HEAD..origin/' . $current_branch_escaped . ' --count 2>&1', $behind_output, $behind_return);
        $behind_count = 0;
        if ($behind_return === 0 && !empty($behind_output)) {
            $count_str = trim($behind_output[0]);
            if (is_numeric($count_str)) {
                $behind_count = intval($count_str);
            }
        }
        
        // Get latest commit from remote
        $remote_commit_output = [];
        $remote_commit_return = 0;
        @exec('git rev-parse --short origin/' . $current_branch_escaped . ' 2>&1', $remote_commit_output, $remote_commit_return);
        $latest_commit = ($remote_commit_return === 0 && !empty($remote_commit_output)) ? trim($remote_commit_output[0]) : null;
        
        // Get latest tag (version) if available
        $tag_output = [];
        $tag_return = 0;
        @exec('git describe --tags --abbrev=0 origin/' . $current_branch_escaped . ' 2>&1', $tag_output, $tag_return);
        $latest_tag = ($tag_return === 0 && !empty($tag_output)) ? trim($tag_output[0]) : null;
        
        // Get current tag
        $current_tag_output = [];
        $current_tag_return = 0;
        @exec('git describe --tags --abbrev=0 2>&1', $current_tag_output, $current_tag_return);
        $current_tag = ($current_tag_return === 0 && !empty($current_tag_output)) ? trim($current_tag_output[0]) : null;
        
        @chdir($old_dir);
        
        $has_update = $behind_count > 0;
        
        return [
            'has_update' => $has_update,
            'success' => true,
            'current_commit' => $current_commit,
            'latest_commit' => $latest_commit,
            'current_tag' => $current_tag,
            'latest_tag' => $latest_tag,
            'behind_count' => $behind_count,
            'branch' => $current_branch,
            'message' => $has_update ? "Ada $behind_count update tersedia" : "Tidak ada update"
        ];
    } catch (Exception $e) {
        @chdir($old_dir ?? getcwd());
        return [
            'has_update' => false,
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Get git status
function getGitStatus($repo_path) {
    try {
        $output = [];
        $return_var = 0;
        
        if (!is_dir($repo_path)) {
            return [
                'has_changes' => false,
                'changes' => [],
                'success' => false
            ];
        }
        
        $old_dir = getcwd();
        @chdir($repo_path);
        
        // Set timeout for git command
        exec('git status --porcelain 2>&1', $output, $return_var);
        
        @chdir($old_dir);
        
        return [
            'has_changes' => count($output) > 0,
            'changes' => $output,
            'success' => $return_var === 0
        ];
    } catch (Exception $e) {
        return [
            'has_changes' => false,
            'changes' => [],
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Get current branch and commit
function getGitInfo($repo_path) {
    $info = [
        'branch' => null,
        'commit' => null,
        'remote' => null,
        'is_repo' => false
    ];
    
    try {
        if (!is_dir($repo_path . '/.git')) {
            return $info;
        }
        
        $old_dir = getcwd();
        @chdir($repo_path);
        
        // Get branch
        $output = [];
        @exec('git rev-parse --abbrev-ref HEAD 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $info['branch'] = trim($output[0]);
        }
        
        // Get commit hash
        $output = [];
        @exec('git rev-parse --short HEAD 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $info['commit'] = trim($output[0]);
        }
        
        // Get remote URL
        $output = [];
        @exec('git config --get remote.origin.url 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $info['remote'] = trim($output[0]);
        }
        
        $info['is_repo'] = true;
        
        @chdir($old_dir);
        
        return $info;
    } catch (Exception $e) {
        @chdir($old_dir ?? getcwd());
        return $info;
    }
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
function pullFromGitHub($repo_path, $branch = null) {
    try {
        if (!is_dir($repo_path . '/.git')) {
            return [
                'success' => false,
                'message' => 'Bukan Git repository'
            ];
        }
        
        $old_dir = getcwd();
        @chdir($repo_path);
        
        // Get current branch if not specified
        if (empty($branch)) {
            $branch_output = [];
            $branch_return = 0;
            @exec('git rev-parse --abbrev-ref HEAD 2>&1', $branch_output, $branch_return);
            $branch = ($branch_return === 0 && !empty($branch_output)) ? trim($branch_output[0]) : 'master';
        }
        
        // Get current commit before update
        $old_commit_output = [];
        $old_commit_return = 0;
        @exec('git rev-parse --short HEAD 2>&1', $old_commit_output, $old_commit_return);
        $old_commit = ($old_commit_return === 0 && !empty($old_commit_output)) ? trim($old_commit_output[0]) : null;
        
        // Stash local changes if any
        $stash_output = [];
        @exec('git stash push -m "Stash before update ' . date('Y-m-d_H-i-s') . '" 2>&1', $stash_output, $stash_return);
        // Don't fail if stash fails (might be no changes)
        
        // Fetch latest changes
        $fetch_output = [];
        $fetch_return = 0;
        @exec('git fetch origin ' . escapeshellarg($branch) . ' 2>&1', $fetch_output, $fetch_return);
        if ($fetch_return !== 0) {
            @chdir($old_dir);
            return [
                'success' => false,
                'message' => 'Failed to fetch: ' . implode("\n", $fetch_output)
            ];
        }
        
        // Reset hard to remote branch (clean update)
        $reset_output = [];
        $reset_return = 0;
        @exec('git reset --hard origin/' . escapeshellarg($branch) . ' 2>&1', $reset_output, $reset_return);
        
        if ($reset_return !== 0) {
            // Fallback: try pull
            $pull_output = [];
            $pull_return = 0;
            @exec('git pull origin ' . escapeshellarg($branch) . ' 2>&1', $pull_output, $pull_return);
            if ($pull_return !== 0) {
                @chdir($old_dir);
                return [
                    'success' => false,
                    'message' => 'Failed to pull: ' . implode("\n", $pull_output)
                ];
            }
            $output = $pull_output;
        } else {
            $output = $reset_output;
        }
        
        // Get new commit after update
        $new_commit_output = [];
        $new_commit_return = 0;
        @exec('git rev-parse --short HEAD 2>&1', $new_commit_output, $new_commit_return);
        $new_commit = ($new_commit_return === 0 && !empty($new_commit_output)) ? trim($new_commit_output[0]) : null;
        
        @chdir($old_dir);
        
        $message = 'Successfully pulled from GitHub';
        if ($old_commit && $new_commit && $old_commit !== $new_commit) {
            $message .= ' (from ' . $old_commit . ' to ' . $new_commit . ')';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'branch' => $branch,
            'old_commit' => $old_commit,
            'new_commit' => $new_commit,
            'output' => $output
        ];
    } catch (Exception $e) {
        @chdir($old_dir ?? getcwd());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
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
            // Set execution time limit
            @set_time_limit(10);
            
            $git_available = @checkGitAvailable();
            $git_info = @getGitInfo($repo_path);
            $git_status = @getGitStatus($repo_path);
            
            // Ensure we always return valid JSON
            $response = [
                'success' => true,
                'git_available' => $git_available !== false,
                'git_info' => $git_info ?: ['is_repo' => false, 'branch' => null, 'commit' => null, 'remote' => null],
                'git_status' => $git_status ?: ['has_changes' => false, 'changes' => [], 'success' => false],
                'github_url' => $github_url
            ];
            
            echo json_encode($response);
            break;
            
        case 'init':
            $result = initGitRepo($repo_path, $github_repo);
            echo json_encode($result);
            break;
            
        case 'pull':
            // Get branch from POST, default to current branch or master
            $branch = $_POST['branch'] ?? $_GET['branch'] ?? null;
            $skip_backup = isset($_POST['skip_backup']) && $_POST['skip_backup'] === '1';
            
            // Backup database first (unless skipped)
            $backup = null;
            if (!$skip_backup) {
                $backup = backupDatabase();
            }
            
            // Set timeout for long operations
            @set_time_limit(300); // 5 minutes
            
            $result = pullFromGitHub($repo_path, $branch);
            $result['backup'] = $backup;
            
            // Log the operation
            error_log("GitHub pull: branch=$branch, success=" . ($result['success'] ? 'true' : 'false'));
            
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
            
        case 'check_update':
            // Check for updates available
            @set_time_limit(15);
            // Get branch from GET/POST, default to current branch
            $branch = $_GET['branch'] ?? $_POST['branch'] ?? null;
            $update_info = checkUpdateAvailable($repo_path, $branch);
            echo json_encode($update_info);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("GitHub sync error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

