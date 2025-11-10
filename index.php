<?php
// Load config to get base URL functions
require_once __DIR__ . '/config/config.php';

// Always redirect to siswa-login for root URL
// This is the default landing page for the application
redirect('siswa-login');
exit;