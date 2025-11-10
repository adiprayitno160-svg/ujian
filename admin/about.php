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
                
                <div class="mb-3">
                    <p class="text-muted mb-2">Pull update terbaru dari repository GitHub</p>
                    <div class="d-flex align-items-center mb-3">
                        <span class="badge bg-info me-2">Versi Saat Ini:</span>
                        <strong id="currentVersionBeforePull">v<?php echo APP_VERSION; ?></strong>
                        <small class="text-muted ms-2" id="versionSource">(dari config)</small>
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="checkVersionUpdate(true)">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Backup database akan dibuat otomatis sebelum update.
                </div>
                
                <form id="pullForm">
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" id="pullBranch" name="branch">
                            <option value="main">main</option>
                            <option value="master" selected>master</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="autoBackup" checked>
                        <label class="form-check-label" for="autoBackup">
                            Buat backup otomatis sebelum update
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="updateVersionAfterPull">
                        <label class="form-check-label" for="updateVersionAfterPull">
                            Update versi sistem setelah pull berhasil
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100" id="pullBtn">
                        <i class="fas fa-download"></i> Pull Update dari GitHub
                    </button>
                </form>
                
                <div id="pullResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Backup & Restore -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-database"></i> Backup & Restore</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Backup Section -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-download"></i> Backup</h6>
                        <p class="text-muted small">Backup database atau full backup (database + source code)</p>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-danger mb-2" id="backupDbBtn">
                                <i class="fas fa-database"></i> Backup Database
                            </button>
                            <div class="text-muted small mb-2">Export database ke file SQL</div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-danger mb-2" id="backupFullBtn">
                                <i class="fas fa-archive"></i> Backup Full (Database + Source Code)
                            </button>
                            <div class="text-muted small mb-2">Export database dan seluruh source code ke file ZIP</div>
                        </div>
                        
                        <div id="backupResult" class="mt-2"></div>
                    </div>
                    
                    <!-- Restore Section -->
                    <div class="col-md-6">
                        <h6><i class="fas fa-upload"></i> Restore</h6>
                        <p class="text-muted small">Restore database dari file backup (.sql atau .zip)</p>
                        
                        <form id="restoreForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="backupFile" class="form-label">Pilih File Backup</label>
                                <input type="file" class="form-control" id="backupFile" name="backup_file" 
                                       accept=".sql,.zip" required>
                                <small class="text-muted">Format: .sql (database only) atau .zip (full backup)</small>
                            </div>
                            
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>Peringatan:</strong> Restore akan mengganti database saat ini. Pastikan sudah melakukan backup sebelumnya.
                            </div>
                            
                            <button type="submit" class="btn btn-warning" id="restoreBtn">
                                <i class="fas fa-upload"></i> Restore Database
                            </button>
                        </form>
                        
                        <div id="restoreResult" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Version & Changelog Management -->
<div class="row g-4 mb-4" id="versionManagementSection">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm" style="border-left: 4px solid #212529 !important;">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-code-branch"></i> Version & Changelog Management
                    <small class="ms-2 text-light opacity-75">(Kelola versi sistem dan changelog)</small>
                </h5>
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#versionModal">
                    <i class="fas fa-plus"></i> Versi Baru
                </button>
            </div>
            <div class="card-body">
                <div id="versionList">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Memuat data versi...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Backups -->
<div class="row g-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Recent Backups</h5>
            </div>
            <div class="card-body" id="backupList">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Version Modal -->
