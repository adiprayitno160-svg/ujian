<?php
/**
 * AI Correction Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Google Gemini API Integration
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai_config.php';

/**
 * Send request to Gemini API
 */
function send_to_gemini($prompt, $api_key = null) {
    if (empty($api_key)) {
        $api_key = get_ai_api_key();
    }
    
    if (empty($api_key)) {
        return ['success' => false, 'message' => 'API key tidak ditemukan'];
    }
    
    $url = GEMINI_API_URL . AI_MODEL . ':generateContent?key=' . $api_key;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => AI_TEMPERATURE,
            'maxOutputTokens' => AI_MAX_TOKENS
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_CORRECTION_TIMEOUT);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return ['success' => false, 'message' => 'API request failed: ' . $http_code];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'success' => true,
            'text' => $result['candidates'][0]['content']['parts'][0]['text']
        ];
    }
    
    return ['success' => false, 'message' => 'Invalid API response'];
}

/**
 * Generate prompt for essay correction
 */
function generate_correction_prompt($pertanyaan, $jawaban, $kunci_jawaban = null, $bobot = 100) {
    $prompt = "Anda adalah seorang guru yang akan menilai jawaban ujian siswa.\n\n";
    $prompt .= "PERTANYAAN:\n" . $pertanyaan . "\n\n";
    
    if ($kunci_jawaban) {
        $prompt .= "KUNCI JAWABAN (sebagai referensi):\n" . $kunci_jawaban . "\n\n";
    }
    
    $prompt .= "JAWABAN SISWA:\n" . $jawaban . "\n\n";
    $prompt .= "TUGAS ANDA:\n";
    $prompt .= "1. Berikan penilaian terhadap jawaban siswa (skala 0-" . $bobot . ")\n";
    $prompt .= "2. Berikan feedback yang konstruktif dan membantu\n";
    $prompt .= "3. Identifikasi kekuatan dan kelemahan jawaban\n";
    $prompt .= "4. Berikan saran perbaikan jika diperlukan\n\n";
    $prompt .= "Format jawaban Anda:\n";
    $prompt .= "NILAI: [angka 0-" . $bobot . "]\n";
    $prompt .= "FEEDBACK: [penjelasan lengkap]\n";
    $prompt .= "KEKUATAN: [daftar kekuatan]\n";
    $prompt .= "KELEMAHAN: [daftar kelemahan]\n";
    $prompt .= "SARAN: [saran perbaikan]";
    
    return $prompt;
}

/**
 * Parse AI response
 */
function parse_ai_response($response_text) {
    $result = [
        'nilai' => null,
        'feedback' => '',
        'kekuatan' => '',
        'kelemahan' => '',
        'saran' => ''
    ];
    
    // Extract nilai
    if (preg_match('/NILAI:\s*(\d+(?:\.\d+)?)/i', $response_text, $matches)) {
        $result['nilai'] = (float)$matches[1];
    }
    
    // Extract feedback
    if (preg_match('/FEEDBACK:\s*(.*?)(?=KEKUATAN:|KELEMAHAN:|SARAN:|$)/is', $response_text, $matches)) {
        $result['feedback'] = trim($matches[1]);
    }
    
    // Extract kekuatan
    if (preg_match('/KEKUATAN:\s*(.*?)(?=KELEMAHAN:|SARAN:|$)/is', $response_text, $matches)) {
        $result['kekuatan'] = trim($matches[1]);
    }
    
    // Extract kelemahan
    if (preg_match('/KELEMAHAN:\s*(.*?)(?=SARAN:|$)/is', $response_text, $matches)) {
        $result['kelemahan'] = trim($matches[1]);
    }
    
    // Extract saran
    if (preg_match('/SARAN:\s*(.*?)$/is', $response_text, $matches)) {
        $result['saran'] = trim($matches[1]);
    }
    
    // If no structured format, use full response as feedback
    if (empty($result['feedback']) && !empty($response_text)) {
        $result['feedback'] = $response_text;
    }
    
    return $result;
}

/**
 * Correct essay answer using AI
 */
