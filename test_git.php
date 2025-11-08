<?php
/**
 * Test Git Installation & Configuration
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Akses: http://localhost/UJAN/test_git.php
 */

require_once __DIR__ . '/config/config.php';

// Only allow admin or localhost
$is_admin = (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN);
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if (!$is_admin && !$is_localhost) {
    die('Access denied');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test Git Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-check-circle"></i> Test Git Installation</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $repo_path = __DIR__;
                        $results = [];
                        
                        // Test 1: Check if Git is installed
                        echo "<h5>1. Checking Git Installation</h5>";
                        $output = [];
                        $return_var = 0;
                        exec('git --version 2>&1', $output, $return_var);
                        
                        if ($return_var === 0) {
                            echo "<p class='success'><i class='fas fa-check'></i> <strong>Git is installed:</strong> " . htmlspecialchars($output[0]) . "</p>";
                            $results['git_installed'] = true;
                        } else {
                            echo "<p class='error'><i class='fas fa-times'></i> <strong>Git is NOT installed</strong></p>";
                            echo "<p>Please install Git from: <a href='https://git-scm.com/download/win' target='_blank'>https://git-scm.com/download/win</a></p>";
                            $results['git_installed'] = false;
                        }
                        
                        if ($results['git_installed']) {
                            // Test 2: Check Git configuration
                            echo "<hr><h5>2. Checking Git Configuration</h5>";
                            
                            $output = [];
                            exec('git config --global user.name 2>&1', $output, $return_var);
                            $user_name = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                            
                            $output = [];
                            exec('git config --global user.email 2>&1', $output, $return_var);
                            $user_email = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                            
                            if ($user_name && $user_email) {
                                echo "<p class='success'><i class='fas fa-check'></i> <strong>Git configured:</strong></p>";
                                echo "<ul>";
                                echo "<li>User Name: <strong>" . htmlspecialchars($user_name) . "</strong></li>";
                                echo "<li>User Email: <strong>" . htmlspecialchars($user_email) . "</strong></li>";
                                echo "</ul>";
                                $results['git_configured'] = true;
                            } else {
                                echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>Git not configured</strong></p>";
                                echo "<p>Run these commands in terminal:</p>";
                                echo "<pre>git config --global user.name \"Your Name\"\ngit config --global user.email \"your.email@example.com\"</pre>";
                                $results['git_configured'] = false;
                            }
                            
                            // Test 3: Check if current directory is a Git repository
                            echo "<hr><h5>3. Checking Git Repository</h5>";
                            chdir($repo_path);
                            
                            if (is_dir($repo_path . '/.git')) {
                                echo "<p class='success'><i class='fas fa-check'></i> <strong>Git repository initialized</strong></p>";
                                
                                // Get branch
                                $output = [];
                                exec('git rev-parse --abbrev-ref HEAD 2>&1', $output, $return_var);
                                $branch = ($return_var === 0 && !empty($output)) ? trim($output[0]) : 'unknown';
                                echo "<p>Current Branch: <strong>" . htmlspecialchars($branch) . "</strong></p>";
                                
                                // Get remote
                                $output = [];
                                exec('git config --get remote.origin.url 2>&1', $output, $return_var);
                                $remote = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                                
                                if ($remote) {
                                    echo "<p class='success'><i class='fas fa-check'></i> <strong>Remote configured:</strong> " . htmlspecialchars($remote) . "</p>";
                                    $results['remote_configured'] = true;
                                } else {
                                    echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>Remote not configured</strong></p>";
                                    echo "<p>Run this command:</p>";
                                    echo "<pre>git remote add origin https://github.com/adiprayitno160-svg/ujian.git</pre>";
                                    $results['remote_configured'] = false;
                                }
                                
                                // Get status
                                $output = [];
                                exec('git status --porcelain 2>&1', $output, $return_var);
                                $has_changes = count($output) > 0;
                                
                                if ($has_changes) {
                                    echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>You have uncommitted changes:</strong></p>";
                                    echo "<pre>" . htmlspecialchars(implode("\n", array_slice($output, 0, 10))) . "</pre>";
                                    if (count($output) > 10) {
                                        echo "<p>... and " . (count($output) - 10) . " more files</p>";
                                    }
                                } else {
                                    echo "<p class='success'><i class='fas fa-check'></i> <strong>Working directory is clean</strong></p>";
                                }
                                
                                $results['repo_initialized'] = true;
                            } else {
                                echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>Git repository not initialized</strong></p>";
                                echo "<p>You can initialize it from admin panel or run:</p>";
                                echo "<pre>cd " . htmlspecialchars($repo_path) . "\ngit init\ngit remote add origin https://github.com/adiprayitno160-svg/ujian.git</pre>";
                                $results['repo_initialized'] = false;
                            }
                            
                            // Test 4: Test GitHub connection
                            echo "<hr><h5>4. Testing GitHub Connection</h5>";
                            if ($results['repo_initialized'] && $results['remote_configured']) {
                                $output = [];
                                exec('git ls-remote --heads origin 2>&1', $output, $return_var);
                                
                                if ($return_var === 0) {
                                    echo "<p class='success'><i class='fas fa-check'></i> <strong>Can connect to GitHub</strong></p>";
                                    echo "<p>Available branches:</p>";
                                    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
                                    $results['github_connected'] = true;
                                } else {
                                    echo "<p class='error'><i class='fas fa-times'></i> <strong>Cannot connect to GitHub</strong></p>";
                                    echo "<p>Error: " . htmlspecialchars(implode("\n", $output)) . "</p>";
                                    echo "<p>Possible reasons:</p>";
                                    echo "<ul>";
                                    echo "<li>Repository doesn't exist or is private</li>";
                                    echo "<li>Network/firewall blocking GitHub</li>";
                                    echo "<li>Authentication required (use Personal Access Token)</li>";
                                    echo "</ul>";
                                    $results['github_connected'] = false;
                                }
                            } else {
                                echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>Cannot test - repository not initialized or remote not configured</strong></p>";
                                $results['github_connected'] = false;
                            }
                        }
                        
                        // Summary
                        echo "<hr><h5>Summary</h5>";
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-bordered'>";
                        echo "<tr><th>Test</th><th>Status</th></tr>";
                        echo "<tr><td>Git Installed</td><td>" . ($results['git_installed'] ?? false ? "<span class='success'><i class='fas fa-check'></i> OK</span>" : "<span class='error'><i class='fas fa-times'></i> FAILED</span>") . "</td></tr>";
                        if ($results['git_installed'] ?? false) {
                            echo "<tr><td>Git Configured</td><td>" . ($results['git_configured'] ?? false ? "<span class='success'><i class='fas fa-check'></i> OK</span>" : "<span class='warning'><i class='fas fa-exclamation-triangle'></i> NOT CONFIGURED</span>") . "</td></tr>";
                            echo "<tr><td>Repository Initialized</td><td>" . ($results['repo_initialized'] ?? false ? "<span class='success'><i class='fas fa-check'></i> OK</span>" : "<span class='warning'><i class='fas fa-exclamation-triangle'></i> NOT INITIALIZED</span>") . "</td></tr>";
                            echo "<tr><td>Remote Configured</td><td>" . ($results['remote_configured'] ?? false ? "<span class='success'><i class='fas fa-check'></i> OK</span>" : "<span class='warning'><i class='fas fa-exclamation-triangle'></i> NOT CONFIGURED</span>") . "</td></tr>";
                            echo "<tr><td>GitHub Connection</td><td>" . ($results['github_connected'] ?? false ? "<span class='success'><i class='fas fa-check'></i> OK</span>" : "<span class='warning'><i class='fas fa-exclamation-triangle'></i> FAILED</span>") . "</td></tr>";
                        }
                        echo "</table>";
                        echo "</div>";
                        
                        // Next steps
                        if ($results['git_installed'] ?? false) {
                            $all_ok = ($results['git_configured'] ?? false) && 
                                     ($results['repo_initialized'] ?? false) && 
                                     ($results['remote_configured'] ?? false);
                            
                            if ($all_ok) {
                                echo "<div class='alert alert-success mt-3'>";
                                echo "<h5><i class='fas fa-check-circle'></i> Ready to Use!</h5>";
                                echo "<p>Git is properly configured. You can now use the GitHub sync features in <a href='admin/about.php'>Admin About page</a>.</p>";
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-warning mt-3'>";
                                echo "<h5><i class='fas fa-exclamation-triangle'></i> Setup Required</h5>";
                                echo "<p>Please complete the setup steps above before using GitHub sync features.</p>";
                                echo "<p><a href='admin/about.php' class='btn btn-primary'>Go to Admin About</a></p>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