<div class="modal fade" id="versionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="versionModalTitle">Tambah Versi Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="versionForm">
                    <input type="hidden" id="versionId" name="version_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Versi <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="versionInput" name="version" 
                               placeholder="1.0.1" pattern="^\d+\.\d+\.\d+$" required>
                        <small class="text-muted">Format: X.Y.Z (contoh: 1.0.1, 1.1.0, 2.0.0)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tanggal Release <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="releaseDate" name="release_date" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Release Notes</label>
                        <textarea class="form-control" id="releaseNotes" name="release_notes" rows="3" 
                                  placeholder="Catatan rilis versi ini..."></textarea>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="isCurrent" name="is_current" checked>
                        <label class="form-check-label" for="isCurrent">
                            Set sebagai versi saat ini
                        </label>
                    </div>
                    
                    <hr>
                    
                    <h6>Changelog (Fitur yang di-update/di-betulkan)</h6>
                    <div id="changelogItems"></div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addChangelogItem()">
                        <i class="fas fa-plus"></i> Tambah Changelog
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="saveVersion()">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Changelog Item Template -->
<template id="changelogItemTemplate">
    <div class="changelog-item border rounded p-3 mb-3">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipe</label>
                <select class="form-select form-select-sm changelog-type">
                    <option value="feature">Feature (Fitur Baru)</option>
                    <option value="bugfix">Bugfix (Perbaikan Bug)</option>
                    <option value="improvement">Improvement (Peningkatan)</option>
                    <option value="security">Security (Keamanan)</option>
                    <option value="other">Other (Lainnya)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kategori</label>
                <input type="text" class="form-control form-control-sm changelog-category" 
                       placeholder="PR, Ujian, Admin, dll">
            </div>
            <div class="col-md-6">
                <label class="form-label">Judul <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm changelog-title" 
                       placeholder="Judul perubahan" required>
            </div>
            <div class="col-12">
                <label class="form-label">Deskripsi</label>
                <textarea class="form-control form-control-sm changelog-description" rows="2" 
                          placeholder="Deskripsi detail perubahan..."></textarea>
            </div>
            <div class="col-12 text-end">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeChangelogItem(this)">
                    <i class="fas fa-trash"></i> Hapus
                </button>
            </div>
        </div>
    </div>
</template>

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
                    
                    // Auto-update: Start update automatically when version is detected
                    console.log('Auto-update: Memulai update otomatis ke v' + response.latest_version);
                    $('#manualUpdateBtn').hide();
                    $('#autoUpdateStatus').show();
                    setTimeout(function() {
                        startUpdate();
                    }, 1000); // Wait 1 second before starting auto-update
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

// Start update process (automatic, no backup, no confirmation)
function startUpdate() {
    if (!window.updateInfo || !window.updateInfo.has_update) {
        alert('Tidak ada update yang tersedia');
        return;
    }
    
    // Show loading
    $('#updateAvailableAlert').html('<div class="text-center"><i class="fas fa-spinner fa-spin me-2"></i> Memproses update otomatis...</div>');
    
    // Get branch from selector or use current branch
    const selectedBranch = $('#pullBranch').val() || 'master';
    
    // Start pull process (skip backup, automatic update)
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'pull',
            branch: selectedBranch,
            skip_backup: '1' // Skip backup for automatic update
        },
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout for pull operation
        success: function(response) {
            if (response.success) {
                // Pull successful, now update version and config.php automatically
                updateSystemVersionAuto(window.updateInfo.latest_version, window.updateInfo.tag_name);
            } else {
                alert('Gagal melakukan update: ' + (response.message || 'Unknown error'));
                $('#updateAvailableAlert').removeClass('d-none');
                checkVersionUpdate(true);
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = 'Error: ' + error;
            if (status === 'timeout') {
                errorMsg = 'Timeout saat melakukan update. Proses mungkin memakan waktu lebih lama. Silakan cek log atau coba lagi.';
            }
            alert(errorMsg);
            $('#updateAvailableAlert').removeClass('d-none');
            checkVersionUpdate(true);
        }
    });
}

