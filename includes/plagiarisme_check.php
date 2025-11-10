<?php
/**
 * Plagiarisme Check Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Calculate similarity between two strings using Levenshtein distance
 */
function calculate_similarity($str1, $str2) {
    if (empty($str1) || empty($str2)) {
        return 0;
    }
    
    // Normalize strings
    $str1 = strtolower(trim($str1));
    $str2 = strtolower(trim($str2));
    
    // Exact match
    if ($str1 === $str2) {
        return 100;
    }
    
    // Calculate Levenshtein distance
    $distance = levenshtein($str1, $str2);
    $max_length = max(strlen($str1), strlen($str2));
    
    if ($max_length == 0) {
        return 100;
    }
    
    // Calculate similarity percentage
    $similarity = (1 - ($distance / $max_length)) * 100;
    
    return round($similarity, 2);
}

/**
 * Calculate similarity by sections (paragraphs or sentences)
 */
function calculate_similarity_by_sections($str1, $str2, $section_type = 'paragraph') {
    if (empty($str1) || empty($str2)) {
        return [
            'overall_similarity' => 0,
            'sections' => []
        ];
    }
    
    // Split into sections
    if ($section_type === 'paragraph') {
        $sections1 = array_filter(array_map('trim', explode("\n\n", $str1)));
        $sections2 = array_filter(array_map('trim', explode("\n\n", $str2)));
    } else { // sentences
        $sections1 = array_filter(array_map('trim', preg_split('/[.!?]+/', $str1)));
        $sections2 = array_filter(array_map('trim', preg_split('/[.!?]+/', $str2)));
    }
    
    $section_similarities = [];
    $total_similarity = 0;
    $max_sections = max(count($sections1), count($sections2));
    
    if ($max_sections == 0) {
        return [
            'overall_similarity' => 100,
            'sections' => []
        ];
    }
    
    // Compare each section from str1 with all sections from str2
    foreach ($sections1 as $idx1 => $section1) {
        $best_match = 0;
        $best_match_idx = -1;
        
        foreach ($sections2 as $idx2 => $section2) {
            $similarity = calculate_similarity($section1, $section2);
            if ($similarity > $best_match) {
                $best_match = $similarity;
                $best_match_idx = $idx2;
            }
        }
        
        $section_similarities[] = [
            'section_index' => $idx1 + 1,
            'section_text' => substr($section1, 0, 100) . (strlen($section1) > 100 ? '...' : ''),
            'matched_section_index' => $best_match_idx + 1,
            'similarity' => $best_match
        ];
        
        $total_similarity += $best_match;
    }
    
    // Handle sections in str2 that don't have matches in str1
    for ($i = count($sections1); $i < count($sections2); $i++) {
        $section_similarities[] = [
            'section_index' => $i + 1,
            'section_text' => substr($sections2[$i], 0, 100) . (strlen($sections2[$i]) > 100 ? '...' : ''),
            'matched_section_index' => null,
            'similarity' => 0
        ];
    }
    
    $overall_similarity = $max_sections > 0 ? ($total_similarity / $max_sections) : 0;
    
    return [
        'overall_similarity' => round($overall_similarity, 2),
        'sections' => $section_similarities,
        'total_sections_str1' => count($sections1),
        'total_sections_str2' => count($sections2)
    ];
}

/**
 * Check plagiarisme for a soal
 */
