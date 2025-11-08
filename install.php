<?php
/**
 * Database Installation Script
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Script ini akan mengimpor database.sql secara otomatis
 * HAPUS file ini setelah instalasi selesai untuk keamanan!
 */

// Load database config
require_once __DIR__ . '/config/database.php';

// Security: Hanya izinkan akses jika file .installed tidak ada
$installed_file = __DIR__ . '/.installed';
$is_installed = file_exists($installed_file);

// Jika sudah terinstall dan tidak ada parameter force, tampilkan pesan
if ($is_installed && !isset($_GET['force'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Sudah Terinstall</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <h1>Database Sudah Terinstall</h1>
        <div class="success">Database sudah berhasil diimport sebelumnya.</div>
        <div class="warning">
            <strong>Peringatan:</strong> Jika Anda ingin mengimport ulang, hapus file <code>.installed</code> terlebih dahulu, 
            atau tambahkan parameter <code>?force=1</code> di URL (ini akan menghapus semua data yang ada!).
        </div>
        <p><a href="index.php" class="btn">Kembali ke Aplikasi</a></p>
    </body>
    </html>
    ');
}

// Jika force dan konfirmasi, lanjutkan
if (isset($_GET['force']) && !isset($_POST['confirm_force'])) {
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Konfirmasi Re-import Database</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            .warning { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .btn { display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; }
            form { margin-top: 20px; }
        </style>
    </head>
    <body>
        <h1>Peringatan!</h1>
        <div class="warning">
            <strong>PERINGATAN:</strong> Mengimport ulang database akan menghapus semua data yang ada!
            Pastikan Anda sudah melakukan backup sebelum melanjutkan.
        </div>
        <form method="POST">
            <input type="hidden" name="confirm_force" value="1">
            <button type="submit" class="btn">Ya, Saya Yakin - Import Ulang</button>
            <a href="install.php" style="margin-left: 10px;">Batal</a>
        </form>
    </body>
    </html>
    ');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Install Database - UJAN</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .info {
            background: #e7f3ff;
            color: #004085;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #007bff;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .progress {
            background: #e9ecef;
            border-radius: 10px;
            padding: 3px;
            margin: 20px 0;
        }
        .progress-bar {
            background: #007bff;
            height: 20px;
            border-radius: 10px;
            width: 0%;
            transition: width 0.3s;
            text-align: center;
            color: white;
            line-height: 20px;
            font-size: 12px;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📦 Install Database - UJAN</h1>
        
        <?php
        $sql_file = __DIR__ . '/database.sql';
        $errors = [];
        $success = false;
        
        // Check if SQL file exists
        if (!file_exists($sql_file)) {
            echo '<div class="error"><strong>Error:</strong> File <code>database.sql</code> tidak ditemukan!</div>';
            exit;
        }
        
        // Check database connection (without database first)
        try {
            $dsn_no_db = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo_no_db = new PDO($dsn_no_db, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            
            echo '<div class="success">✓ Koneksi ke MySQL berhasil</div>';
        } catch (PDOException $e) {
            echo '<div class="error"><strong>Error Koneksi:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '<div class="info">';
            echo '<strong>Periksa konfigurasi di <code>config/database.php</code>:</strong><ul>';
            echo '<li>DB_HOST: ' . DB_HOST . '</li>';
            echo '<li>DB_USER: ' . DB_USER . '</li>';
            echo '<li>DB_PASS: ' . (DB_PASS ? '***' : '(kosong)') . '</li>';
            echo '</ul></div>';
            exit;
        }
        
        // Process import if form submitted or auto-start
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auto'])) {
            echo '<div class="info">Memulai proses import database...</div>';
            
            try {
                // Read SQL file
                $sql_content = file_get_contents($sql_file);
                
                if (empty($sql_content)) {
                    throw new Exception("File database.sql kosong atau tidak dapat dibaca");
                }
                
                // Remove BOM if present
                $sql_content = preg_replace('/^\xEF\xBB\xBF/', '', $sql_content);
                
                // Split SQL into individual statements
                // Remove comments and empty lines
                $sql_content = preg_replace('/--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                // Split by semicolon, but preserve semicolons inside quotes
                $statements = [];
                $current = '';
                $in_string = false;
                $string_char = '';
                
                for ($i = 0; $i < strlen($sql_content); $i++) {
                    $char = $sql_content[$i];
                    $next_char = isset($sql_content[$i + 1]) ? $sql_content[$i + 1] : '';
                    
                    if (!$in_string && ($char === '"' || $char === "'" || $char === '`')) {
                        $in_string = true;
                        $string_char = $char;
                    } elseif ($in_string && $char === $string_char && $sql_content[$i - 1] !== '\\') {
                        $in_string = false;
                    }
                    
                    $current .= $char;
                    
                    if (!$in_string && $char === ';') {
                        $stmt = trim($current);
                        if (!empty($stmt) && strlen($stmt) > 5) {
                            $statements[] = $stmt;
                        }
                        $current = '';
                    }
                }
                
                // Add remaining statement if any
                if (!empty(trim($current))) {
                    $statements[] = trim($current);
                }
                
                $total_statements = count($statements);
                $executed = 0;
                $failed = 0;
                
                echo '<div class="progress"><div class="progress-bar" id="progress">0%</div></div>';
                echo '<div id="status">Memproses...</div>';
                
                // Execute each statement
                foreach ($statements as $index => $statement) {
                    $statement = trim($statement);
                    if (empty($statement) || strlen($statement) < 5) {
                        continue;
                    }
                    
                    try {
                        // Use database connection without database name for CREATE DATABASE
                        if (stripos($statement, 'CREATE DATABASE') !== false || 
                            stripos($statement, 'USE ') !== false) {
                            $pdo_no_db->exec($statement);
                        } else {
                            // For other statements, use connection with database
                            if (!isset($pdo)) {
                                try {
                                    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                                    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                    ]);
                                } catch (PDOException $e) {
                                    // Database might not exist yet, create it first
                                    if (stripos($e->getMessage(), 'Unknown database') !== false) {
                                        $pdo_no_db->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                        $pdo_no_db->exec("USE " . DB_NAME);
                                        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                                        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                        ]);
                                    } else {
                                        throw $e;
                                    }
                                }
                            }
                            $pdo->exec($statement);
                        }
                        
                        $executed++;
                        $progress = round(($executed / $total_statements) * 100);
                        
                        // Update progress (for real-time display, but this is server-side)
                        if ($index % 10 == 0 || $index == $total_statements - 1) {
                            echo '<script>document.getElementById("progress").style.width="' . $progress . '%"; document.getElementById("progress").textContent="' . $progress . '%";</script>';
                            flush();
                            ob_flush();
                        }
                        
                    } catch (PDOException $e) {
                        $failed++;
                        $error_msg = $e->getMessage();
                        // Skip some common non-critical errors
                        if (stripos($error_msg, 'already exists') === false && 
                            stripos($error_msg, 'Duplicate') === false) {
                            $errors[] = "Statement " . ($index + 1) . ": " . substr($statement, 0, 50) . "... - " . $error_msg;
                        }
                    }
                }
                
                // Create .installed file
                file_put_contents($installed_file, date('Y-m-d H:i:s'));
                
                echo '<script>document.getElementById("progress").style.width="100%"; document.getElementById("progress").textContent="100%";</script>';
                
                if ($failed == 0 || $executed > 0) {
                    echo '<div class="success">';
                    echo '<strong>✓ Import Database Berhasil!</strong><br>';
                    echo "Total statement: $total_statements<br>";
                    echo "Berhasil: $executed<br>";
                    if ($failed > 0) {
                        echo "Gagal (non-critical): $failed<br>";
                    }
                    echo '</div>';
                    
                    echo '<div class="info">';
                    echo '<strong>Informasi Login Default:</strong><ul>';
                    echo '<li>Username: <code>admin</code></li>';
                    echo '<li>Password: <code>admin123</code></li>';
                    echo '</ul>';
                    echo '<strong>PENTING:</strong> Ganti password default setelah login pertama kali!';
                    echo '</div>';
                    
                    echo '<div class="warning">';
                    echo '<strong>⚠️ Keamanan:</strong> Hapus file <code>install.php</code> setelah instalasi selesai!';
                    echo '</div>';
                    
                    $success = true;
                } else {
                    throw new Exception("Tidak ada statement yang berhasil dieksekusi");
                }
                
            } catch (Exception $e) {
                echo '<div class="error"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            // Show errors if any
            if (!empty($errors)) {
                echo '<div class="warning">';
                echo '<strong>Peringatan:</strong> Beberapa error non-critical terjadi:<ul>';
                foreach (array_slice($errors, 0, 10) as $error) {
                    echo '<li>' . htmlspecialchars($error) . '</li>';
                }
                if (count($errors) > 10) {
                    echo '<li>... dan ' . (count($errors) - 10) . ' error lainnya</li>';
                }
                echo '</ul></div>';
            }
        } else {
            // Show installation info
            echo '<div class="info">';
            echo '<strong>Informasi Instalasi:</strong><ul>';
            echo '<li>Database: <code>' . DB_NAME . '</code></li>';
            echo '<li>Host: <code>' . DB_HOST . '</code></li>';
            echo '<li>User: <code>' . DB_USER . '</code></li>';
            echo '<li>File SQL: <code>database.sql</code></li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="warning">';
            echo '<strong>Perhatian:</strong> Pastikan MySQL/XAMPP sudah berjalan sebelum melanjutkan.';
            echo '</div>';
            
            echo '<form method="POST">';
            echo '<button type="submit" class="btn">🚀 Mulai Import Database</button>';
            echo '</form>';
        }
        
        if ($success) {
            echo '<p><a href="index.php" class="btn">Lanjut ke Aplikasi</a></p>';
        }
        ?>
    </div>
</body>
</html>

