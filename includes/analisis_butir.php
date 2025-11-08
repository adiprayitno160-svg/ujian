<?php
/**
 * Analisis Butir Soal Functions
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Calculate Difficulty Index (Tingkat Kesukaran)
 * Formula: (Jumlah yang benar) / (Total peserta)
 */
function calculate_difficulty_index($ujian_id, $soal_id) {
    global $pdo;
    
    try {
        // Get total peserta yang sudah submit
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_siswa) as total 
                              FROM nilai 
                              WHERE id_ujian = ? AND status = 'selesai'");
        $stmt->execute([$ujian_id]);
        $total = $stmt->fetch()['total'];
        
        if ($total == 0) return null;
        
        // Get jumlah yang benar
        $stmt = $pdo->prepare("SELECT COUNT(*) as benar 
                              FROM jawaban_siswa js
                              INNER JOIN soal s ON js.id_soal = s.id
                              WHERE js.id_ujian = ? AND js.id_soal = ?
                              AND js.jawaban = s.kunci_jawaban");
        $stmt->execute([$ujian_id, $soal_id]);
        $benar = $stmt->fetch()['benar'];
        
        $difficulty = $benar / $total;
        
        // Categorize
        $category = 'sedang';
        if ($difficulty > 0.7) $category = 'mudah';
        elseif ($difficulty < 0.3) $category = 'sulit';
        
        return [
            'index' => round($difficulty, 3),
            'category' => $category,
            'benar' => $benar,
            'total' => $total
        ];
    } catch (PDOException $e) {
        error_log("Calculate difficulty index error: " . $e->getMessage());
        return null;
    }
}

/**
 * Calculate Discrimination Index (Daya Beda)
 * Formula: Korelasi antara skor soal dengan skor total
 */
