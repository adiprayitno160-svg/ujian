<?php
/**
 * OCR Functions - Gemini Vision API
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Fungsi untuk scan dokumen dengan Gemini Vision API
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';

/**
 * Convert PDF to image (first page only)
 * Requires Imagick extension
 */
function pdf_to_image($pdf_path, $output_path = null) {
    if (!extension_loaded('imagick')) {
        return ['success' => false, 'message' => 'Imagick extension not available'];
    }
    
    try {
        $imagick = new Imagick();
        $imagick->setResolution(300, 300); // High resolution for better OCR
        $imagick->readImage($pdf_path . '[0]'); // Read first page only
        $imagick->setImageFormat('png');
        $imagick->setImageCompressionQuality(95);
        
        if ($output_path) {
            $imagick->writeImage($output_path);
        } else {
            $output_path = sys_get_temp_dir() . '/pdf_' . uniqid() . '.png';
            $imagick->writeImage($output_path);
        }
        
        $imagick->clear();
        $imagick->destroy();
        
        return ['success' => true, 'image_path' => $output_path];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'PDF conversion failed: ' . $e->getMessage()];
    }
}

/**
 * Convert image to base64 for Gemini API
 */
function image_to_base64($image_path) {
    if (!file_exists($image_path)) {
        return ['success' => false, 'message' => 'Image file not found'];
    }
    
    $image_data = file_get_contents($image_path);
    if ($image_data === false) {
        return ['success' => false, 'message' => 'Failed to read image file'];
    }
    
    $mime_type = mime_content_type($image_path);
    $base64 = base64_encode($image_data);
    
    return [
        'success' => true,
        'base64' => $base64,
        'mime_type' => $mime_type
    ];
}

/**
 * Scan dokumen dengan Gemini Vision API
 * @param string $file_path Path to document file (PDF or image)
 * @param string $jenis_dokumen Type of document: 'ijazah', 'kk', 'akte'
 * @return array Result dengan data terstruktur
 */
