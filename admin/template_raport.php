<?php
/**
 * Template Raport - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk melihat dan mengelola template raport
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Template Raport';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle save template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_template') {
    $id = intval($_POST['id'] ?? 0);
    $nama_template = sanitize($_POST['nama_template'] ?? '');
    $html_content = $_POST['html_content'] ?? '';
    $css_content = $_POST['css_content'] ?? '';
    
    if (empty($nama_template)) {
        $error = 'Nama template harus diisi';
    } else {
        try {
            // Handle logo raport upload
            $logo_raport = null;
            if ($id > 0) {
                // Get existing logo
                $stmt = $pdo->prepare("SELECT logo_raport FROM template_raport WHERE id = ?");
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                $logo_raport = $existing['logo_raport'] ?? null;
            }
            
            if (isset($_FILES['logo_raport']) && $_FILES['logo_raport']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_file($_FILES['logo_raport'], UPLOAD_PROFILE, ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old logo if exists
                    if ($logo_raport && file_exists(UPLOAD_PROFILE . '/' . $logo_raport)) {
                        delete_file(UPLOAD_PROFILE . '/' . $logo_raport);
                    }
                    $logo_raport = $upload_result['filename'];
                }
            }
            
            if ($id > 0) {
                // Update existing template
                $stmt = $pdo->prepare("UPDATE template_raport SET nama_template = ?, html_content = ?, css_content = ?, logo_raport = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$nama_template, $html_content, $css_content, $logo_raport, $id]);
                $success = 'Template berhasil diupdate';
                log_activity('update_template_raport', 'template_raport', $id);
            } else {
                // Create new template
                $stmt = $pdo->prepare("INSERT INTO template_raport (nama_template, html_content, css_content, logo_raport, is_active, created_by) VALUES (?, ?, ?, ?, 1, ?)");
                $stmt->execute([$nama_template, $html_content, $css_content, $logo_raport, $_SESSION['user_id']]);
                $success = 'Template berhasil dibuat';
                log_activity('create_template_raport', 'template_raport', $pdo->lastInsertId());
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Handle set active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_active') {
    $id = intval($_POST['id'] ?? 0);
    try {
        // Set all to inactive first
        $pdo->exec("UPDATE template_raport SET is_active = 0");
        // Set selected to active
        $stmt = $pdo->prepare("UPDATE template_raport SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Template aktif berhasil diubah';
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Handle create default template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_default') {
    try {
        // Load default template
        $default_template_file = __DIR__ . '/../includes/template_raport_laporan_hasil_belajar.php';
        if (file_exists($default_template_file)) {
            $default_template = require $default_template_file;
            
            // Check if default template already exists
            $stmt = $pdo->prepare("SELECT id FROM template_raport WHERE nama_template = 'Raport Tengah Semester'");
            $stmt->execute();
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing template
                $stmt = $pdo->prepare("UPDATE template_raport SET html_content = ?, css_content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$default_template['html_content'], $default_template['css_content'], $existing['id']]);
                $success = 'Template default berhasil diupdate dengan versi terbaru';
                log_activity('update_template_raport', 'template_raport', $existing['id']);
            } else {
                // Create new template
                $stmt = $pdo->prepare("INSERT INTO template_raport (nama_template, html_content, css_content, logo_raport, is_active, created_by) VALUES (?, ?, ?, NULL, 0, ?)");
                $stmt->execute(['Raport Tengah Semester', $default_template['html_content'], $default_template['css_content'], $_SESSION['user_id']]);
                $success = 'Template default berhasil dibuat';
                log_activity('create_template_raport', 'template_raport', $pdo->lastInsertId());
            }
        } else {
            $error = 'File template default tidak ditemukan';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Handle update active template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_active') {
    try {
        // Load default template
        $default_template_file = __DIR__ . '/../includes/template_raport_laporan_hasil_belajar.php';
        if (file_exists($default_template_file)) {
            $default_template = require $default_template_file;
            
            // Get active template
            $stmt = $pdo->query("SELECT id FROM template_raport WHERE is_active = 1 LIMIT 1");
            $active = $stmt->fetch();
            
            if ($active) {
                // Update active template
                $stmt = $pdo->prepare("UPDATE template_raport SET html_content = ?, css_content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$default_template['html_content'], $default_template['css_content'], $active['id']]);
                $success = 'Template aktif berhasil diupdate dengan versi terbaru';
                log_activity('update_template_raport', 'template_raport', $active['id']);
            } else {
                $error = 'Tidak ada template aktif yang ditemukan';
            }
        } else {
            $error = 'File template default tidak ditemukan';
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Handle delete template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_template') {
    $id = intval($_POST['id'] ?? 0);
    try {
        // Check if template is active
        $stmt = $pdo->prepare("SELECT is_active, logo_raport FROM template_raport WHERE id = ?");
        $stmt->execute([$id]);
        $template = $stmt->fetch();
        
        if (!$template) {
            $error = 'Template tidak ditemukan';
        } elseif ($template['is_active']) {
            $error = 'Tidak dapat menghapus template yang sedang aktif. Silakan nonaktifkan terlebih dahulu.';
        } else {
            // Delete logo file if exists
            if (!empty($template['logo_raport']) && file_exists(UPLOAD_PROFILE . '/' . $template['logo_raport'])) {
                delete_file(UPLOAD_PROFILE . '/' . $template['logo_raport']);
            }
            
            // Delete template
            $stmt = $pdo->prepare("DELETE FROM template_raport WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'Template berhasil dihapus';
            log_activity('delete_template_raport', 'template_raport', $id);
        }
    } catch (PDOException $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Get all templates
$stmt = $pdo->query("SELECT t.*, u.nama as created_by_name FROM template_raport t LEFT JOIN users u ON t.created_by = u.id ORDER BY t.is_active DESC, t.created_at DESC");
$templates = $stmt->fetchAll();

// Get active template
$stmt = $pdo->query("SELECT * FROM template_raport WHERE is_active = 1 LIMIT 1");
$active_template = $stmt->fetch();

// Get template to edit
$edit_template = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM template_raport WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_template = $stmt->fetch();
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Template Raport</h2>
                <p class="text-muted mb-0">Kelola template raport untuk mencetak raport siswa</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (!$edit_template): ?>
                    <?php if ($active_template): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin mengupdate template aktif dengan versi terbaru dari file?');">
                            <input type="hidden" name="action" value="update_active">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-sync-alt"></i> Update Template Aktif
                            </button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin membuat/update template default \"Raport Tengah Semester\" dengan versi terbaru? Template yang sudah ada akan diupdate.');">
                        <input type="hidden" name="action" value="create_default">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-magic"></i> Buat/Update Template Default
                        </button>
                    </form>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                        <i class="fas fa-plus"></i> Buat Template Baru
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<?php if ($edit_template): ?>
    <!-- Edit Template Form -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Template: <?php echo escape($edit_template['nama_template']); ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" id="templateForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="id" value="<?php echo $edit_template['id']; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nama_template" value="<?php echo escape($edit_template['nama_template']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Logo Raport</label>
                    <input type="file" class="form-control" name="logo_raport" accept="image/*">
                    <small class="text-muted">Logo khusus untuk raport. Format: JPG, PNG, GIF, WebP. Max: 2MB</small>
                    <?php if (!empty($edit_template['logo_raport'])): ?>
                        <div class="mt-2">
                            <img src="<?php echo asset_url('uploads/profile/' . $edit_template['logo_raport']); ?>" alt="Logo Raport" height="100" class="img-thumbnail">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">HTML Content <span class="text-danger">*</span></label>
                    <small class="text-muted d-block mb-2">
                        <strong>Variabel yang tersedia:</strong><br>
                        {{LOGO_RAPORT}}, {{LOGO_KOP_SURAT}}, {{LOGO_SEKOLAH}}, {{PEMERINTAH_KABUPATEN}}, {{DINAS_PENDIDIKAN}}, {{NAMA_SEKOLAH}}, {{NSS}}, {{NPSN}}, {{ALAMAT_SEKOLAH}}, {{NO_TELP_SEKOLAH}}, {{KODE_POS}}, {{NAMA_SISWA}}, {{NIS}}, {{KELAS}}, {{TAHUN_PELAJARAN}}, {{SEMESTER}}, {{NAMA_WALI_KELAS}}, {{NIP_WALI_KELAS}}, {{TABEL_NILAI}}
                    </small>
                    <textarea class="form-control font-monospace" name="html_content" rows="15" required><?php echo escape($edit_template['html_content']); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">CSS Content</label>
                    <textarea class="form-control font-monospace" name="css_content" rows="10"><?php echo escape($edit_template['css_content']); ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Template
                    </button>
                    <a href="<?php echo base_url('admin/template_raport.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="button" class="btn btn-info" onclick="previewTemplate()">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Template List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-file-alt"></i> Daftar Template</h5>
        </div>
        <div class="card-body">
            <?php if (empty($templates)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Belum ada template. Silakan buat template baru atau buat template default.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama Template</th>
                                <th>Status</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal Dibuat</th>
                                <th>Terakhir Diupdate</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td><strong><?php echo escape($template['nama_template']); ?></strong></td>
                                    <td>
                                        <?php if ($template['is_active']): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tidak Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($template['created_by_name'] ?? '-'); ?></td>
                                    <td><?php echo format_date($template['created_at'], 'd/m/Y H:i'); ?></td>
                                    <td><?php echo format_date($template['updated_at'], 'd/m/Y H:i'); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="<?php echo base_url('admin/template_raport.php?edit=' . $template['id']); ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <?php if (!$template['is_active']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin mengaktifkan template ini?');">
                                                    <input type="hidden" name="action" value="set_active">
                                                    <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Aktifkan
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus template ini? Tindakan ini tidak dapat dibatalkan.');">
                                                    <input type="hidden" name="action" value="delete_template">
                                                    <input type="hidden" name="id" value="<?php echo $template['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i> Hapus
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Buat Template Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_template">
                    <div class="mb-3">
                        <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_template" required placeholder="Contoh: Template Raport Semester">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">HTML Content <span class="text-danger">*</span></label>
                        <small class="text-muted d-block mb-2">
                            <strong>Variabel yang tersedia:</strong><br>
                            {{LOGO_RAPORT}}, {{LOGO_KOP_SURAT}}, {{LOGO_SEKOLAH}}, {{PEMERINTAH_KABUPATEN}}, {{DINAS_PENDIDIKAN}}, {{NAMA_SEKOLAH}}, {{NSS}}, {{NPSN}}, {{ALAMAT_SEKOLAH}}, {{NO_TELP_SEKOLAH}}, {{KODE_POS}}, {{NAMA_SISWA}}, {{NIS}}, {{KELAS}}, {{TAHUN_PELAJARAN}}, {{SEMESTER}}, {{NAMA_WALI_KELAS}}, {{NIP_WALI_KELAS}}, {{TABEL_NILAI}}
                        </small>
                        <textarea class="form-control font-monospace" name="html_content" rows="15" required><?php echo $active_template ? escape($active_template['html_content']) : ''; ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">CSS Content</label>
                        <textarea class="form-control font-monospace" name="css_content" rows="10"><?php echo $active_template ? escape($active_template['css_content']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function previewTemplate() {
    const form = document.getElementById('templateForm');
    const formData = new FormData(form);
    const html = formData.get('html_content');
    const css = formData.get('css_content');
    
    // Replace variables with sample data
    let previewHtml = html
        .replace(/\{\{LOGO_KOP_SURAT\}\}/g, '<img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjQwIiB5PSI0NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5MT0dPPC90ZXh0Pgo8L3N2Zz4K" alt="Logo" class="logo-kop-surat" />')
        .replace(/\{\{LOGO_SEKOLAH\}\}/g, '<img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjRjBGMEYwIi8+Cjx0ZXh0IHg9IjQwIiB5PSI0NSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIj5MT0dPPC90ZXh0Pgo8L3N2Zz4K" alt="Logo" class="logo-kop-surat" />')
        .replace(/\{\{PEMERINTAH_KABUPATEN\}\}/g, 'PEMERINTAH KABUPATEN TULUNGAGUNG')
        .replace(/\{\{DINAS_PENDIDIKAN\}\}/g, 'DINAS PENDIDIKAN')
        .replace(/\{\{NAMA_SEKOLAH\}\}/g, 'SEKOLAH MENENGAH PERTAMA NEGERI 1 BOYOLANGU')
        .replace(/\{\{NSS\}\}/g, '201051602053')
        .replace(/\{\{NPSN\}\}/g, '20515534')
        .replace(/\{\{ALAMAT_SEKOLAH\}\}/g, 'Jl. Raya Boyolangu Tulungagung')
        .replace(/\{\{NO_TELP_SEKOLAH\}\}/g, '0355 - 324146')
        .replace(/\{\{KODE_POS\}\}/g, '66235')
        .replace(/\{\{NAMA_SISWA\}\}/g, 'Nama Siswa')
        .replace(/\{\{NIS\}\}/g, '12345')
        .replace(/\{\{KELAS\}\}/g, 'VII A')
        .replace(/\{\{TAHUN_PELAJARAN\}\}/g, '2025/2026')
        .replace(/\{\{SEMESTER\}\}/g, '1')
        .replace(/\{\{TABEL_NILAI\}\}/g, '<tr><td>1</td><td>Pendidikan Agama Islam dan Budi Pekerti</td><td>85.00</td></tr><tr><td>2</td><td>Pendidikan Pancasila</td><td>90.00</td></tr><tr><td>3</td><td>Bahasa Indonesia</td><td>88.00</td></tr>');
    
    // Create preview window
    const previewWindow = window.open('', '_blank', 'width=800,height=600');
    previewWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Preview Template</title>
            <style>${css}</style>
        </head>
        <body>
            ${previewHtml}
        </body>
        </html>
    `);
    previewWindow.document.close();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

