<?php
/**
 * List Available Gemini Models - Admin
 * Script untuk melihat model Gemini yang tersedia untuk API key
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/ai_config.php';

require_role('admin');
check_session_timeout();

$page_title = 'List Available Gemini Models';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$api_key = get_ai_api_key();
$available_models = [];
$error = null;

if (empty($api_key)) {
    $error = 'API key tidak ditemukan. Silakan isi API key di Pengaturan AI terlebih dahulu.';
} else {
    // Try to list models using v1beta API
    $list_url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $api_key;
    
    $ch = curl_init($list_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        if (isset($result['models']) && is_array($result['models'])) {
            foreach ($result['models'] as $model) {
                $model_name = $model['name'] ?? '';
                // Extract model name from full path (e.g., "models/gemini-pro" -> "gemini-pro")
                if (strpos($model_name, 'models/') === 0) {
                    $model_name = substr($model_name, 7);
                }
                
                // Check if model supports generateContent
                $supported_methods = $model['supportedGenerationMethods'] ?? [];
                if (in_array('generateContent', $supported_methods)) {
                    $available_models[] = [
                        'name' => $model_name,
                        'display_name' => $model['displayName'] ?? $model_name,
                        'description' => $model['description'] ?? '',
                        'supported_methods' => $supported_methods
                    ];
                }
            }
        }
    } else {
        $error = 'Gagal mengambil daftar model: HTTP ' . $http_code;
        if ($response) {
            $error_result = json_decode($response, true);
            if (isset($error_result['error']['message'])) {
                $error .= ' - ' . $error_result['error']['message'];
            }
        }
    }
}

// Test each common model (including newer models)
$test_models = [
    'gemini-1.5-flash',
    'gemini-1.5-pro', 
    'gemini-pro',
    'gemini-2.0-flash-exp',
    'gemini-1.5-flash-8b',
    'gemini-1.5-pro-latest'
];
$test_results = [];

if (!empty($api_key)) {
    foreach ($test_models as $test_model) {
        // Test with v1beta
        $test_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $test_model . ':generateContent?key=' . $api_key;
        
        $test_data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Test']
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 10
            ]
        ];
        
        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        curl_exec($ch);
        $test_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $test_results[$test_model] = [
            'v1beta' => $test_http_code === 200 ? 'OK' : 'FAIL (' . $test_http_code . ')'
        ];
        
        // Also test v1
        $test_url_v1 = 'https://generativelanguage.googleapis.com/v1/models/' . $test_model . ':generateContent?key=' . $api_key;
        $ch = curl_init($test_url_v1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        curl_exec($ch);
        $test_http_code_v1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $test_results[$test_model]['v1'] = $test_http_code_v1 === 200 ? 'OK' : 'FAIL (' . $test_http_code_v1 . ')';
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold"><i class="fas fa-list"></i> Daftar Model Gemini yang Tersedia</h3>
        <p class="text-muted">Lihat model Gemini yang tersedia untuk API key Anda</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($available_models)): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Model yang Tersedia (dari ListModels API)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Model Name</th>
                            <th>Display Name</th>
                            <th>Description</th>
                            <th>Supported Methods</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_models as $model): ?>
                            <tr>
                                <td><code><?php echo escape($model['name']); ?></code></td>
                                <td><?php echo escape($model['display_name']); ?></td>
                                <td><?php echo escape($model['description']); ?></td>
                                <td>
                                    <?php 
                                    foreach ($model['supported_methods'] as $method) {
                                        echo '<span class="badge bg-info me-1">' . escape($method) . '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-vial"></i> Test Model Umum</h5>
    </div>
    <div class="card-body">
        <p>Test apakah model umum tersedia dan berfungsi:</p>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Model</th>
                        <th>API v1beta</th>
                        <th>API v1</th>
                        <th>Rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $working_model = null;
                    foreach ($test_results as $model_name => $result): 
                        if ($result['v1beta'] === 'OK' && !$working_model) {
                            $working_model = $model_name;
                        }
                    ?>
                        <tr class="<?php echo ($result['v1beta'] === 'OK') ? 'table-success' : ''; ?>">
                            <td>
                                <code><?php echo escape($model_name); ?></code>
                                <?php if ($result['v1beta'] === 'OK'): ?>
                                    <span class="badge bg-success ms-2">✓ BERFUNGSI</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($result['v1beta'] === 'OK'): ?>
                                    <span class="badge bg-success">✓ OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">✗ <?php echo escape($result['v1beta']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($result['v1'] === 'OK'): ?>
                                    <span class="badge bg-success">✓ OK</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">✗ <?php echo escape($result['v1']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($result['v1beta'] === 'OK') {
                                    echo '<span class="badge bg-primary">✓ Rekomendasi: Gunakan v1beta</span>';
                                } elseif ($result['v1'] === 'OK') {
                                    echo '<span class="badge bg-warning">Gunakan v1</span>';
                                } else {
                                    echo '<span class="badge bg-danger">Tidak Tersedia</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($working_model): ?>
            <div class="alert alert-success mt-3">
                <strong>✓ Model yang Berfungsi Ditemukan!</strong>
                <p class="mb-2">Model <code><?php echo escape($working_model); ?></code> berfungsi dengan API key Anda.</p>
                <p class="mb-0">
                    <strong>Langkah selanjutnya:</strong>
                    <ol class="mb-0">
                        <li>Buka <a href="<?php echo base_url('admin/ai_settings.php'); ?>">Pengaturan AI</a></li>
                        <li>Pilih model <strong><?php echo escape($working_model); ?></strong> di dropdown "Model Gemini"</li>
                        <li>Simpan pengaturan</li>
                        <li>Test lagi di <a href="<?php echo base_url('admin/test_ai_api.php'); ?>">Test API Key</a></li>
                    </ol>
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning mt-3">
                <strong>⚠ Tidak Ada Model yang Berfungsi</strong>
                <p class="mb-2">Semua model yang di-test gagal. Kemungkinan:</p>
                <ul class="mb-0">
                    <li>API key tidak valid atau tidak memiliki akses ke Gemini API</li>
                    <li>API key tidak memiliki quota</li>
                    <li>Perlu mengaktifkan Gemini API di Google Cloud Console</li>
                    <li>Model yang di-test tidak tersedia untuk API key Anda</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="alert alert-info mt-3">
            <strong>Catatan:</strong>
            <ul class="mb-0">
                <li>Pilih model yang menunjukkan <span class="badge bg-success">✓ OK</span> untuk API v1beta atau v1</li>
                <li>Jika model menunjukkan <span class="badge bg-danger">✗ FAIL</span>, model tersebut tidak tersedia untuk API key Anda</li>
                <li>Gunakan model yang tersedia di pengaturan AI/OCR</li>
                <li>Baris hijau menunjukkan model yang berfungsi</li>
            </ul>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="<?php echo base_url('admin/ai_settings.php'); ?>" class="btn btn-primary">
        <i class="fas fa-cog"></i> Pengaturan AI
    </a>
    <a href="<?php echo base_url('admin/list_gemini_models.php'); ?>" class="btn btn-secondary">
        <i class="fas fa-sync"></i> Refresh
    </a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

