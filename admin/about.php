h error
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
                <h5 class="mb-0"><i class="fas fa-code-branch"></i> Git Status</h5>
            </div>
            <div class="card-body" id="gitStatus">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat status Git...</p>
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
                
                <!-- Git Status Alert (Secondary - Git Commit Check, hidden by default) -->
                <div id="gitUpdateStatusAlert" class="alert alert-secondary d-none mb-3" style="display: none !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <i class="fas fa-code-branch me-2"></i>
                            <small id="gitUpdateStatusText">Git status check (informational)</small>
                        </div>
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
    
    // Show progress
    $('#quickUpdateBtn').hide();
    $('#updateProgress').show();
    $('#updateAvailableAlert').html('<div class="text-center"><i class="fas fa-spinner fa-spin me-2"></i> Memproses update...</div>');
    
    // Start pull process (skip backup, automatic update)
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'pull',
            branch: 'master',
            skip_backup: '1' // Skip backup for quick update
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
                
                // Pull successful, now update version and config.php automatically
                updateSystemVersionAuto(window.updateInfo.latest_version, window.updateInfo.tag_name);
            } else {
                alert('Gagal melakukan update: ' + (response.message || 'Unknown error'));
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
// Load Git Status (simplified)
function loadGitStatus() {
    console.log('Loading Git status from:', apiUrl);
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { action: 'status' },
        dataType: 'json',
        timeout: 10000, // 10 seconds timeout
        success: function(response) {
            console.log('Git status response:', response);
            
            if (response && response.success) {
                let html = '<table class="table table-sm table-borderless">';
                
                if (!response.git_available) {
                    html += '<tr><td colspan="2"><div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Git tidak tersedia di server</div></td></tr>';
                } else {
                    html += '<tr><th>Repository</th><td><a href="' + (response.github_url || 'https://github.com/adiprayitno160-svg/ujian') + '" target="_blank"><i class="fab fa-github"></i> ' + (response.github_url || 'GitHub Repository') + '</a></td></tr>';
                    
                    if (response.git_info && response.git_info.is_repo) {
                        html += '<tr><th>Branch</th><td><span class="badge bg-primary">' + (response.git_info.branch || 'N/A') + '</span></td></tr>';
                        html += '<tr><th>Commit</th><td><code>' + (response.git_info.commit || 'N/A') + '</code></td></tr>';
                        if (response.git_info.remote) {
                            html += '<tr><th>Remote</th><td><small>' + response.git_info.remote + '</small></td></tr>';
                        }
                        
                        if (response.git_status && response.git_status.has_changes) {
                            html += '<tr><th>Status</th><td><span class="badge bg-warning">Modified</span></td></tr>';
                            if (response.git_status.changes && response.git_status.changes.length > 0) {
                                html += '<tr><th>Changes</th><td><small><ul class="mb-0">';
                                response.git_status.changes.slice(0, 5).forEach(function(change) {
                                    html += '<li>' + escapeHtml(change) + '</li>';
                                });
                                if (response.git_status.changes.length > 5) {
                                    html += '<li>... dan ' + (response.git_status.changes.length - 5) + ' file lainnya</li>';
                                }
                                html += '</ul></small></td></tr>';
                            }
                        } else {
                            html += '<tr><th>Status</th><td><span class="badge bg-success">Clean</span></td></tr>';
                        }
                    } else {
                        html += '<tr><td colspan="2"><div class="alert alert-info">';
                        html += '<i class="fas fa-info-circle"></i> Repository belum diinisialisasi. ';
                        html += '<button class="btn btn-sm btn-primary mt-2" onclick="initRepo()">Initialize Repository</button>';
                        html += '</div></td></tr>';
                    }
                }
                
                html += '</table>';
                $('#gitStatus').html(html);
            } else {
                $('#gitStatus').html('<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> ' + (response && response.message ? response.message : 'Gagal memuat status Git') + '</div>');
            }
        },
        error: function(xhr, status, error) {
            console.error('Git status error:', status, error, xhr);
            let message = 'Gagal memuat status Git';
            
            if (status === 'timeout') {
                message = 'Request timeout. Git mungkin tidak tersedia atau server tidak merespons.';
            } else if (xhr.status === 0) {
                message = 'Tidak dapat terhubung ke server. Pastikan API endpoint tersedia.';
            } else {
                try {
                    const response = JSON.parse(xhr.responseText);
                    message = response.message || message;
                } catch(e) {
                    if (xhr.responseText) {
                        message = 'Error: ' + xhr.status + ' - ' + error;
                    }
                }
            }
            
            $('#gitStatus').html('<div class="alert alert-danger">' +
                '<i class="fas fa-exclamation-triangle"></i> ' + message + 
                '<br><small class="mt-2 d-block">Cek console browser (F12) untuk detail error.</small>' +
                '</div>');
        }
    });
}

// Initialize Repository
function initRepo() {
    if (!confirm('Inisialisasi repository Git? Ini akan membuat folder .git di project.')) {
        return;
    }
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: { action: 'init' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('Repository berhasil diinisialisasi!');
                loadGitStatus();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Terjadi kesalahan');
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

// Initialize
$(document).ready(function() {
    console.log('About page initialized');
    
    loadGitStatus();
    loadCurrentVersionForPull();
    
    // Check version update on page load
    checkVersionUpdate(false);
    
    // Auto-check for version updates every 5 minutes
    setInterval(function() {
        checkVersionUpdate(false);
    }, 300000); // 5 minutes
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

