<?php
/**
 * Remove Anti Contek Feature
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Script untuk menghapus fitur Anti Contek dan is_fraud dari sistem
 * 
 * PERINGATAN: Script ini akan menghapus kolom-kolom terkait dari database
 * Pastikan untuk backup database terlebih dahulu!
 * 
 * Akses: http://localhost/UJAN/remove_anti_contek.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

$success_messages = [];
$error_messages = [];
$warnings = [];

// Columns to remove from nilai table
$nilai_columns_to_remove = [
    'is_fraud',
    'requires_relogin',
    'fraud_reason',
    'fraud_detected_at',
    'warning_count',
    'is_suspicious'
];

// Columns to remove from ujian table
$ujian_columns_to_remove = [
    'anti_contek_enabled'
];

// Tables to drop (optional - uncomment if you want to drop these tables)
$tables_to_drop = [
    // 'anti_contek_settings',
    // 'anti_contek_logs'
];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_remove'])) {
        $pdo->beginTransaction();
        
        // Remove columns from nilai table
        foreach ($nilai_columns_to_remove as $column) {
            try {
                // Check if column exists
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'nilai' 
                    AND COLUMN_NAME = ?
                ");
                $stmt->execute([DB_NAME, $column]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $pdo->exec("ALTER TABLE nilai DROP COLUMN `{$column}`");
                    $success_messages[] = "Kolom '{$column}' berhasil dihapus dari tabel 'nilai'";
                } else {
                    $warnings[] = "Kolom '{$column}' tidak ditemukan di tabel 'nilai' (mungkin sudah dihapus)";
                }
            } catch (PDOException $e) {
                $error_messages[] = "Error menghapus kolom '{$column}' dari tabel 'nilai': " . $e->getMessage();
            }
        }
        
        // Remove columns from ujian table
        foreach ($ujian_columns_to_remove as $column) {
            try {
                // Check if column exists
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = 'ujian' 
                    AND COLUMN_NAME = ?
                ");
                $stmt->execute([DB_NAME, $column]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $pdo->exec("ALTER TABLE ujian DROP COLUMN `{$column}`");
                    $success_messages[] = "Kolom '{$column}' berhasil dihapus dari tabel 'ujian'";
                } else {
                    $warnings[] = "Kolom '{$column}' tidak ditemukan di tabel 'ujian' (mungkin sudah dihapus)";
                }
            } catch (PDOException $e) {
                $error_messages[] = "Error menghapus kolom '{$column}' dari tabel 'ujian': " . $e->getMessage();
            }
        }
        
        // Drop tables (optional)
        foreach ($tables_to_drop as $table) {
            try {
                // Check if table exists
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ?
                ");
                $stmt->execute([DB_NAME, $table]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
                    $success_messages[] = "Tabel '{$table}' berhasil dihapus";
                } else {
                    $warnings[] = "Tabel '{$table}' tidak ditemukan (mungkin sudah dihapus)";
                }
            } catch (PDOException $e) {
                $error_messages[] = "Error menghapus tabel '{$table}': " . $e->getMessage();
            }
        }
        
        if (empty($error_messages)) {
            $pdo->commit();
        } else {
            $pdo->rollBack();
        }
    }
    
    // Check current status
    $nilai_columns_exist = [];
    $ujian_columns_exist = [];
    
    foreach ($nilai_columns_to_remove as $column) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'nilai' 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([DB_NAME, $column]);
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            $nilai_columns_exist[] = $column;
        }
    }
    
    foreach ($ujian_columns_to_remove as $column) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = 'ujian' 
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([DB_NAME, $column]);
        $result = $stmt->fetch();
        if ($result['count'] > 0) {
            $ujian_columns_exist[] = $column;
        }
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error_messages[] = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Fitur Anti Contek</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .card {
            max-width: 1000px;
            margin: 0 auto;
        }
        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header bg-danger text-white">
                <h4 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Hapus Fitur Anti Contek</h4>
            </div>
            <div class="card-body">
                <div class="warning-box">
                    <h5><i class="fas fa-exclamation-triangle text-warning"></i> PERINGATAN!</h5>
                    <p class="mb-0">
                        Script ini akan <strong>menghapus kolom-kolom terkait Anti Contek</strong> dari database:
                    </p>
                    <ul class="mt-2">
                        <li><strong>Tabel 'nilai':</strong> is_fraud, requires_relogin, fraud_reason, fraud_detected_at, warning_count, is_suspicious</li>
                        <li><strong>Tabel 'ujian':</strong> anti_contek_enabled</li>
                    </ul>
                    <p class="text-danger mt-2 mb-0">
                        <strong>PENTING:</strong> Pastikan untuk backup database terlebih dahulu sebelum menjalankan script ini!
                    </p>
                </div>
                
                <?php if (!empty($success_messages)): ?>
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle"></i> Berhasil:</h5>
                        <ul class="mb-0">
                            <?php foreach ($success_messages as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_messages)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-circle"></i> Error:</h5>
                        <ul class="mb-0">
                            <?php foreach ($error_messages as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($warnings)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-info-circle"></i> Peringatan:</h5>
                        <ul class="mb-0">
                            <?php foreach ($warnings as $msg): ?>
                                <li><?php echo htmlspecialchars($msg); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h5>Status Kolom di Database</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Tabel 'nilai':</h6>
                            <?php if (!empty($nilai_columns_exist)): ?>
                                <ul class="list-group">
                                    <?php foreach ($nilai_columns_exist as $col): ?>
                                        <li class="list-group-item list-group-item-danger">
                                            <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($col); ?> (masih ada)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Semua kolom Anti Contek sudah dihapus dari tabel 'nilai'
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Tabel 'ujian':</h6>
                            <?php if (!empty($ujian_columns_exist)): ?>
                                <ul class="list-group">
                                    <?php foreach ($ujian_columns_exist as $col): ?>
                                        <li class="list-group-item list-group-item-danger">
                                            <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars($col); ?> (masih ada)
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle"></i> Semua kolom Anti Contek sudah dihapus dari tabel 'ujian'
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($nilai_columns_exist) || !empty($ujian_columns_exist)): ?>
                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus semua kolom Anti Contek dari database?\\n\\nPastikan sudah backup database terlebih dahulu!');">
                        <input type="hidden" name="confirm_remove" value="1">
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-trash"></i> Hapus Kolom Anti Contek dari Database
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Semua kolom Anti Contek sudah dihapus!</strong>
                        <br>Langkah selanjutnya: Hapus atau nonaktifkan fungsi-fungsi terkait di kode.
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Kembali ke Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

