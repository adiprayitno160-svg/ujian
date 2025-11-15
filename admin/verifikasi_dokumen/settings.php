<?php
/**
 * Pengaturan Verifikasi Dokumen - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Pengaturan Verifikasi Dokumen';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gemini_enabled = isset($_POST['gemini_enabled']) ? 1 : 0;
    $gemini_api_key = sanitize($_POST['gemini_api_key'] ?? '');
    $gemini_model = sanitize($_POST['gemini_model'] ?? 'gemini-2.0-flash');
    $deadline = sanitize($_POST['deadline_verifikasi'] ?? '');
    $menu_aktif_default = isset($_POST['menu_aktif_default']) ? 1 : 0;
    
    try {
        // Update settings
        $settings = [
            'gemini_enabled' => $gemini_enabled,
            'gemini_api_key' => $gemini_api_key,
            'gemini_model' => $gemini_model,
            'deadline_verifikasi' => $deadline,
            'menu_aktif_default' => $menu_aktif_default
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO verifikasi_settings (setting_key, setting_value, updated_at) 
                                  VALUES (?, ?, NOW())
                                  ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->execute([$key, $value, $value]);
        }
        
        $success = 'Pengaturan berhasil disimpan';
        log_activity('update_verifikasi_settings', 'verifikasi_settings', null);
    } catch (PDOException $e) {
        error_log("Update verifikasi settings error: " . $e->getMessage());
        $error = 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage();
    }
}

// Get current settings
$gemini_enabled = get_verifikasi_setting('gemini_enabled') == '1';
$gemini_api_key = get_verifikasi_setting('gemini_api_key') ?? '';
$gemini_model = get_verifikasi_setting('gemini_model') ?? 'gemini-2.0-flash';
$deadline = get_verifikasi_setting('deadline_verifikasi') ?? '';
$menu_aktif_default = get_verifikasi_setting('menu_aktif_default') == '1';
?>

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

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold">Pengaturan Verifikasi Dokumen</h3>
        <p class="text-muted">Konfigurasi sistem verifikasi dokumen untuk siswa kelas IX</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cog"></i> Konfigurasi Gemini OCR</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="gemini_enabled" 
                                   name="gemini_enabled" <?php echo $gemini_enabled ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="gemini_enabled">
                                Aktifkan Gemini OCR
                            </label>
                        </div>
                        <small class="text-muted">Aktifkan untuk menggunakan Gemini API untuk scan dokumen otomatis</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gemini_api_key" class="form-label">Gemini API Key</label>
                        <input type="text" class="form-control" id="gemini_api_key" name="gemini_api_key" 
                               value="<?php echo escape($gemini_api_key); ?>" 
                               placeholder="Masukkan Gemini API Key">
                        <small class="text-muted">
                            Dapatkan API key dari 
                            <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>
                            <br><strong>✓ Bisa menggunakan akun Google reguler (gmail.com) atau akun belajar.id</strong>
                            <br><strong>✓ Free tier tersedia untuk semua akun Google (gratis, tidak perlu kartu kredit)</strong>
                            <br><strong>Free Tier:</strong> 60 requests/menit, 1,500 requests/hari
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <a href="<?php echo base_url('admin/test_ai_api.php'); ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-vial"></i> Test API Key
                        </a>
                        <small class="text-muted d-block mt-2">
                            Klik tombol di atas untuk test apakah API key sudah berfungsi dengan normal
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="gemini_model" class="form-label">Model Gemini</label>
                        <select class="form-select" id="gemini_model" name="gemini_model">
                            <option value="gemini-2.0-flash" <?php echo $gemini_model === 'gemini-2.0-flash' ? 'selected' : ''; ?>>
                                Gemini 2.0 Flash (Recommended)
                            </option>
                            <option value="gemini-flash-latest" <?php echo $gemini_model === 'gemini-flash-latest' ? 'selected' : ''; ?>>
                                Gemini Flash Latest
                            </option>
                            <option value="gemini-2.0-flash-001" <?php echo $gemini_model === 'gemini-2.0-flash-001' ? 'selected' : ''; ?>>
                                Gemini 2.0 Flash 001
                            </option>
                            <option value="gemini-1.5-flash" <?php echo $gemini_model === 'gemini-1.5-flash' ? 'selected' : ''; ?>>
                                Gemini 1.5 Flash (Legacy)
                            </option>
                            <option value="gemini-1.5-pro" <?php echo $gemini_model === 'gemini-1.5-pro' ? 'selected' : ''; ?>>
                                Gemini 1.5 Pro (Legacy)
                            </option>
                            <option value="gemini-pro" <?php echo $gemini_model === 'gemini-pro' ? 'selected' : ''; ?>>
                                Gemini Pro (Legacy - May Not Work)
                            </option>
                        </select>
                        <small class="text-muted">
                            <strong>Gemini 2.0 Flash</strong> direkomendasikan karena sudah terbukti berfungsi dengan API key saat ini dan mendukung vision (OCR).
                            Jika terjadi error 404, sistem akan otomatis mencoba model alternatif.
                        </small>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3">Pengaturan Verifikasi</h6>
                    
                    <div class="mb-3">
                        <label for="deadline_verifikasi" class="form-label">Deadline Upload Dokumen</label>
                        <input type="date" class="form-control" id="deadline_verifikasi" 
                               name="deadline_verifikasi" value="<?php echo escape($deadline); ?>">
                        <small class="text-muted">Batas waktu upload dokumen verifikasi (kosongkan jika tidak ada deadline)</small>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="menu_aktif_default" 
                                   name="menu_aktif_default" <?php echo $menu_aktif_default ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="menu_aktif_default">
                                Menu Verifikasi Aktif Secara Default
                            </label>
                        </div>
                        <small class="text-muted">Menu verifikasi akan tampil untuk semua siswa kelas IX secara default</small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi</h5>
            </div>
            <div class="card-body">
                <h6>Gemini OCR</h6>
                <p class="small text-muted">
                    Sistem menggunakan Google Gemini Vision API untuk melakukan OCR (Optical Character Recognition) 
                    pada dokumen yang diupload siswa. Sistem akan mengekstrak nama siswa, nama ayah, dan nama ibu 
                    dari dokumen secara otomatis.
                </p>
                
                <h6 class="mt-3">Validasi Strict</h6>
                <p class="small text-muted">
                    Sistem menggunakan validasi strict (exact match) untuk memastikan nama di semua dokumen 
                    sama persis. Tidak ada toleransi untuk typo atau alias.
                </p>
                
                <h6 class="mt-3">Data Residu</h6>
                <p class="small text-muted">
                    Siswa yang setelah upload ulang (maksimal 1x) masih memiliki nama yang tidak sesuai 
                    akan masuk ke data residu.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



