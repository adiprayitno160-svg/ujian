<?php
/**
 * Auto Archive Ujian
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Auto-archive ujian yang sudah completed lebih dari X hari
 */

/**
 * Auto archive ujian yang sudah completed
 * @param int $days_after_completion Days after completion to archive (default: 30)
 * @return int Number of ujian archived
 */
function auto_archive_ujian($days_after_completion = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE ujian 
                              SET archived_at = NOW() 
                              WHERE status = 'completed' 
                              AND archived_at IS NULL 
                              AND DATE_ADD(updated_at, INTERVAL ? DAY) < NOW()");
        $stmt->execute([$days_after_completion]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Auto archive ujian error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Run auto archive (call this from cron job or scheduled task)
 */
function run_auto_archive() {
    // Archive ujian completed more than 30 days ago
    $archived_count = auto_archive_ujian(30);
    
    if ($archived_count > 0) {
        error_log("Auto archive: {$archived_count} ujian di-archive");
    }
    
    return $archived_count;
}

// If called directly from CLI (for cron job)
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    
    echo "Running auto archive...\n";
    $count = run_auto_archive();
    echo "Archived {$count} ujian\n";
}