// Auto-update when version is detected (called automatically)
function autoUpdateIfAvailable() {
    if (window.updateInfo && window.updateInfo.has_update) {
        console.log('Auto-update: Versi baru terdeteksi, memulai update otomatis...');
        startUpdate();
    }
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
const backupRestoreApiUrl = '<?php echo base_url("api/backup_restore.php"); ?>';

// Load Git Status
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

// Pull from GitHub
$('#pullForm').on('submit', function(e) {
    e.preventDefault();
    
    const updateVersion = $('#updateVersionAfterPull').is(':checked');
    let confirmMsg = 'Pull update dari GitHub? Database akan di-backup otomatis sebelum update.';
    if (updateVersion) {
        confirmMsg += '\n\nSetelah pull berhasil, Anda akan diminta untuk membuat versi baru.';
    }
    
    if (!confirm(confirmMsg)) {
        return;
    }
    
    $('#pullBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#pullResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sedang memproses...</div>');
    
    // Get selected branch
    const selectedBranch = $('#pullBranch').val();
    const skipBackup = !$('#autoBackup').is(':checked');
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: { 
            action: 'pull',
            branch: selectedBranch,
            skip_backup: skipBackup ? '1' : '0'
        },
        timeout: 300000, // 5 minutes timeout
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> ' + response.message;
                if (response.branch) {
                    html += '<br><small><i class="fas fa-code-branch"></i> Branch: ' + escapeHtml(response.branch) + '</small>';
                }
                if (response.old_commit && response.new_commit) {
                    html += '<br><small><i class="fas fa-code"></i> Commit: ' + escapeHtml(response.old_commit) + ' → ' + escapeHtml(response.new_commit) + '</small>';
                }
                if (response.backup && response.backup.success) {
                    html += '<br><small><i class="fas fa-database"></i> Backup database: ' + escapeHtml(response.backup.filename) + '</small>';
                }
                if (response.output && response.output.length > 0) {
                    html += '<br><details class="mt-2"><summary>Detail Output</summary><pre class="mt-2 small">' + escapeHtml(response.output.join('\n')) + '</pre></details>';
                }
                html += '</div>';
                
                // If update version is checked, prompt for version update
                if (updateVersion) {
                    html += '<div class="alert alert-info mt-3">';
                    html += '<i class="fas fa-info-circle"></i> <strong>Update Versi:</strong> ';
                    html += '<button class="btn btn-sm btn-primary ms-2" onclick="$(\'#versionModal\').modal(\'show\')">';
                    html += '<i class="fas fa-plus"></i> Buat Versi Baru</button>';
                    html += '</div>';
                }
                
                $('#pullResult').html(html);
                loadGitStatus();
                loadVersions();
                loadCurrentVersionForPull();
                
                // Re-check for version updates after pull
                setTimeout(function() {
                    checkVersionUpdate(true); // Force refresh after pull
                }, 2000);
                
                // If update version is checked, show modal after a short delay
                if (updateVersion) {
                    setTimeout(function() {
                        if (confirm('Pull berhasil! Apakah Anda ingin membuat versi baru sekarang?')) {
                            $('#versionModal').modal('show');
                        }
                    }, 1000);
                }
            } else {
                $('#pullResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#pullResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#pullBtn').prop('disabled', false).html('<i class="fas fa-download"></i> Pull Update dari GitHub');
        }
    });
});

// Push to GitHub - Removed (gunakan Git CLI untuk push)

// Backup Database
$('#backupDbBtn').on('click', function() {
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#backupResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Membuat backup database...</div>');
    
    $.ajax({
        url: backupRestoreApiUrl,
        method: 'POST',
        data: { 
            action: 'backup',
            include_sourcecode: '0'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Backup database berhasil!</strong>';
                html += '<br><small>File: ' + response.filename + '</small>';
                if (response.size) {
                    const sizeMB = (response.size / (1024 * 1024)).toFixed(2);
                    html += '<br><small>Ukuran: ' + sizeMB + ' MB</small>';
                }
                html += '<br><a href="' + backupRestoreApiUrl + '?action=download&filename=' + encodeURIComponent(response.filename) + '" class="btn btn-sm btn-success mt-2">';
                html += '<i class="fas fa-download"></i> Download Backup</a>';
                html += '</div>';
                $('#backupResult').html(html);
                loadBackupList();
            } else {
                $('#backupResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#backupResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#backupDbBtn').prop('disabled', false).html('<i class="fas fa-database"></i> Backup Database');
        }
    });
});

