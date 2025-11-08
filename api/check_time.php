<?php
/**
 * Check Time API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Return server time
echo json_encode([
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'timestamp' => time()
]);

