<?php
/**
 * Helper Script: Push to GitHub
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Script ini membantu push pertama kali ke GitHub
 * Akses: http://localhost/UJAN/push_to_github.php
 */

require_once __DIR__ . '/config/config.php';

// Only allow admin or localhost
$is_admin = (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN);
$is_localhost = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1');

if (!$is_admin && !$is_localhost) {
    die('Access denied. Please login as admin or access from localhost.');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Push to GitHub - Helper</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .card { margin-bottom: 20px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .step { margin-bottom: 30px; padding: 20px; background: white; border-radius: 8px; border-left: 4px solid #007bff; }
        .step-number { display: inline-block; width: 30px; height: 30px; line-height: 30px; text-align: center; background: #007bff; color: white; border-radius: 50%; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-upload"></i> Push to GitHub - Helper</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $repo_path = __DIR__;
                        chdir($repo_path);
                        
                        // Check if Git is available
                        $output = [];
                        $return_var = 0;
                        exec('git --version 2>&1', $output, $return_var);
                        
                        if ($return_var !== 0) {
                            echo "<div class='alert alert-danger'>";
                            echo "<h5><i class='fas fa-times'></i> Git tidak terinstall</h5>";
                            echo "<p>Silakan install Git terlebih dahulu: <a href='https://git-scm.com/download/win' target='_blank'>Download Git</a></p>";
                            echo "</div>";
                            exit;
                        }
                        
                        // Step 1: Check if repository initialized
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>1</span> Check Repository</h5>";
                        
                        if (!is_dir($repo_path . '/.git')) {
                            echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Repository belum diinisialisasi</p>";
                            echo "<p>Menginisialisasi repository...</p>";
                            
                            exec('git init 2>&1', $output, $return_var);
                            if ($return_var === 0) {
                                echo "<p class='success'><i class='fas fa-check'></i> Repository berhasil diinisialisasi</p>";
                            } else {
                                echo "<p class='error'><i class='fas fa-times'></i> Gagal: " . htmlspecialchars(implode("\n", $output)) . "</p>";
                                echo "</div>";
                                exit;
                            }
                        } else {
                            echo "<p class='success'><i class='fas fa-check'></i> Repository sudah diinisialisasi</p>";
                        }
                        echo "</div>";
                        
                        // Step 2: Check remote
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>2</span> Check Remote</h5>";
                        
                        $output = [];
                        exec('git config --get remote.origin.url 2>&1', $output, $return_var);
                        $remote_url = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                        
                        if (!$remote_url) {
                            echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Remote belum dikonfigurasi</p>";
                            echo "<p>Menambahkan remote...</p>";
                            
                            exec('git remote add origin https://github.com/adiprayitno160-svg/ujian.git 2>&1', $output, $return_var);
                            if ($return_var === 0) {
                                echo "<p class='success'><i class='fas fa-check'></i> Remote berhasil ditambahkan</p>";
                                $remote_url = 'https://github.com/adiprayitno160-svg/ujian.git';
                            } else {
                                // Maybe already exists, try to set URL
                                exec('git remote set-url origin https://github.com/adiprayitno160-svg/ujian.git 2>&1', $output, $return_var);
                                if ($return_var === 0) {
                                    echo "<p class='success'><i class='fas fa-check'></i> Remote URL berhasil diupdate</p>";
                                    $remote_url = 'https://github.com/adiprayitno160-svg/ujian.git';
                                } else {
                                    echo "<p class='error'><i class='fas fa-times'></i> Gagal: " . htmlspecialchars(implode("\n", $output)) . "</p>";
                                }
                            }
                        } else {
                            echo "<p class='success'><i class='fas fa-check'></i> Remote sudah dikonfigurasi: <code>" . htmlspecialchars($remote_url) . "</code></p>";
                        }
                        echo "</div>";
                        
                        // Step 3: Add files
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>3</span> Add Files</h5>";
                        
                        exec('git add . 2>&1', $output, $return_var);
                        if ($return_var === 0) {
                            echo "<p class='success'><i class='fas fa-check'></i> Files berhasil ditambahkan</p>";
                            
                            // Check status
                            exec('git status --short 2>&1', $output, $return_var);
                            if (!empty($output)) {
                                echo "<p>Files yang akan di-commit:</p>";
                                echo "<pre>" . htmlspecialchars(implode("\n", array_slice($output, 0, 20))) . "</pre>";
                                if (count($output) > 20) {
                                    echo "<p>... dan " . (count($output) - 20) . " file lainnya</p>";
                                }
                            } else {
                                echo "<p class='info'><i class='fas fa-info-circle'></i> Tidak ada perubahan untuk di-commit</p>";
                            }
                        } else {
                            echo "<p class='error'><i class='fas fa-times'></i> Gagal: " . htmlspecialchars(implode("\n", $output)) . "</p>";
                        }
                        echo "</div>";
                        
                        // Step 3.5: Check Git Config
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>3.5</span> Check Git Configuration</h5>";
                        
                        $output = [];
                        exec('git config --global user.name 2>&1', $output, $return_var);
                        $user_name = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                        
                        $output = [];
                        exec('git config --global user.email 2>&1', $output, $return_var);
                        $user_email = ($return_var === 0 && !empty($output)) ? trim($output[0]) : null;
                        
                        if (!$user_name || !$user_email) {
                            echo "<p class='error'><i class='fas fa-times'></i> Git belum dikonfigurasi!</p>";
                            echo "<p>Silakan setup Git configuration terlebih dahulu:</p>";
                            echo "<p><a href='setup_git_config.php' class='btn btn-primary'><i class='fas fa-cog'></i> Setup Git Config</a></p>";
                            echo "</div>";
                            echo "<div class='alert alert-danger mt-4'>";
                            echo "<h5><i class='fas fa-exclamation-triangle'></i> Action Required</h5>";
                            echo "<p>Git configuration harus disetup sebelum commit. Klik tombol di atas untuk setup.</p>";
                            echo "</div>";
                            exit;
                        } else {
                            echo "<p class='success'><i class='fas fa-check'></i> Git sudah dikonfigurasi</p>";
                            echo "<ul>";
                            echo "<li>Name: <strong>" . htmlspecialchars($user_name) . "</strong></li>";
                            echo "<li>Email: <strong>" . htmlspecialchars($user_email) . "</strong></li>";
                            echo "</ul>";
                        }
                        echo "</div>";
                        
                        // Step 4: Commit
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>4</span> Commit Changes</h5>";
                        
                        // Check if there are changes to commit
                        exec('git diff --cached --quiet 2>&1', $output, $return_var);
                        $has_changes = $return_var !== 0;
                        
                        if ($has_changes) {
                            // Check if this is first commit (no HEAD exists)
                            exec('git rev-parse --verify HEAD 2>&1', $output, $return_var);
                            $is_first_commit = ($return_var !== 0);
                            
                            if ($is_first_commit) {
                                $commit_message = "Initial commit - Sistem Ujian dan Pekerjaan Rumah (UJAN)";
                            } else {
                                $commit_message = "Update sistem - " . date('Y-m-d H:i:s');
                            }
                            
                            $commit_message_escaped = escapeshellarg($commit_message);
                            exec('git commit -m ' . $commit_message_escaped . ' 2>&1', $output, $return_var);
                            
                            if ($return_var === 0) {
                                echo "<p class='success'><i class='fas fa-check'></i> Commit berhasil</p>";
                                echo "<p>Commit message: <strong>" . htmlspecialchars($commit_message) . "</strong></p>";
                                if (!empty($output)) {
                                    echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
                                }
                            } else {
                                $error_output = implode("\n", $output);
                                // Check if nothing to commit
                                if (strpos($error_output, 'nothing to commit') !== false) {
                                    echo "<p class='info'><i class='fas fa-info-circle'></i> Tidak ada perubahan untuk di-commit</p>";
                                } else {
                                    echo "<p class='error'><i class='fas fa-times'></i> Gagal commit:</p>";
                                    echo "<pre>" . htmlspecialchars($error_output) . "</pre>";
                                    
                                    // Check if it's config error
                                    if (strpos($error_output, 'Author identity unknown') !== false || 
                                        strpos($error_output, 'unable to auto-detect email') !== false) {
                                        echo "<div class='alert alert-warning mt-3'>";
                                        echo "<h6><i class='fas fa-exclamation-triangle'></i> Git Configuration Error</h6>";
                                        echo "<p>Silakan setup Git configuration terlebih dahulu:</p>";
                                        echo "<p><a href='setup_git_config.php' class='btn btn-primary'><i class='fas fa-cog'></i> Setup Git Config</a></p>";
                                        echo "</div>";
                                    }
                                }
                            }
                        } else {
                            echo "<p class='info'><i class='fas fa-info-circle'></i> Tidak ada perubahan untuk di-commit</p>";
                        }
                        echo "</div>";
                        
                        // Step 5: Push
                        echo "<div class='step'>";
                        echo "<h5><span class='step-number'>5</span> Push to GitHub</h5>";
                        
                        // Get current branch
                        exec('git rev-parse --abbrev-ref HEAD 2>&1', $output, $return_var);
                        $current_branch = ($return_var === 0 && !empty($output)) ? trim($output[0]) : 'main';
                        
                        echo "<p>Current branch: <strong>" . htmlspecialchars($current_branch) . "</strong></p>";
                        echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> <strong>Penting:</strong> Untuk push, Anda perlu autentikasi GitHub.</p>";
                        echo "<p>Gunakan salah satu metode berikut:</p>";
                        echo "<ul>";
                        echo "<li><strong>Personal Access Token:</strong> Username = <code>adiprayitno160-svg</code>, Password = <code>your_token</code></li>";
                        echo "<li><strong>SSH Key:</strong> Setup SSH key di GitHub</li>";
                        echo "</ul>";
                        
                        echo "<div class='alert alert-info'>";
                        echo "<h6><i class='fas fa-info-circle'></i> Cara Push:</h6>";
                        echo "<p><strong>Option 1: Via Web Interface</strong></p>";
                        echo "<ol>";
                        echo "<li>Buka <a href='admin/about.php'>Admin About page</a></li>";
                        echo "<li>Masukkan commit message</li>";
                        echo "<li>Klik 'Push ke GitHub'</li>";
                        echo "<li>Masukkan GitHub credentials saat diminta</li>";
                        echo "</ol>";
                        
                        echo "<p><strong>Option 2: Via Command Line</strong></p>";
                        echo "<pre>cd " . htmlspecialchars($repo_path) . "\ngit push -u origin " . htmlspecialchars($current_branch) . "</pre>";
                        echo "<p>Saat diminta, masukkan:</p>";
                        echo "<ul>";
                        echo "<li>Username: <code>adiprayitno160-svg</code></li>";
                        echo "<li>Password: <code>your_personal_access_token</code></li>";
                        echo "</ul>";
                        echo "</div>";
                        
                        // Try to check if we can push (will fail if not authenticated, but that's OK)
                        echo "<p><strong>Status:</strong></p>";
                        exec('git ls-remote origin 2>&1', $output, $return_var);
                        if ($return_var === 0) {
                            echo "<p class='success'><i class='fas fa-check'></i> Dapat terhubung ke GitHub</p>";
                            echo "<p>Anda siap untuk push! Gunakan salah satu metode di atas.</p>";
                        } else {
                            echo "<p class='warning'><i class='fas fa-exclamation-triangle'></i> Perlu autentikasi untuk push</p>";
                            echo "<p>Setup Personal Access Token atau SSH key terlebih dahulu.</p>";
                        }
                        echo "</div>";
                        
                        // Summary
                        $all_ready = ($user_name && $user_email);
                        
                        if ($all_ready) {
                            echo "<div class='alert alert-success mt-4'>";
                            echo "<h5><i class='fas fa-check-circle'></i> Setup Selesai!</h5>";
                            echo "<p>Repository sudah siap untuk push ke GitHub.</p>";
                            echo "<p>";
                            echo "<a href='admin/about.php' class='btn btn-primary'><i class='fas fa-arrow-left'></i> Kembali ke Admin About</a> ";
                            echo "<a href='admin/about.php' class='btn btn-success'><i class='fas fa-upload'></i> Push via Admin About</a>";
                            echo "</p>";
                            echo "</div>";
                        } else {
                            echo "<div class='alert alert-warning mt-4'>";
                            echo "<h5><i class='fas fa-exclamation-triangle'></i> Perlu Setup Git Config</h5>";
                            echo "<p>Silakan setup Git configuration terlebih dahulu sebelum commit.</p>";
                            echo "<p><a href='setup_git_config.php' class='btn btn-primary'><i class='fas fa-cog'></i> Setup Git Config</a></p>";
                            echo "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

