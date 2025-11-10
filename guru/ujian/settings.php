<?php
/**
 * Settings Ujian - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Pengaturan Ujian';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$ujian_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acak_soal = isset($_POST['acak_soal']) ? 1 : 0;
    $acak_opsi = isset($_POST['acak_opsi']) ? 1 : 0;
    $anti_contek_enabled = isset($_POST['anti_contek']) ? 1 : 0;
    $min_submit_minutes = intval($_POST['min_submit_minutes'] ?? 0);
    $ai_correction_enabled = isset($_POST['ai_correction']) ? 1 : 0;
    $show_review_mode = isset($_POST['show_review_mode']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE ujian SET 
                              acak_soal = ?, acak_opsi = ?, anti_contek_enabled = ?, 
                              min_submit_minutes = ?, ai_correction_enabled = ?, show_review_mode = ?
                              WHERE id = ?");
        $stmt->execute([$acak_soal, $acak_opsi, $anti_contek_enabled, $min_submit_minutes, $ai_correction_enabled, $show_review_mode, $ujian_id]);
        
        $success = 'Pengaturan berhasil disimpan';
        log_activity('update_ujian_settings', 'ujian', $ujian_id);
        
        // Refresh
        $stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ?");
        $stmt->execute([$ujian_id]);
        $ujian = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Update ujian settings error: " . $e->getMessage());
        $error = 'Terjadi kesalahan saat menyimpan pengaturan';
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Pengaturan Ujian: <?php echo escape($ujian['judul']); ?></h2>
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

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="mb-4">
                <h5>Pengaturan Soal</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="acak_soal" name="acak_soal" 
                           <?php echo ($ujian['acak_soal'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="acak_soal">
                        Acak Urutan Soal
                    </label>
                    <small class="d-block text-muted">Soal akan diacak untuk setiap siswa</small>
                </div>
                
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="acak_opsi" name="acak_opsi" 
                           <?php echo ($ujian['acak_opsi'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="acak_opsi">
                        Acak Urutan Opsi Jawaban
                    </label>
                    <small class="d-block text-muted">Opsi jawaban akan diacak untuk setiap siswa</small>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Pengaturan Keamanan</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="anti_contek" name="anti_contek" 
                           <?php echo ($ujian['anti_contek_enabled'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="anti_contek">
                        Aktifkan Fitur Anti Contek
                    </label>
                    <small class="d-block text-muted">Mendeteksi tab switching, copy-paste, dan aktivitas mencurigakan</small>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Pengaturan Waktu</h5>
                <div class="mb-3">
                    <label for="min_submit_minutes" class="form-label">
                        Minimum Waktu Submit (menit)
                    </label>
                    <input type="number" class="form-control" id="min_submit_minutes" 
                           name="min_submit_minutes" value="<?php echo $ujian['min_submit_minutes'] ?? DEFAULT_MIN_SUBMIT_MINUTES; ?>" 
                           min="0" max="60">
                    <small class="text-muted">Siswa harus menunggu minimal X menit setelah mulai ujian sebelum bisa submit. Default: <?php echo DEFAULT_MIN_SUBMIT_MINUTES; ?> menit (0 = tidak ada batasan)</small>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Pengaturan Koreksi</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="ai_correction" name="ai_correction" 
                           <?php echo ($ujian['ai_correction_enabled'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="ai_correction">
                        Aktifkan Koreksi AI untuk Soal Esai/Uraian
                    </label>
                    <small class="d-block text-muted">Menggunakan AI (Gemini) untuk koreksi otomatis soal esai, uraian singkat, rangkuman, cerita, dll</small>
                </div>
            </div>
            
            <div class="mb-4">
                <h5>Pengaturan Review</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="show_review_mode" name="show_review_mode" 
                           <?php echo ($ujian['show_review_mode'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="show_review_mode">
                        Tampilkan Mode Review Sebelum Submit
                    </label>
                    <small class="d-block text-muted">Siswa dapat melihat dan mereview semua jawaban sebelum submit ujian</small>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Pengaturan
                </button>
                <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $ujian_id); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
