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
                        <td><?php echo escape(APP_VERSION ?? '1.0.0'); ?></td>
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
                            <small class="text-muted">
                                <a href="<?php echo base_url('test_git.php'); ?>" target="_blank">
                                    <i class="fas fa-vial"></i> Test Git Setup
                                </a>
                            </small>
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
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-download"></i> Update dari GitHub</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Pull update terbaru dari repository GitHub</p>
                
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Peringatan:</strong> Backup database akan dibuat otomatis sebelum update.
                </div>
                
                <form id="pullForm">
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" id="pullBranch" name="branch">
                            <option value="main">main</option>
                            <option value="master">master</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="autoBackup" checked>
                        <label class="form-check-label" for="autoBackup">
                            Buat backup otomatis sebelum update
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
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-upload"></i> Upload ke GitHub</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Push perubahan ke repository GitHub</p>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    Pastikan Anda sudah mengkonfigurasi Git credentials.
                </div>
                
                <form id="pushForm">
                    <div class="mb-3">
                        <label class="form-label">Commit Message</label>
                        <input type="text" class="form-control" id="commitMessage" 
                               placeholder="Deskripsi perubahan" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Branch</label>
                        <select class="form-select" id="pushBranch" name="branch">
                            <option value="main">main</option>
                            <option value="master">master</option>
                        </select>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="includeDatabase">
                        <label class="form-check-label" for="includeDatabase">
                            Include database backup (akan di-export terlebih dahulu)
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100" id="pushBtn">
                        <i class="fas fa-upload"></i> Push ke GitHub
                    </button>
                </form>
                
                <div id="pushResult" class="mt-3"></div>
            </div>
        </div>
    </div>
</div>

<!-- Database Backup -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-database"></i> Database Management</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Backup Database</h6>
                        <p class="text-muted small">Export database ke file SQL</p>
                        <button type="button" class="btn btn-danger" id="backupDbBtn">
                            <i class="fas fa-download"></i> Backup Database
                        </button>
                        <div id="backupDbResult" class="mt-2"></div>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>Upload Database ke GitHub</h6>
                        <p class="text-muted small">Export dan push database ke GitHub</p>
                        <button type="button" class="btn btn-outline-danger" id="uploadDbBtn">
                            <i class="fab fa-github"></i> Upload DB ke GitHub
                        </button>
                        <div id="uploadDbResult" class="mt-2"></div>
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

<script>
const apiUrl = '<?php echo base_url("api/github_sync.php"); ?>';

