<?php
/**
 * Header Include
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

if (!defined('APP_NAME')) {
    require_once __DIR__ . '/../config/config.php';
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$sekolah = get_sekolah_info();
$current_user = get_logged_in_user();

// Check for updates (only for admin, cached)
$update_available = false;
$update_info = null;
if (is_logged_in() && $_SESSION['role'] === 'admin') {
    // Only check on page load, use cache
    if (!isset($_SESSION['update_check_time']) || (time() - $_SESSION['update_check_time']) > 3600) {
        try {
            require_once __DIR__ . '/version_check.php';
            $update_check = check_update_available(false); // Use cache
            // Only show notification if update is available and check was successful
            if ($update_check['success'] && isset($update_check['has_update']) && $update_check['has_update']) {
                $update_available = true;
                $update_info = $update_check;
            }
            $_SESSION['update_check_time'] = time();
            $_SESSION['update_available'] = $update_available;
            $_SESSION['update_info'] = $update_info;
            // Store error info for debugging but don't show notification for errors
            if (!$update_check['success']) {
                $_SESSION['update_check_error'] = $update_check['error'] ?? 'Unknown error';
            }
        } catch (Exception $e) {
            error_log("Update check error: " . $e->getMessage());
            $_SESSION['update_check_error'] = $e->getMessage();
        }
    } else {
        // Use cached result
        $update_available = $_SESSION['update_available'] ?? false;
        $update_info = $_SESSION['update_info'] ?? null;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <?php if (isset($role_css)): ?>
        <link rel="stylesheet" href="<?php echo asset_url('css/' . $role_css . '.css'); ?>">
    <?php endif; ?>
    
    <style>
        :root {
            --primary-color: #0066cc;
            --secondary-color: #0052a3;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #0066cc;
            --dark-color: #1e293b;
            --light-color: #f8fafc;
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --unbk-blue: #0066cc;
            --unbk-blue-dark: #004d99;
            --unbk-blue-light: #e6f2ff;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            overflow-x: hidden;
        }
        
        /* Full Page Layout */
        .app-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, #0066cc 0%, #0052a3 100%);
            color: #ffffff;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 2px 0 20px rgba(0, 102, 204, 0.3);
            display: flex;
            flex-direction: column;
            border-right: none;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.1);
            flex-shrink: 0;
        }
        
        .sidebar-header img {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            object-fit: cover;
        }
        
        .sidebar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            color: #1f2937;
            text-decoration: none;
            white-space: normal;
            word-wrap: break-word;
            line-height: 1.4;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
            flex: 1;
            overflow-y: auto;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            margin: 0.25rem 0.75rem;
            border-radius: 8px;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            border-left-color: #ffffff;
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
            border-left-color: #ffffff;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .menu-item i {
            width: 22px;
            margin-right: 0.875rem;
            text-align: center;
            font-size: 1rem;
        }
        
        .menu-label {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .menu-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.2rem 0.5rem;
            font-size: 0.6rem;
            font-weight: 700;
            border-radius: 12px;
            margin-left: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            border: none;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4), 
                        0 1px 3px rgba(0, 0, 0, 0.2),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .menu-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .menu-item:hover .menu-badge {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.5), 
                        0 2px 6px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
        }
        
        .menu-item:hover .menu-badge::before {
            left: 100%;
        }
        
        .menu-item.active .menu-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 4px 12px rgba(245, 87, 108, 0.5), 
                        0 2px 6px rgba(0, 0, 0, 0.3),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        .sidebar-footer {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-menu:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .user-info {
            flex: 1;
            min-width: 0;
        }
        
        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            text-transform: capitalize;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            background: #f0f4f8;
        }
        
        .content-header {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            padding: 1.5rem 2rem;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 102, 204, 0.2);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }
        
        .content-header h2 {
            color: #ffffff;
        }
        
        .sekolah-name-header {
            color: rgba(255, 255, 255, 0.95) !important;
        }
        
        .content-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .sekolah-name-header {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin: 0;
        }
        
        .content-header-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logout-btn {
            display: inline-flex !important;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: #ffffff;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            visibility: visible !important;
            opacity: 1 !important;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: #ffffff;
        }
        
        .logout-btn i {
            font-size: 1rem;
        }
        
        .content-header h2 {
            margin: 0;
        }
        
        .sidebar-toggle-btn {
            display: none;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 1.25rem;
            color: #ffffff;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .sidebar-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
        
        .sidebar-toggle-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
        }
        
        .content-body {
            padding: 2rem;
        }
        
        .main-content.no-header .content-body {
            padding-top: 2rem;
        }
        
        /* Sidebar Toggle */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-color);
            color: #fff;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }
        
        .sidebar.collapsed .sidebar-brand,
        .sidebar.collapsed .menu-label,
        .sidebar.collapsed .user-info {
            display: none;
        }
        
        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 0.75rem;
            margin: 0.25rem 0.5rem;
        }
        
        .sidebar.collapsed .menu-item i {
            margin-right: 0;
        }
        
        .sidebar.collapsed .user-menu {
            justify-content: center;
            padding: 0.5rem;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .sidebar-toggle-btn {
                display: block;
            }
            
            .content-header,
            .content-body {
                padding: 1rem;
            }
            
            .logout-btn span {
                display: none;
            }
            
            .logout-btn {
                padding: 0.5rem;
                min-width: 40px;
                justify-content: center;
            }
        }
        
        /* Overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
    </style>
</head>
<body <?php echo (isset($hide_navbar) && $hide_navbar) ? 'class="hide-navbar"' : ''; ?>>
    <?php if (is_logged_in() && !isset($hide_navbar)): ?>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="<?php echo base_url($_SESSION['role'] . '/index.php'); ?>" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">
                    <?php if ($sekolah && !empty($sekolah['logo'])): ?>
                        <img src="<?php echo asset_url('uploads/' . $sekolah['logo']); ?>" alt="Logo">
                    <?php else: ?>
                        <div style="width: 42px; height: 42px; background: rgba(255, 255, 255, 0.3); border: 2px solid rgba(255, 255, 255, 0.5); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);">U</div>
                    <?php endif; ?>
                </a>
            </div>
            
            <nav class="sidebar-menu">
                <a href="<?php echo base_url($_SESSION['role'] . '/index.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="menu-label">Dashboard</span>
                </a>
                
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="<?php echo base_url('admin/manage_siswa.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_siswa.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span class="menu-label">Kelola Siswa</span>
                    </a>
                    <a href="<?php echo base_url('admin/manage_users.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php' && (!isset($_GET['role']) || $_GET['role'] != 'siswa')) ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="menu-label">Users</span>
                    </a>
                    <a href="<?php echo base_url('admin/manage_kelas.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_kelas.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard"></i>
                        <span class="menu-label">Kelola Kelas</span>
                    </a>
                    <a href="<?php echo base_url('admin-manage-mapel'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_mapel.php') ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span class="menu-label">Mata Pelajaran</span>
                    </a>
                    <a href="<?php echo base_url('admin-manage-tahun-ajaran'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_tahun_ajaran.php') ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-label">Tahun Ajaran</span>
                    </a>
                    <a href="<?php echo base_url('admin/naik_kelas.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'naik_kelas.php') ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up"></i>
                        <span class="menu-label">Naik Kelas</span>
                    </a>
                    <a href="<?php echo base_url('admin/verifikasi_dokumen/index.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/verifikasi_dokumen/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-shield"></i>
                        <span class="menu-label">Verifikasi Dokumen</span>
                    </a>
                    <a href="<?php echo base_url('admin/arsip_soal/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/arsip_soal/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-database"></i>
                        <span class="menu-label">Arsip Soal</span>
                    </a>
                    <hr style="margin: 0.5rem 1.5rem; border-color: rgba(255, 255, 255, 0.2);">
                    <a href="<?php echo base_url('admin/about.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php' && strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span class="menu-label">About & System</span>
                    </a>
                    <a href="<?php echo base_url('admin/sekolah_settings.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'sekolah_settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Pengaturan</span>
                    </a>
                <?php elseif ($_SESSION['role'] === 'guru'): ?>
                    <a href="<?php echo base_url('guru/manage_siswa.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_siswa.php' && strpos($_SERVER['REQUEST_URI'], '/guru/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span class="menu-label">Kelola Siswa</span>
                    </a>
                    <a href="<?php echo base_url('guru/ujian/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/ujian/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Ujian</span>
                    </a>
                    <a href="<?php echo base_url('guru/sesi/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/sesi/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i>
                        <span class="menu-label">Sesi</span>
                    </a>
                    <a href="<?php echo base_url('guru/pr/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/pr/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                        <span class="menu-label">PR</span>
                    </a>
                    <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/tugas/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="menu-label">Tugas</span>
                    </a>
                    <a href="<?php echo base_url('guru/penilaian/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/penilaian/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i>
                        <span class="menu-label">Penilaian Manual</span>
                    </a>
                    <?php if (can_create_assessment_soal()): ?>
                        <a href="<?php echo base_url('guru-assessment-soal-create'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/assessment/soal/') !== false) ? 'active' : ''; ?>">
                            <i class="fas fa-file-question"></i>
                            <span class="menu-label">Pembuatan Soal Assessment</span>
                        </a>
                    <?php endif; ?>
                    <?php if (has_operator_access()): ?>
                        <a href="<?php echo base_url('operator/index.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/operator/') !== false && strpos($_SERVER['REQUEST_URI'], '/assessment/') === false) ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i>
                            <span class="menu-label">
                                Operator
                                <span class="menu-badge">OP</span>
                            </span>
                        </a>
                        <a href="<?php echo base_url('operator-assessment-index'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/assessment/') !== false) ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span class="menu-label">
                                Assessment
                                <span class="menu-badge">OP</span>
                            </span>
                        </a>
                    <?php endif; ?>
                    <hr style="margin: 0.5rem 1.5rem; border-color: rgba(255, 255, 255, 0.2);">
                    <a href="<?php echo base_url('admin/sekolah_settings.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'sekolah_settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Pengaturan</span>
                    </a>
                    <a href="<?php echo base_url('guru-about'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php' && strpos($_SERVER['REQUEST_URI'], '/guru/') !== false) || strpos($_SERVER['REQUEST_URI'], 'guru-about') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span class="menu-label">Informasi</span>
                    </a>
                <?php elseif ($_SESSION['role'] === 'operator'): ?>
                    <!-- Kelola Siswa -->
                    <a href="<?php echo base_url('operator-manage-siswa'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_siswa.php' && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span class="menu-label">
                            Kelola Siswa
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Kelola Kelas -->
                    <a href="<?php echo base_url('operator-manage-kelas'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_kelas.php' && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard"></i>
                        <span class="menu-label">
                            Kelola Kelas
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Template Raport -->
                    <a href="<?php echo base_url('operator-template-raport'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'template_raport.php' && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">
                            Template Raport
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Ledger Nilai Manual -->
                    <a href="<?php echo base_url('operator-ledger-nilai-manual'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'form.php' && strpos($_SERVER['REQUEST_URI'], '/operator/penilaian/') !== false) || (strpos($_SERVER['REQUEST_URI'], 'operator-ledger-nilai-manual') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span class="menu-label">
                            Ledger Nilai Manual
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Print Raport -->
                    <a href="<?php echo base_url('operator/raport/list.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'list.php' && strpos($_SERVER['REQUEST_URI'], '/operator/raport/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-print"></i>
                        <span class="menu-label">
                            Print Raport
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Sesi -->
                    <a href="<?php echo base_url('operator/sesi/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/sesi/') !== false && strpos($_SERVER['REQUEST_URI'], '/assessment/') === false && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i>
                        <span class="menu-label">
                            Sesi
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Monitoring -->
                    <a href="<?php echo base_url('operator/monitoring/realtime.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/monitoring/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span class="menu-label">
                            Monitoring
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <hr style="margin: 0.5rem 1.5rem; border-color: rgba(255, 255, 255, 0.2);">
                    
                    <!-- Assessment -->
                    <a href="<?php echo base_url('operator-assessment-index'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/assessment/') !== false && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-check"></i>
                        <span class="menu-label">
                            Assessment
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    
                    <!-- Raport -->
                    <a href="<?php echo base_url('operator/raport/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/raport/') !== false && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">
                            Raport
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    <a href="<?php echo base_url('operator-verifikasi-dokumen-index'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/verifikasi_dokumen/') !== false && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-shield"></i>
                        <span class="menu-label">
                            Verifikasi Dokumen
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    <a href="<?php echo base_url('admin/arsip_soal/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/arsip_soal/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-database"></i>
                        <span class="menu-label">
                            Arsip Soal
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    <a href="<?php echo base_url('operator-about'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php' && strpos($_SERVER['REQUEST_URI'], '/operator/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-info-circle"></i>
                        <span class="menu-label">
                            Informasi
                            <span class="menu-badge">OP</span>
                        </span>
                    </a>
                    <hr style="margin: 0.5rem 1.5rem; border-color: rgba(255, 255, 255, 0.2);">
                    <a href="<?php echo base_url('admin/sekolah_settings.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'sekolah_settings.php') ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="menu-label">Pengaturan</span>
                    </a>
                <?php elseif ($_SESSION['role'] === 'siswa'): ?>
                    <a href="<?php echo base_url('siswa/ujian/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/ujian/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Ujian</span>
                    </a>
                    <a href="<?php echo base_url('siswa/pr/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/pr/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i>
                        <span class="menu-label">PR</span>
                    </a>
                    <a href="<?php echo base_url('siswa/tugas/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/tugas/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span class="menu-label">Tugas</span>
                    </a>
                    <a href="<?php echo base_url('siswa/raport/list.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/raport/') !== false && strpos($_SERVER['REQUEST_URI'], '/siswa/') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span class="menu-label">Raport</span>
                    </a>
                    <?php
                    // Show verifikasi dokumen menu only for class IX students
                    if (is_logged_in() && $_SESSION['role'] === 'siswa') {
                        require_once __DIR__ . '/verifikasi_functions.php';
                        $id_siswa = $_SESSION['user_id'];
                        if (is_siswa_kelas_IX($id_siswa) && is_menu_verifikasi_aktif($id_siswa)) {
                            ?>
                            <a href="<?php echo base_url('siswa/verifikasi_dokumen/index.php'); ?>" class="menu-item <?php echo (strpos($_SERVER['REQUEST_URI'], '/verifikasi_dokumen/') !== false) ? 'active' : ''; ?>">
                                <i class="fas fa-file-shield"></i>
                                <span class="menu-label">Verifikasi Dokumen</span>
                            </a>
                            <?php
                        }
                    }
                    ?>
                    <a href="<?php echo base_url('siswa/profile.php'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span class="menu-label">Profile</span>
                    </a>
                <?php endif; ?>
                
                <?php if ($_SESSION['role'] === 'siswa'): ?>
                <a href="<?php echo base_url('siswa-about'); ?>" class="menu-item <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php' && strpos($_SERVER['REQUEST_URI'], '/siswa/') !== false) ? 'active' : ''; ?>">
                    <i class="fas fa-info-circle"></i>
                    <span class="menu-label">Informasi</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <?php if ($update_available && isset($update_info) && $_SESSION['role'] === 'admin'): ?>
                <div class="update-notification mb-2 px-3">
                    <a href="<?php echo base_url('admin/about.php'); ?>" class="btn btn-sm btn-warning text-dark w-100" title="Update Available: v<?php echo escape($update_info['latest_version']); ?>">
                        <i class="fas fa-download"></i>
                        <span class="ms-2">Update v<?php echo escape($update_info['latest_version']); ?></span>
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="user-menu dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($current_user['nama'] ?? $_SESSION['nama'], 0, 1)); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo escape($current_user['nama'] ?? $_SESSION['nama']); ?></div>
                        <div class="user-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.8);"></i>
                </div>
                <ul class="dropdown-menu" style="width: 100%; margin-top: 0.5rem; border: 1px solid rgba(255, 255, 255, 0.3); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); background: rgba(0, 102, 204, 0.95); backdrop-filter: blur(10px);">
                    <?php if ($_SESSION['role'] === 'siswa'): ?>
                        <li><a class="dropdown-item" href="<?php echo base_url('siswa/profile.php'); ?>" style="color: #ffffff;"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="#" style="color: #ffffff;"><i class="fas fa-user me-2"></i> Profile</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>
        
        <!-- Overlay for mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Main Content -->
        <div class="main-content <?php echo isset($hide_content_header) ? 'no-header' : ''; ?>">
            <?php if (!isset($hide_content_header)): ?>
            <div class="content-header">
                <?php if ($sekolah && !empty($sekolah['nama_sekolah'])): ?>
                <div class="content-header-top">
                    <p class="sekolah-name-header mb-0">
                        <i class="fas fa-school me-2"></i><?php echo escape($sekolah['nama_sekolah']); ?>
                    </p>
                    <button class="sidebar-toggle-btn" id="sidebarToggleBtn" type="button">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
                <?php endif; ?>
                <div class="content-header-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!$sekolah || empty($sekolah['nama_sekolah'])): ?>
                        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" type="button">
                            <i class="fas fa-bars"></i>
                        </button>
                        <?php endif; ?>
                        <h2 class="mb-0 fw-bold"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h2>
                    </div>
                    <div class="header-actions">
                        <a href="<?php echo base_url('logout.php'); ?>" class="logout-btn" title="Logout" onclick="return confirm('Apakah Anda yakin ingin logout?');">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="content-body">
    <?php else: ?>
    <main class="<?php echo isset($container_class) ? $container_class : 'container my-4'; ?>">
    <?php endif; ?>

