<?php
/**
 * Import Soal dari Excel - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Import Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

$error = '';
$success = '';
$imported_count = 0;

// Function to extract text from Word document
function extract_text_from_word($filepath) {
    // Simple extraction using ZIP (Word files are ZIP archives)
    $zip = new ZipArchive;
    if ($zip->open($filepath) === TRUE) {
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($content) {
            // Remove XML tags and decode entities
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
            return $content;
        }
    }
    return false;
}

// Function to extract text from PDF
function extract_text_from_pdf($filepath) {
    // Simple PDF text extraction
    // Note: For better results, install library: composer require smalot/pdfparser
    $content = file_get_contents($filepath);
    
    // Try to extract readable text from PDF stream
    $text = '';
    
    // Method 1: Extract text between parentheses (common in PDF)
    preg_match_all('/\((.*?)\)/s', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            // Filter out binary data and keep readable text
            $match = trim($match);
            if (strlen($match) > 2 && preg_match('/[a-zA-Z0-9]/', $match)) {
                // Remove non-printable characters
                $match = preg_replace('/[\x00-\x1F\x7F]/', '', $match);
                if (strlen($match) > 0) {
                    $text .= $match . "\n";
                }
            }
        }
    }
    
    // Method 2: Try to find text streams
    if (empty($text)) {
        preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $streams);
        foreach ($streams[1] ?? [] as $stream) {
            // Try to extract readable text
            $decoded = @gzuncompress($stream);
            if ($decoded !== false) {
                preg_match_all('/\((.*?)\)/', $decoded, $decoded_matches);
                foreach ($decoded_matches[1] ?? [] as $match) {
                    $match = trim($match);
                    if (strlen($match) > 2) {
                        $text .= $match . "\n";
                    }
                }
            }
        }
    }
    
    return !empty($text) ? $text : false;
}

// Function to parse soal from text
function parse_soal_from_text($text) {
    $soal_list = [];
    
    // Pattern untuk berbagai format
    // Format 1: Nomor. Pertanyaan? A. Opsi A B. Opsi B ...
    // Format 2: [SOAL] Pertanyaan [OPSI] A. Opsi A ...
    
    // Split by common patterns
    $patterns = [
        '/\d+[\.\)]\s*(.+?)(?=\d+[\.\)]|$)/s', // Nomor. Pertanyaan
        '/\[SOAL\](.+?)(\[OPSI\]|\[KUNCI\]|$)/s', // [SOAL] format
    ];
    
    // Simple parsing: split by numbered questions
    $lines = explode("\n", $text);
    $current_soal = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Detect question start
        if (preg_match('/^(\d+)[\.\)]\s*(.+)/', $line, $matches)) {
            if ($current_soal) {
                $soal_list[] = $current_soal;
            }
            $current_soal = [
                'pertanyaan' => $matches[2],
                'tipe_soal' => 'pilihan_ganda',
                'opsi' => [],
                'kunci_jawaban' => '',
                'bobot' => 1.0
            ];
        }
        // Detect options
        elseif (preg_match('/^([A-E])[\.\)]\s*(.+)/', $line, $matches)) {
            if ($current_soal) {
                $current_soal['opsi'][$matches[1]] = $matches[2];
            }
        }
        // Detect answer key
        elseif (preg_match('/^(kunci|jawaban|answer)[\s:]+([A-E])/i', $line, $matches)) {
            if ($current_soal) {
                $current_soal['kunci_jawaban'] = strtoupper($matches[2]);
            }
        }
        // Continue question if multi-line
        elseif ($current_soal && !preg_match('/^[A-E][\.\)]/', $line)) {
            $current_soal['pertanyaan'] .= ' ' . $line;
        }
    }
    
    if ($current_soal) {
        $soal_list[] = $current_soal;
    }
    
    return $soal_list;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file_import']) && $_FILES['file_import']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file_import'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'xlsx', 'xls', 'docx', 'doc', 'pdf'])) {
            $error = 'File harus berformat Excel (.xlsx, .xls), CSV, Word (.docx, .doc), atau PDF (.pdf)';
        } else {
            try {
                $pdo->beginTransaction();
                $imported = 0;
                $soal_data = [];
                
                // Handle different file types
                if ($extension === 'csv') {
                    // CSV import
                    $handle = fopen($file['tmp_name'], 'r');
                    $header = fgetcsv($handle); // Skip header
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) < 4) continue;
                        
                        $tipe_soal = sanitize($row[0] ?? 'pilihan_ganda');
                        $pertanyaan = sanitize($row[1] ?? '');
                        $opsi_a = sanitize($row[2] ?? '');
                        $opsi_b = sanitize($row[3] ?? '');
                        $opsi_c = sanitize($row[4] ?? '');
                        $opsi_d = sanitize($row[5] ?? '');
                        $opsi_e = sanitize($row[6] ?? '');
                        $kunci_jawaban = sanitize($row[7] ?? '');
                        $bobot = floatval($row[8] ?? 1.0);
                        
                        if (empty($pertanyaan)) continue;
                        
                        // Prepare opsi_json
                        $opsi_json = null;
                        if ($tipe_soal === 'pilihan_ganda') {
                            $opsi = [
                                'A' => $opsi_a,
                                'B' => $opsi_b,
                                'C' => $opsi_c,
                                'D' => $opsi_d
                            ];
                            // Remove empty options
                            $opsi = array_filter($opsi, function($value) {
                                return !empty($value);
                            });
                            $opsi_json = json_encode($opsi);
                        } elseif ($tipe_soal === 'benar_salah') {
                            $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
                        }
                        
                        // Get max urutan
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM soal WHERE id_ujian = ?");
                        $stmt->execute([$ujian_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO soal 
                                              (id_ujian, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$ujian_id, $tipe_soal, $pertanyaan, $opsi_json, $kunci_jawaban, $bobot, $next_urutan]);
                        $imported++;
                    }
                    
                    fclose($handle);
                } elseif (in_array($extension, ['docx', 'doc'])) {
                    // Word import
                    $text = extract_text_from_word($file['tmp_name']);
                    if ($text === false) {
                        throw new Exception('Gagal membaca file Word');
                    }
                    
                    $soal_list = parse_soal_from_text($text);
                    
                    foreach ($soal_list as $soal) {
                        if (empty($soal['pertanyaan'])) continue;
                        
                        $opsi_json = null;
                        if (!empty($soal['opsi'])) {
                            $opsi_json = json_encode($soal['opsi']);
                        }
                        
                        // Get max urutan
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM soal WHERE id_ujian = ?");
                        $stmt->execute([$ujian_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO soal 
                                              (id_ujian, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $ujian_id, 
                            $soal['tipe_soal'], 
                            $soal['pertanyaan'], 
                            $opsi_json, 
                            $soal['kunci_jawaban'], 
                            $soal['bobot'], 
                            $next_urutan
                        ]);
                        $imported++;
                    }
                } elseif ($extension === 'pdf') {
                    // PDF import
                    $text = extract_text_from_pdf($file['tmp_name']);
                    if ($text === false) {
                        throw new Exception('Gagal membaca file PDF. Pastikan PDF berisi teks yang dapat dibaca.');
                    }
                    
                    $soal_list = parse_soal_from_text($text);
                    
                    foreach ($soal_list as $soal) {
                        if (empty($soal['pertanyaan'])) continue;
                        
                        $opsi_json = null;
                        if (!empty($soal['opsi'])) {
                            $opsi_json = json_encode($soal['opsi']);
                        }
                        
                        // Get max urutan
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM soal WHERE id_ujian = ?");
                        $stmt->execute([$ujian_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO soal 
                                              (id_ujian, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $ujian_id, 
                            $soal['tipe_soal'], 
                            $soal['pertanyaan'], 
                            $opsi_json, 
                            $soal['kunci_jawaban'], 
                            $soal['bobot'], 
                            $next_urutan
                        ]);
                        $imported++;
                    }
                } elseif (in_array($extension, ['xlsx', 'xls'])) {
                    // Excel import - simple CSV conversion approach
                    $error = 'Format Excel (.xlsx, .xls) memerlukan library tambahan. Silakan konversi ke CSV terlebih dahulu atau gunakan format Word/PDF.';
                }
                
                if (!$error) {
                    $pdo->commit();
                    $imported_count = $imported;
                    $success = "Berhasil mengimport $imported soal";
                    log_activity('import_soal', 'soal', $ujian_id);
                } else {
                    $pdo->rollBack();
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Import soal error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengimport soal: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'File harus diupload';
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Import Soal</h2>
        <p class="text-muted">Ujian: <?php echo escape($ujian['judul']); ?></p>
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

<div class="row mb-4">
    <div class="col-md-6">
        <a href="<?php echo base_url('guru/soal/template.php?format=csv'); ?>" class="btn btn-outline-primary w-100 mb-2">
            <i class="fas fa-download"></i> Download Template CSV
        </a>
    </div>
    <div class="col-md-6">
        <a href="<?php echo base_url('guru/soal/template.php?format=word'); ?>" class="btn btn-outline-primary w-100 mb-2">
            <i class="fas fa-download"></i> Download Template Word
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Format File yang Didukung</h5>
    </div>
    <div class="card-body">
        <p><strong>Format CSV:</strong></p>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Tipe Soal</th>
                        <th>Pertanyaan</th>
                        <th>Opsi A</th>
                        <th>Opsi B</th>
                        <th>Opsi C</th>
                        <th>Opsi D</th>
                        <th>Opsi E (opsional)</th>
                        <th>Kunci Jawaban</th>
                        <th>Bobot</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>pilihan_ganda</td>
                        <td>Pertanyaan contoh?</td>
                        <td>Jawaban A</td>
                        <td>Jawaban B</td>
                        <td>Jawaban C</td>
                        <td>Jawaban D</td>
                        <td>Jawaban E</td>
                        <td>A</td>
                        <td>1.0</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2">
            <strong>Catatan CSV:</strong> 
            <ul class="small">
                <li>Tipe soal: pilihan_ganda, benar_salah, isian_singkat, matching, esai</li>
                <li>Untuk benar_salah, opsi A dan B akan diabaikan</li>
                <li>Untuk isian_singkat dan esai, opsi bisa dikosongkan</li>
                <li>Kunci jawaban untuk isian_singkat bisa multiple (pisahkan dengan koma)</li>
            </ul>
        </p>
        
        <hr>
        
        <p><strong>Format Word/PDF:</strong></p>
        <p class="text-muted small">
            Format yang didukung untuk Word dan PDF:
            <ul class="small">
                <li>Nomor. Pertanyaan?</li>
                <li>A. Opsi A</li>
                <li>B. Opsi B</li>
                <li>C. Opsi C</li>
                <li>D. Opsi D</li>
                <li>E. Opsi E (opsional)</li>
                <li>Kunci: A (atau Jawaban: A)</li>
            </ul>
            <strong>Contoh:</strong><br>
            <code>
            1. Apa ibukota Indonesia?<br>
            A. Jakarta<br>
            B. Bandung<br>
            C. Surabaya<br>
            D. Yogyakarta<br>
            Kunci: A
            </code>
        </p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="file_import" class="form-label">File Import <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="file_import" name="file_import" 
                       accept=".csv,.xlsx,.xls,.docx,.doc,.pdf" required>
                <small class="text-muted">Format: CSV, Word (.docx, .doc), PDF (.pdf). Max: 10MB</small>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import
                </button>
                <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $ujian_id); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