// Load Git Status
function loadGitStatus() {
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: { action: 'status' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<table class="table table-sm table-borderless">';
                
                if (!response.git_available) {
                    html += '<tr><td colspan="2"><div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Git tidak tersedia di server</div></td></tr>';
                } else {
                    html += '<tr><th>Repository</th><td><a href="' + response.github_url + '" target="_blank"><i class="fab fa-github"></i> ' + response.github_url + '</a></td></tr>';
                    
                    if (response.git_info.is_repo) {
                        html += '<tr><th>Branch</th><td><span class="badge bg-primary">' + response.git_info.branch + '</span></td></tr>';
                        html += '<tr><th>Commit</th><td><code>' + response.git_info.commit + '</code></td></tr>';
                        html += '<tr><th>Remote</th><td><small>' + response.git_info.remote + '</small></td></tr>';
                        
                        if (response.git_status.has_changes) {
                            html += '<tr><th>Status</th><td><span class="badge bg-warning">Modified</span></td></tr>';
                            if (response.git_status.changes.length > 0) {
                                html += '<tr><th>Changes</th><td><small><ul class="mb-0">';
                                response.git_status.changes.slice(0, 5).forEach(function(change) {
                                    html += '<li>' + change + '</li>';
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
                $('#gitStatus').html('<div class="alert alert-warning">' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Gagal memuat status Git';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#gitStatus').html('<div class="alert alert-danger">' + message + '</div>');
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

// Pull from GitHub
$('#pullForm').on('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('Pull update dari GitHub? Database akan di-backup otomatis sebelum update.')) {
        return;
    }
    
    $('#pullBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#pullResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sedang memproses...</div>');
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: { action: 'pull' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> ' + response.message;
                if (response.backup && response.backup.success) {
                    html += '<br><small><i class="fas fa-database"></i> Backup database: ' + response.backup.filename + '</small>';
                }
                if (response.output && response.output.length > 0) {
                    html += '<br><details class="mt-2"><summary>Detail Output</summary><pre class="mt-2 small">' + response.output.join('\n') + '</pre></details>';
                }
                html += '</div>';
                $('#pullResult').html(html);
                loadGitStatus();
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

// Push to GitHub
$('#pushForm').on('submit', function(e) {
    e.preventDefault();
    const commitMessage = $('#commitMessage').val();
    const includeDatabase = $('#includeDatabase').is(':checked');
    
    if (!commitMessage.trim()) {
        alert('Commit message harus diisi');
        return;
    }
    
    if (!confirm('Push perubahan ke GitHub? Pastikan Anda sudah mengkonfigurasi Git credentials.')) {
        return;
    }
    
    $('#pushBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#pushResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Sedang memproses...</div>');
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: {
            action: 'push',
            commit_message: commitMessage,
            include_database: includeDatabase ? 1 : 0
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> ' + response.message;
                if (response.backup && response.backup.success) {
                    html += '<br><small><i class="fas fa-database"></i> Database backup dibuat: ' + response.backup.filename + '</small>';
                }
                if (response.output && response.output.length > 0) {
                    html += '<br><details class="mt-2"><summary>Detail Output</summary><pre class="mt-2 small">' + response.output.join('\n') + '</pre></details>';
                }
                html += '</div>';
                $('#pushResult').html(html);
                loadGitStatus();
            } else {
                $('#pushResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#pushResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#pushBtn').prop('disabled', false).html('<i class="fas fa-upload"></i> Push ke GitHub');
        }
    });
});

// Backup Database
$('#backupDbBtn').on('click', function() {
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#backupDbResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Membuat backup...</div>');
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: { action: 'backup_db' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Backup berhasil!</strong>';
                html += '<br><small>File: ' + response.filename + '</small>';
                html += '<br><a href="' + apiUrl + '?action=download&file=' + encodeURIComponent(response.filename) + '" class="btn btn-sm btn-success mt-2">';
                html += '<i class="fas fa-download"></i> Download Backup</a>';
                html += '</div>';
                $('#backupDbResult').html(html);
            } else {
                $('#backupDbResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#backupDbResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#backupDbBtn').prop('disabled', false).html('<i class="fas fa-download"></i> Backup Database');
        }
    });
});

// Upload Database to GitHub
$('#uploadDbBtn').on('click', function() {
    if (!confirm('Upload database ke GitHub? Database akan di-backup dan di-push ke repository.')) {
        return;
    }
    
    const commitMessage = prompt('Commit message untuk database backup:', 'Database backup ' + new Date().toLocaleString('id-ID'));
    if (!commitMessage) {
        return;
    }
    
    $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
    $('#uploadDbResult').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Memproses...</div>');
    
    $.ajax({
        url: apiUrl,
        method: 'POST',
        data: { 
            action: 'push',
            commit_message: commitMessage,
            include_database: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                let html = '<div class="alert alert-success">';
                html += '<i class="fas fa-check-circle"></i> <strong>Berhasil!</strong> ' + response.message;
                if (response.backup && response.backup.success) {
                    html += '<br><small>Database backup: ' + response.backup.filename + '</small>';
                }
                html += '</div>';
                $('#uploadDbResult').html(html);
                loadGitStatus();
            } else {
                $('#uploadDbResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + response.message + '</div>');
            }
        },
        error: function(xhr) {
            let message = 'Terjadi kesalahan';
            try {
                const response = JSON.parse(xhr.responseText);
                message = response.message || message;
            } catch(e) {}
            $('#uploadDbResult').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ' + message + '</div>');
        },
        complete: function() {
            $('#uploadDbBtn').prop('disabled', false).html('<i class="fab fa-github"></i> Upload DB ke GitHub');
        }
    });
});

// Load Backup List
function loadBackupList() {
    // Get backups from backups directory
    $.ajax({
        url: '<?php echo base_url("api/backup_restore.php"); ?>',
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
                    html += '<tr>';
                    html += '<td><i class="fas fa-file-archive"></i> ' + backup.filename + '</td>';
                    html += '<td>' + typeBadge + '</td>';
                    html += '<td>' + backup.size_formatted + '</td>';
                    html += '<td>' + backup.modified + '</td>';
                    html += '<td>';
                    html += '<a href="<?php echo base_url("api/backup_restore.php"); ?>?action=download&filename=' + encodeURIComponent(backup.filename) + '" class="btn btn-sm btn-success me-1" title="Download">';
                    html += '<i class="fas fa-download"></i></a>';
                    html += '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                $('#backupList').html(html);
            } else {
                $('#backupList').html('<div class="alert alert-info"><i class="fas fa-info-circle"></i> Belum ada backup</div>');
            }
        },
        error: function() {
            $('#backupList').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Gagal memuat daftar backup</div>');
        }
    });
}

// Initialize
$(document).ready(function() {
    loadGitStatus();
    loadBackupList();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

