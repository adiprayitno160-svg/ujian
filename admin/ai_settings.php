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
    $api_key_input = sanitize($_POST['api_key'] ?? '');
    $model = sanitize($_POST['model'] ?? 'gemini-1.5-flash');
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
$model = $ai_settings['model'] ?? 'gemini-1.5-flash';
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
                        <label for="api_key" class="form-label">Gemini API Key</label>
                        <input type="text" class="form-control" id="api_key" name="api_key" 
                               value="" 
                               placeholder="<?php echo !empty($api_key) ? 'API key sudah diatur (kosongkan untuk tidak mengubah)' : 'Masukkan Gemini API Key'; ?>">
                        <?php if (!empty($api_key)): ?>
                            <input type="hidden" name="api_key_set" value="1">
                        <?php endif; ?>
                        <small class="text-muted">
                            Dapatkan API key dari 
                            <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.
                            API key ini akan digunakan untuk koreksi otomatis semua ujian yang mengaktifkan AI correction.
                            <?php if (!empty($api_key)): ?>
                                <br><span class="text-success"><i class="fas fa-check-circle"></i> API key sudah diatur</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="model" class="form-label">Model Gemini</label>
                        <select class="form-select" id="model" name="model">
                            <option value="gemini-1.5-flash" <?php echo $model === 'gemini-1.5-flash' ? 'selected' : ''; ?>>
                                Gemini 1.5 Flash (Cepat, Recommended)
                            </option>
                            <option value="gemini-1.5-pro" <?php echo $model === 'gemini-1.5-pro' ? 'selected' : ''; ?>>
                                Gemini 1.5 Pro (Lebih Akurat)
                            </option>
                            <option value="gemini-pro" <?php echo $model === 'gemini-pro' ? 'selected' : ''; ?>>
                                Gemini Pro (Legacy)
                            </option>
                        </select>
                        <small class="text-muted">
                            Gemini 1.5 Flash direkomendasikan karena lebih cepat dan efisien untuk koreksi ujian.
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
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan Pengaturan
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-question-circle"></i> Panduan</h5>
            </div>
            <div class="card-body">
                <h6>Cara Mendapatkan API Key:</h6>
                <ol>
                    <li>Kunjungi <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a></li>
                    <li>Login dengan akun Google</li>
                    <li>Klik "Create API Key"</li>
                    <li>Salin API key yang diberikan</li>
                    <li>Paste di form ini</li>
                </ol>
                
                <hr>
                
                <h6>Penggunaan:</h6>
                <ul>
                    <li>Setelah API key diatur, guru dapat mengaktifkan AI correction di pengaturan ujian</li>
                    <li>Soal esai, uraian, rangkuman, cerita akan dikoreksi otomatis oleh AI</li>
                    <li>Hasil koreksi dapat dilihat di detail nilai</li>
                </ul>
                
                <hr>
                
                <h6>Catatan:</h6>
                <ul class="mb-0">
                    <li>API key disimpan di database dengan aman</li>
                    <li>Jangan bagikan API key kepada pihak yang tidak berwenang</li>
                    <li>Penggunaan API akan dikenakan biaya sesuai dengan quota Google</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

