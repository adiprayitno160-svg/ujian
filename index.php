<?php
// Load config to get base URL functions
require_once __DIR__ . '/config/config.php';

// Always redirect to login for root URL
// This is the default landing page for the application
redirect('login');
exit;