<?php
/**
 * Pengaturan AI (Gemini) - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Pengaturan AI (Gemini)';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Get current settings first
$stmt = $pdo->prepare("SELECT * FROM ai_settings WHERE provider = 'gemini'");
$stmt->execute();
$ai_settings = $stmt->fetch();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    $api_key_input = trim(sanitize($_POST['api_key'] ?? ''));
    $model = sanitize($_POST['model'] ?? 'gemini-2.0-flash');
    $temperature = floatval($_POST['temperature'] ?? 0.70);
    $max_tokens = intval($_POST['max_tokens'] ?? 2000);
    
    // If API key input is empty, keep existing API key (user doesn't want to change it)
    if (empty($api_key_input) && $ai_settings && !empty($ai_settings['api_key'])) {
        $api_key = $ai_settings['api_key'];
    } else {
        $api_key = $api_key_input;
    }
    
    // Validate - only require API key if enabled
    if ($enabled && empty($api_key)) {
        $error = 'API key harus diisi jika AI correction diaktifkan';
    }
    
    if (empty($error)) {
        try {
            // Check if record exists
            $existing = $ai_settings;
            
            if ($existing) {
                // Update
                $stmt = $pdo->prepare("UPDATE ai_settings SET 
                                      api_key = ?, enabled = ?, model = ?, temperature = ?, max_tokens = ?, updated_at = NOW()
                                      WHERE provider = 'gemini'");
                $stmt->execute([$api_key, $enabled, $model, $temperature, $max_tokens]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO ai_settings (provider, api_key, enabled, model, temperature, max_tokens) 
                                      VALUES ('gemini', ?, ?, ?, ?, ?)");
                $stmt->execute([$api_key, $enabled, $model, $temperature, $max_tokens]);
            }
            
            $success = 'Pengaturan AI berhasil disimpan';
            log_activity('update_ai_settings', 'ai_settings', $existing['id'] ?? null);
            
            // Reload settings after successful save
            $stmt = $pdo->prepare("SELECT * FROM ai_settings WHERE provider = 'gemini'");
            $stmt->execute();
            $ai_settings = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Update AI settings error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat menyimpan: ' . $e->getMessage();
        }
    }
}

// Reload settings if not already loaded from POST
if (!isset($ai_settings) || ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error))) {
    $stmt = $pdo->prepare("SELECT * FROM ai_settings WHERE provider = 'gemini'");
    $stmt->execute();
    $ai_settings = $stmt->fetch();
}

// Default values (AI correction enabled by default)
$enabled = $ai_settings['enabled'] ?? 1; // Default enabled
$api_key = $ai_settings['api_key'] ?? '';
$model = $ai_settings['model'] ?? 'gemini-2.0-flash'; // Default to gemini-2.0-flash (proven to work)
$temperature = $ai_settings['temperature'] ?? 0.70;
$max_tokens = $ai_settings['max_tokens'] ?? 2000;
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
        <h3 class="fw-bold"><i class="fas fa-robot"></i> Pengaturan AI (Gemini)</h3>
        <p class="text-muted">Konfigurasi Google Gemini API untuk koreksi otomatis ujian (esai, uraian, rangkuman, cerita, dll)</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-cog"></i> Konfigurasi Gemini API</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enabled" 
                                   name="enabled" <?php echo $enabled ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="enabled">
                                <strong>Aktifkan AI Correction</strong>
                            </label>
                        </div>
                        <small class="text-muted">Aktifkan untuk menggunakan Gemini API untuk koreksi otomatis soal esai, uraian, rangkuman, cerita, dll</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_key" class="form-label">Gemini API Key <span class="text-danger">*</span></label>
                        <?php if (!empty($api_key)): ?>
                            <div class="alert alert-info mb-2">
                                <i class="fas fa-info-circle"></i> <strong>API key sudah diatur.</strong>
                                <br><small>Preview: <code><?php echo substr($api_key, 0, 10) . '...' . substr($api_key, -4); ?></code></small>
                                <br><small>Untuk mengganti API key, masukkan API key baru di bawah ini.</small>
                            </div>
                        <?php endif; ?>
                        <input type="text" class="form-control" id="api_key" name="api_key" 
                               value="" 
                               placeholder="<?php echo !empty($api_key) ? 'Masukkan API key baru untuk mengganti (kosongkan untuk tidak mengubah)' : 'Masukkan Gemini API Key (contoh: AIzaSyB6szMSV7Iq3r7oDXzxgu1NOST_sIE-2LI)'; ?>"
                               autocomplete="off" <?php echo empty($api_key) ? 'required' : ''; ?>>
                        <?php if (!empty($api_key)): ?>
                            <input type="hidden" name="api_key_set" value="1">
                        <?php endif; ?>
                        <small class="text-muted">
                            <strong>Langkah-langkah:</strong>
                            <ol class="mb-2">
                                <li>Dapatkan API key dari <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                                <li>Salin API key yang diberikan (contoh: <code>AIzaSyB6szMSV7Iq3r7oDXzxgu1NOST_sIE-2LI</code>)</li>
                                <li>Paste API key di form ini</li>
                                <li>Pilih model yang tersedia (direkomendasikan: <strong>Gemini Pro</strong>)</li>
                                <li>Centang "Aktifkan AI Correction"</li>
                                <li>Klik "Simpan Pengaturan"</li>
                                <li>Test API key menggunakan tombol "Test API Key"</li>
                            </ol>
                            <strong>✓ Bisa menggunakan akun Google reguler (gmail.com) atau akun belajar.id</strong>
                            <br><strong>✓ Free tier tersedia untuk semua akun Google (gratis, tidak perlu kartu kredit)</strong>
                            <br><strong>Format:</strong> API key biasanya dimulai dengan "AIzaSy" (39 karakter).
                            <br><strong>Free Tier:</strong> 60 requests/menit, 1,500 requests/hari (cukup untuk penggunaan pendidikan)
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="model" class="form-label">Model Gemini</label>
                        <select class="form-select" id="model" name="model">
                            <option value="gemini-2.0-flash" <?php echo $model === 'gemini-2.0-flash' ? 'selected' : ''; ?>>
                                Gemini 2.0 Flash (Recommended - Proven to Work)
                            </option>
                            <option value="gemini-flash-latest" <?php echo $model === 'gemini-flash-latest' ? 'selected' : ''; ?>>
                                Gemini Flash Latest (Latest Version)
                            </option>
                            <option value="gemini-2.0-flash-001" <?php echo $model === 'gemini-2.0-flash-001' ? 'selected' : ''; ?>>
                                Gemini 2.0 Flash 001 (Alternative)
                            </option>
                            <option value="gemini-1.5-flash" <?php echo $model === 'gemini-1.5-flash' ? 'selected' : ''; ?>>
                                Gemini 1.5 Flash (Legacy)
                            </option>
                            <option value="gemini-1.5-pro" <?php echo $model === 'gemini-1.5-pro' ? 'selected' : ''; ?>>
                                Gemini 1.5 Pro (Legacy)
                            </option>
                            <option value="gemini-pro" <?php echo $model === 'gemini-pro' ? 'selected' : ''; ?>>
                                Gemini Pro (Legacy - May Not Work)
                            </option>
                        </select>
                        <small class="text-muted">
                            <strong>Gemini 2.0 Flash</strong> direkomendasikan karena sudah terbukti berfungsi dengan API key saat ini.
                            Jika terjadi error 404, sistem akan otomatis mencoba model alternatif.
                        </small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="temperature" class="form-label">Temperature</label>
                                <input type="number" class="form-control" id="temperature" name="temperature" 
                                       value="<?php echo $temperature; ?>" 
                                       min="0" max="1" step="0.01">
                                <small class="text-muted">Kontrol kreativitas AI (0.0-1.0). Default: 0.70</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_tokens" class="form-label">Max Tokens</label>
                                <input type="number" class="form-control" id="max_tokens" name="max_tokens" 
                                       value="<?php echo $max_tokens; ?>" 
                                       min="100" max="8000" step="100">
                                <small class="text-muted">Maksimal panjang respons (100-8000). Default: 2000</small>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Informasi:</h6>
                        <ul class="mb-0">
                            <li>Pengaturan ini bersifat <strong>global</strong> untuk semua ujian</li>
                            <li>Guru dapat mengaktifkan/nonaktifkan AI correction per ujian di pengaturan ujian</li>
                            <li>AI correction mendukung: Esai, Uraian Singkat, Rangkuman, Cerita, Narasi</li>
                            <li>API key akan digunakan untuk semua fitur AI (koreksi ujian dan OCR dokumen jika tidak dikonfigurasi terpisah)</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan Pengaturan
                        </button>
                        <a href="<?php echo base_url('admin/test_ai_api.php'); ?>" class="btn btn-success">
                            <i class="fas fa-vial"></i> Test API Key
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-vial"></i> Test API Key</h5>
            </div>
            <div class="card-body">
                <p>Test apakah API key yang sudah diinputkan berfungsi dengan normal.</p>
                <a href="<?php echo base_url('admin/test_ai_api.php'); ?>" class="btn btn-success w-100">
                    <i class="fas fa-vial"></i> Test API Key Sekarang
                </a>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-question-circle"></i> Panduan</h5>
            </div>
            <div class="card-body">
                <h6>Cara Mendapatkan API Key:</h6>
                <ol>
                    <li>Kunjungi <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                    <li>Login dengan akun Google (bisa menggunakan akun belajar.id)</li>
                    <li>Klik "Create API Key"</li>
                    <li>Salin API key yang diberikan</li>
                    <li>Paste di form ini</li>
                </ol>
                
                <hr>
                
                <h6>Menggunakan Akun Belajar.id:</h6>
                <div class="alert alert-info">
                    <strong>✓ Ya, Bisa Menggunakan Akun Belajar.id!</strong>
                    <ul class="mb-0 mt-2">
                        <li>Akun belajar.id adalah akun Google Workspace untuk pendidikan</li>
                        <li>Bisa digunakan untuk membuat API key Gemini (gratis)</li>
                        <li>Google Gemini API memiliki <strong>free tier</strong> yang cukup untuk penggunaan pendidikan</li>
                        <li>Free tier biasanya: 60 requests per menit, 1,500 requests per hari</li>
                    </ul>
                </div>
                
                <p><strong>Cara menggunakan akun belajar.id:</strong></p>
                <ol>
                    <li>Login ke <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a> dengan akun belajar.id</li>
                    <li>Buat API key baru</li>
                    <li>API key akan otomatis menggunakan free tier</li>
                    <li>Salin API key dan paste di form ini</li>
                </ol>
                
                <hr>
                
                <h6>Penggunaan:</h6>
                <ul>
                    <li>Setelah API key diatur, guru dapat mengaktifkan AI correction di pengaturan ujian</li>
                    <li>Soal esai, uraian, rangkuman, cerita akan dikoreksi otomatis oleh AI</li>
                    <li>Hasil koreksi dapat dilihat di detail nilai</li>
                </ul>
                
                <hr>
                
                <h6>Catatan Penting:</h6>
                <ul class="mb-0">
                    <li><strong>Akun Google Reguler:</strong> Bisa digunakan untuk membuat API key (gratis dengan free tier)</li>
                    <li><strong>Akun Belajar.id:</strong> Juga bisa digunakan (sama-sama gratis dengan free tier)</li>
                    <li><strong>Free Tier:</strong> Google Gemini API memberikan free tier untuk semua akun Google</li>
                    <li><strong>Quota:</strong> Free tier: 60 requests/menit, 1,500 requests/hari</li>
                    <li><strong>Tidak Perlu Kartu Kredit:</strong> Free tier tidak memerlukan kartu kredit</li>
                    <li><strong>Keamanan:</strong> API key disimpan di database dengan aman</li>
                    <li><strong>Jangan bagikan:</strong> API key kepada pihak yang tidak berwenang</li>
                    <li><strong>Monitoring:</strong> Pantau penggunaan quota di Google AI Studio</li>
                </ul>
                
                <hr>
                
                <h6>Info Free Tier Google Gemini API:</h6>
                <ul class="mb-0">
                    <li>✓ <strong>Gratis</strong> untuk semua akun Google (reguler atau belajar.id)</li>
                    <li>✓ <strong>Tidak perlu kartu kredit</strong> untuk free tier</li>
                    <li>✓ Cocok untuk ujian dengan jumlah siswa terbatas</li>
                    <li>✓ Quota: 60 requests/menit, 1,500 requests/hari</li>
                    <li>✓ Quota reset setiap hari</li>
                    <li>⚠ Jika quota habis, perlu upgrade ke paid plan atau buat API key baru</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

