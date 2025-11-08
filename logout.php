<?php
/**
 * Logout Page
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logout();
redirect('index.php');

