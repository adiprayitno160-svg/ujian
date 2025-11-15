<?php
/**
 * Update Sistem - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Fitur: Update sistem aplikasi dari GitHub
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Update Sistem';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$sekolah = get_sekolah_info();
$github_repo = 'https://github.com/adiprayitno160-svg/ujian';
?>
<style>
/* Pulse animation for update notification */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}
.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Update alert styles */
#updateAvailableAlert {
    border-left: 4px solid #f59e0b;
}

#updateProgress {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Update Sistem</h2>
                <p class="text-muted">Update sistem aplikasi dari GitHub repository</p>
            </div>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sistem</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Versi Saat Ini</th>
                        <td>
                            <span id="currentVersionDisplay" class="badge bg-primary">v<?php echo escape(APP_VERSION ?? '1.0.0'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>GitHub Repository</th>
                        <td>
                            <a href="<?php echo $github_repo; ?>" target="_blank" class="text-decoration-none">
                                <i class="fab fa-github"></i> <?php echo $github_repo; ?>
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <th>Status Git</th>
                        <td>
                            <span id="gitStatus" class="badge bg-secondary">
                                <i class="fas fa-spinner fa-spin"></i> Memeriksa...
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Peringatan</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning mb-0">
                    <strong><i class="fas fa-info-circle"></i> Sebelum melakukan update:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Pastikan telah membuat backup database</li>
                        <li>Pastikan tidak ada perubahan lokal yang belum di-commit</li>
                        <li>Update akan mengambil perubahan dari branch main di GitHub</li>
                        <li>Sistem akan membuat backup database otomatis sebelum update</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Update from GitHub -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-download"></i> Update dari GitHub</h5>
            </div>
            <div class="card-body">
                <!-- Version Check Alert -->
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
                
                <!-- Update Available Alert -->
                <div id="updateAvailableAlert" class="alert alert-warning d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="flex-grow-1">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Update Tersedia!</strong>
                            <div class="small mt-1">
                                Versi saat ini: <strong id="currentVersionInAlert">v<?php echo escape(APP_VERSION ?? '1.0.0'); ?></strong> → 
                                Versi terbaru: <strong id="latestVersionDisplay"></strong>
                                <br>
                                <span id="updateReleaseNotes" class="text-muted"></span>
                            </div>
                        </div>
                        <div>
                            <button class="btn btn-primary" onclick="startUpdate()" id="updateNowBtn">
                                <i class="fas fa-download"></i> Update Sekarang
                            </button>
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
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-light" onclick="checkVersionUpdate(true)">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearCache()" title="Bersihkan cache untuk memaksa pengecekan ulang">
                                <i class="fas fa-trash-alt"></i> Clear Cache
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Manual Update Section -->
                <div class="border-top pt-3 mt-3">
                    <h6 class="mb-3"><i class="fas fa-tools"></i> Update Manual</h6>
                    <p class="text-muted mb-3">
                        Jika Anda yakin ingin melakukan update manual tanpa pengecekan versi, klik tombol di bawah ini.
                    </p>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success" onclick="manualUpdate()" id="manualUpdateBtnFull">
                            <i class="fas fa-download"></i> Update dari GitHub (Manual)
                        </button>
                        <small class="text-muted text-center mt-2">
                            <i class="fas fa-info-circle"></i> Update manual akan mengambil perubahan terbaru dari branch main
                        </small>
                    </div>
                    <div id="updateProgress" class="mt-3" style="display:none;">
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm text-primary me-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="flex-grow-1">
                                    <strong id="updateProgressText">Sedang memproses update...</strong>
                                    <div class="progress mt-2" style="height: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <small class="text-muted d-block mt-1">Mohon tunggu, jangan tutup halaman ini...</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Update Result -->
                <div id="updateResult" class="mt-3" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Git Status Information -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-code-branch"></i> Informasi Git</h5>
            </div>
            <div class="card-body">
                <div id="gitInfoContainer">
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2">Memuat informasi Git...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const apiUrl = '<?php echo base_url("api/github_sync.php"); ?>';

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

// Check version update from GitHub Releases
function checkVersionUpdate(forceRefresh = false) {
    // Hide all alerts first
    $('#versionCheckAlert').removeClass('d-none');
    $('#updateAvailableAlert').addClass('d-none');
    $('#noUpdateAlert').addClass('d-none');
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { 
            action: 'check_version',
            force_refresh: forceRefresh ? '1' : '0'
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            $('#versionCheckAlert').addClass('d-none');
            
            if (response.success) {
                // Update current version display
                if (response.current_version) {
                    $('#currentVersionDisplay').text('v' + response.current_version);
                }
                
                if (response.has_update) {
                    // Update available
                    $('#latestVersionDisplay').text('v' + response.latest_version);
                    $('#currentVersionInAlert').text('v' + response.current_version);
                    
                    if (response.release_notes) {
                        const notes = response.release_notes.substring(0, 150);
                        $('#updateReleaseNotes').html('<div class="mt-2"><em>' + escapeHtml(notes + (response.release_notes.length > 150 ? '...' : '')) + '</em></div>');
                    } else {
                        $('#updateReleaseNotes').html('');
                    }
                    
                    // Show release URL if available
                    if (response.release_url) {
                        $('#updateReleaseNotes').append('<div class="mt-1"><a href="' + response.release_url + '" target="_blank" class="text-decoration-none"><small><i class="fas fa-external-link-alt"></i> Lihat detail release</small></a></div>');
                    }
                    
                    $('#updateAvailableAlert').removeClass('d-none');
                    $('#noUpdateAlert').addClass('d-none');
                    
                    // Store update info for later use
                    window.updateInfo = response;
                    
                    // Scroll to alert
                    $('html, body').animate({
                        scrollTop: $('#updateAvailableAlert').offset().top - 100
                    }, 500);
                } else {
                    // No update
                    let message = response.message || 'Tidak ada update tersedia';
                    
                    if (response.warning) {
                        $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-info');
                        $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(response.warning) + '<br><small class="text-muted">' + escapeHtml(message) + '</small>');
                    } else {
                        $('#noUpdateAlert').removeClass('d-none').removeClass('alert-warning').removeClass('alert-info').addClass('alert-success');
                        $('#noUpdateInfo').html(escapeHtml(message));
                    }
                    $('#updateAvailableAlert').addClass('d-none');
                }
            } else {
                // Error checking
                let errorMsg = response.error || 'Gagal memeriksa update';
                
                if (response.error_type === 'timeout') {
                    errorMsg = 'Timeout saat memeriksa update';
                }
                
                $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-warning');
                $('#noUpdateInfo').html('<i class="fas fa-exclamation-triangle"></i> ' + escapeHtml(errorMsg) + '<br><small class="text-muted">Gunakan fitur Update Manual jika diperlukan.</small>');
                $('#updateAvailableAlert').addClass('d-none');
            }
        },
        error: function(xhr, status, error) {
            $('#versionCheckAlert').addClass('d-none');
            
            let errorMsg = 'Gagal memeriksa update';
            if (status === 'timeout') {
                errorMsg = 'Timeout saat memeriksa update';
            }
            
            $('#noUpdateAlert').removeClass('d-none').removeClass('alert-success').addClass('alert-warning');
            $('#noUpdateInfo').html('<i class="fas fa-info-circle"></i> ' + escapeHtml(errorMsg) + '<br><small class="text-muted">Gunakan fitur Update Manual jika diperlukan.</small>');
            $('#updateAvailableAlert').addClass('d-none');
            
            console.error('Version check error:', status, error);
        }
    });
}