function calculate_discrimination_index($ujian_id, $soal_id) {
    global $pdo;
    
    try {
        // Get all students who completed the exam
        $stmt = $pdo->prepare("SELECT id_siswa, nilai FROM nilai 
                              WHERE id_ujian = ? AND status = 'selesai' 
                              ORDER BY nilai DESC");
        $stmt->execute([$ujian_id]);
        $all_scores = $stmt->fetchAll();
        
        if (count($all_scores) < 2) return null;
        
        // Split into upper and lower groups (27% each)
        $n = count($all_scores);
        $group_size = (int)($n * 0.27);
        
        $upper_group = array_slice($all_scores, 0, $group_size);
        $lower_group = array_slice($all_scores, -$group_size);
        
        $upper_ids = array_column($upper_group, 'id_siswa');
        $lower_ids = array_column($lower_group, 'id_siswa');
        
        // Count correct in upper group
        $stmt = $pdo->prepare("SELECT COUNT(*) as benar 
                              FROM jawaban_siswa js
                              INNER JOIN soal s ON js.id_soal = s.id
                              WHERE js.id_ujian = ? AND js.id_soal = ?
                              AND js.jawaban = s.kunci_jawaban
                              AND js.id_siswa IN (" . implode(',', array_fill(0, count($upper_ids), '?')) . ")");
        $stmt->execute(array_merge([$ujian_id, $soal_id], $upper_ids));
        $upper_benar = $stmt->fetch()['benar'];
        
        // Count correct in lower group
        $stmt = $pdo->prepare("SELECT COUNT(*) as benar 
                              FROM jawaban_siswa js
                              INNER JOIN soal s ON js.id_soal = s.id
                              WHERE js.id_ujian = ? AND js.id_soal = ?
                              AND js.jawaban = s.kunci_jawaban
                              AND js.id_siswa IN (" . implode(',', array_fill(0, count($lower_ids), '?')) . ")");
        $stmt->execute(array_merge([$ujian_id, $soal_id], $lower_ids));
        $lower_benar = $stmt->fetch()['benar'];
        
        // Calculate discrimination index
        $discrimination = ($upper_benar - $lower_benar) / $group_size;
        
        // Categorize
        $category = 'cukup';
        if ($discrimination > 0.4) $category = 'baik';
        elseif ($discrimination < 0.2) $category = 'kurang';
        
        return [
            'index' => round($discrimination, 3),
            'category' => $category,
            'upper_benar' => $upper_benar,
            'lower_benar' => $lower_benar
        ];
    } catch (PDOException $e) {
        error_log("Calculate discrimination index error: " . $e->getMessage());
        return null;
    }
}

/**
 * Analyze distractor effectiveness
 */
function analyze_distractor_effectiveness($ujian_id, $soal_id) {
    global $pdo;
    
    try {
        // Get soal info
        $stmt = $pdo->prepare("SELECT opsi_json FROM soal WHERE id = ?");
        $stmt->execute([$soal_id]);
        $soal = $stmt->fetch();
        
        if (!$soal || empty($soal['opsi_json'])) return null;
        
        $opsi = json_decode($soal['opsi_json'], true);
        $distractors = [];
        
        // Count selection for each option
        foreach ($opsi as $key => $value) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as dipilih 
                                  FROM jawaban_siswa 
                                  WHERE id_ujian = ? AND id_soal = ? AND jawaban = ?");
            $stmt->execute([$ujian_id, $soal_id, $key]);
            $dipilih = $stmt->fetch()['dipilih'];
            
            // Get total peserta
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_siswa) as total 
                                  FROM nilai 
                                  WHERE id_ujian = ? AND status = 'selesai'");
            $stmt->execute([$ujian_id]);
            $total = $stmt->fetch()['total'];
            
            $persentase = $total > 0 ? ($dipilih / $total) * 100 : 0;
            
            $distractors[$key] = [
                'opsi' => $key,
                'teks' => $value,
                'dipilih' => $dipilih,
                'persentase' => round($persentase, 2),
                'efektif' => $persentase >= 5 && $persentase <= 95 // Effective if 5-95% choose it
            ];
        }
        
        return $distractors;
    } catch (PDOException $e) {
        error_log("Analyze distractor effectiveness error: " . $e->getMessage());
        return null;
    }
}

/**
 * Run full analysis for a soal
 */
function analyze_soal($ujian_id, $soal_id) {
    global $pdo;
    
    try {
        $difficulty = calculate_difficulty_index($ujian_id, $soal_id);
        $discrimination = calculate_discrimination_index($ujian_id, $soal_id);
        $distractors = analyze_distractor_effectiveness($ujian_id, $soal_id);
        
        // Get statistics
        $stmt = $pdo->prepare("SELECT 
                              COUNT(DISTINCT js.id_siswa) as total_peserta,
                              SUM(CASE WHEN js.jawaban = s.kunci_jawaban THEN 1 ELSE 0 END) as benar,
                              SUM(CASE WHEN js.jawaban != s.kunci_jawaban AND js.jawaban IS NOT NULL AND js.jawaban != '' THEN 1 ELSE 0 END) as salah,
                              SUM(CASE WHEN js.jawaban IS NULL OR js.jawaban = '' THEN 1 ELSE 0 END) as kosong
                              FROM jawaban_siswa js
                              INNER JOIN soal s ON js.id_soal = s.id
                              WHERE js.id_ujian = ? AND js.id_soal = ?");
        $stmt->execute([$ujian_id, $soal_id]);
        $stats = $stmt->fetch();
        
        // Save or update analysis
        $stmt = $pdo->prepare("INSERT INTO analisis_butir 
                              (id_ujian, id_soal, total_peserta, benar, salah, kosong, 
                               tingkat_kesukaran, daya_beda, efektivitas_distraktor) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE
                              total_peserta = VALUES(total_peserta),
                              benar = VALUES(benar),
                              salah = VALUES(salah),
                              kosong = VALUES(kosong),
                              tingkat_kesukaran = VALUES(tingkat_kesukaran),
                              daya_beda = VALUES(daya_beda),
                              efektivitas_distraktor = VALUES(efektivitas_distraktor),
                              updated_at = NOW()");
        $stmt->execute([
            $ujian_id,
            $soal_id,
            $stats['total_peserta'] ?? 0,
            $stats['benar'] ?? 0,
            $stats['salah'] ?? 0,
            $stats['kosong'] ?? 0,
            $difficulty['index'] ?? 0,
            $discrimination['index'] ?? 0,
            json_encode($distractors)
        ]);
        
        return [
            'difficulty' => $difficulty,
            'discrimination' => $discrimination,
            'distractors' => $distractors,
            'stats' => $stats
        ];
    } catch (PDOException $e) {
        error_log("Analyze soal error: " . $e->getMessage());
        return null;
    }
}

/**
 * Run analysis for all soal in ujian
 */
function analyze_all_soal($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM soal WHERE id_ujian = ?");
        $stmt->execute([$ujian_id]);
        $soal_list = $stmt->fetchAll();
        
        $results = [];
        foreach ($soal_list as $soal) {
            $results[$soal['id']] = analyze_soal($ujian_id, $soal['id']);
        }
        
        return $results;
    } catch (PDOException $e) {
        error_log("Analyze all soal error: " . $e->getMessage());
        return null;
    }
}

/**
 * Get soal recommendations
 */
function get_soal_recommendations($ujian_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT ab.*, s.pertanyaan 
                              FROM analisis_butir ab
                              INNER JOIN soal s ON ab.id_soal = s.id
                              WHERE ab.id_ujian = ?");
        $stmt->execute([$ujian_id]);
        $analisis = $stmt->fetchAll();
        
        $recommendations = [
            'revisi' => [],
            'hapus' => [],
            'distraktor' => []
        ];
        
        foreach ($analisis as $item) {
            // Soal terlalu mudah atau sulit
            if ($item['tingkat_kesukaran'] > 0.9 || $item['tingkat_kesukaran'] < 0.1) {
                $recommendations['revisi'][] = $item;
            }
            
            // Daya beda kurang
            if ($item['daya_beda'] < 0.2) {
                $recommendations['hapus'][] = $item;
            }
            
            // Check distractor effectiveness
            $distractors = json_decode($item['efektivitas_distraktor'], true);
            if ($distractors) {
                foreach ($distractors as $dist) {
                    if (!$dist['efektif']) {
                        $recommendations['distraktor'][] = [
                            'soal_id' => $item['id_soal'],
                            'pertanyaan' => $item['pertanyaan'],
                            'distraktor' => $dist
                        ];
                        break;
                    }
                }
            }
        }
        
        return $recommendations;
    } catch (PDOException $e) {
        error_log("Get soal recommendations error: " . $e->getMessage());
        return null;
    }
}

