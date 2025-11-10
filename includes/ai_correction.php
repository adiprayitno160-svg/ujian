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
 * Generate prompt for correction based on question type
 */
function generate_correction_prompt($pertanyaan, $jawaban, $kunci_jawaban = null, $bobot = 100, $tipe_soal = 'esai') {
    $prompt = "Anda adalah seorang guru yang akan menilai jawaban ujian siswa menggunakan Google Gemini AI.\n\n";
    $prompt .= "PERTANYAAN:\n" . $pertanyaan . "\n\n";
    
    // Add context based on question type
    switch ($tipe_soal) {
        case 'esai':
            $prompt .= "TIPE SOAL: Esai/Uraian Panjang\n";
            $prompt .= "Kriteria penilaian: kelengkapan, kedalaman analisis, struktur penulisan, penggunaan bahasa yang baik, dan ketepatan jawaban.\n\n";
            break;
            
        case 'uraian_singkat':
        case 'isian_singkat':
            $prompt .= "TIPE SOAL: Uraian Singkat/Isian Singkat\n";
            $prompt .= "Kriteria penilaian: ketepatan jawaban, kelengkapan poin-poin penting, kejelasan penjelasan, dan relevansi dengan pertanyaan.\n\n";
            break;
            
        case 'rangkuman':
        case 'ringkasan':
            $prompt .= "TIPE SOAL: Rangkuman/Ringkasan\n";
            $prompt .= "Kriteria penilaian: kemampuan merangkum informasi penting, struktur rangkuman yang baik, ketepatan poin-poin utama, kelengkapan informasi kunci, dan kejelasan penyampaian.\n\n";
            break;
            
        case 'cerita':
        case 'narasi':
            $prompt .= "TIPE SOAL: Cerita/Narasi\n";
            $prompt .= "Kriteria penilaian: alur cerita, karakterisasi, penggunaan bahasa, kreativitas, struktur naratif, dan relevansi dengan tema atau instruksi.\n\n";
            break;
            
        default:
            $prompt .= "TIPE SOAL: Jawaban Teks Bebas\n";
            $prompt .= "Kriteria penilaian: ketepatan jawaban, kelengkapan, kejelasan, dan relevansi.\n\n";
    }
    
    if ($kunci_jawaban && !empty(trim($kunci_jawaban))) {
        $prompt .= "KUNCI JAWABAN (sebagai referensi):\n" . $kunci_jawaban . "\n\n";
        $prompt .= "CATATAN: Gunakan kunci jawaban sebagai panduan, namun pertimbangkan juga variasi jawaban yang valid dan kreatif dari siswa.\n\n";
    }
    
    $prompt .= "JAWABAN SISWA:\n" . ($jawaban ?: 'Siswa tidak memberikan jawaban') . "\n\n";
    
    $prompt .= "TUGAS ANDA:\n";
    $prompt .= "1. Berikan penilaian terhadap jawaban siswa (skala 0-" . $bobot . ")\n";
    $prompt .= "2. Berikan feedback yang konstruktif, jelas, dan membantu siswa memahami kekuatan dan area perbaikan\n";
    $prompt .= "3. Identifikasi kekuatan jawaban siswa\n";
    $prompt .= "4. Identifikasi kelemahan atau area yang perlu diperbaiki\n";
    $prompt .= "5. Berikan saran perbaikan yang spesifik dan dapat ditindaklanjuti\n\n";
    
    $prompt .= "Format jawaban Anda HARUS mengikuti format berikut (wajib):\n";
    $prompt .= "NILAI: [angka 0-" . $bobot . ", boleh desimal seperti 85.5]\n";
    $prompt .= "FEEDBACK: [penjelasan lengkap dan konstruktif tentang penilaian]\n";
    $prompt .= "KEKUATAN: [daftar kekuatan jawaban siswa, minimal 2-3 poin]\n";
    $prompt .= "KELEMAHAN: [daftar kelemahan atau area perbaikan, minimal 2-3 poin]\n";
    $prompt .= "SARAN: [saran perbaikan yang spesifik dan dapat ditindaklanjuti, minimal 2-3 poin]\n";
    $prompt .= "ASPEK_PENILAIAN: [penilaian per aspek dengan format: Aspek 1: X/100, Aspek 2: Y/100, dst]\n";
    $prompt .= "CONTOH_JAWABAN: [berikan contoh jawaban yang baik atau poin-poin penting yang harus ada]\n\n";
    
    $prompt .= "PENTING: \n";
    $prompt .= "1. Pastikan nilai yang diberikan sesuai dengan kualitas jawaban. Berikan penilaian yang adil dan objektif.\n";
    $prompt .= "2. Untuk ASPEK_PENILAIAN, gunakan aspek yang relevan dengan tipe soal (misalnya: Kelengkapan, Ketepatan, Struktur, Bahasa, dll)\n";
    $prompt .= "3. CONTOH_JAWABAN harus memberikan insight yang membantu siswa memahami jawaban yang diharapkan.\n";
    $prompt .= "4. Feedback harus spesifik, konstruktif, dan membantu siswa untuk improvement.\n";
    
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
        'saran' => '',
        'aspek_penilaian' => '',
        'contoh_jawaban' => ''
    ];
    
    // Extract nilai
    if (preg_match('/NILAI:\s*(\d+(?:\.\d+)?)/i', $response_text, $matches)) {
        $result['nilai'] = (float)$matches[1];
    }
    
    // Extract feedback
    if (preg_match('/FEEDBACK:\s*(.*?)(?=KEKUATAN:|KELEMAHAN:|SARAN:|ASPEK_PENILAIAN:|CONTOH_JAWABAN:|$)/is', $response_text, $matches)) {
        $result['feedback'] = trim($matches[1]);
    }
    
    // Extract kekuatan
    if (preg_match('/KEKUATAN:\s*(.*?)(?=KELEMAHAN:|SARAN:|ASPEK_PENILAIAN:|CONTOH_JAWABAN:|$)/is', $response_text, $matches)) {
        $result['kekuatan'] = trim($matches[1]);
    }
    
    // Extract kelemahan
    if (preg_match('/KELEMAHAN:\s*(.*?)(?=SARAN:|ASPEK_PENILAIAN:|CONTOH_JAWABAN:|$)/is', $response_text, $matches)) {
        $result['kelemahan'] = trim($matches[1]);
    }
    
    // Extract saran
    if (preg_match('/SARAN:\s*(.*?)(?=ASPEK_PENILAIAN:|CONTOH_JAWABAN:|$)/is', $response_text, $matches)) {
        $result['saran'] = trim($matches[1]);
    }
    
    // Extract aspek penilaian
    if (preg_match('/ASPEK_PENILAIAN:\s*(.*?)(?=CONTOH_JAWABAN:|$)/is', $response_text, $matches)) {
        $result['aspek_penilaian'] = trim($matches[1]);
    }
    
    // Extract contoh jawaban
    if (preg_match('/CONTOH_JAWABAN:\s*(.*?)$/is', $response_text, $matches)) {
        $result['contoh_jawaban'] = trim($matches[1]);
    }
    
    // If no structured format, use full response as feedback
    if (empty($result['feedback']) && !empty($response_text)) {
        $result['feedback'] = $response_text;
    }
    
    return $result;
}