function correct_essay_ai($ujian_id, $soal_id, $jawaban_id, $api_key = null) {
    global $pdo;
    
    try {
        // Get soal and jawaban
        $stmt = $pdo->prepare("SELECT s.pertanyaan, s.kunci_jawaban, s.bobot, js.jawaban, js.id_siswa
                              FROM soal s
                              INNER JOIN jawaban_siswa js ON s.id = js.id_soal
                              WHERE s.id = ? AND js.id = ? AND js.id_ujian = ?");
        $stmt->execute([$soal_id, $jawaban_id, $ujian_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return ['success' => false, 'message' => 'Data tidak ditemukan'];
        }
        
        // Generate prompt
        $prompt = generate_correction_prompt(
            $data['pertanyaan'],
            $data['jawaban'],
            $data['kunci_jawaban'],
            $data['bobot'] * 100
        );
        
        // Send to AI
        $ai_response = send_to_gemini($prompt, $api_key);
        
        if (!$ai_response['success']) {
            // Log failed attempt
            $stmt = $pdo->prepare("INSERT INTO ai_correction_log 
                                  (id_nilai, tipe, prompt, response, status) 
                                  VALUES (NULL, 'ujian', ?, ?, 'failed')");
            $stmt->execute([$prompt, json_encode($ai_response)]);
            
            return $ai_response;
        }
        
        // Parse response
        $parsed = parse_ai_response($ai_response['text']);
        
        // Save to log
        $stmt = $pdo->prepare("INSERT INTO ai_correction_log 
                              (id_nilai, tipe, prompt, response, status) 
                              VALUES (NULL, 'ujian', ?, ?, 'success')");
        $stmt->execute([$prompt, json_encode($parsed)]);
        
        return [
            'success' => true,
            'nilai' => $parsed['nilai'],
            'feedback' => $parsed['feedback'],
            'kekuatan' => $parsed['kekuatan'],
            'kelemahan' => $parsed['kelemahan'],
            'saran' => $parsed['saran'],
            'raw_response' => $ai_response['text']
        ];
    } catch (PDOException $e) {
        error_log("Correct essay AI error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

/**
 * Correct PR submission using AI
 */
function correct_pr_ai($pr_id, $submission_id, $api_key = null) {
    global $pdo;
    
    try {
        // Get PR and submission info
        $stmt = $pdo->prepare("SELECT p.judul, p.deskripsi, ps.komentar, ps.id_siswa
                              FROM pr p
                              INNER JOIN pr_submission ps ON p.id = ps.id_pr
                              WHERE p.id = ? AND ps.id = ?");
        $stmt->execute([$pr_id, $submission_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return ['success' => false, 'message' => 'Data tidak ditemukan'];
        }
        
        // Generate prompt for PR
        $prompt = "Anda adalah seorang guru yang akan menilai pekerjaan rumah siswa.\n\n";
        $prompt .= "TUGAS:\n" . $data['judul'] . "\n";
        if (!empty($data['deskripsi'])) {
            $prompt .= $data['deskripsi'] . "\n\n";
        }
        $prompt .= "JAWABAN/KOMENTAR SISWA:\n" . ($data['komentar'] ?? 'Tidak ada komentar') . "\n\n";
        $prompt .= "Berikan penilaian dan feedback yang konstruktif.\n";
        $prompt .= "Format: NILAI: [0-100], FEEDBACK: [penjelasan]";
        
        // Send to AI
        $ai_response = send_to_gemini($prompt, $api_key);
        
        if (!$ai_response['success']) {
            return $ai_response;
        }
        
        // Parse response
        $parsed = parse_ai_response($ai_response['text']);
        
        // Save to log
        $stmt = $pdo->prepare("INSERT INTO ai_correction_log 
                              (id_pr_submission, tipe, prompt, response, status) 
                              VALUES (?, 'pr', ?, ?, 'success')");
        $stmt->execute([$submission_id, $prompt, json_encode($parsed)]);
        
        return [
            'success' => true,
            'nilai' => $parsed['nilai'],
            'feedback' => $parsed['feedback']
        ];
    } catch (PDOException $e) {
        error_log("Correct PR AI error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan'];
    }
}

