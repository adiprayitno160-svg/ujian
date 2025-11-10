<?php
/**
 * Ujian Templates - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Kelola template ujian
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Template Ujian';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$guru_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_template') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $durasi = intval($_POST['durasi'] ?? 90);
        $acak_soal = isset($_POST['acak_soal']) ? 1 : 0;
        $acak_opsi = isset($_POST['acak_opsi']) ? 1 : 0;
        $anti_contek_enabled = isset($_POST['anti_contek_enabled']) ? 1 : 0;
        $min_submit_minutes = intval($_POST['min_submit_minutes'] ?? 0);
        $ai_correction_enabled = isset($_POST['ai_correction_enabled']) ? 0 : 0;
        
        if (empty($name)) {
            $_SESSION['error_message'] = 'Nama template tidak boleh kosong';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ujian_templates 
                                      (name, description, id_mapel, durasi, acak_soal, acak_opsi, anti_contek_enabled, min_submit_minutes, ai_correction_enabled, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $id_mapel ?: null, $durasi, $acak_soal, $acak_opsi, $anti_contek_enabled, $min_submit_minutes, $ai_correction_enabled, $guru_id]);
                $_SESSION['success_message'] = 'Template berhasil dibuat';
                redirect('guru/ujian/templates.php');
            } catch (PDOException $e) {
                error_log("Create template error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Terjadi kesalahan saat membuat template';
            }
        }
    } elseif ($action === 'delete_template') {
        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM ujian_templates WHERE id = ? AND created_by = ?");
                $stmt->execute([$template_id, $guru_id]);
                $_SESSION['success_message'] = 'Template berhasil dihapus';
                redirect('guru/ujian/templates.php');
            } catch (PDOException $e) {
                error_log("Delete template error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Terjadi kesalahan saat menghapus template';
            }
        }
    }
}

// Get templates
$templates = [];
try {
    $stmt = $pdo->prepare("SELECT t.*, m.nama_mapel 
                          FROM ujian_templates t 
                          LEFT JOIN mapel m ON t.id_mapel = m.id 
                          WHERE t.created_by = ? 
                          ORDER BY t.created_at DESC");
    $stmt->execute([$guru_id]);
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Get templates error: " . $e->getMessage());
}

// Get mapel for guru
$mapel_list = get_mapel_by_guru($guru_id);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-2">
                    <i class="fas fa-file-alt"></i> Template Ujian
                </h2>
                <p class="text-muted mb-0">Kelola template ujian untuk mempermudah pembuatan ujian</p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                <i class="fas fa-plus"></i> Buat Template
            </button>
        </div>
    </div>
</div>

<!-- Templates List -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (!empty($templates)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama Template</th>
                                <th>Deskripsi</th>
                                <th>Mata Pelajaran</th>
                                <th>Durasi</th>
                                <th>Pengaturan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                            <tr>
                                <td><strong><?php echo escape($template['name']); ?></strong></td>
                                <td><?php echo escape($template['description'] ?? '-'); ?></td>
                                <td><?php echo escape($template['nama_mapel'] ?? 'Semua'); ?></td>
                                <td><?php echo $template['durasi']; ?> menit</td>
                                <td>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($template['acak_soal']): ?>
                                        <span class="badge bg-info">Acak Soal</span>
                                        <?php endif; ?>
                                        <?php if ($template['acak_opsi']): ?>
                                        <span class="badge bg-info">Acak Opsi</span>
                                        <?php endif; ?>
                                        <?php if ($template['anti_contek_enabled']): ?>
                                        <span class="badge bg-warning">Anti Contek</span>
                                        <?php endif; ?>
                                        <?php if ($template['ai_correction_enabled']): ?>
                                        <span class="badge bg-success">AI Correction</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('guru/ujian/create.php?template_id=' . $template['id']); ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-plus"></i> Gunakan
                                    </a>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus template ini?');">
                                        <input type="hidden" name="action" value="delete_template">
                                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle"></i> Belum ada template. Buat template baru untuk mempermudah pembuatan ujian.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Template Modal -->
<div class="modal fade" id="createTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Buat Template Ujian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_template">
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Template <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Mata Pelajaran</label>
                                <select name="id_mapel" class="form-select">
                                    <option value="0">Semua</option>
                                    <?php foreach ($mapel_list as $mapel): ?>
                                    <option value="<?php echo $mapel['id']; ?>">
                                        <?php echo escape($mapel['nama_mapel']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Durasi (menit)</label>
                                <input type="number" name="durasi" class="form-control" value="90" min="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Pengaturan</label>
                        <div class="form-check">
                            <input type="checkbox" name="acak_soal" class="form-check-input" id="acak_soal" checked>
                            <label class="form-check-label" for="acak_soal">Acak Urutan Soal</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="acak_opsi" class="form-check-input" id="acak_opsi" checked>
                            <label class="form-check-label" for="acak_opsi">Acak Urutan Opsi Jawaban</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="anti_contek_enabled" class="form-check-input" id="anti_contek_enabled" checked>
                            <label class="form-check-label" for="anti_contek_enabled">Aktifkan Fitur Anti Contek</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="ai_correction_enabled" class="form-check-input" id="ai_correction_enabled">
                            <label class="form-check-label" for="ai_correction_enabled">Aktifkan AI Correction</label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Minimal Waktu Submit (menit)</label>
                        <input type="number" name="min_submit_minutes" class="form-control" value="0" min="0">
                        <small class="text-muted">0 = tidak ada batasan waktu minimal</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