/**
 * Check if question type requires AI correction
 */
function requires_ai_correction($tipe_soal) {
    $ai_correctable_types = [
        'esai',
        'uraian_singkat',
        'isian_singkat',
        'rangkuman',
        'ringkasan',
        'cerita',
        'narasi'
    ];
    
    return in_array(strtolower($tipe_soal), $ai_correctable_types);
}

/**
 * Correct answer using AI (supports multiple question types)
 */
function correct_answer_ai($ujian_id, $soal_id, $jawaban_id, $api_key = null, $tipe_soal = null) {
    global $pdo;
    
    try {
        // Get soal and jawaban
        $stmt = $pdo->prepare("SELECT s.pertanyaan, s.kunci_jawaban, s.bobot, s.tipe_soal, js.jawaban, js.id_siswa, js.id_sesi
                              FROM soal s
                              INNER JOIN jawaban_siswa js ON s.id = js.id_soal
                              WHERE s.id = ? AND js.id = ? AND js.id_ujian = ?");
        $stmt->execute([$soal_id, $jawaban_id, $ujian_id]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return ['success' => false, 'message' => 'Data tidak ditemukan'];
        }
        
        // Use provided tipe_soal or get from database
        $tipe_soal = $tipe_soal ?: $data['tipe_soal'];
        
        // Check if empty answer
        if (empty(trim($data['jawaban'] ?? ''))) {
            return [
                'success' => true,
                'nilai' => 0,
                'feedback' => 'Siswa tidak memberikan jawaban.',
                'kekuatan' => '',
                'kelemahan' => 'Tidak ada jawaban yang diberikan.',
                'saran' => 'Pastikan untuk menjawab semua pertanyaan.',
                'raw_response' => ''
            ];
        }
        
        // Generate prompt based on question type
        $prompt = generate_correction_prompt(
            $data['pertanyaan'],
            $data['jawaban'],
            $data['kunci_jawaban'],
            $data['bobot'] * 100,
            $tipe_soal
        );
        
        // Send to Gemini AI
        $ai_response = send_to_gemini($prompt, $api_key);
        
        if (!$ai_response['success']) {
            // Log failed attempt
            try {
                $stmt = $pdo->prepare("INSERT INTO ai_correction_log 
                                      (id_nilai, tipe, prompt, response, status) 
                                      VALUES (NULL, 'ujian', ?, ?, 'failed')");
                $stmt->execute([$prompt, json_encode($ai_response)]);
            } catch (PDOException $e) {
                error_log("Failed to log AI correction error: " . $e->getMessage());
            }
            
            return $ai_response;
        }
        
        // Parse response
        $parsed = parse_ai_response($ai_response['text']);
        
        // Ensure nilai is within valid range
        if ($parsed['nilai'] !== null) {
            $max_score = $data['bobot'] * 100;
            $parsed['nilai'] = max(0, min($max_score, $parsed['nilai']));
        }
        
        // Save to log with additional metadata
        try {
            // Get nilai_id from sesi and siswa
            $stmt = $pdo->prepare("SELECT id FROM nilai 
                                  WHERE id_sesi = ? AND id_ujian = ? AND id_siswa = ?");
            $stmt->execute([$data['id_sesi'], $ujian_id, $data['id_siswa']]);
            $nilai_data = $stmt->fetch();
            $nilai_id = $nilai_data['id'] ?? null;
            
            // Store metadata in response for easier retrieval
            $response_data = $parsed;
            $response_data['soal_id'] = $soal_id;
            $response_data['jawaban_id'] = $jawaban_id;
            $response_data['ujian_id'] = $ujian_id;
            
            $stmt = $pdo->prepare("INSERT INTO ai_correction_log 
                                  (id_nilai, tipe, prompt, response, status) 
                                  VALUES (?, 'ujian', ?, ?, 'success')");
            $stmt->execute([$nilai_id, $prompt, json_encode($response_data)]);
        } catch (PDOException $e) {
            error_log("Failed to log AI correction: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'nilai' => $parsed['nilai'],
            'feedback' => $parsed['feedback'],
            'kekuatan' => $parsed['kekuatan'],
            'kelemahan' => $parsed['kelemahan'],
            'saran' => $parsed['saran'],
            'raw_response' => $ai_response['text'],
            'tipe_soal' => $tipe_soal
        ];
    } catch (PDOException $e) {
        error_log("Correct answer AI error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()];
    }
}

/**
 * Correct essay answer using AI (backward compatibility)
 */
function correct_essay_ai($ujian_id, $soal_id, $jawaban_id, $api_key = null) {
    return correct_answer_ai($ujian_id, $soal_id, $jawaban_id, $api_key, 'esai');
}

/**
 * Get AI correction feedback for a specific answer
 */
function get_ai_feedback($ujian_id, $soal_id, $siswa_id, $sesi_id = null) {
    global $pdo;
    
    try {
        // Get nilai_id first
        $sql = "SELECT id FROM nilai WHERE id_ujian = ? AND id_siswa = ?";
        $params = [$ujian_id, $siswa_id];
        
        if ($sesi_id) {
            $sql .= " AND id_sesi = ?";
            $params[] = $sesi_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $nilai_data = $stmt->fetch();
        
        if (!$nilai_data) {
            return null;
        }
        
        $nilai_id = $nilai_data['id'];
        
        // Get all AI correction logs for this nilai (including prompt for fallback matching)
        $stmt = $pdo->prepare("SELECT response, prompt, created_at FROM ai_correction_log 
                              WHERE id_nilai = ? AND tipe = 'ujian' AND status = 'success' 
                              ORDER BY created_at DESC");
        $stmt->execute([$nilai_id]);
        $logs = $stmt->fetchAll();
        
        // Find the log that matches this soal_id
        foreach ($logs as $log) {
            if ($log['response']) {
                $response = json_decode($log['response'], true);
                if ($response && isset($response['soal_id']) && $response['soal_id'] == $soal_id) {
                    // Return feedback data without metadata
                    return [
                        'nilai' => $response['nilai'] ?? null,
                        'feedback' => $response['feedback'] ?? '',
                        'kekuatan' => $response['kekuatan'] ?? '',
                        'kelemahan' => $response['kelemahan'] ?? '',
                        'saran' => $response['saran'] ?? ''
                    ];
                }
            }
        }
        
        // Fallback: try to find by checking prompt content (contains pertanyaan)
        // This is less reliable but might work for older logs
        $stmt = $pdo->prepare("SELECT s.pertanyaan FROM soal s WHERE s.id = ?");
        $stmt->execute([$soal_id]);
        $soal_data = $stmt->fetch();
        
        if ($soal_data && !empty($soal_data['pertanyaan'])) {
            // Fallback: check if prompt contains the question
            $pertanyaan_keywords = substr(trim($soal_data['pertanyaan']), 0, 100); // First 100 chars for better matching
            
            foreach ($logs as $log) {
                // Check if prompt contains the question (more reliable than response)
                if (isset($log['prompt']) && !empty($pertanyaan_keywords) && 
                    strpos($log['prompt'], $pertanyaan_keywords) !== false) {
                    $response = json_decode($log['response'], true);
                    if ($response) {
                        return [
                            'nilai' => $response['nilai'] ?? null,
                            'feedback' => $response['feedback'] ?? '',
                            'kekuatan' => $response['kekuatan'] ?? '',
                            'kelemahan' => $response['kelemahan'] ?? '',
                            'saran' => $response['saran'] ?? ''
                        ];
                    }
                }
            }
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Get AI feedback error: " . $e->getMessage());
        return null;
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

