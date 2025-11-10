<?php
/**
 * Git Push Script
 * Commit and push changes to GitHub
 */

$files = [
    'config/config.php',
    'includes/auth.php',
    'admin_guru/login.php',
    'includes/maintenance_mode.php'
];

echo "Adding files to git...\n";
foreach ($files as $file) {
    if (file_exists($file)) {
        $output = [];
        $return = 0;
        exec("git add " . escapeshellarg($file) . " 2>&1", $output, $return);
        echo "Added: $file\n";
        if ($return !== 0) {
            echo "Error: " . implode("\n", $output) . "\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}

echo "\nCommitting changes...\n";
$commit_message = "Fix redirect loop: Update has_operator_access, fix login redirects, change cookie samesite to Lax";
$output = [];
$return = 0;
exec("git commit -m " . escapeshellarg($commit_message) . " 2>&1", $output, $return);
echo implode("\n", $output) . "\n";

if ($return !== 0 && strpos(implode("\n", $output), "nothing to commit") === false) {
    echo "Commit error!\n";
    exit(1);
}

echo "\nPushing to GitHub...\n";
$output = [];
$return = 0;
exec("git push origin main 2>&1", $output, $return);
echo implode("\n", $output) . "\n";

if ($return === 0) {
    echo "\n✓ Successfully pushed to GitHub!\n";
} else {
    echo "\n✗ Push failed!\n";
    exit(1);
}

