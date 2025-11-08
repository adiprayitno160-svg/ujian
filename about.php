<?php
/**
 * About Page
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$sekolah = get_sekolah_info();
$page_title = 'Tentang Aplikasi';
include __DIR__ . '/includes/header.php';

// Backup/Restore JavaScript (only for admin)
$backup_restore_js = '';
if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN) {
    $backup_restore_js = '
    <script>
    $(document).ready(function() {
        const apiUrl = "' . base_url('api/backup_restore.php') . '";
        
        // Load backup list
        function loadBackupList() {
            $.ajax({
                url: apiUrl,
                method: "GET",
                data: { action: "list" },
                dataType: "json",
                success: function(response) {
                    if (response.success && response.files.length > 0) {
                        let html = "<table class=\"table table-hover\"><thead><tr><th>Nama File</th><th>Tipe</th><th>Ukuran</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>";
                        response.files.forEach(function(file) {
                            const typeBadge = file.type === "full" 
                                ? "<span class=\"badge bg-success\">Full</span>" 
                                : "<span class=\"badge bg-primary\">Database</span>";
                            html += "<tr>";
                            html += "<td><i class=\"fas fa-file-" + (file.type === "full" ? "archive" : "code") + " me-2\"></i>" + file.filename + "</td>";
                            html += "<td>" + typeBadge + "</td>";
                            html += "<td>" + file.size_formatted + "</td>";
                            html += "<td>" + file.modified + "</td>";
                            html += "<td>";
                            html += "<a href=\"" + apiUrl + "?action=download&filename=" + encodeURIComponent(file.filename) + "\" class=\"btn btn-sm btn-success me-1\" title=\"Download\"><i class=\"fas fa-download\"></i></a>";
                            html += "<button class=\"btn btn-sm btn-danger\" onclick=\"deleteBackup(\'" + file.filename + "\')\" title=\"Hapus\"><i class=\"fas fa-trash\"></i></button>";
                            html += "</td>";
                            html += "</tr>";
                        });
                        html += "</tbody></table>";
                        $("#backupList").html(html);
                    } else {
                        $("#backupList").html("<div class=\"alert alert-info\"><i class=\"fas fa-info-circle me-2\"></i>Belum ada file backup.</div>");
                    }
                },
                error: function() {
                    $("#backupList").html("<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>Gagal memuat daftar backup.</div>");
                }
            });
        }
        
        // Backup form
        $("#backupForm").on("submit", function(e) {
            e.preventDefault();
            const backupType = $("input[name=backup_type]:checked").val();
            const includeSourcecode = backupType === "full" ? 1 : 0;
            
            $("#backupBtn").prop("disabled", true).html("<i class=\"fas fa-spinner fa-spin me-2\"></i>Memproses...");
            $("#backupResult").html("");
            
            $.ajax({
                url: apiUrl,
                method: "POST",
                data: {
                    action: "backup",
                    include_sourcecode: includeSourcecode
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        const filename = response.zip_filename || response.filename;
                        const size = response.zip_size || response.size;
                        const sizeFormatted = formatFileSize(size);
                        let html = "<div class=\"alert alert-success\">";
                        html += "<i class=\"fas fa-check-circle me-2\"></i><strong>Berhasil!</strong> " + response.message;
                        html += "<br><small>File: " + filename + " (" + sizeFormatted + ")</small>";
                        if (response.zip_filename) {
                            html += "<br><a href=\"" + apiUrl + "?action=download&filename=" + encodeURIComponent(response.zip_filename) + "\" class=\"btn btn-sm btn-success mt-2\"><i class=\"fas fa-download me-2\"></i>Download Backup</a>";
                        } else {
                            html += "<br><a href=\"" + apiUrl + "?action=download&filename=" + encodeURIComponent(response.filename) + "\" class=\"btn btn-sm btn-success mt-2\"><i class=\"fas fa-download me-2\"></i>Download Backup</a>";
                        }
                        html += "</div>";
                        $("#backupResult").html(html);
                        loadBackupList();
                    } else {
                        $("#backupResult").html("<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>" + response.message + "</div>");
                    }
                },
                error: function(xhr) {
                    let message = "Terjadi kesalahan saat membuat backup.";
                    try {
                        const response = JSON.parse(xhr.responseText);
                        message = response.message || message;
                    } catch(e) {}
                    $("#backupResult").html("<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>" + message + "</div>");
                },
                complete: function() {
                    $("#backupBtn").prop("disabled", false).html("<i class=\"fas fa-download me-2\"></i>Buat Backup");
                }
            });
        });
        
        // Restore form
        $("#restoreForm").on("submit", function(e) {
            e.preventDefault();
            
            if (!confirm("PERINGATAN: Restore akan mengganti semua data yang ada dengan data dari backup. Apakah Anda yakin ingin melanjutkan?")) {
                return;
            }
            
            const formData = new FormData(this);
            formData.append("action", "restore");
            
            $("#restoreBtn").prop("disabled", true).html("<i class=\"fas fa-spinner fa-spin me-2\"></i>Memproses...");
            $("#restoreResult").html("");
            
            $.ajax({
                url: apiUrl,
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        $("#restoreResult").html("<div class=\"alert alert-success\"><i class=\"fas fa-check-circle me-2\"></i><strong>Berhasil!</strong> " + response.message + "</div>");
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        $("#restoreResult").html("<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>" + response.message + "</div>");
                    }
                },
                error: function(xhr) {
                    let message = "Terjadi kesalahan saat restore.";
                    try {
                        const response = JSON.parse(xhr.responseText);
                        message = response.message || message;
                    } catch(e) {}
                    $("#restoreResult").html("<div class=\"alert alert-danger\"><i class=\"fas fa-exclamation-triangle me-2\"></i>" + message + "</div>");
                },
                complete: function() {
                    $("#restoreBtn").prop("disabled", false).html("<i class=\"fas fa-upload me-2\"></i>Restore dari Backup");
                }
            });
        });
        
        // Delete backup function
        window.deleteBackup = function(filename) {
            if (!confirm("Apakah Anda yakin ingin menghapus backup ini?")) {
                return;
            }
            
            $.ajax({
                url: apiUrl,
                method: "POST",
                data: {
                    action: "delete",
                    filename: filename
                },
                dataType: "json",
                success: function(response) {
                    if (response.success) {
                        showToast(response.message, "success");
                        loadBackupList();
                    } else {
                        showToast(response.message, "error");
                    }
                },
                error: function() {
                    showToast("Terjadi kesalahan saat menghapus backup.", "error");
                }
            });
        };
        
        // Format file size helper
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + " GB";
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + " MB";
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + " KB";
            } else {
                return bytes + " bytes";
            }
        }
        
        // Load backup list on page load
        loadBackupList();
    });
    </script>';
}
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-5">
                <div class="text-center mb-5">
                    <h1 class="display-4 fw-bold text-primary mb-3"><?php echo APP_NAME; ?></h1>
                    <p class="lead text-muted">Versi <?php echo APP_VERSION; ?></p>
                </div>
                
                <div class="mb-5">
                    <h3 class="fw-bold mb-4">Deskripsi Aplikasi</h3>
                    <p class="text-muted">
                        Sistem Ujian dan Pekerjaan Rumah (UJAN) adalah aplikasi berbasis web yang dirancang untuk 
                        memudahkan proses ujian dan pengumpulan pekerjaan rumah secara digital. Aplikasi ini 
                        mendukung berbagai tipe soal dan dilengkapi dengan fitur keamanan yang ketat.
                    </p>
                </div>
                
                <div class="mb-5">
                    <h3 class="fw-bold mb-4">Fitur Utama</h3>
                    
                    <?php if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_SISWA): ?>
                        <!-- Hanya tampilkan fitur untuk siswa -->
                        <div class="row justify-content-center">
                            <div class="col-md-8">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-info bg-opacity-10 rounded p-3">
                                            <i class="fas fa-user-graduate fa-2x text-info"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="fw-bold">Fitur untuk Siswa</h5>
                                        <ul class="list-unstyled text-muted">
                                            <li><i class="fas fa-check text-success"></i> Kerjakan Ujian Online</li>
                                            <li><i class="fas fa-check text-success"></i> Submit Pekerjaan Rumah (PR)</li>
                                            <li><i class="fas fa-check text-success"></i> Lihat Hasil Ujian & Nilai</li>
                                            <li><i class="fas fa-check text-success"></i> Fitur Ragu-Ragu (Mark untuk review)</li>
                                            <li><i class="fas fa-check text-success"></i> Auto-save Otomatis</li>
                                            <li><i class="fas fa-check text-success"></i> Timer & Countdown</li>
                                            <li><i class="fas fa-check text-success"></i> Review Jawaban Sebelum Submit</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Tampilkan semua fitur untuk role lain -->
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary bg-opacity-10 rounded p-3">
                                            <i class="fas fa-user-shield fa-2x text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="fw-bold">Admin</h5>
                                        <ul class="list-unstyled text-muted">
                                            <li><i class="fas fa-check text-success"></i> Manajemen Users</li>
                                            <li><i class="fas fa-check text-success"></i> Manajemen Kelas & Mata Pelajaran</li>
                                            <li><i class="fas fa-check text-success"></i> Pengaturan Sekolah</li>
                                            <li><i class="fas fa-check text-success"></i> Approve Migrasi Kelas</li>
                                            <li><i class="fas fa-check text-success"></i> System Logs</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-success bg-opacity-10 rounded p-3">
                                            <i class="fas fa-chalkboard-teacher fa-2x text-success"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="fw-bold">Guru</h5>
                                        <ul class="list-unstyled text-muted">
                                            <li><i class="fas fa-check text-success"></i> Buat & Kelola Ujian</li>
                                            <li><i class="fas fa-check text-success"></i> Jadwal Sesi Ujian</li>
                                            <li><i class="fas fa-check text-success"></i> Bank Soal</li>
                                            <li><i class="fas fa-check text-success"></i> Kelola PR</li>
                                            <li><i class="fas fa-check text-success"></i> Review & Nilai</li>
                                            <li><i class="fas fa-check text-success"></i> Analisis Butir Soal</li>
                                            <li><i class="fas fa-check text-success"></i> Plagiarisme Check</li>
                                            <li><i class="fas fa-check text-success"></i> AI Correction (Gemini)</li>
                                            <li><i class="fas fa-check text-success"></i> Kontrol Token</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-warning bg-opacity-10 rounded p-3">
                                            <i class="fas fa-user-cog fa-2x text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="fw-bold">Operator</h5>
                                        <ul class="list-unstyled text-muted">
                                            <li><i class="fas fa-check text-success"></i> Kelola Sesi Ujian</li>
                                            <li><i class="fas fa-check text-success"></i> Assign Peserta</li>
                                            <li><i class="fas fa-check text-success"></i> Kontrol Token</li>
                                            <li><i class="fas fa-check text-success"></i> Real-time Monitoring</li>
                                            <li><i class="fas fa-check text-success"></i> Analisis & Plagiarisme</li>
                                            <li><i class="fas fa-check text-success"></i> Kontrol Fitur Ujian</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <div class="bg-info bg-opacity-10 rounded p-3">
                                            <i class="fas fa-user-graduate fa-2x text-info"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h5 class="fw-bold">Siswa</h5>
                                        <ul class="list-unstyled text-muted">
                                            <li><i class="fas fa-check text-success"></i> Kerjakan Ujian</li>
                                            <li><i class="fas fa-check text-success"></i> Submit PR</li>
                                            <li><i class="fas fa-check text-success"></i> Lihat Hasil</li>
                                            <li><i class="fas fa-check text-success"></i> Fitur Ragu-Ragu</li>
                                            <li><i class="fas fa-check text-success"></i> Auto-save</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-5">
                    <h3 class="fw-bold mb-4">Tipe Soal yang Didukung</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-list-ul fa-2x text-primary mb-2"></i>
                                    <h6>Pilihan Ganda</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-keyboard fa-2x text-primary mb-2"></i>
                                    <h6>Isian Singkat</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                                    <h6>Benar/Salah</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-link fa-2x text-primary mb-2"></i>
                                    <h6>Matching</h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                    <h6>Esai/Uraian</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-5">
                    <h3 class="fw-bold mb-4">Fitur Keamanan</h3>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <i class="fas fa-shield-alt text-primary me-2"></i>
                            <strong>Anti Contek:</strong> Deteksi tab switch, copy-paste, screenshot, multiple device, idle
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-fingerprint text-primary me-2"></i>
                            <strong>Device Fingerprinting:</strong> Identifikasi dan tracking perangkat
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-lock text-primary me-2"></i>
                            <strong>App Lock:</strong> Lock aplikasi lain saat ujian
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-search text-primary me-2"></i>
                            <strong>Plagiarisme Check:</strong> Deteksi kesamaan jawaban antar siswa
                        </li>
                        <li class="list-group-item">
                            <i class="fas fa-key text-primary me-2"></i>
                            <strong>Token System:</strong> Kontrol akses dengan token
                        </li>
                    </ul>
                </div>
                
                <?php if (is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === ROLE_ADMIN): ?>
                <div class="mb-5">
                    <h3 class="fw-bold mb-4">
                        <i class="fas fa-database text-primary me-2"></i>
                        Backup & Restore
                    </h3>
                    
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-download me-2"></i>Backup Data</h5>
                        </div>
                        <div class="card-body">
                            <form id="backupForm">
                                <div class="mb-3">
                                    <label class="form-label">Pilih Tipe Backup:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="backup_type" id="backup_database" value="database" checked>
                                        <label class="form-check-label" for="backup_database">
                                            <i class="fas fa-database text-primary me-2"></i>
                                            <strong>Database Saja</strong> - Backup hanya database (file .sql)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="backup_type" id="backup_full" value="full">
                                        <label class="form-check-label" for="backup_full">
                                            <i class="fas fa-file-archive text-success me-2"></i>
                                            <strong>Database + Source Code</strong> - Backup database dan seluruh source code (file .zip)
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary" id="backupBtn">
                                    <i class="fas fa-download me-2"></i>Buat Backup
                                </button>
                            </form>
                            <div id="backupResult" class="mt-3"></div>
                        </div>
                    </div>
                    
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-upload me-2"></i>Restore Data</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Peringatan:</strong> Restore akan mengganti semua data yang ada dengan data dari backup. 
                                Pastikan Anda sudah melakukan backup sebelum restore!
                            </div>
                            <form id="restoreForm" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="backup_file" class="form-label">Pilih File Backup:</label>
                                    <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql,.zip" required>
                                    <div class="form-text">Format yang didukung: .sql (database) atau .zip (full backup)</div>
                                </div>
                                <button type="submit" class="btn btn-warning" id="restoreBtn">
                                    <i class="fas fa-upload me-2"></i>Restore dari Backup
                                </button>
                            </form>
                            <div id="restoreResult" class="mt-3"></div>
                        </div>
                    </div>
                    
                    <div class="card border-info mt-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Daftar Backup</h5>
                        </div>
                        <div class="card-body">
                            <div id="backupList">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center">
                    <a href="<?php echo base_url('index.php'); ?>" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (!empty($backup_restore_js)) {
    $page_scripts = $backup_restore_js;
}
include __DIR__ . '/includes/footer.php'; 
?>