function check_plagiarisme_soal($ujian_id, $soal_id, $threshold = 80, $include_sections = false) {
    global $pdo;
    
    try {
        // Get all answers for this soal
        $stmt = $pdo->prepare("SELECT id_siswa, jawaban FROM jawaban_siswa 
                              WHERE id_ujian = ? AND id_soal = ? 
                              AND jawaban IS NOT NULL AND jawaban != ''");
        $stmt->execute([$ujian_id, $soal_id]);
        $answers = $stmt->fetchAll();
        
        if (count($answers) < 2) {
            return [];
        }
        
        $similarities = [];
        
        // Compare each pair of answers
        for ($i = 0; $i < count($answers); $i++) {
            for ($j = $i + 1; $j < count($answers); $j++) {
                $similarity = calculate_similarity($answers[$i]['jawaban'], $answers[$j]['jawaban']);
                
                if ($similarity >= $threshold) {
                    $result = [
                        'id_siswa1' => $answers[$i]['id_siswa'],
                        'id_siswa2' => $answers[$j]['id_siswa'],
                        'similarity_score' => $similarity
                    ];
                    
                    // Add section-by-section analysis if requested
                    if ($include_sections) {
                        $section_analysis = calculate_similarity_by_sections(
                            $answers[$i]['jawaban'], 
                            $answers[$j]['jawaban'],
                            strlen($answers[$i]['jawaban']) > 500 ? 'paragraph' : 'sentence'
                        );
                        $result['section_analysis'] = $section_analysis;
                    }
                    
                    $similarities[] = $result;
                }
            }
        }
        
        return $similarities;
    } catch (PDOException $e) {
        error_log("Check plagiarisme soal error: " . $e->getMessage());
        return [];
    }
}

/**
 * Batch check plagiarisme for all soal in ujian
 */
function batch_check_plagiarisme($ujian_id, $threshold = 80, $include_sections = false) {
    global $pdo;
    
    try {
        // Get all soal
        $stmt = $pdo->prepare("SELECT id FROM soal WHERE id_ujian = ?");
        $stmt->execute([$ujian_id]);
        $soal_list = $stmt->fetchAll();
        
        $all_results = [];
        
        foreach ($soal_list as $soal) {
            $similarities = check_plagiarisme_soal($ujian_id, $soal['id'], $threshold, $include_sections);
            
            // Save to database
            foreach ($similarities as $sim) {
                // Check if already exists
                $stmt = $pdo->prepare("SELECT id FROM plagiarisme_check 
                                      WHERE id_ujian = ? AND id_soal = ? 
                                      AND ((id_siswa1 = ? AND id_siswa2 = ?) OR (id_siswa1 = ? AND id_siswa2 = ?))");
                $stmt->execute([
                    $ujian_id, $soal['id'], 
                    $sim['id_siswa1'], $sim['id_siswa2'],
                    $sim['id_siswa2'], $sim['id_siswa1']
                ]);
                
                $section_data = isset($sim['section_analysis']) ? json_encode($sim['section_analysis']) : null;
                
                if (!$stmt->fetch()) {
                    // Check if table has section_analysis column
                    try {
                        $stmt = $pdo->prepare("INSERT INTO plagiarisme_check 
                                              (id_ujian, id_siswa1, id_siswa2, id_soal, similarity_score, status, section_analysis) 
                                              VALUES (?, ?, ?, ?, ?, 'pending', ?)");
                        $stmt->execute([
                            $ujian_id,
                            $sim['id_siswa1'],
                            $sim['id_siswa2'],
                            $soal['id'],
                            $sim['similarity_score'],
                            $section_data
                        ]);
                    } catch (PDOException $e) {
                        // If column doesn't exist, insert without it
                        if (strpos($e->getMessage(), 'section_analysis') !== false) {
                            $stmt = $pdo->prepare("INSERT INTO plagiarisme_check 
                                                  (id_ujian, id_siswa1, id_siswa2, id_soal, similarity_score, status) 
                                                  VALUES (?, ?, ?, ?, ?, 'pending')");
                            $stmt->execute([
                                $ujian_id,
                                $sim['id_siswa1'],
                                $sim['id_siswa2'],
                                $soal['id'],
                                $sim['similarity_score']
                            ]);
                        } else {
                            throw $e;
                        }
                    }
                } else {
                    // Update existing record with section analysis if available
                    if ($section_data) {
                        try {
                            $stmt = $pdo->prepare("UPDATE plagiarisme_check 
                                                  SET similarity_score = ?, section_analysis = ? 
                                                  WHERE id_ujian = ? AND id_soal = ? 
                                                  AND ((id_siswa1 = ? AND id_siswa2 = ?) OR (id_siswa1 = ? AND id_siswa2 = ?))");
                            $stmt->execute([
                                $sim['similarity_score'],
                                $section_data,
                                $ujian_id,
                                $soal['id'],
                                $sim['id_siswa1'],
                                $sim['id_siswa2'],
                                $sim['id_siswa2'],
                                $sim['id_siswa1']
                            ]);
                        } catch (PDOException $e) {
                            // Column might not exist, ignore
                            error_log("Update section analysis error: " . $e->getMessage());
                        }
                    }
                }
            }
            
            $all_results[$soal['id']] = $similarities;
        }
        
        return $all_results;
    } catch (PDOException $e) {
        error_log("Batch check plagiarisme error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get plagiarisme report for ujian
 */
function get_plagiarisme_report($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT pc.*, 
                              u1.nama as nama_siswa1, u2.nama as nama_siswa2,
                              s.pertanyaan
                              FROM plagiarisme_check pc
                              INNER JOIN users u1 ON pc.id_siswa1 = u1.id
                              INNER JOIN users u2 ON pc.id_siswa2 = u2.id
                              INNER JOIN soal s ON pc.id_soal = s.id
                              WHERE pc.id_ujian = ?
                              ORDER BY pc.similarity_score DESC");
        $stmt->execute([$ujian_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get plagiarisme report error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get similarity groups (students with similar answers)
 */
function get_similarity_groups($ujian_id, $soal_id, $threshold = 80) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id_siswa1, id_siswa2, similarity_score 
                              FROM plagiarisme_check 
                              WHERE id_ujian = ? AND id_soal = ? AND similarity_score >= ?
                              ORDER BY similarity_score DESC");
        $stmt->execute([$ujian_id, $soal_id, $threshold]);
        $pairs = $stmt->fetchAll();
        
        // Group students
        $groups = [];
        $processed = [];
        
        foreach ($pairs as $pair) {
            $s1 = $pair['id_siswa1'];
            $s2 = $pair['id_siswa2'];
            
            $found_group = null;
            foreach ($groups as $idx => $group) {
                if (in_array($s1, $group) || in_array($s2, $group)) {
                    $found_group = $idx;
                    break;
                }
            }
            
            if ($found_group !== null) {
                if (!in_array($s1, $groups[$found_group])) {
                    $groups[$found_group][] = $s1;
                }
                if (!in_array($s2, $groups[$found_group])) {
                    $groups[$found_group][] = $s2;
                }
            } else {
                $groups[] = [$s1, $s2];
            }
        }
        
        return $groups;
    } catch (PDOException $e) {
        error_log("Get similarity groups error: " . $e->getMessage());
        return [];
    }
}

/**
 * Flag suspicious answers
 */
function flag_suspicious_answers($ujian_id, $check_id, $action = 'flagged') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE plagiarisme_check 
                              SET status = ?, checked_by = ?, checked_at = NOW() 
                              WHERE id = ?");
        $stmt->execute([$action, $_SESSION['user_id'] ?? null, $check_id]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Flag suspicious answers error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get plagiarisme settings for ujian
 */
function get_plagiarisme_settings($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM plagiarisme_settings WHERE id_ujian = ?");
        $stmt->execute([$ujian_id]);
        $settings = $stmt->fetch();
        
        if (!$settings) {
            // Create default settings
            $stmt = $pdo->prepare("INSERT INTO plagiarisme_settings 
                                  (id_ujian, enabled, similarity_threshold, auto_check) 
                                  VALUES (?, 1, 80.00, 1)");
            $stmt->execute([$ujian_id]);
            
            $stmt = $pdo->prepare("SELECT * FROM plagiarisme_settings WHERE id_ujian = ?");
            $stmt->execute([$ujian_id]);
            $settings = $stmt->fetch();
        }
        
        return $settings;
    } catch (PDOException $e) {
        error_log("Get plagiarisme settings error: " . $e->getMessage());
        return null;
    }
}