function scan_dokumen_with_gemini($file_path, $jenis_dokumen) {
    global $pdo;
    
    // Check if Gemini OCR is enabled
    if (!is_gemini_ocr_enabled()) {
        return ['success' => false, 'message' => 'Gemini OCR is not enabled'];
    }
    
    // Get API key
    $api_key = get_gemini_api_key_for_ocr();
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'Gemini API key not found'];
    }
    
    // Get model
    $model = get_gemini_model_for_ocr();
    
    // Check file exists
    if (!file_exists($file_path)) {
        return ['success' => false, 'message' => 'File not found'];
    }
    
    // Determine file type
    $mime_type = mime_content_type($file_path);
    $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Convert PDF to image if needed
    $image_path = $file_path;
    $is_temp_image = false;
    
    if ($mime_type === 'application/pdf' || $file_ext === 'pdf') {
        $pdf_convert = pdf_to_image($file_path);
        if (!$pdf_convert['success']) {
            return ['success' => false, 'message' => 'PDF conversion failed: ' . $pdf_convert['message']];
        }
        $image_path = $pdf_convert['image_path'];
        $is_temp_image = true;
    }
    
    // Convert image to base64
    $image_data = file_get_contents($image_path);
    if ($image_data === false) {
        return ['success' => false, 'message' => 'Failed to read image file'];
    }
    $base64_image = base64_encode($image_data);
    $image_mime = mime_content_type($image_path);
    
    // Clean up temp image
    if ($is_temp_image && file_exists($image_path)) {
        @unlink($image_path);
    }
    
    // Generate prompt based on document type
    $prompt = generate_ocr_prompt($jenis_dokumen);
    
    // Use v1beta API for all models (v1 may not support all models)
    // v1beta is more up-to-date and supports newer models including vision
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/';
    $url = $api_url . $model . ':generateContent?key=' . $api_key;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $image_mime,
                            'data' => $base64_image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1, // Low temperature for accurate extraction
            'maxOutputTokens' => 2000,
            'responseMimeType' => 'application/json' // Force JSON response
        ]
    ];
    
    // Send request to Gemini API
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, GEMINI_VISION_TIMEOUT);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        $error_message = 'API request failed: HTTP ' . $http_code;
        
        // Parse error response if available
        if ($response) {
            $error_result = json_decode($response, true);
            if (isset($error_result['error']['message'])) {
                $error_message .= ' - ' . $error_result['error']['message'];
            } elseif (isset($error_result['error'])) {
                $error_message .= ' - ' . json_encode($error_result['error']);
            }
        }
        
        // If 404 error, try alternative models
        if ($http_code === 404) {
            // Try alternative model names
            $alternative_models = [];
            
            // Map common model names to alternatives (prioritize models that are proven to work)
            if ($model === 'gemini-pro' || $model === 'gemini-1.5-pro' || $model === 'gemini-1.5-flash') {
                // Try newer models that are proven to work
                $alternative_models = ['gemini-2.0-flash', 'gemini-flash-latest', 'gemini-2.0-flash-001', 'gemini-1.5-flash', 'gemini-1.5-pro'];
            } elseif ($model === 'gemini-2.0-flash') {
                $alternative_models = ['gemini-flash-latest', 'gemini-2.0-flash-001', 'gemini-1.5-flash', 'gemini-1.5-pro'];
            } elseif ($model === 'gemini-flash-latest') {
                $alternative_models = ['gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-1.5-flash', 'gemini-1.5-pro'];
            } else {
                // Default fallback for any other model
                $alternative_models = ['gemini-2.0-flash', 'gemini-flash-latest', 'gemini-2.0-flash-001', 'gemini-1.5-flash', 'gemini-1.5-pro'];
            }
            
            // Try alternative models
            foreach ($alternative_models as $alt_model) {
                error_log("OCR: Trying alternative model: " . $alt_model);
                $alt_url = $api_url . $alt_model . ':generateContent?key=' . $api_key;
                
                $ch = curl_init($alt_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, GEMINI_VISION_TIMEOUT);
                
                $alt_response = curl_exec($ch);
                $alt_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($alt_http_code === 200) {
                    // Success with alternative model, continue processing
                    error_log("OCR: Alternative model " . $alt_model . " works! Using it instead of " . $model);
                    $response = $alt_response;
                    $http_code = $alt_http_code;
                    // Clear error message since we have successful response
                    $error_message = '';
                    break; // Exit loop and continue with successful response
                }
            }
            
            // If all models fail, return error
            if ($http_code !== 200) {
                $error_message .= ' (Semua model gagal. Coba model lain: gemini-2.0-flash, gemini-flash-latest, gemini-1.5-flash, atau gemini-1.5-pro)';
                return [
                    'success' => false,
                    'message' => $error_message,
                    'error' => $curl_error,
                    'response' => $response
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => $error_message,
                'error' => $curl_error,
                'response' => $response
            ];
        }
    }
    
    $result = json_decode($response, true);
    
    // Extract text from response
    $extracted_text = '';
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $extracted_text = $result['candidates'][0]['content']['parts'][0]['text'];
    } else {
        return [
            'success' => false,
            'message' => 'Invalid API response: No text found',
            'response' => $response
        ];
    }
    
    // Parse JSON response
    $parsed_data = json_decode($extracted_text, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try to extract JSON from text if response is not pure JSON
        preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/', $extracted_text, $json_matches);
        if (!empty($json_matches)) {
            $parsed_data = json_decode($json_matches[0], true);
        }
    }
    
    if (!$parsed_data) {
        return [
            'success' => false,
            'message' => 'Failed to parse OCR response',
            'raw_text' => $extracted_text
        ];
    }
    
    // Normalize and validate data
    $normalized_data = normalize_ocr_data($parsed_data, $jenis_dokumen);
    
    return [
        'success' => true,
        'data' => $normalized_data,
        'raw_text' => $extracted_text,
        'confidence' => 100 // Gemini doesn't provide confidence, assume high
    ];
}

/**
 * Generate OCR prompt based on document type
 */
