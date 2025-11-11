<?php
/**
 * Assign Peserta - Operator
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

// Validasi: Hanya sesi assessment yang bisa dikelola di halaman operator
// Sesi ulangan harian harus dikelola melalui menu guru
global $pdo;
$stmt = $pdo->prepare("SELECT u.tipe_asesmen FROM ujian u INNER JOIN sesi_ujian s ON u.id = s.id_ujian WHERE s.id = ?");
$stmt->execute([$sesi_id]);
$ujian = $stmt->fetch();
if (!$ujian || !in_array($ujian['tipe_asesmen'], ['sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun'])) {
    // Ini bukan sesi assessment, redirect ke list
    redirect('operator/sesi/list.php');
}

// Same as guru version, just copy the logic
include __DIR__ . '/../../guru/sesi/assign_peserta.php';

