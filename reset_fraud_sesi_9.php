<?php
/**
 * Quick Reset Fraud for Sesi ID 9
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * 
 * Akses langsung: http://localhost/UJAN/reset_fraud_sesi_9.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

$sesi_id = 9;
$success = false;
$error = '';
$affected_rows = 0;
$records_before = [];
$records_after = [];

try {
    // Get records before reset
    $stmt = $pdo->prepare("SELECT n.id_siswa, n.id_ujian, n.is_fraud, n.requires_relogin, n.fraud_reason, 
                          u.nama, u.username as nis, uj.judul as judul_ujian, uj.anti_contek_enabled
                          FROM nilai n 
                          INNER JOIN users u ON n.id_siswa = u.id 
                          INNER JOIN ujian uj ON n.id_ujian = uj.id
                          WHERE n.id_sesi = ? AND (n.is_fraud = 1 OR n.requires_relogin = 1)");
    $stmt->execute([$sesi_id]);
    $records_before = $stmt->fetchAll();
    
    // Check if sesi exists
    $stmt = $pdo->prepare("SELECT id, id_ujian, nama_sesi FROM sesi_ujian WHERE id = ?");
    $stmt->execute([$sesi_id]);
    $sesi = $stmt->fetch();
    
    if (!$sesi) {
        $error = "Sesi ID {$sesi_id} tidak ditemukan";
    } else {
        // Reset fraud flags
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE nilai 
                                  SET requires_relogin = 0,
                                      is_fraud = 0,
                                      fraud_reason = NULL,
                                      fraud_detected_at = NULL,
                                      warning_count = 0,
                                      is_suspicious = 0
                                  WHERE id_sesi = ?");
            $stmt->execute([$sesi_id]);
            
            $affected_rows = $stmt->rowCount();
            $pdo->commit();
            $success = true;
            
            // Get records after reset
            $stmt = $pdo->prepare("SELECT n.id_siswa, n.id_ujian, n.is_fraud, n.requires_relogin, n.fraud_reason, 
                                  u.nama, u.username as nis
                                  FROM nilai n 
                                  INNER JOIN users u ON n.id_siswa = u.id 
                                  WHERE n.id_sesi = ? AND (n.is_fraud = 1 OR n.requires_relogin = 1)");
            $stmt->execute([$sesi_id]);
            $records_after = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Fraud Flags - Sesi ID <?php echo $sesi_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f5f5f5;
            padding: 20px;
        }
        .card {
            max-width: 900px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="fas fa-unlock"></i> Reset Fraud Flags - Sesi ID <?php echo $sesi_id; ?></h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Berhasil!</strong> <?php echo $affected_rows; ?> fraud flag(s) telah direset untuk sesi ID <?php echo $sesi_id; ?>.
                    </div>
                <?php endif; ?>
                
                <?php if ($sesi): ?>
                    <div class="mb-3">
                        <h5>Informasi Sesi</h5>
                        <ul>
                            <li><strong>Sesi ID:</strong> <?php echo $sesi['id']; ?></li>
                            <li><strong>Nama Sesi:</strong> <?php echo htmlspecialchars($sesi['nama_sesi'] ?? '-'); ?></li>
                            <li><strong>Ujian ID:</strong> <?php echo $sesi['id_ujian']; ?></li>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($records_before)): ?>
                    <div class="mb-3">
                        <h5>Records dengan Fraud Flags (Sebelum Reset)</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>NIS</th>
                                        <th>Nama</th>
                                        <th>Ujian</th>
                                        <th>Anti Contek</th>
                                        <th>is_fraud</th>
                                        <th>requires_relogin</th>
                                        <th>Alasan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records_before as $record): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['nis']); ?></td>
                                        <td><?php echo htmlspecialchars($record['nama']); ?></td>
                                        <td><?php echo htmlspecialchars($record['judul_ujian'] ?? '-'); ?></td>
                                        <td>
                                            <?php if ($record['anti_contek_enabled'] ?? 0): ?>
                                                <span class="badge bg-danger">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $record['is_fraud'] ? '<span class="badge bg-danger">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>'; ?></td>
                                        <td><?php echo $record['requires_relogin'] ? '<span class="badge bg-warning">Ya</span>' : '<span class="badge bg-secondary">Tidak</span>'; ?></td>
                                        <td><?php echo htmlspecialchars($record['fraud_reason'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!$success): ?>
                        <form method="POST" onsubmit="return confirm('Yakin ingin mereset semua fraud flags untuk sesi ID <?php echo $sesi_id; ?>?');">
                            <input type="hidden" name="confirm_reset" value="1">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="fas fa-unlock"></i> Reset Fraud Flags
                            </button>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Tidak ada fraud flags untuk sesi ID <?php echo $sesi_id; ?>.
                    </div>
                <?php endif; ?>
                
                <?php if ($success && !empty($records_after)): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Peringatan:</strong> Masih ada <?php echo count($records_after); ?> record(s) dengan fraud flags setelah reset. 
                        Mungkin ada masalah dengan query atau data.
                    </div>
                <?php elseif ($success && empty($records_after)): ?>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-check-circle"></i> 
                        Semua fraud flags telah berhasil direset. Siswa dapat login kembali.
                    </div>
                <?php endif; ?>
                
                <div class="mt-4">
                    <a href="siswa/login.php?sesi_id=<?php echo $sesi_id; ?>" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Kembali ke Login
                    </a>
                    <a href="operator/reset_fraud_lock.php" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> Halaman Reset Operator
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


