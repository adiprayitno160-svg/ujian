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
                            <button class="btn btn-sm btn-primary" onclick="startUpdate()" id="manualUpdateBtn">
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
                    </div>
                    <div id="updateProgress" class="mt-3" style="display:none;">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            <span id="updateProgressText">Sedang mengupdate sistem...</span>
                        </p>
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
                    if (response.release_notes) {
                        const notes = response.release_notes.substring(0, 200);
                        $('#updateReleaseNotes').text(notes + (response.release_notes.length > 200 ? '...' : ''));
                    }
                    $('#updateAvailableAlert').removeClass('d-none');
                    $('#noUpdateAlert').addClass('d-none');
                    
                    // Store update info for later use
                    window.updateInfo = response;
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
    if (!window.updateInfo || !window.updateInfo.has_update) {
        alert('Tidak ada update yang tersedia');
        return;
    }
    
    // Detect if this is live server
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
    
    performUpdate(isLiveServer);
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
    
    performUpdate(isLiveServer);
}

// Perform update
function performUpdate(isLiveServer) {
    // Show progress
    $('#manualUpdateBtn').prop('disabled', true);
    $('#manualUpdateBtnFull').prop('disabled', true);
    $('#updateProgress').show();
    $('#updateProgressText').text('Sedang memproses update...');
    $('#updateResult').hide();
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'pull',
            branch: 'main',
            skip_backup: isLiveServer ? '0' : '1',
            is_live_server: isLiveServer ? '1' : '0'
        },
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout
        success: function(response) {
            $('#updateProgress').hide();
            $('#manualUpdateBtn').prop('disabled', false);
            $('#manualUpdateBtnFull').prop('disabled', false);
            
            if (response.success) {
                // Show success message
                let successMsg = '<div class="alert alert-success">' +
                    '<i class="fas fa-check-circle me-2"></i>' +
                    '<strong>Update Berhasil!</strong><br>' +
                    '<small>' + escapeHtml(response.message || 'Sistem berhasil diupdate dari GitHub') + '</small>';
                
                if (response.backup && response.backup.success) {
                    successMsg += '<br><small class="text-muted">Backup database: ' + escapeHtml(response.backup.filename) + '</small>';
                }
                
                if (response.old_commit && response.new_commit) {
                    successMsg += '<br><small class="text-muted">Commit: ' + escapeHtml(response.old_commit) + ' → ' + escapeHtml(response.new_commit) + '</small>';
                }
                
                successMsg += '<br><br><strong>Halaman akan di-refresh dalam 3 detik...</strong>' +
                    '</div>';
                
                $('#updateResult').html(successMsg).show();
                
                // Reload page after 3 seconds
                setTimeout(function() {
                    location.reload();
                }, 3000);
            } else {
                // Show error message
                let errorMsg = '<div class="alert alert-danger">' +
                    '<i class="fas fa-exclamation-circle me-2"></i>' +
                    '<strong>Update Gagal!</strong><br>' +
                    '<small>' + escapeHtml(response.message || 'Gagal melakukan update') + '</small>';
                
                if (response.rollback) {
                    errorMsg += '<br><small class="text-warning">Sistem telah di-rollback ke versi sebelumnya.</small>';
                }
                
                if (response.backup_error) {
                    errorMsg += '<br><small class="text-muted">Error backup: ' + escapeHtml(response.backup_error) + '</small>';
                }
                
                errorMsg += '</div>';
                
                $('#updateResult').html(errorMsg).show();
                
                // Refresh version check
                checkVersionUpdate(true);
            }
        },
        error: function(xhr, status, error) {
            $('#updateProgress').hide();
            $('#manualUpdateBtn').prop('disabled', false);
            $('#manualUpdateBtnFull').prop('disabled', false);
            
            let errorMsg = 'Error: ' + error;
            if (status === 'timeout') {
                errorMsg = 'Timeout saat melakukan update. Proses mungkin memakan waktu lebih lama. Silakan cek log atau coba lagi.';
            }
            
            $('#updateResult').html(
                '<div class="alert alert-danger">' +
                '<i class="fas fa-exclamation-circle me-2"></i>' +
                '<strong>Update Gagal!</strong><br>' +
                '<small>' + escapeHtml(errorMsg) + '</small>' +
                '</div>'
            ).show();
            
            // Refresh version check
            checkVersionUpdate(true);
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

