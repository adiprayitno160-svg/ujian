<?php
/**
 * About & System Management - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Fitur: Update dari GitHub, Upload ke GitHub, Backup Database
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'About & System Management';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$sekolah = get_sekolah_info();
$github_repo = 'https://github.com/adiprayitno160-svg/ujian';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">About & System Management</h2>
                <p class="text-muted">Kelola sistem, update dari GitHub, dan backup database</p>
            </div>
            <div class="btn-group">
                <a href="<?php echo base_url('cleanup_repo.php'); ?>" class="btn btn-outline-danger" target="_blank">
                    <i class="fas fa-broom"></i> Cleanup
                </a>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sistem</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Nama Aplikasi</th>
                        <td><?php echo escape(APP_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>Versi</th>
                        <td>
                            <span id="currentVersionDisplay"><?php echo escape(APP_VERSION ?? '1.0.0'); ?></span>
                            <small class="text-muted ms-2">(Kelola versi di section Version Management di bawah)</small>
                        </td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Database</th>
                        <td><?php echo DB_NAME; ?></td>
                    </tr>
                    <tr>
                        <th>GitHub Repository</th>
                        <td>
                            <a href="<?php echo $github_repo; ?>" target="_blank" class="text-decoration-none">
                                <i class="fab fa-github"></i> <?php echo $github_repo; ?>
                            </a>
                            <br>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fab fa-github"></i> GitHub CLI & Git Status</h5>
            </div>
            <div class="card-body">
                <div id="gitStatusLoading" class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2 mb-0">Memuat status...</p>
                </div>
                <div id="gitStatusContent" style="display:none;">
                    <table class="table table-borderless">
                        <tr>
                            <th width="200">Git Status</th>
                            <td>
                                <span id="gitAvailableStatus" class="badge bg-secondary">Checking...</span>
                            </td>
                        </tr>
                        <tr>
                            <th>GitHub CLI Status</th>
                            <td>
                                <span id="githubCliStatus" class="badge bg-secondary">Checking...</span>
                            </td>
                        </tr>
                        <tr id="githubCliUserRow" style="display:none;">
                            <th>GitHub CLI User</th>
                            <td>
                                <span id="githubCliUser" class="text-muted">-</span>
                            </td>
                        </tr>
                        <tr id="githubCliVersionRow" style="display:none;">
                            <th>GitHub CLI Version</th>
                            <td>
                                <span id="githubCliVersion" class="text-muted">-</span>
                            </td>
                        </tr>
                        <tr id="gitBranchRow" style="display:none;">
                            <th>Git Branch</th>
                            <td>
                                <span id="gitBranch" class="text-muted">-</span>
                            </td>
                        </tr>
                        <tr id="gitCommitRow" style="display:none;">
                            <th>Git Commit</th>
                            <td>
                                <code id="gitCommit" class="text-muted">-</code>
                            </td>
                        </tr>
                    </table>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="loadGitStatus()">
                            <i class="fas fa-sync"></i> Refresh Status
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Features Section -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-star"></i> Fitur Baru Sistem UJAN</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Dashboard & Progress Tracking -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-chart-line text-primary"></i>
                                    </div>
                                    <h6 class="mb-0">Dashboard & Progress Tracking</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Dashboard siswa dengan grafik performa, progress tracking per mata pelajaran, 
                                    dan analisis trend nilai untuk memantau perkembangan belajar siswa.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sistem Notifikasi -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-info bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-bell text-info"></i>
                                    </div>
                                    <h6 class="mb-0">Sistem Notifikasi</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Notifikasi real-time untuk siswa dan guru, reminder ujian, notifikasi nilai keluar, 
                                    dan reminder deadline PR/Tugas untuk meningkatkan komunikasi dan engagement.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- AI Correction -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-robot text-success"></i>
                                    </div>
                                    <h6 class="mb-0">AI Correction (Google Gemini)</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Koreksi otomatis jawaban esai menggunakan Google Gemini AI dengan feedback 
                                    konstruktif, analisis kekuatan dan kelemahan, serta saran perbaikan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Plagiarisme Check -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-warning bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-shield-alt text-warning"></i>
                                    </div>
                                    <h6 class="mb-0">Plagiarisme Check</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Deteksi plagiarisme dengan similarity score, analisis per bagian, 
                                    dan identifikasi jawaban yang mencurigakan untuk menjaga integritas ujian.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- GitHub Sync & Update -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-dark bg-opacity-10 rounded p-2 me-2">
                                        <i class="fab fa-github text-dark"></i>
                                    </div>
                                    <h6 class="mb-0">GitHub Sync & Auto Update</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Integrasi dengan GitHub untuk update otomatis, backup database sebelum update, 
                                    version management, dan rollback otomatis jika update gagal.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Analisis Butir Soal -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-danger bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-chart-bar text-danger"></i>
                                    </div>
                                    <h6 class="mb-0">Analisis Butir Soal</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Analisis tingkat kesukaran, daya pembeda, efektivitas distraktor, 
                                    dan statistik butir soal untuk meningkatkan kualitas soal ujian.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Real-time Monitoring -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-tv text-primary"></i>
                                    </div>
                                    <h6 class="mb-0">Real-time Monitoring</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Monitoring real-time ujian untuk operator, tracking progress peserta, 
                                    status pengerjaan, dan monitoring aktivitas ujian secara live.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Security Features -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-success bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-lock text-success"></i>
                                    </div>
                                    <h6 class="mb-0">Enhanced Security</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Fitur keamanan lanjutan: browser lock mode, fullscreen enforcement, 
                                    deteksi multiple device login, dan monitoring aktivitas mencurigakan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Statistik & Analytics -->
                    <div class="col-md-6 col-lg-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="bg-info bg-opacity-10 rounded p-2 me-2">
                                        <i class="fas fa-analytics text-info"></i>
                                    </div>
                                    <h6 class="mb-0">Statistik & Analytics</h6>
                                </div>
                                <p class="text-muted small mb-0">
                                    Statistik nilai lengkap dengan grafik distribusi, perbandingan dengan ujian lain, 
                                    trend performa, dan analisis per kelas untuk insights yang lebih mendalam.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="alert alert-info mb-0">
                    <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Informasi Tambahan</h6>
                    <p class="mb-2">Sistem UJAN terus dikembangkan dengan fitur-fitur baru untuk meningkatkan pengalaman pengguna dan kualitas pembelajaran.</p>
                    <p class="mb-0 small">
                        <strong>Fitur yang akan datang:</strong> Review Mode sebelum Submit, Analisis Waktu per Soal, 
                        Question Tagging & Kategorisasi, Rubric-Based Grading, Export/Import Lanjutan, dan banyak lagi.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- GitHub Operations -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-download"></i> Update dari GitHub</h5>
            </div>
            <div class="card-body">
                <!-- Version Check Alert (Primary - GitHub Releases) -->
                <div id="versionCheckAlert" class="alert alert-info d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-sync-alt fa-spin me-2"></i>
                            <strong>Memeriksa versi terbaru...</strong>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" onclick="checkVersionUpdate(true)">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Update Available Alert (from GitHub Releases) -->
                <div id="updateAvailableAlert" class="alert alert-warning d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Update Tersedia!</strong>
                            <div class="small mt-1">
                                Versi terbaru: <strong id="latestVersionDisplay"></strong>
                                <br>
                                <span id="updateReleaseNotes" class="text-muted"></span>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-primary" onclick="startUpdate()" id="manualUpdateBtn" style="display:none;">
                                <i class="fas fa-download"></i> Update Sekarang
                            </button>
                            <span id="autoUpdateStatus" class="badge bg-info" style="display:none;">
                                <i class="fas fa-sync fa-spin"></i> Update otomatis dimulai...
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- No Update Alert -->
                <div id="noUpdateAlert" class="alert alert-success d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>Sistem sudah up-to-date</strong>
                            <div class="small mt-1" id="noUpdateInfo">Tidak ada update tersedia</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" onclick="checkVersionUpdate(true)">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 text-center">
                    <p class="text-muted mb-2">Versi Saat Ini: <strong id="currentVersionBeforePull">v<?php echo APP_VERSION; ?></strong></p>
                </div>
                
                    <div class="text-center">
                    <button type="button" class="btn btn-success btn-lg" id="quickUpdateBtn" onclick="quickUpdate()" style="display:none;">
                        <i class="fas fa-download"></i> Update ke Versi Terbaru
                    </button>
                    <div id="updateProgress" class="mt-3" style="display:none;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Updating...</span>
                        </div>
                        <p class="mt-2">Sedang mengupdate sistem...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




<script>
const apiUrl = '<?php echo base_url("api/github_sync.php"); ?>';
const versionApiUrl = '<?php echo base_url("api/version_management.php"); ?>';

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Check version update from GitHub Releases (Primary method)
function checkVersionUpdate(forceRefresh = false) {
    // Hide all alerts first
    $('#versionCheckAlert').removeClass('d-none');
    $('#updateAvailableAlert').addClass('d-none');
    $('#noUpdateAlert').addClass('d-none');
    $('#gitUpdateStatusAlert').addClass('d-none');
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { 
            action: 'check_version',
            force_refresh: forceRefresh ? '1' : '0'
        },
        dataType: 'json',
        timeout: 10000, // 10 seconds timeout
        success: function(response) {
            $('#versionCheckAlert').addClass('d-none');
            
            if (response.success) {
                // Update current version display
                if (response.current_version) {
                    $('#currentVersionBeforePull').text('v' + response.current_version);
                    $('#versionSource').text('(dari sistem)');
                }
                
                if (response.has_update) {
                    // Update available
                    $('#latestVersionDisplay').text('v' + response.latest_version);
                    if (response.release_notes) {
                        const notes = response.release_notes.substring(0, 200);
                        $('#updateReleaseNotes').text(notes + (response.release_notes.length > 200 ? '...' : ''));
                    }
                    $('#updateAvailableAlert').removeClass('d-none');
                    $('#noUpdateAlert').addClass('d-none');
                    
                    // Store update info for later use (for startUpdate function)
                    window.updateInfo = response;
                    console.log('Update info stored:', window.updateInfo);
                    
                    // Show quick update button
                    $('#quickUpdateBtn').show();
                    $('#updateProgress').hide();
                } else {
                    // No update - show success message
                    let message = response.message || 'Tidak ada update tersedia';
                    if (response.warning) {
                        // If there's a warning (e.g., using fallback), show info alert
                        $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-info');
                        $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(response.warning) + '<br><small class="text-muted">' + escapeHtml(message) + '</small>');
                    } else {
                        // Normal success - system is up-to-date
                        $('#noUpdateAlert').removeClass('d-none').removeClass('alert-warning').removeClass('alert-info').addClass('alert-success');
                        $('#noUpdateInfo').html(escapeHtml(message));
                    }
                    $('#updateAvailableAlert').addClass('d-none');
                }
            } else {
                // Error checking - show warning but don't fail completely
                let errorMsg = response.error || 'Gagal memeriksa update';
                let errorDetail = '';
                
                if (response.error_type === 'timeout') {
                    errorMsg = 'Timeout saat memeriksa update';
                    errorDetail = '<br><small class="text-muted">Cek koneksi internet atau gunakan fitur Pull dari GitHub untuk update manual.</small>';
                    $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-warning');
                    $('#noUpdateInfo').html('<i class="fas fa-clock"></i> ' + escapeHtml(errorMsg) + errorDetail);
                } else if (response.error_code === 404) {
                    errorMsg = 'Repository GitHub tidak ditemukan atau belum ada release';
                    errorDetail = '<br><small class="text-muted">Gunakan fitur Pull dari GitHub untuk update manual.</small>';
                    $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-warning');
                    $('#noUpdateInfo').html('<i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(errorMsg) + errorDetail);
                } else if (response.warning) {
                    // If there's a warning, show info alert instead of error
                    $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-info');
                    $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(response.warning) + '<br><small class="text-muted">' + escapeHtml(errorMsg) + '</small>');
                } else {
                    // Generic error - show warning
                    if (response.error_code) {
                        errorDetail = '<br><small class="text-muted">Error code: ' + escapeHtml(response.error_code) + '</small>';
                    }
                    $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-warning');
                    $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(errorMsg) + '<br><small class="text-muted">Gunakan fitur Pull dari GitHub untuk update manual jika diperlukan.</small>' + errorDetail);
                }
                $('#updateAvailableAlert').addClass('d-none');
            }
        },
        error: function(xhr, status, error) {
            $('#versionCheckAlert').addClass('d-none');
            
            let errorMsg = 'Gagal memeriksa update';
            let errorDetail = '';
            
            if (status === 'timeout') {
                errorMsg = 'Timeout saat memeriksa update';
                errorDetail = '<br><small class="text-muted">Request memakan waktu terlalu lama. Cek koneksi internet atau gunakan fitur Pull dari GitHub untuk update manual.</small>';
            } else {
                errorDetail = '<br><small class="text-muted">' + escapeHtml(error) + '</small>';
            }
            
            // Show warning but don't make it look like a critical error
            $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-info');
            $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(errorMsg) + errorDetail + '<br><small class="text-muted">Sistem mungkin sudah up-to-date. Gunakan fitur Pull untuk update manual jika diperlukan.</small>');
            $('#updateAvailableAlert').addClass('d-none');
            
            console.error('Version check error:', status, error);
        }
    });
}

// Quick update function - sekali klik langsung update
function quickUpdate() {
    if (!window.updateInfo || !window.updateInfo.has_update) {
        alert('Tidak ada update yang tersedia');
        return;
    }
    
    // Detect if this is live server (check if URL is not localhost)
    const isLiveServer = window.location.hostname !== 'localhost' && 
                         window.location.hostname !== '127.0.0.1' &&
                         !window.location.hostname.startsWith('192.168.') &&
                         !window.location.hostname.startsWith('10.') &&
                         window.location.protocol === 'https';
    
    // Confirm for live server
    if (isLiveServer) {
        if (!confirm('⚠️ LIVE SERVER DETECTED ⚠️\n\n' +
                     'Update akan:\n' +
                     '1. Membuat backup database otomatis\n' +
                     '2. Mengaktifkan maintenance mode\n' +
                     '3. Update file dan database\n' +
                     '4. Rollback otomatis jika gagal\n\n' +
                     'Lanjutkan update?')) {
        return;
        }
    }
    
    // Show progress
    $('#quickUpdateBtn').hide();
    $('#updateProgress').show();
    $('#updateAvailableAlert').html('<div class="text-center"><i class="fas fa-spinner fa-spin me-2"></i> Memproses update...</div>');
    
    // Get latest tag from update info if available
    const latestTag = window.updateInfo && window.updateInfo.tag_name ? window.updateInfo.tag_name : null;
    
    // Start pull process
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'pull',
            branch: 'main',
            tag: latestTag, // Use latest tag if available
            use_latest_tag: latestTag ? '1' : '0', // Use latest release tag
            skip_backup: isLiveServer ? '0' : '1', // Always backup for live server
            is_live_server: isLiveServer ? '1' : '0' // Mark as live server
        },
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout
        success: function(response) {
            if (response.success) {
                // Show migration info if available
                if (response.migrations) {
                    console.log('Database migrations executed:', response.migrations);
                }
                if (response.migration_error) {
                    console.warn('Migration error:', response.migration_error);
                }
                
                // Show success message
                if (response.backup && response.backup.success) {
                    console.log('Backup created:', response.backup.filename);
                }
                
                // Pull successful, now update version and config.php automatically
                updateSystemVersionAuto(window.updateInfo.latest_version, window.updateInfo.tag_name);
            } else {
                let errorMsg = 'Gagal melakukan update: ' + (response.message || 'Unknown error');
                if (response.rollback) {
                    errorMsg += '\n\nSistem telah di-rollback ke versi sebelumnya.';
                }
                if (response.backup_error) {
                    errorMsg += '\n\nError backup: ' + response.backup_error;
                }
                alert(errorMsg);
                $('#quickUpdateBtn').show();
                $('#updateProgress').hide();
                checkVersionUpdate(true);
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Error: ' + error;
            if (status === 'timeout') {
                errorMsg = 'Timeout saat melakukan update. Proses mungkin memakan waktu lebih lama. Silakan cek log atau coba lagi.';
            }
            alert(errorMsg);
            $('#quickUpdateBtn').show();
            $('#updateProgress').hide();
            checkVersionUpdate(true);
        }
    });
}

// Start update process (kept for compatibility)
function startUpdate() {
    quickUpdate();
}

// Update system version after successful pull (automatic version)
function updateSystemVersionAuto(version, tagName) {
    // Try to create or update version in database automatically
    $.ajax({
        url: versionApiUrl,
        method: 'POST',
        data: {
            action: 'create_or_update_version',
            version: version,
            release_date: new Date().toISOString().split('T')[0],
            release_notes: window.updateInfo.release_notes || 'Update otomatis dari GitHub',
            is_current: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Version saved to database, now update config.php (silent for auto-update)
                updateConfigVersion(version, true);
            } else {
                // If create fails, try to update existing version
                    updateExistingVersionToCurrent(version);
            }
        },
        error: function(xhr) {
            // If API doesn't support create_or_update_version, try update existing
                    updateExistingVersionToCurrent(version);
        }
    });
}

// Update system version after successful pull (manual version - kept for compatibility)
function updateSystemVersion(version, tagName) {
    updateSystemVersionAuto(version, tagName);
}

// Update existing version to current
function updateExistingVersionToCurrent(version) {
    // Get all versions and find the one with this version number
    $.ajax({
        url: versionApiUrl,
        method: 'GET',
        data: { action: 'get_all_versions' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.versions) {
                const existingVersion = response.versions.find(v => v.version === version);
                if (existingVersion) {
                    // Update this version to current
                    $.ajax({
                        url: versionApiUrl,
                        method: 'POST',
                        data: {
                            action: 'update_version',
                            version_id: existingVersion.id,
                            version: version,
                            release_date: existingVersion.release_date || new Date().toISOString().split('T')[0],
                            release_notes: existingVersion.release_notes || 'Update dari GitHub',
                            is_current: 1
                        },
                        dataType: 'json',
                        success: function() {
                            updateConfigVersion(version, true); // Silent for auto-update
                        },
                        error: function() {
                            updateConfigVersion(version, true);
                        }
                    });
                } else {
                    updateConfigVersion(version, true);
                }
            } else {
                updateConfigVersion(version, true);
            }
        },
        error: function() {
            updateConfigVersion(version, true);
        }
    });
}

// Update APP_VERSION in config.php (automatic, silent for auto-update)
function updateConfigVersion(version, silent = true) {
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'update_config_version',
            version: version
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (!silent) {
                alert('Update berhasil! Sistem telah diupdate ke v' + version + '\n\nHalaman akan di-refresh.');
                }
                // Auto-reload after update
                setTimeout(function() {
                    location.reload();
                }, silent ? 500 : 1000);
            } else {
                // Config update failed, but database update succeeded
                if (!silent) {
                alert('Update berhasil! Versi di database: v' + version + '\n\n' + 
                      (response.message || 'Gagal mengupdate config.php, tetapi versi di database sudah diupdate.') +
                      '\n\nHalaman akan di-refresh.');
                }
                setTimeout(function() {
                    location.reload();
                }, silent ? 500 : 1000);
            }
        },
        error: function() {
            // Config update failed, but database update succeeded
            if (!silent) {
            alert('Update berhasil! Versi di database: v' + version + 
                  '\n\nGagal mengupdate config.php, tetapi versi di database sudah diupdate.\n\nHalaman akan di-refresh.');
            }
            setTimeout(function() {
                location.reload();
            }, silent ? 500 : 1000);
        }
    });
}

// Load current version for display
function loadCurrentVersionForPull() {
    // Set default first (in case API fails)
    $('#versionSource').text('(dari config)');
    console.log('Loading current version...');
    
    // First, try to get version from database (system_version table)
    $.ajax({
        url: versionApiUrl,
        method: 'GET',
        data: { action: 'get_current_version' },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            console.log('Version API response:', response);
            if (response && response.success && response.version && response.version.version) {
                // Version found in database
                const dbVersion = response.version.version;
                const configVersion = '<?php echo APP_VERSION; ?>';
                
                $('#currentVersionBeforePull').text('v' + dbVersion);
                if (dbVersion !== configVersion) {
                    $('#versionSource').text('(dari database)');
                } else {
                    $('#versionSource').text('(dari database/config)');
                }
            } else {
                // No version in database, try Git tag
                loadVersionFromGit();
            }
        },
        error: function(xhr, status, error) {
            // API error, try Git tag
            console.log('Error loading version from API:', error);
            loadVersionFromGit();
        }
    });
}

// Load version from Git tag (fallback)
function loadVersionFromGit() {
    // Ensure versionSource is set
    $('#versionSource').text('(dari config)');
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { action: 'check_update' },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response && response.success) {
                if (response.current_tag) {
                    // Git tag found
                    $('#currentVersionBeforePull').text(response.current_tag);
                    $('#versionSource').text('(dari Git tag)');
                } else if (response.current_commit) {
                    // No tag, but commit available
                    $('#currentVersionBeforePull').text('v<?php echo APP_VERSION; ?>');
                    $('#versionSource').text('(commit: ' + response.current_commit + ')');
                } else {
                    // No Git info, use config
                    $('#currentVersionBeforePull').text('v<?php echo APP_VERSION; ?>');
                    $('#versionSource').text('(dari config)');
                }
            } else {
                // Response failed, use config
                $('#currentVersionBeforePull').text('v<?php echo APP_VERSION; ?>');
                $('#versionSource').text('(dari config)');
            }
        },
        error: function(xhr, status, error) {
            // Git API error, use config (already set above)
            $('#currentVersionBeforePull').text('v<?php echo APP_VERSION; ?>');
            $('#versionSource').text('(dari config)');
            console.log('Error loading version from Git:', error);
        }
    });
}

// Quick update function handles everything

// Load Git and GitHub CLI status
function loadGitStatus() {
    $('#gitStatusLoading').show();
    $('#gitStatusContent').hide();
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { action: 'status' },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            $('#gitStatusLoading').hide();
            $('#gitStatusContent').show();
            
            // Git status
            if (response.git_available) {
                $('#gitAvailableStatus').removeClass('bg-secondary bg-danger').addClass('bg-success').text('Tersedia');
            } else {
                $('#gitAvailableStatus').removeClass('bg-secondary bg-success').addClass('bg-danger').text('Tidak Tersedia');
            }
            
            // GitHub CLI status
            if (response.github_cli && response.github_cli.available) {
                if (response.github_cli.authenticated) {
                    $('#githubCliStatus').removeClass('bg-secondary bg-danger bg-warning').addClass('bg-success').text('Aktif & Terautentikasi');
                    
                    // Show user and version if available
                    if (response.github_cli.user) {
                        $('#githubCliUser').text(response.github_cli.user);
                        $('#githubCliUserRow').show();
                    }
                    if (response.github_cli.version) {
                        $('#githubCliVersion').text(response.github_cli.version);
                        $('#githubCliVersionRow').show();
                    }
                } else {
                    $('#githubCliStatus').removeClass('bg-secondary bg-danger bg-success').addClass('bg-warning').text('Tersedia (Belum Login)');
                    $('#githubCliUserRow').hide();
                    $('#githubCliVersionRow').hide();
                }
            } else {
                $('#githubCliStatus').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger').text('Tidak Tersedia');
                $('#githubCliUserRow').hide();
                $('#githubCliVersionRow').hide();
            }
            
            // Git info
            if (response.git_info && response.git_info.is_repo) {
                if (response.git_info.branch) {
                    $('#gitBranch').text(response.git_info.branch);
                    $('#gitBranchRow').show();
                }
                if (response.git_info.commit) {
                    $('#gitCommit').text(response.git_info.commit);
                    $('#gitCommitRow').show();
                }
            } else {
                $('#gitBranchRow').hide();
                $('#gitCommitRow').hide();
            }
        },
        error: function(xhr, status, error) {
            $('#gitStatusLoading').hide();
            $('#gitStatusContent').show();
            $('#gitAvailableStatus').removeClass('bg-secondary bg-success').addClass('bg-danger').text('Error');
            $('#githubCliStatus').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger').text('Error');
            console.error('Error loading Git status:', error);
        }
    });
}

// Initialize
$(document).ready(function() {
    console.log('About page initialized');
    
    loadCurrentVersionForPull();
    loadGitStatus();
    
    // Check version update on page load
    checkVersionUpdate(false);
    
    // Auto-check for version updates every 5 minutes
    setInterval(function() {
        checkVersionUpdate(false);
    }, 300000); // 5 minutes
    
    // Auto-refresh Git status every 2 minutes
    setInterval(function() {
        loadGitStatus();
    }, 120000); // 2 minutes
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

