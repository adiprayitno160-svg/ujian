<?php
/**
 * API: Version & Changelog Management
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
check_session_timeout();

global $pdo;

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_current_version':
            // Get current version
            $stmt = $pdo->query("SELECT * FROM system_version WHERE is_current = 1 ORDER BY release_date DESC LIMIT 1");
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($version) {
                // Get changelog for this version
                $stmt = $pdo->prepare("SELECT * FROM system_changelog WHERE version_id = ? ORDER BY type, id");
                $stmt->execute([$version['id']]);
                $version['changelog'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'version' => $version ?: null
            ]);
            break;
            
        case 'get_all_versions':
            // Get all versions
            $stmt = $pdo->query("SELECT v.*, u.nama as created_by_name 
                                 FROM system_version v 
                                 LEFT JOIN users u ON v.created_by = u.id 
                                 ORDER BY v.release_date DESC, v.version DESC");
            $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get changelog for each version
            foreach ($versions as &$version) {
                $stmt = $pdo->prepare("SELECT * FROM system_changelog WHERE version_id = ? ORDER BY type, id");
                $stmt->execute([$version['id']]);
                $version['changelog'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode([
                'success' => true,
                'versions' => $versions
            ]);
            break;
            
        case 'create_version':
            // Create new version
            $version = trim($_POST['version'] ?? '');
            $release_date = $_POST['release_date'] ?? date('Y-m-d');
            $release_notes = trim($_POST['release_notes'] ?? '');
            
            if (empty($version)) {
                throw new Exception('Versi harus diisi');
            }
            
            // Validate version format (X.Y.Z)
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new Exception('Format versi tidak valid. Gunakan format X.Y.Z (contoh: 1.0.1)');
            }
            
            // Check if version already exists
            $stmt = $pdo->prepare("SELECT id FROM system_version WHERE version = ?");
            $stmt->execute([$version]);
            if ($stmt->fetch()) {
                throw new Exception('Versi sudah ada');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Set all versions to not current
                $pdo->exec("UPDATE system_version SET is_current = 0");
                
                // Insert new version
                $stmt = $pdo->prepare("INSERT INTO system_version (version, release_date, release_notes, is_current, created_by) 
                                       VALUES (?, ?, ?, 1, ?)");
                $stmt->execute([$version, $release_date, $release_notes, $_SESSION['user_id']]);
                $version_id = $pdo->lastInsertId();
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Versi berhasil dibuat',
                    'version_id' => $version_id
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'update_version':
            // Update version
            $version_id = intval($_POST['version_id'] ?? 0);
            $version = trim($_POST['version'] ?? '');
            $release_date = $_POST['release_date'] ?? '';
            $release_notes = trim($_POST['release_notes'] ?? '');
            $is_current = intval($_POST['is_current'] ?? 0);
            
            if ($version_id <= 0) {
                throw new Exception('ID versi tidak valid');
            }
            
            if (empty($version)) {
                throw new Exception('Versi harus diisi');
            }
            
            // Validate version format
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new Exception('Format versi tidak valid. Gunakan format X.Y.Z');
            }
            
            $pdo->beginTransaction();
            
            try {
                // If setting as current, unset others
                if ($is_current) {
                    $pdo->exec("UPDATE system_version SET is_current = 0");
                }
                
                // Update version
                $stmt = $pdo->prepare("UPDATE system_version 
                                       SET version = ?, release_date = ?, release_notes = ?, is_current = ?
                                       WHERE id = ?");
                $stmt->execute([$version, $release_date, $release_notes, $is_current, $version_id]);
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Versi berhasil diupdate'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'add_changelog':
            // Add changelog item
            $version_id = intval($_POST['version_id'] ?? 0);
            $type = $_POST['type'] ?? 'other';
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            
            if ($version_id <= 0) {
                throw new Exception('ID versi tidak valid');
            }
            
            if (empty($title)) {
                throw new Exception('Judul harus diisi');
            }
            
            // Validate type
            $allowed_types = ['feature', 'bugfix', 'improvement', 'security', 'other'];
            if (!in_array($type, $allowed_types)) {
                throw new Exception('Tipe tidak valid');
            }
            
            $stmt = $pdo->prepare("INSERT INTO system_changelog (version_id, type, title, description, category) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$version_id, $type, $title, $description, $category]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Changelog berhasil ditambahkan',
                'changelog_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_changelog':
            // Update changelog item
            $changelog_id = intval($_POST['changelog_id'] ?? 0);
            $type = $_POST['type'] ?? 'other';
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            
            if ($changelog_id <= 0) {
                throw new Exception('ID changelog tidak valid');
            }
            
            if (empty($title)) {
                throw new Exception('Judul harus diisi');
            }
            
            $allowed_types = ['feature', 'bugfix', 'improvement', 'security', 'other'];
            if (!in_array($type, $allowed_types)) {
                throw new Exception('Tipe tidak valid');
            }
            
            $stmt = $pdo->prepare("UPDATE system_changelog 
                                   SET type = ?, title = ?, description = ?, category = ?
                                   WHERE id = ?");
            $stmt->execute([$type, $title, $description, $category, $changelog_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Changelog berhasil diupdate'
            ]);
            break;
            
        case 'delete_changelog':
            // Delete changelog item
            $changelog_id = intval($_POST['changelog_id'] ?? 0);
            
            if ($changelog_id <= 0) {
                throw new Exception('ID changelog tidak valid');
            }
            
            $stmt = $pdo->prepare("DELETE FROM system_changelog WHERE id = ?");
            $stmt->execute([$changelog_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Changelog berhasil dihapus'
            ]);
            break;
            
        case 'delete_version':
            // Delete version (and its changelog)
            $version_id = intval($_POST['version_id'] ?? 0);
            
            if ($version_id <= 0) {
                throw new Exception('ID versi tidak valid');
            }
            
            // Check if it's current version
            $stmt = $pdo->prepare("SELECT is_current FROM system_version WHERE id = ?");
            $stmt->execute([$version_id]);
            $version = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($version && $version['is_current']) {
                throw new Exception('Tidak dapat menghapus versi yang sedang aktif');
            }
            
            $stmt = $pdo->prepare("DELETE FROM system_version WHERE id = ?");
            $stmt->execute([$version_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Versi berhasil dihapus'
            ]);
            break;
            
        default:
            throw new Exception('Action tidak valid');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