// Backup Full (Database + Source Code)
$('#backupFullBtn').on('click', function() {
    if (!confirm('Backup full akan membuat file ZIP yang berisi database dan seluruh source code. Proses ini mungkin memakan waktu cukup lama. Lanjutkan?')) {
        return;
    }
    
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#backupResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Membuat backup full (database + source code)...</div>');
    
    $.ajax({
        url: backupRestoreApiUrl,
        method: 'POST',
        data: { 
            action: 'backup',
            include_sourcecode: '1'
        },
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout for full backup
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Backup full berhasil!</strong>';
                const filename = response.zip_filename || response.filename;
                html += '<br><small>File: ' + filename + '</small>';
                if (response.zip_size || response.size) {
                    const size = response.zip_size || response.size;
                    const sizeMB = (size / (1024 * 1024)).toFixed(2);
                    html += '<br><small>Ukuran: ' + sizeMB + ' MB</small>';
                }
                html += '<br><a href="' + backupRestoreApiUrl + '?action=download&filename=' + encodeURIComponent(filename) + '" class="btn btn-sm btn-success mt-2">';
                html += '<i class="fas fa-download"></i> Download Backup</a>';
                html += '</div>';
                $('#backupResult').html(html);
                loadBackupList();
            } else {
                $('#backupResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            let message = 'Terjadi kesalahan';
            if (status === 'timeout') {
                message = 'Request timeout. Backup full mungkin memakan waktu lebih lama. Coba lagi atau cek folder backups.';
            } else {
                try {
                    const response = JSON.parse(xhr.responseText);
                    message = response.message || message;
                } catch(e) {
                    message = 'Error: ' + error;
                }
            }
            $('#backupResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#backupFullBtn').prop('disabled', false).html('<i class="fas fa-archive"></i> Backup Full (Database + Source Code)');
        }
    });
});