// Clear cache function
function clearCache() {
    if (!confirm('Yakin ingin membersihkan cache? Ini akan memaksa sistem untuk memeriksa versi terbaru dari GitHub.')) {
        return;
    }
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { 
            action: 'clear_cache'
        },
        dataType: 'json',
        timeout: 5000,
        success: function(response) {
            if (response.success) {
                alert('Cache berhasil dibersihkan! Sekarang akan memeriksa versi terbaru...');
                checkVersionUpdate(true);
            } else {
                alert('Gagal membersihkan cache: ' + (response.message || 'Unknown error'));
            }
        },
        error: function(xhr, status, error) {
            alert('Error membersihkan cache: ' + error);
            console.error('Clear cache error:', status, error);
        }
    });
}

// Start update process
function startUpdate() {
    // Get update info from window or re-check if not available
    let updateInfo = window.updateInfo;
    if (!updateInfo || !updateInfo.has_update) {
        // Try to get from the alert element
        const latestVersion = $('#latestVersionDisplay').text();
        if (!latestVersion || latestVersion.trim() === '') {
            alert('Tidak ada update yang tersedia. Silakan refresh halaman untuk memeriksa ulang.');
            checkVersionUpdate(true);
            return;
        }
        // Create update info from displayed version
        updateInfo = {
            has_update: true,
            latest_version: latestVersion.replace('v', ''),
            current_version: '<?php echo APP_VERSION ?? "1.0.0"; ?>'
        };
    }
    
    // Detect if this is live server
    const isLiveServer = window.location.hostname !== 'localhost' && 
                         window.location.hostname !== '127.0.0.1' &&
                         !window.location.hostname.startsWith('192.168.') &&
                         !window.location.hostname.startsWith('10.') &&
                         window.location.protocol === 'https';
    
    // Create confirmation message
    let confirmMessage = '⚠️ KONFIRMASI UPDATE ⚠️\n\n';
    confirmMessage += 'Versi saat ini: v' + updateInfo.current_version + '\n';
    confirmMessage += 'Versi terbaru: v' + updateInfo.latest_version + '\n\n';
    confirmMessage += 'Update akan:\n';
    confirmMessage += '1. Membuat backup database otomatis\n';
    confirmMessage += '2. Mengaktifkan maintenance mode (jika live server)\n';
    confirmMessage += '3. Update file dari GitHub\n';
    confirmMessage += '4. Menjalankan database migration\n';
    if (isLiveServer) {
        confirmMessage += '5. Rollback otomatis jika gagal\n';
    }
    confirmMessage += '\n';
    if (isLiveServer) {
        confirmMessage += '⚠️ LIVE SERVER DETECTED ⚠️\n';
    }
    confirmMessage += '\nLanjutkan update?';
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Store update info for later use
    window.updateInfo = updateInfo;
    
    performUpdate(isLiveServer, updateInfo);
}