function generate_ocr_prompt($jenis_dokumen) {
    $prompts = [
        'ijazah' => "Analisis dokumen ijazah ini dengan teliti. Ekstrak informasi berikut dalam format JSON yang valid (hanya JSON, tanpa teks lain):
{
  \"nama_siswa\": \"NAMA_LENGKAP_SISWA\",
  \"nama_sekolah\": \"NAMA_SEKOLAH\",
  \"tahun_lulus\": \"TAHUN\",
  \"nomor_ijazah\": \"NOMOR\"
}
Pastikan nama siswa diambil dari bagian nama penerima ijazah. Gunakan nama lengkap sesuai dokumen. Jika informasi tidak ditemukan, gunakan null.",
        
        'kk' => "Analisis Kartu Keluarga ini dengan teliti. Ekstrak informasi dalam format JSON yang valid (hanya JSON, tanpa teks lain):
{
  \"nama_kepala_keluarga\": \"NAMA\",
  \"alamat\": \"ALAMAT\",
  \"anggota_keluarga\": [
    {\"nama\": \"NAMA_LENGKAP\", \"hubungan\": \"HUBUNGAN\", \"nik\": \"NIK\"}
  ]
}
Cari anggota yang hubungannya 'ANAK' untuk nama anak.
Cari yang hubungannya 'SUAMI' atau kepala keluarga (jika anak perempuan) untuk nama ayah.
Cari yang hubungannya 'ISTRI' untuk nama ibu.
Pastikan nama diambil lengkap sesuai dokumen. Jika informasi tidak ditemukan, gunakan null.",
        
        'akte' => "Analisis akte kelahiran ini dengan teliti. Ekstrak informasi dalam format JSON yang valid (hanya JSON, tanpa teks lain):
{
  \"nama_anak\": \"NAMA_LENGKAP_ANAK\",
  \"nama_ayah\": \"NAMA_LENGKAP_AYAH\",
  \"nama_ibu\": \"NAMA_LENGKAP_IBU\",
  \"tempat_lahir\": \"TEMPAT\",
  \"tanggal_lahir\": \"YYYY-MM-DD\",
  \"no_akte\": \"NOMOR\"
}
Pastikan semua nama diambil lengkap sesuai dokumen. Jika informasi tidak ditemukan, gunakan null."
    ];
    
    return $prompts[$jenis_dokumen] ?? $prompts['ijazah'];
}

/**
 * Normalize OCR data based on document type
 */
function normalize_ocr_data($data, $jenis_dokumen) {
    $normalized = [
        'nama_anak' => null,
        'nama_ayah' => null,
        'nama_ibu' => null,
        'nik' => null,
        'tempat_lahir' => null,
        'tanggal_lahir' => null,
        'data_lainnya' => []
    ];
    
    switch ($jenis_dokumen) {
        case 'ijazah':
            $normalized['nama_anak'] = trim($data['nama_siswa'] ?? '');
            $normalized['data_lainnya'] = [
                'nama_sekolah' => $data['nama_sekolah'] ?? null,
                'tahun_lulus' => $data['tahun_lulus'] ?? null,
                'nomor_ijazah' => $data['nomor_ijazah'] ?? null
            ];
            break;
            
        case 'kk':
            // Extract nama anak from anggota_keluarga
            if (isset($data['anggota_keluarga']) && is_array($data['anggota_keluarga'])) {
                foreach ($data['anggota_keluarga'] as $anggota) {
                    $hubungan = strtoupper(trim($anggota['hubungan'] ?? ''));
                    $nama = trim($anggota['nama'] ?? '');
                    
                    if (stripos($hubungan, 'ANAK') !== false) {
                        $normalized['nama_anak'] = $nama;
                    } elseif (stripos($hubungan, 'SUAMI') !== false || ($hubungan === 'KEPALA KELUARGA' && empty($normalized['nama_ayah']))) {
                        $normalized['nama_ayah'] = $nama;
                    } elseif (stripos($hubungan, 'ISTRI') !== false) {
                        $normalized['nama_ibu'] = $nama;
                    }
                }
            }
            
            // Fallback: if nama_ayah not found, use kepala keluarga
            if (empty($normalized['nama_ayah']) && !empty($data['nama_kepala_keluarga'])) {
                $normalized['nama_ayah'] = trim($data['nama_kepala_keluarga']);
            }
            
            $normalized['data_lainnya'] = [
                'nama_kepala_keluarga' => $data['nama_kepala_keluarga'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'anggota_keluarga' => $data['anggota_keluarga'] ?? []
            ];
            break;
            
        case 'akte':
            $normalized['nama_anak'] = trim($data['nama_anak'] ?? '');
            $normalized['nama_ayah'] = trim($data['nama_ayah'] ?? '');
            $normalized['nama_ibu'] = trim($data['nama_ibu'] ?? '');
            $normalized['tempat_lahir'] = trim($data['tempat_lahir'] ?? '');
            $normalized['tanggal_lahir'] = $data['tanggal_lahir'] ?? null;
            $normalized['data_lainnya'] = [
                'no_akte' => $data['no_akte'] ?? null
            ];
            break;
    }
    
    // Clean up empty values
    foreach ($normalized as $key => $value) {
        if (is_string($value) && empty(trim($value))) {
            $normalized[$key] = null;
        }
    }
    
    return $normalized;
}

/**
 * Note: The following functions are defined in config/ai_config.php which is already included above.
 * These functions are removed from here to avoid redeclaration error:
 * - get_gemini_api_key_for_ocr()
 * - is_gemini_ocr_enabled()
 * - get_gemini_model_for_ocr()
 */

