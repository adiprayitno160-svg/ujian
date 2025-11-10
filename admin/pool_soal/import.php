<?php
/**
 * Import Soal ke Arsip - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

global $pdo;

$pool_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT ps.*, m.nama_mapel FROM arsip_soal ps
                       INNER JOIN mapel m ON ps.id_mapel = m.id
                       WHERE ps.id = ?");
$stmt->execute([$pool_id]);
$pool = $stmt->fetch();

if (!$pool) {
    redirect('admin/arsip_soal/list.php');
}

$error = '';
$success = '';
$imported_count = 0;

// Functions untuk import (similar to guru/soal/import.php)
function extract_text_from_word($filepath) {
    $zip = new ZipArchive;
    if ($zip->open($filepath) === TRUE) {
        $content = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($content) {
            $content = strip_tags($content);
            $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
            return $content;
        }
    }
    return false;
}

function extract_text_from_pdf($filepath) {
    $content = file_get_contents($filepath);
    $text = '';
    
    preg_match_all('/\((.*?)\)/s', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            $match = trim($match);
            if (strlen($match) > 2 && preg_match('/[a-zA-Z0-9]/', $match)) {
                $match = preg_replace('/[\x00-\x1F\x7F]/', '', $match);
                if (strlen($match) > 0) {
                    $text .= $match . "\n";
                }
            }
        }
    }
    
    return !empty($text) ? $text : false;
}

function parse_soal_from_text($text) {
    $soal_list = [];
    $lines = explode("\n", $text);
    $current_soal = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Detect question number (1., 2., etc.)
        if (preg_match('/^\d+[\.\)]\s*(.+)$/', $line, $matches)) {
            if ($current_soal) {
                $soal_list[] = $current_soal;
            }
            $current_soal = [
                'pertanyaan' => $matches[1],
                'tipe_soal' => 'pilihan_ganda',
                'opsi' => [],
                'kunci_jawaban' => '',
                'bobot' => 1.0
            ];
        } elseif ($current_soal) {
            // Detect options (a., b., c., d. or A., B., C., D.)
            if (preg_match('/^[a-eA-E][\.\)]\s*(.+)$/', $line, $matches)) {
                $key = strtoupper($matches[1][0]);
                $current_soal['opsi'][$key] = $matches[1];
            }
            // Detect answer key
            elseif (preg_match('/^(kunci|jawaban|answer)[\s:]+([a-eA-E])/i', $line, $matches)) {
                $current_soal['kunci_jawaban'] = strtoupper($matches[2]);
            } else {
                // Append to question if no pattern matched
                $current_soal['pertanyaan'] .= ' ' . $line;
            }
        }
    }
    
    if ($current_soal) {
        $soal_list[] = $current_soal;
    }
    
    return $soal_list;
}

// Handle POST
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
                
                if ($extension === 'csv') {
                    $handle = fopen($file['tmp_name'], 'r');
                    $header = fgetcsv($handle);
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) < 4) continue;
                        
                        $tipe_soal = sanitize($row[0] ?? 'pilihan_ganda');
                        $pertanyaan = sanitize($row[1] ?? '');
                        $opsi_a = sanitize($row[2] ?? '');
                        $opsi_b = sanitize($row[3] ?? '');
                        $opsi_c = sanitize($row[4] ?? '');
                        $opsi_d = sanitize($row[5] ?? '');
                        $kunci_jawaban = sanitize($row[6] ?? '');
                        $bobot = floatval($row[7] ?? 1.0);
                        
                        if (empty($pertanyaan)) continue;
                        
                        $opsi_json = null;
                        if ($tipe_soal === 'pilihan_ganda') {
                            $opsi = [
                                'A' => $opsi_a,
                                'B' => $opsi_b,
                                'C' => $opsi_c,
                                'D' => $opsi_d
                            ];
                            $opsi = array_filter($opsi, function($value) {
                                return !empty($value);
                            });
                            $opsi_json = json_encode($opsi);
                        } elseif ($tipe_soal === 'benar_salah') {
                            $opsi_json = json_encode(['Benar' => 'Benar', 'Salah' => 'Salah']);
                        }
                        
                        // Get max urutan
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM arsip_soal_item WHERE id_arsip_soal = ?");
                        $stmt->execute([$pool_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO arsip_soal_item 
                                              (id_arsip_soal, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$pool_id, $tipe_soal, $pertanyaan, $opsi_json, $kunci_jawaban, $bobot, $next_urutan]);
                        $imported++;
                    }
                    fclose($handle);
                } elseif (in_array($extension, ['docx', 'doc'])) {
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
                        
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM arsip_soal_item WHERE id_arsip_soal = ?");
                        $stmt->execute([$pool_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO arsip_soal_item 
                                              (id_arsip_soal, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $pool_id, 
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
                        
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(urutan), 0) + 1 as next_urutan FROM arsip_soal_item WHERE id_arsip_soal = ?");
                        $stmt->execute([$pool_id]);
                        $next_urutan = $stmt->fetch()['next_urutan'];
                        
                        $stmt = $pdo->prepare("INSERT INTO arsip_soal_item 
                                              (id_arsip_soal, tipe_soal, pertanyaan, opsi_json, kunci_jawaban, bobot, urutan) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $pool_id, 
                            $soal['tipe_soal'], 
                            $soal['pertanyaan'], 
                            $opsi_json, 
                            $soal['kunci_jawaban'], 
                            $soal['bobot'], 
                            $next_urutan
                        ]);
                        $imported++;
                    }
                }
                
                $pdo->commit();
                $imported_count = $imported;
                $success = "Berhasil mengimpor $imported soal ke arsip";
                
                // Update total_soal
                $stmt = $pdo->prepare("UPDATE arsip_soal SET total_soal = (SELECT COUNT(*) FROM arsip_soal_item WHERE id_arsip_soal = ?) WHERE id = ?");
                $stmt->execute([$pool_id, $pool_id]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Import soal error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat mengimpor soal: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'File tidak valid atau tidak diunggah';
    }
}

$page_title = 'Import Soal ke Arsip - ' . escape($pool['nama_pool']);
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Import Soal ke Arsip</h2>
                <p class="text-muted mb-0"><?php echo escape($pool['nama_pool']); ?> - <?php echo escape($pool['nama_mapel']); ?></p>
            </div>
            <a href="<?php echo base_url('admin/arsip_soal/detail.php?id=' . $pool_id); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title">Format File</h5>
        <p>File yang didukung:</p>
        <ul>
            <li><strong>CSV:</strong> Format: Tipe Soal, Pertanyaan, Opsi A, Opsi B, Opsi C, Opsi D, Kunci Jawaban, Bobot</li>
            <li><strong>Word (.docx, .doc):</strong> Dokumen dengan format soal standar</li>
            <li><strong>PDF:</strong> File PDF dengan teks yang dapat dibaca</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Pilih File <span class="text-danger">*</span></label>
                <input type="file" class="form-control" name="file_import" accept=".csv,.xlsx,.xls,.docx,.doc,.pdf" required>
                <small class="text-muted">Maksimal ukuran file: 10MB</small>
            </div>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Petunjuk:</strong> Pastikan file berisi soal dengan format yang benar. 
                Soal akan ditambahkan ke arsip yang sudah ada.
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Import Soal
            </button>
            <a href="<?php echo base_url('admin/arsip_soal/detail.php?id=' . $pool_id); ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Batal
            </a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

