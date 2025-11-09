<?php
/**
 * General Helper Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date
 */
function format_date($date, $format = 'd/m/Y H:i') {
    if (empty($date)) return '-';
    $datetime = new DateTime($date);
    return $datetime->format($format);
}

/**
 * Format time ago
 */
function time_ago($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
    if ($diff < 2592000) return floor($diff / 86400) . ' hari yang lalu';
    if ($diff < 31536000) return floor($diff / 2592000) . ' bulan yang lalu';
    return floor($diff / 31536000) . ' tahun yang lalu';
}

/**
 * Generate random string
 */
function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Generate token format: 6 digit angka
 */
function generate_token() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Upload file
 */
function upload_file($file, $destination, $allowed_types = [], $max_size = MAX_FILE_SIZE) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File terlalu besar'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!empty($allowed_types) && !in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $destination . '/' . $filename;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $filepath];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

/**
 * Delete file
 */
function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get sekolah info
 */
function get_sekolah_info() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM sekolah LIMIT 1");
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Get sekolah info error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by ID
 */
function get_user($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get kelas by ID
 */
function get_kelas($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get kelas error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get mapel by ID
 */
function get_mapel($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM mapel WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get mapel error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get ujian by ID
 */
function get_ujian($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT u.*, m.nama_mapel, u2.nama as nama_guru 
                              FROM ujian u 
                              LEFT JOIN mapel m ON u.id_mapel = m.id 
                              LEFT JOIN users u2 ON u.id_guru = u2.id 
                              WHERE u.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get ujian error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get sesi by ID
 */
function get_sesi($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian 
                              FROM sesi_ujian s 
                              LEFT JOIN ujian u ON s.id_ujian = u.id 
                              WHERE s.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Get sesi error: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if user is assigned to sesi
 */
function is_user_assigned_to_sesi($user_id, $sesi_id) {
    global $pdo;
    
    try {
        // Check individual assignment
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sesi_peserta 
                              WHERE id_sesi = ? AND id_user = ? AND tipe_assign = 'individual'");
        $stmt->execute([$sesi_id, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            return true;
        }
        
        // Check kelas assignment
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sesi_peserta sp
                              INNER JOIN user_kelas uk ON sp.id_kelas = uk.id_kelas
                              WHERE sp.id_sesi = ? AND uk.id_user = ? AND sp.tipe_assign = 'kelas'");
        $stmt->execute([$sesi_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Check user assignment error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update ujian settings
 */
function update_ujian_settings($ujian_id, $settings) {
    global $pdo;
    
    $allowed_settings = [
        'acak_soal', 'acak_opsi', 'show_result', 'min_submit_minutes',
        'ai_correction_enabled', 'anti_contek_enabled', 'plagiarisme_check_enabled'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($settings as $key => $value) {
        if (in_array($key, $allowed_settings)) {
            $updates[] = "$key = ?";
            $params[] = $value;
        }
    }
    
    if (empty($updates)) {
        return false;
    }
    
    $params[] = $ujian_id;
    $sql = "UPDATE ujian SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Update ujian settings error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log activity
 */
function log_activity($action, $table_name = null, $record_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $action,
            $table_name,
            $record_id,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Log activity error: " . $e->getMessage());
    }
}

/**
 * Pagination helper
 */
function paginate($total_items, $current_page = 1, $items_per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'items_per_page' => $items_per_page,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Backup database
 */
function backup_database($include_sourcecode = false) {
    global $pdo;
    
    try {
        $backup_dir = BASE_PATH . '/backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = 'backup_' . $timestamp . '.sql';
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Get all tables
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if (empty($tables)) {
            return ['success' => false, 'message' => 'Tidak ada tabel yang ditemukan'];
        }
        
        $output = "-- Database Backup\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Database: " . DB_NAME . "\n\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        // Backup each table
        foreach ($tables as $table) {
            $output .= "-- Table: $table\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Get CREATE TABLE statement
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
            $output .= $create_table['Create Table'] . ";\n\n";
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $output .= "INSERT INTO `$table` VALUES\n";
                $values = [];
                    foreach ($rows as $row) {
                        $row_values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $row_values[] = 'NULL';
                            } else {
                                // Properly escape SQL values
                                $escaped = $pdo->quote($value);
                                $row_values[] = $escaped;
                            }
                        }
                        $values[] = "(" . implode(",", $row_values) . ")";
                    }
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        
        // Write to file
        if (file_put_contents($backup_path, $output) === false) {
            return ['success' => false, 'message' => 'Gagal menulis file backup'];
        }
        
        $result = [
            'success' => true,
            'message' => 'Backup database berhasil',
            'filename' => $backup_filename,
            'path' => $backup_path,
            'size' => filesize($backup_path)
        ];
        
        // If include sourcecode, create zip file
        if ($include_sourcecode) {
            if (!class_exists('ZipArchive')) {
                return ['success' => false, 'message' => 'Extension ZipArchive tidak tersedia. Install php-zip extension.'];
            }
            
            $zip_filename = 'backup_full_' . $timestamp . '.zip';
            $zip_path = $backup_dir . '/' . $zip_filename;
            
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                // Add database backup
                $zip->addFile($backup_path, 'database/' . $backup_filename);
                
                // Add source code (exclude certain directories)
                $exclude_dirs = ['backups', 'node_modules', '.git', 'vendor'];
                $exclude_files = ['.gitignore', '.env'];
                
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(BASE_PATH, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                
                foreach ($iterator as $file) {
                    $relative_path = str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $should_exclude = false;
                    
                    // Check if in excluded directory
                    foreach ($exclude_dirs as $exclude_dir) {
                        if (strpos($relative_path, $exclude_dir) === 0) {
                            $should_exclude = true;
                            break;
                        }
                    }
                    
                    // Check if excluded file
                    if (!$should_exclude) {
                        foreach ($exclude_files as $exclude_file) {
                            if (basename($relative_path) === $exclude_file) {
                                $should_exclude = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$should_exclude && $file->isFile()) {
                        $zip->addFile($file->getPathname(), 'sourcecode/' . $relative_path);
                    }
                }
                
                $zip->close();
                
                // Update result
                $result['zip_filename'] = $zip_filename;
                $result['zip_path'] = $zip_path;
                $result['zip_size'] = filesize($zip_path);
                $result['message'] = 'Backup database dan source code berhasil';
            } else {
                return ['success' => false, 'message' => 'Gagal membuat file ZIP'];
            }
        }
        
        return $result;
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Restore database from SQL file
 */
function restore_database($sql_file_path) {
    global $pdo;
    
    try {
        if (!file_exists($sql_file_path)) {
            return ['success' => false, 'message' => 'File backup tidak ditemukan'];
        }
        
        // Read SQL file
        $sql_content = file_get_contents($sql_file_path);
        if (empty($sql_content)) {
            return ['success' => false, 'message' => 'File backup kosong'];
        }
        
        // Remove BOM if present
        $sql_content = preg_replace('/^\xEF\xBB\xBF/', '', $sql_content);
        
        // Remove comments
        $sql_content = preg_replace('/--.*$/m', '', $sql_content);
        $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
        
        // Split into statements
        $statements = [];
        $current = '';
        $in_string = false;
        $string_char = '';
        
        for ($i = 0; $i < strlen($sql_content); $i++) {
            $char = $sql_content[$i];
            
            if (!$in_string && ($char === '"' || $char === "'" || $char === '`')) {
                $in_string = true;
                $string_char = $char;
                $current .= $char;
            } elseif ($in_string && $char === $string_char && ($i === 0 || $sql_content[$i-1] !== '\\')) {
                $in_string = false;
                $current .= $char;
            } elseif (!$in_string && $char === ';') {
                $current = trim($current);
                if (!empty($current)) {
                    $statements[] = $current;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Execute statements
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        
        return ['success' => true, 'message' => 'Restore database berhasil'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get list of backup files
 */
function get_backup_files() {
    $backup_dir = BASE_PATH . '/backups';
    if (!is_dir($backup_dir)) {
        return [];
    }
    
    $files = [];
    $items = scandir($backup_dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $file_path = $backup_dir . '/' . $item;
        if (is_file($file_path)) {
            $files[] = [
                'filename' => $item,
                'path' => $file_path,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path),
                'type' => pathinfo($item, PATHINFO_EXTENSION) === 'zip' ? 'full' : 'database'
            ];
        }
    }
    
    // Sort by modified time (newest first)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $files;
}

/**
 * Get tahun ajaran aktif
 * Returns tahun ajaran string (e.g., "2024/2025") or null if not found
 */
function get_tahun_ajaran_aktif() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT tahun_ajaran FROM tahun_ajaran WHERE is_active = 1 ORDER BY tahun_mulai DESC LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result['tahun_ajaran'];
        }
        
        // Fallback: generate from current date
        $current_year = (int)date('Y');
        return $current_year . '/' . ($current_year + 1);
    } catch (PDOException $e) {
        // If table doesn't exist, fallback to generated year
        error_log("Get tahun ajaran aktif error: " . $e->getMessage());
        $current_year = (int)date('Y');
        return $current_year . '/' . ($current_year + 1);
    }
}

/**
 * Get tahun ajaran by ID
 */
function get_tahun_ajaran($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM tahun_ajaran WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get tahun ajaran error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all tahun ajaran
 */
function get_all_tahun_ajaran($order_by = 'tahun_mulai DESC') {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM tahun_ajaran ORDER BY $order_by");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get all tahun ajaran error: " . $e->getMessage());
        return [];
    }
}

/**
 * Set tahun ajaran aktif
 * Deactivates all other tahun ajaran and activates the specified one
 */
function set_tahun_ajaran_aktif($tahun_ajaran_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Deactivate all
        $pdo->exec("UPDATE tahun_ajaran SET is_active = 0");
        
        // Activate specified
        $stmt = $pdo->prepare("UPDATE tahun_ajaran SET is_active = 1 WHERE id = ?");
        $stmt->execute([$tahun_ajaran_id]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Set tahun ajaran aktif error: " . $e->getMessage());
        return false;
    }
}

/**
 * Parse tahun ajaran string to tahun_mulai and tahun_selesai
 * Supports formats: "2024/2025", "2024-2025", "2024 2025"
 */
function parse_tahun_ajaran($tahun_ajaran_str) {
    // Remove spaces and normalize separators
    $tahun_ajaran_str = trim($tahun_ajaran_str);
    $tahun_ajaran_str = str_replace(['-', ' '], '/', $tahun_ajaran_str);
    
    // Extract years
    if (preg_match('/(\d{4})\s*\/\s*(\d{4})/', $tahun_ajaran_str, $matches)) {
        return [
            'tahun_mulai' => (int)$matches[1],
            'tahun_selesai' => (int)$matches[2],
            'tahun_ajaran' => $matches[1] . '/' . $matches[2]
        ];
    }
    
    // If only one year is provided, assume it's the start year
    if (preg_match('/(\d{4})/', $tahun_ajaran_str, $matches)) {
        $tahun_mulai = (int)$matches[1];
        return [
            'tahun_mulai' => $tahun_mulai,
            'tahun_selesai' => $tahun_mulai + 1,
            'tahun_ajaran' => $tahun_mulai . '/' . ($tahun_mulai + 1)
        ];
    }
    
    return null;
}

