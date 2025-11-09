<?php
/**
 * Manage Token - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

// Check if user has operator access (admin or guru with is_operator = 1)
if (!has_operator_access()) {
    redirect('index.php');
}

$sesi_id = intval($_GET['sesi_id'] ?? 0);
if (!$sesi_id) {
    redirect('operator/sesi/list.php');
}

$sesi = get_sesi($sesi_id);
if (!$sesi) {
    redirect('operator/sesi/list.php');
}

// Same as guru version
include __DIR__ . '/../../guru/sesi/manage_token.php';

