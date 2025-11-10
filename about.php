<?php
/**
 * About - Informasi Sistem untuk Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Redirect ke halaman about operator jika memiliki akses operator, atau tampilkan informasi dasar
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
check_session_timeout();

// Jika guru, redirect ke halaman about guru
if ($_SESSION['role'] === 'guru') {
    redirect('guru-about');
}

// Jika role lain, redirect sesuai role
if ($_SESSION['role'] === 'operator') {
    redirect('operator-about');
} elseif ($_SESSION['role'] === 'siswa') {
    redirect('siswa-about');
} elseif ($_SESSION['role'] === 'admin') {
    redirect('admin-about');
}

// Fallback
redirect('index.php');

