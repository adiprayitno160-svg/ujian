<?php
/**
 * Includes Folder - Access Denied
 * Folder ini tidak boleh diakses langsung via URL
 */

http_response_code(403);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }
        .error {
            background: white;
            padding: 30px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            color: #dc3545;
            font-size: 72px;
            margin: 0;
        }
        h2 {
            color: #333;
            margin-top: 20px;
        }
        p {
            color: #666;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="error">
        <h1>403</h1>
        <h2>Forbidden</h2>
        <p>Akses ke folder ini tidak diizinkan.</p>
    </div>
</body>
</html>









