<?php
/**
 * Create Release Script
 * Script untuk membuat release baru dan upload ke GitHub
 * 
 * Usage: php scripts/create_release.php [version] [release_name] [release_body]
 * Example: php scripts/create_release.php 1.0.15 "Release v1.0.15" "Fixed soal creation issue"
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/version_check.php';

// Get version from command line or use current version + 0.0.1
$version = $argv[1] ?? '1.0.15';
$release_name = $argv[2] ?? "Release v{$version}";
$release_body = $argv[3] ?? "Release v{$version}\n\nPerbaikan bug dan peningkatan fitur.";

// Validate version format
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    die("Error: Format versi tidak valid. Gunakan format X.Y.Z (contoh: 1.0.15)\n");
}

echo "Creating release v{$version}...\n";
echo "Release name: {$release_name}\n";
echo "Release body: {$release_body}\n\n";

// Check if we're in a git repository
$repo_path = __DIR__ . '/..';
if (!is_dir($repo_path . '/.git')) {
    die("Error: Bukan Git repository. Silakan jalankan dari direktori repository.\n");
}

// Get GitHub token
$github_token = getenv('GITHUB_TOKEN');
if (empty($github_token)) {
    $token_file = __DIR__ . '/../config/github_token.php';
    if (file_exists($token_file)) {
        $token_config = @include $token_file;
        if (isset($token_config['token'])) {
            $github_token = $token_config['token'];
        }
    }
}

if (empty($github_token)) {
    die("Error: GitHub token tidak ditemukan.\n" .
        "Silakan set environment variable GITHUB_TOKEN atau buat file config/github_token.php\n" .
        "Format config/github_token.php:\n" .
        "<?php\n" .
        "return ['token' => 'your_github_token_here'];\n");
}

// Create release via GitHub API
echo "Creating GitHub release...\n";
$result = create_github_release($version, $release_name, $release_body, false, false, $github_token);

if ($result['success']) {
    echo "✓ Release berhasil dibuat!\n";
    echo "  Tag: {$result['tag_name']}\n";
    echo "  Name: {$result['release_name']}\n";
    if (isset($result['release_url'])) {
        echo "  URL: {$result['release_url']}\n";
    }
    
    // Now we need to commit, tag, and push
    echo "\nCommitting changes and creating tag...\n";
    
    $old_dir = getcwd();
    chdir($repo_path);
    
    // Add all changes
    exec('git add .', $output, $return_var);
    if ($return_var !== 0) {
        echo "Warning: Failed to add files to git\n";
    }
    
    // Commit changes
    $commit_message = "Release v{$version}: {$release_name}";
    exec('git commit -m ' . escapeshellarg($commit_message), $output, $return_var);
    if ($return_var !== 0) {
        echo "Warning: Failed to commit changes (might be no changes to commit)\n";
        // Check if there are changes
        exec('git status --porcelain', $status_output, $status_return);
        if ($status_return === 0 && empty($status_output)) {
            echo "No changes to commit.\n";
        }
    } else {
        echo "✓ Changes committed\n";
    }
    
    // Create tag
    $tag_name = 'v' . $version;
    exec('git tag -a ' . escapeshellarg($tag_name) . ' -m ' . escapeshellarg($release_name), $output, $return_var);
    if ($return_var !== 0) {
        echo "Warning: Failed to create tag (might already exist)\n";
    } else {
        echo "✓ Tag created: {$tag_name}\n";
    }
    
    // Push to GitHub
    echo "\nPushing to GitHub...\n";
    exec('git push origin main', $output, $return_var);
    if ($return_var !== 0) {
        echo "Error: Failed to push to GitHub\n";
        echo "Output: " . implode("\n", $output) . "\n";
    } else {
        echo "✓ Pushed to GitHub\n";
    }
    
    // Push tags
    exec('git push origin ' . escapeshellarg($tag_name), $output, $return_var);
    if ($return_var !== 0) {
        echo "Warning: Failed to push tag to GitHub\n";
        echo "Output: " . implode("\n", $output) . "\n";
    } else {
        echo "✓ Tag pushed to GitHub\n";
    }
    
    chdir($old_dir);
    
    echo "\n✓ Release v{$version} berhasil dibuat dan di-upload ke GitHub!\n";
    if (isset($result['release_url'])) {
        echo "  Release URL: {$result['release_url']}\n";
    }
} else {
    echo "Error: Gagal membuat release\n";
    echo "  Error: {$result['error']}\n";
    if (isset($result['suggestion'])) {
        echo "  Suggestion: {$result['suggestion']}\n";
    }
    exit(1);
}

