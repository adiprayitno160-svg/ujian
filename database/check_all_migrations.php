<?php
/**
 * Check All SQL Migrations Status
 * Script untuk mengecek apakah semua file SQL migration sudah diimport ke database
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

echo "========================================\n";
echo "  CHECK ALL SQL MIGRATIONS STATUS\n";
echo "========================================\n\n";

$migrations = [
    'database.sql' => [
        'type' => 'full_database',
        'tables' => ['users', 'kelas', 'mapel', 'ujian', 'soal', 'nilai', 'pr', 'pr_submission']
    ],
    'database/migration_assessment_system.sql' => [
        'type' => 'migration',
        'tables' => ['bank_soal', 'jadwal_assessment', 'berita_acara', 'absensi_ujian', 'sumatip_kelas_target', 'sumatip_log'],
        'columns' => [
            ['table' => 'users', 'column' => 'can_create_assessment_soal'],
            ['table' => 'soal', 'column' => 'media_type']
        ]
    ],
    'database/migration_verifikasi_dokumen.sql' => [
        'type' => 'migration',
        'tables' => ['verifikasi_settings', 'siswa_dokumen_verifikasi', 'verifikasi_data_siswa', 'verifikasi_data_history', 'notifikasi_verifikasi']
    ],
    'database/migration_guru_mapel_kelas.sql' => [
        'type' => 'migration',
        'tables' => ['guru_mapel_kelas']
    ],
    'migration_version_system.sql' => [
        'type' => 'migration',
        'tables' => ['system_version', 'system_changelog']
    ],
    'migration_add_keterangan.sql' => [
        'type' => 'migration',
        'columns' => [
            ['table' => 'migrasi_history', 'column' => 'keterangan']
        ]
    ],
    'database/migration_add_submission_text_fields.sql' => [
        'type' => 'migration',
        'columns' => [
            ['table' => 'pr_submission', 'column' => 'jawaban_text'],
            ['table' => 'pr_submission', 'column' => 'tipe_submission'],
            ['table' => 'tugas_submission', 'column' => 'jawaban_text'],
            ['table' => 'tugas_submission', 'column' => 'tipe_submission']
        ]
    ],
    'database/migration_add_soal_media_type.sql' => [
        'type' => 'migration',
        'columns' => [
            ['table' => 'soal', 'column' => 'media_type']
        ]
    ]
];

$all_migrated = true;
$migrated_files = [];
$not_migrated_files = [];

foreach ($migrations as $file => $config) {
    echo "Checking: $file\n";
    echo str_repeat('-', 50) . "\n";
    
    $file_exists = file_exists(__DIR__ . '/../' . $file);
    if (!$file_exists) {
        echo "⚠️  File tidak ditemukan: $file\n\n";
        continue;
    }
    
    $is_migrated = true;
    $issues = [];
    
    // Check tables
    if (isset($config['tables']) && is_array($config['tables'])) {
        foreach ($config['tables'] as $table) {
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ?
                ");
                $stmt->execute([DB_NAME, $table]);
                $result = $stmt->fetch();
                
                if ($result['count'] == 0) {
                    $is_migrated = false;
                    $issues[] = "Table '$table' TIDAK ADA";
                } else {
                    echo "  ✓ Table '$table' ADA\n";
                }
            } catch (PDOException $e) {
                $is_migrated = false;
                $issues[] = "Error checking table '$table': " . $e->getMessage();
            }
        }
    }
    
    // Check columns
    if (isset($config['columns']) && is_array($config['columns'])) {
        foreach ($config['columns'] as $col_check) {
            $table = $col_check['table'];
            $column = $col_check['column'];
            
            try {
                // First check if table exists
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ?
                ");
                $stmt->execute([DB_NAME, $table]);
                $table_result = $stmt->fetch();
                
                if ($table_result['count'] == 0) {
                    $is_migrated = false;
                    $issues[] = "Table '$table' TIDAK ADA (required for column '$column')";
                    continue;
                }
                
                // Check column
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND TABLE_NAME = ? 
                    AND COLUMN_NAME = ?
                ");
                $stmt->execute([DB_NAME, $table, $column]);
                $result = $stmt->fetch();
                
                if ($result['count'] == 0) {
                    $is_migrated = false;
                    $issues[] = "Column '$table.$column' TIDAK ADA";
                } else {
                    echo "  ✓ Column '$table.$column' ADA\n";
                }
            } catch (PDOException $e) {
                $is_migrated = false;
                $issues[] = "Error checking column '$table.$column': " . $e->getMessage();
            }
        }
    }
    
    if ($is_migrated) {
        echo "  ✅ MIGRATED - File dapat dihapus\n";
        $migrated_files[] = $file;
    } else {
        echo "  ❌ BELUM MIGRATED\n";
        foreach ($issues as $issue) {
            echo "     - $issue\n";
        }
        $not_migrated_files[] = $file;
        $all_migrated = false;
    }
    
    echo "\n";
}

echo str_repeat('=', 50) . "\n";
echo "SUMMARY\n";
echo str_repeat('=', 50) . "\n";
echo "Total files checked: " . count($migrations) . "\n";
echo "Migrated: " . count($migrated_files) . "\n";
echo "Not migrated: " . count($not_migrated_files) . "\n\n";

if (count($migrated_files) > 0) {
    echo "✅ FILES YANG SUDAH MIGRATED (dapat dihapus):\n";
    foreach ($migrated_files as $file) {
        echo "   - $file\n";
    }
    echo "\n";
}

if (count($not_migrated_files) > 0) {
    echo "❌ FILES YANG BELUM MIGRATED:\n";
    foreach ($not_migrated_files as $file) {
        echo "   - $file\n";
    }
    echo "\n";
}

if ($all_migrated) {
    echo "✅ SEMUA MIGRATIONS SUDAH DITERAPKAN!\n";
} else {
    echo "⚠️  ADA MIGRATIONS YANG BELUM DITERAPKAN!\n";
}

echo str_repeat('=', 50) . "\n";