// Manual update (without version check)
function manualUpdate() {
    if (!confirm('⚠️ PERINGATAN ⚠️\n\n' +
                 'Anda akan melakukan update manual dari GitHub.\n' +
                 'Pastikan:\n' +
                 '1. Backup database sudah dibuat\n' +
                 '2. Tidak ada perubahan lokal penting\n' +
                 '3. Koneksi internet stabil\n\n' +
                 'Lanjutkan update?')) {
        return;
    }
    
    // Detect if this is live server
    const isLiveServer = window.location.hostname !== 'localhost' && 
                         window.location.hostname !== '127.0.0.1' &&
                         !window.location.hostname.startsWith('192.168.') &&
                         !window.location.hostname.startsWith('10.') &&
                         window.location.protocol === 'https';
    
    // Create dummy update info for manual update
    const updateInfo = {
        has_update: true,
        current_version: '<?php echo APP_VERSION ?? "1.0.0"; ?>',
        latest_version: 'manual'
    };
    
    performUpdate(isLiveServer, updateInfo);
}

// Perform update
function performUpdate(isLiveServer, updateInfo) {
    // Disable all update buttons
    $('#updateNowBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#manualUpdateBtnFull').prop('disabled', true);
    
    // Hide alerts and show progress
    $('#updateAvailableAlert').addClass('d-none');
    $('#noUpdateAlert').addClass('d-none');
    $('#updateProgress').show();
    $('#updateProgressText').text('Sedang memproses update...');
    $('#updateResult').hide().html('');
    
    // Update progress messages
    const progressSteps = [
        'Membuat backup database...',
        'Mengaktifkan maintenance mode...',
        'Mengunduh update dari GitHub...',
        'Mengupdate file sistem...',
        'Menjalankan database migration...',
        'Menyelesaikan update...'
    ];
    
    let currentStep = 0;
    const progressInterval = setInterval(function() {
        if (currentStep < progressSteps.length - 1) {
            currentStep++;
            $('#updateProgressText').text(progressSteps[currentStep]);
        }
    }, 5000);
    
    // Get latest tag from update info if available
    const latestTag = window.updateInfo && window.updateInfo.tag_name ? window.updateInfo.tag_name : null;
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'pull',
            branch: 'main',
            tag: latestTag, // Use latest tag if available
            use_latest_tag: latestTag ? '1' : '0', // Use latest release tag
            skip_backup: isLiveServer ? '0' : '1',
            is_live_server: isLiveServer ? '1' : '0'
        },
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout
        success: function(response) {
            clearInterval(progressInterval);
            $('#updateProgress').hide();
            $('#updateNowBtn').prop('disabled', false).html('<i class="fas fa-download"></i> Update Sekarang');
            $('#manualUpdateBtnFull').prop('disabled', false);
            
            if (response.success) {
                // Show success message
                let successMsg = '<div class="alert alert-success alert-dismissible fade show" role="alert">' +
                    '<i class="fas fa-check-circle me-2"></i>' +
                    '<strong>Update Berhasil!</strong><br><br>' +
                    '<div class="small">' + escapeHtml(response.message || 'Sistem berhasil diupdate dari GitHub') + '</div>';
                
                if (response.backup && response.backup.success) {
                    successMsg += '<div class="mt-2"><small class="text-muted"><i class="fas fa-database"></i> Backup database: ' + escapeHtml(response.backup.filename) + '</small></div>';
                }
                
                if (response.old_commit && response.new_commit) {
                    successMsg += '<div class="mt-1"><small class="text-muted"><i class="fas fa-code-branch"></i> Commit: ' + escapeHtml(response.old_commit) + ' → ' + escapeHtml(response.new_commit) + '</small></div>';
                }
                
                if (response.migrations) {
                    const migrationCount = Object.keys(response.migrations).length;
                    if (migrationCount > 0) {
                        successMsg += '<div class="mt-1"><small class="text-muted"><i class="fas fa-sync"></i> Database migrations: ' + migrationCount + ' dijalankan</small></div>';
                    }
                }
                
                successMsg += '<div class="mt-3"><strong><i class="fas fa-info-circle"></i> Halaman akan di-refresh otomatis dalam <span id="countdown">5</span> detik...</strong></div>' +
                    '<button type="button" class="btn btn-sm btn-light mt-2" onclick="location.reload()">Refresh Sekarang</button>' +
                    '</div>';
                
                $('#updateResult').html(successMsg).show();
                
                // Countdown before reload
                let countdown = 5;
                const countdownInterval = setInterval(function() {
                    countdown--;
                    const countdownEl = $('#countdown');
                    if (countdownEl.length) {
                        countdownEl.text(countdown);
                    }
                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        location.reload();
                    }
                }, 1000);
                
                // Clear update info from session
                window.updateInfo = null;
            } else {
                // Show error message
                let errorMsg = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                    '<i class="fas fa-exclamation-circle me-2"></i>' +
                    '<strong>Update Gagal!</strong><br><br>' +
                    '<div class="small">' + escapeHtml(response.message || 'Gagal melakukan update') + '</div>';
                
                if (response.rollback) {
                    errorMsg += '<div class="mt-2"><small class="text-warning"><i class="fas fa-undo"></i> Sistem telah di-rollback ke versi sebelumnya.</small></div>';
                }
                
                if (response.backup_error) {
                    errorMsg += '<div class="mt-2"><small class="text-muted"><i class="fas fa-exclamation-triangle"></i> Error backup: ' + escapeHtml(response.backup_error) + '</small></div>';
                }
                
                if (response.migration_error) {
                    errorMsg += '<div class="mt-2"><small class="text-muted"><i class="fas fa-exclamation-triangle"></i> Error migration: ' + escapeHtml(response.migration_error) + '</small></div>';
                }
                
                errorMsg += '<div class="mt-3"><button type="button" class="btn btn-sm btn-primary" onclick="checkVersionUpdate(true)">Cek Ulang Versi</button></div>' +
                    '</div>';
                
                $('#updateResult').html(errorMsg).show();
                
                // Refresh version check to show current status
                setTimeout(function() {
                    checkVersionUpdate(true);
                }, 1000);
            }
        },
        error: function(xhr, status, error) {
            clearInterval(progressInterval);
            $('#updateProgress').hide();
            $('#updateNowBtn').prop('disabled', false).html('<i class="fas fa-download"></i> Update Sekarang');
            $('#manualUpdateBtnFull').prop('disabled', false);
            
            let errorMsg = 'Error: ' + error;
            if (status === 'timeout') {
                errorMsg = 'Timeout saat melakukan update. Proses mungkin memakan waktu lebih lama. Silakan cek log server atau coba lagi nanti.';
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            
            $('#updateResult').html(
                '<div class="alert alert-danger alert-dismissible fade show" role="alert">' +
                '<i class="fas fa-exclamation-circle me-2"></i>' +
                '<strong>Update Gagal!</strong><br><br>' +
                '<div class="small">' + escapeHtml(errorMsg) + '</div>' +
                '<div class="mt-3"><button type="button" class="btn btn-sm btn-primary" onclick="checkVersionUpdate(true)">Cek Ulang Versi</button></div>' +
                '</div>'
            ).show();
            
            // Refresh version check
            setTimeout(function() {
                checkVersionUpdate(true);
            }, 1000);
        }
    });
}

