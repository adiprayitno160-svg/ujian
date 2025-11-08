<?php
/**
 * Create Sesi - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

global $pdo;

$error = '';
$success = '';

// Handle POST first (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ujian = intval($_POST['id_ujian'] ?? 0);
    $nama_sesi = sanitize($_POST['nama_sesi'] ?? '');
    $waktu_mulai = $_POST['waktu_mulai'] ?? '';
    $waktu_selesai = $_POST['waktu_selesai'] ?? '';
    $durasi = intval($_POST['durasi'] ?? 0);
    $max_peserta = intval($_POST['max_peserta'] ?? 0);
    $token_required = isset($_POST['token_required']) ? 1 : 0;
    
    if (empty($nama_sesi) || !$id_ujian || empty($waktu_mulai) || empty($waktu_selesai) || $durasi <= 0) {
        $error = 'Semua field wajib harus diisi';
    } elseif (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        $error = 'Waktu selesai harus lebih besar dari waktu mulai';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sesi_ujian 
                                  (id_ujian, nama_sesi, waktu_mulai, waktu_selesai, durasi, max_peserta, token_required, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$id_ujian, $nama_sesi, $waktu_mulai, $waktu_selesai, $durasi, $max_peserta ?: null, $token_required]);
            $sesi_id = $pdo->lastInsertId();
            
            log_activity('create_sesi', 'sesi_ujian', $sesi_id);
            
            // Redirect to manage sesi (before any output)
            redirect('guru/sesi/manage.php?id=' . $sesi_id);
        } catch (PDOException $e) {
            error_log("Create sesi error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat sesi: ' . $e->getMessage();
        }
    }
}

$page_title = 'Buat Sesi Ujian';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$ujian = $ujian_id ? get_ujian($ujian_id) : null;

if ($ujian && $ujian['id_guru'] != $_SESSION['user_id']) {
    redirect('guru/ujian/list.php');
}

// Get ujian list for this guru
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id_guru = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$ujian_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Sesi Ujian</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (empty($ujian_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Belum ada ujian. <a href="<?php echo base_url('guru/ujian/create.php'); ?>">Buat ujian terlebih dahulu</a>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="id_ujian" class="form-label">Ujian <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_ujian" name="id_ujian" required>
                        <option value="">Pilih Ujian</option>
                        <?php 
                        $selected_ujian = intval($_POST['id_ujian'] ?? $ujian_id);
                        foreach ($ujian_list as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $selected_ujian == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($u['judul']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="nama_sesi" class="form-label">Nama Sesi <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="nama_sesi" name="nama_sesi" required 
                           value="<?php echo escape($_POST['nama_sesi'] ?? ''); ?>"
                           placeholder="Contoh: Sesi 1 - Pagi">
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="waktu_mulai" class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="waktu_mulai" name="waktu_mulai" required
                                   value="<?php echo escape($_POST['waktu_mulai'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="waktu_selesai" class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="waktu_selesai" name="waktu_selesai" required
                                   value="<?php echo escape($_POST['waktu_selesai'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="durasi" class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="durasi" name="durasi" required min="1" 
                           value="<?php echo escape($_POST['durasi'] ?? ''); ?>"
                           placeholder="Contoh: 90">
                </div>
                
                <div class="mb-3">
                    <label for="max_peserta" class="form-label">Max Peserta (kosongkan untuk unlimited)</label>
                    <input type="number" class="form-control" id="max_peserta" name="max_peserta" min="1"
                           value="<?php echo escape($_POST['max_peserta'] ?? ''); ?>">
                </div>
                
                <div class="mb-3 form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="token_required" name="token_required"
                           <?php echo (isset($_POST['token_required']) && $_POST['token_required']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="token_required">
                        Wajib Token untuk Akses
                    </label>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat Sesi
                    </button>
                    <a href="<?php echo base_url('guru/sesi/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