// Restore Database
$('#restoreForm').on('submit', function(e) {
    e.preventDefault();
    
    const fileInput = $('#backupFile')[0];
    if (!fileInput.files || fileInput.files.length === 0) {
        alert('Pilih file backup terlebih dahulu');
        return;
    }
    
    if (!confirm('Peringatan: Restore akan mengganti database saat ini. Pastikan Anda sudah melakukan backup sebelumnya. Lanjutkan?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'restore');
    formData.append('backup_file', fileInput.files[0]);
    
    $('#restoreBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#restoreResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Memproses restore...</div>');
    
    $.ajax({
        url: backupRestoreApiUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout for restore
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Restore berhasil!</strong>';
                html += '<br><small>' + response.message + '</small>';
                html += '</div>';
                $('#restoreResult').html(html);
                
                // Reset form
                $('#restoreForm')[0].reset();
                
                // Reload page after 2 seconds to reflect changes
                setTimeout(function() {
                    if (confirm('Restore berhasil! Halaman akan di-refresh untuk menampilkan perubahan. Lanjutkan?')) {
                        location.reload();
                    }
                }, 2000);
            } else {
                $('#restoreResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr, status, error) {
            let message = 'Terjadi kesalahan';
            if (status === 'timeout') {
                message = 'Request timeout. Restore mungkin memakan waktu lebih lama.';
            } else {
                try {
                    const response = JSON.parse(xhr.responseText);
                    message = response.message || message;
                } catch(e) {
                    message = 'Error: ' + error;
                }
            }
            $('#restoreResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#restoreBtn').prop('disabled', false).html('<i class="fas fa-upload"></i> Restore Database');
        }
    });
});

// Upload Database to GitHub - Removed (gunakan Git CLI untuk push)

// Load Backup List
function loadBackupList() {
    // Get backups from backups directory
    $.ajax({
        url: backupRestoreApiUrl,
        method: 'GET',
        data: { action: 'list' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.files && response.files.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-hover">';
                html += '<thead><tr><th>File</th><th>Tipe</th><th>Ukuran</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>';
                response.files.forEach(function(backup) {
                    const typeBadge = backup.type === 'full' 
                        ? '<span class="badge bg-success">Full</span>' 
                        : '<span class="badge bg-primary">Database</span>';
                    const fileIcon = backup.type === 'full' 
                        ? '<i class="fas fa-file-archive"></i>' 
                        : '<i class="fas fa-database"></i>';
                    html += '<tr>';
                    html += '<td>' + fileIcon + ' ' + escapeHtml(backup.filename) + '</td>';
                    html += '<td>' + typeBadge + '</td>';
                    html += '<td>' + escapeHtml(backup.size_formatted) + '</td>';
                    html += '<td>' + escapeHtml(backup.modified) + '</td>';
                    html += '<td>';
                    html += '<a href="' + backupRestoreApiUrl + '?action=download&filename=' + encodeURIComponent(backup.filename) + '" class="btn btn-sm btn-success me-1" title="Download">';
                    html += '<i class="fas fa-download"></i></a>';
                    html += '<button class="btn btn-sm btn-danger delete-backup-btn" data-filename="' + escapeHtml(backup.filename.replace(/"/g, '&quot;')) + '" title="Hapus">';
                    html += '<i class="fas fa-trash"></i></button>';
                    html += '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                $('#backupList').html(html);
                
                // Attach delete handlers
                $('.delete-backup-btn').on('click', function() {
                    const filename = $(this).data('filename');
                    deleteBackup(filename);
                });
            } else {
                $('#backupList').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> Belum ada backup</div>');
            }
        },
        error: function() {
            $('#backupList').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat daftar backup</div>');
        }
    });
}

// Delete Backup
function deleteBackup(filename) {
    if (!confirm('Hapus backup "' + filename + '"? Tindakan ini tidak dapat dibatalkan.')) {
        return;
    }
    
    $.ajax({
        url: backupRestoreApiUrl,
        method: 'POST',
        data: {
            action: 'delete',
            filename: filename
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadBackupList();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('Terjadi kesalahan saat menghapus backup');
        }
    });
}

// Version Management Functions
function loadVersions() {
    console.log('Loading versions from:', versionApiUrl);
    
    // Show section if hidden
    $('#versionManagementSection').show();
    
    $.ajax({
        url: versionApiUrl,
        method: 'GET',
        data: { action: 'get_all_versions' },
        dataType: 'json',
        success: function(response) {
            console.log('Versions response:', response);
            if (response.success && response.versions) {
                let html = '';
                
                if (response.versions.length === 0) {
                    html = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Belum ada versi yang dibuat</div>';
                } else {
                    response.versions.forEach(function(version) {
                        const isCurrent = version.is_current == 1;
                        const typeBadges = {
                            'feature': 'success',
                            'bugfix': 'danger',
                            'improvement': 'info',
                            'security': 'warning',
                            'other': 'secondary'
                        };
                        
                        html += '<div class="card mb-3 ' + (isCurrent ? 'border-primary' : '') + '">';
                        html += '<div class="card-header d-flex justify-content-between align-items-center">';
                        html += '<div>';
                        html += '<h6 class="mb-0">';
                        html += '<span class="badge bg-primary me-2">v' + version.version + '</span>';
                        if (isCurrent) {
                            html += '<span class="badge bg-success">Versi Saat Ini</span>';
                        }
                        html += '</h6>';
                        html += '<small class="text-muted">';
                        html += '<i class="fas fa-calendar"></i> ' + version.release_date;
                        if (version.created_by_name) {
                            html += ' | <i class="fas fa-user"></i> ' + version.created_by_name;
                        }
                        html += '</small>';
                        html += '</div>';
                        html += '<div>';
                        html += '<button class="btn btn-sm btn-outline-primary me-1" onclick="editVersion(' + version.id + ')">';
                        html += '<i class="fas fa-edit"></i> Edit</button>';
                        if (!isCurrent) {
                            html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteVersion(' + version.id + ')">';
                            html += '<i class="fas fa-trash"></i></button>';
                        }
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="card-body">';
                        
                        if (version.release_notes) {
                            html += '<p class="text-muted">' + escapeHtml(version.release_notes) + '</p>';
                        }
                        
                        if (version.changelog && version.changelog.length > 0) {
                            html += '<h6 class="mt-3 mb-2">Changelog:</h6>';
                            html += '<ul class="list-unstyled">';
                            version.changelog.forEach(function(item) {
                                html += '<li class="mb-2">';
                                html += '<span class="badge bg-' + (typeBadges[item.type] || 'secondary') + ' me-2">';
                                html += item.type.charAt(0).toUpperCase() + item.type.slice(1);
                                html += '</span>';
                                if (item.category) {
                                    html += '<span class="badge bg-light text-dark me-2">' + escapeHtml(item.category) + '</span>';
                                }
                                html += '<strong>' + escapeHtml(item.title) + '</strong>';
                                if (item.description) {
                                    html += '<br><small class="text-muted ms-4">' + escapeHtml(item.description) + '</small>';
                                }
                                html += '</li>';
                            });
                            html += '</ul>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    });
                }
                
                $('#versionList').html(html);
                
                // Update current version display
                const currentVersion = response.versions.find(v => v.is_current == 1);
                if (currentVersion) {
                    $('#currentVersionDisplay').text('v' + currentVersion.version);
                }
            } else {
                $('#versionList').html('<div class="alert alert-warning">' + (response.message || 'Gagal memuat versi') + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Gagal memuat versi';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {
                if (xhr.status === 404) {
                    message = 'API endpoint tidak ditemukan. Pastikan file api/version_management.php ada.';
                } else if (xhr.status === 500) {
                    message = 'Error server. Cek console browser untuk detail.';
                }
            }
            $('#versionList').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
            console.error('Error loading versions:', xhr);
        }
    });
}

function addChangelogItem() {
    const template = document.getElementById('changelogItemTemplate');
    const clone = template.content.cloneNode(true);
    document.getElementById('changelogItems').appendChild(clone);
}

function removeChangelogItem(btn) {
    $(btn).closest('.changelog-item').remove();
}

function saveVersion() {
    const version = $('#versionInput').val().trim();
    const releaseDate = $('#releaseDate').val();
    const releaseNotes = $('#releaseNotes').val().trim();
    const isCurrent = $('#isCurrent').is(':checked') ? 1 : 0;
    const versionId = $('#versionId').val();
    
    if (!version || !releaseDate) {
        alert('Versi dan tanggal release harus diisi');
        return;
    }
    
    // Validate version format
    if (!/^\d+\.\d+\.\d+$/.test(version)) {
        alert('Format versi tidak valid. Gunakan format X.Y.Z (contoh: 1.0.1)');
        return;
    }
    
    // Collect changelog items
    const changelogItems = [];
    $('.changelog-item').each(function() {
        const title = $(this).find('.changelog-title').val().trim();
        if (title) {
            changelogItems.push({
                type: $(this).find('.changelog-type').val(),
                title: title,
                description: $(this).find('.changelog-description').val().trim(),
                category: $(this).find('.changelog-category').val().trim()
            });
        }
    });
    
    const action = versionId ? 'update_version' : 'create_version';
    const data = {
        action: action,
        version: version,
        release_date: releaseDate,
        release_notes: releaseNotes,
        is_current: isCurrent
    };
    
    if (versionId) {
        data.version_id = versionId;
    }
    
    $.ajax({
        url: versionApiUrl,
        method: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const versionIdToUse = response.version_id || versionId;
                
                // Save changelog items
                if (changelogItems.length > 0 && versionIdToUse) {
                    let saved = 0;
                    changelogItems.forEach(function(item) {
                        $.ajax({
                            url: versionApiUrl,
                            method: 'POST',
                            data: {
                                action: 'add_changelog',
                                version_id: versionIdToUse,
                                type: item.type,
                                title: item.title,
                                description: item.description,
                                category: item.category
                            },
                            dataType: 'json',
                            success: function() {
                                saved++;
                                if (saved === changelogItems.length) {
                                    $('#versionModal').modal('hide');
                                    loadVersions();
                                    loadCurrentVersionForPull();
                                    resetVersionForm();
                                    $(document).trigger('versionSaved');
                                }
                            }
                        });
                    });
                } else {
                    $('#versionModal').modal('hide');
                    loadVersions();
                    loadCurrentVersionForPull();
                    resetVersionForm();
                    $(document).trigger('versionSaved');
                }
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            alert(message);
        }
    });
}

function editVersion(versionId) {
    // Load version data
    $.ajax({
        url: versionApiUrl,
        method: 'GET',
        data: { action: 'get_all_versions' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const version = response.versions.find(v => v.id == versionId);
                if (version) {
                    $('#versionId').val(version.id);
                    $('#versionInput').val(version.version);
                    $('#releaseDate').val(version.release_date);
                    $('#releaseNotes').val(version.release_notes || '');
                    $('#isCurrent').prop('checked', version.is_current == 1);
                    $('#versionModalTitle').text('Edit Versi');
                    
                    // Load changelog
                    $('#changelogItems').empty();
                    if (version.changelog && version.changelog.length > 0) {
                        version.changelog.forEach(function(item) {
                            addChangelogItem();
                            const lastItem = $('#changelogItems .changelog-item').last();
                            lastItem.find('.changelog-type').val(item.type);
                            lastItem.find('.changelog-category').val(item.category || '');
                            lastItem.find('.changelog-title').val(item.title);
                            lastItem.find('.changelog-description').val(item.description || '');
                        });
                    }
                    
                    $('#versionModal').modal('show');
                }
            }
        }
    });
}

function deleteVersion(versionId) {
    if (!confirm('Hapus versi ini? Changelog yang terkait juga akan dihapus.')) {
        return;
    }
    
    $.ajax({
        url: versionApiUrl,
        method: 'POST',
        data: {
            action: 'delete_version',
            version_id: versionId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                loadVersions();
            } else {
                alert('Error: ' + response.message);
            }
        }
    });
}

function resetVersionForm() {
    $('#versionForm')[0].reset();
    $('#versionId').val('');
    $('#versionModalTitle').text('Tambah Versi Baru');
    $('#changelogItems').empty();
    $('#releaseDate').val(new Date().toISOString().split('T')[0]);
}

// Check for updates from Git (Secondary method - for informational purposes only)
// This function is now disabled by default to avoid conflicts with checkVersionUpdate()
// It can be called manually if needed for Git commit-based checking
function checkForUpdate() {
    // This function is deprecated in favor of checkVersionUpdate()
    // It's kept for backward compatibility but doesn't display any UI
    // Git status is now shown separately in the Git Status section
    console.log('checkForUpdate() called - this function is deprecated. Use checkVersionUpdate() instead.');
    
    // Silently check Git status without displaying errors
    const selectedBranch = $('#pullBranch').val();
    
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { 
            action: 'check_update',
            branch: selectedBranch
        },
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            // Only log to console, don't display UI
            if (response.success) {
                console.log('Git update check:', response.has_update ? 'Update available' : 'Up-to-date', response);
            } else {
                console.log('Git update check failed (non-critical):', response.message || response.error);
            }
        },
        error: function(xhr, status, error) {
            // Silent failure - don't show error to user
            console.log('Git update check error (non-critical):', status, error);
        }
    });
}

// Initialize
$(document).ready(function() {
    console.log('About page initialized');
    console.log('Version API URL:', versionApiUrl);
    
    // Ensure version section is visible
    $('#versionManagementSection').show();
    
    // Get branch from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const branchParam = urlParams.get('branch');
    if (branchParam) {
        $('#pullBranch').val(branchParam);
    }
    
    loadGitStatus();
    loadBackupList();
    loadVersions();
    loadCurrentVersionForPull();
    
    // Check version update on page load (primary method)
    checkVersionUpdate(false);
    
    // Auto-check for version updates every 5 minutes
    setInterval(function() {
        checkVersionUpdate(false);
    }, 300000); // 5 minutes
    
    // Reset form when modal is closed
    $('#versionModal').on('hidden.bs.modal', function() {
        resetVersionForm();
    });
    
    // Set default release date to today
    $('#releaseDate').val(new Date().toISOString().split('T')[0]);
    
    // Refresh version display after version is saved
    $(document).on('versionSaved', function() {
        loadCurrentVersionForPull();
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

