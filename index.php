<?php
/**
 * Main Index File
 * Redirects to login page
 */

// Try to load config and redirect
try {
    // Check if config file exists
    if (!file_exists(__DIR__ . '/config/config.php')) {
        throw new Exception('Configuration file not found. Please check your installation.');
    }
    
    // Load config to get base URL functions
    require_once __DIR__ . '/config/config.php';
    
    // Always redirect to login for root URL
    // This is the default landing page for the application
    if (function_exists('redirect')) {
        redirect('login');
    } else {
        // Fallback if redirect function doesn't exist
        header('Location: siswa/login.php');
    }
    exit;
} catch (Exception $e) {
    // If there's an error, show a helpful error page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Configuration Error - UJAN</title>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
                padding: 20px;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 600px;
                width: 100%;
            }
            h1 { color: #dc3545; margin-top: 0; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0; }
            ul { margin: 10px 0; padding-left: 20px; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Configuration Error</h1>
            <div class="error">
                <strong>Error:</strong> <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <div class="info">
                <h3>Possible Solutions:</h3>
                <ul>
                    <li>Check if <code>config/config.php</code> exists</li>
                    <li>Check file permissions (should be readable)</li>
                    <li>Check if database configuration is correct</li>
                    <li>Try accessing: <a href="test.php">test.php</a> for diagnostics</li>
                </ul>
            </div>
            <p>
                <a href="siswa/login.php">Try Direct Login Page</a> |
                <a href="test.php">Run Diagnostics</a>
            </p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>