// Load Git status
function loadGitStatus() {
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { 
            action: 'status'
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            if (response.success) {
                let gitInfoHtml = '<table class="table table-borderless">';
                
                if (response.git_info && response.git_info.is_repo) {
                    $('#gitStatus').removeClass('bg-secondary').addClass('bg-success').html('<i class="fas fa-check-circle"></i> Git Repository');
                    
                    gitInfoHtml += '<tr><th width="200">Branch</th><td><code>' + escapeHtml(response.git_info.branch || 'N/A') + '</code></td></tr>';
                    gitInfoHtml += '<tr><th>Commit</th><td><code>' + escapeHtml(response.git_info.commit || 'N/A') + '</code></td></tr>';
                    gitInfoHtml += '<tr><th>Remote</th><td><code>' + escapeHtml(response.git_info.remote || 'N/A') + '</code></td></tr>';
                    
                    if (response.git_status && response.git_status.has_changes) {
                        gitInfoHtml += '<tr><th>Status</th><td><span class="badge bg-warning">Ada perubahan lokal</span></td></tr>';
                        if (response.git_status.changes && response.git_status.changes.length > 0) {
                            gitInfoHtml += '<tr><th>Perubahan</th><td><ul class="mb-0">';
                            response.git_status.changes.slice(0, 5).forEach(function(change) {
                                gitInfoHtml += '<li><code>' + escapeHtml(change) + '</code></li>';
                            });
                            if (response.git_status.changes.length > 5) {
                                gitInfoHtml += '<li><small class="text-muted">... dan ' + (response.git_status.changes.length - 5) + ' perubahan lainnya</small></li>';
                            }
                            gitInfoHtml += '</ul></td></tr>';
                        }
                    } else {
                        gitInfoHtml += '<tr><th>Status</th><td><span class="badge bg-success">Tidak ada perubahan lokal</span></td></tr>';
                    }
                } else {
                    $('#gitStatus').removeClass('bg-secondary').addClass('bg-danger').html('<i class="fas fa-times-circle"></i> Bukan Git Repository');
                    gitInfoHtml += '<tr><td colspan="2" class="text-center text-muted">Repository Git tidak ditemukan</td></tr>';
                }
                
                gitInfoHtml += '</table>';
                $('#gitInfoContainer').html(gitInfoHtml);
            } else {
                $('#gitStatus').removeClass('bg-secondary').addClass('bg-danger').html('<i class="fas fa-times-circle"></i> Error');
                $('#gitInfoContainer').html('<div class="alert alert-danger">Gagal memuat informasi Git</div>');
            }
        },
        error: function(xhr, status, error) {
            $('#gitStatus').removeClass('bg-secondary').addClass('bg-warning').html('<i class="fas fa-exclamation-triangle"></i> Error');
            $('#gitInfoContainer').html('<div class="alert alert-warning">Gagal memuat informasi Git: ' + escapeHtml(error) + '</div>');
            console.error('Git status error:', status, error);
        }
    });
}

// Initialize
$(document).ready(function() {
    console.log('Update system page initialized');
    
    // Load Git status
    loadGitStatus();
    
    // Check version update on page load
    checkVersionUpdate(false);
    
    // Auto-check for version updates every 5 minutes
    setInterval(function() {
        checkVersionUpdate(false);
    }, 300000); // 5 minutes
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

