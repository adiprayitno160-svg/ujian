<?php
/**
 * Hasil Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Hasil Ujian';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$sesi_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT n.*, u.judul, u.id_mapel, m.nama_mapel, s.nama_sesi
                      FROM nilai n
                      INNER JOIN ujian u ON n.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      INNER JOIN sesi_ujian s ON n.id_sesi = s.id
                      WHERE n.id_sesi = ? AND n.id_siswa = ?");
$stmt->execute([$sesi_id, $_SESSION['user_id']]);
$nilai = $stmt->fetch();

if (!$nilai) {
    redirect('siswa/ujian/list.php');
}

// Get soal and answers
$stmt = $pdo->prepare("SELECT s.*, js.jawaban, js.jawaban_json, js.is_ragu
                      FROM soal s
                      LEFT JOIN jawaban_siswa js ON s.id = js.id_soal 
                      AND js.id_sesi = ? AND js.id_ujian = ? AND js.id_siswa = ?
                      WHERE s.id_ujian = ?
                      ORDER BY s.urutan ASC, s.id ASC");
$stmt->execute([$sesi_id, $nilai['id_ujian'], $_SESSION['user_id'], $nilai['id_ujian']]);
$soal_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Hasil Ujian</h2>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h4><?php echo escape($nilai['judul']); ?></h4>
        <p class="text-muted"><?php echo escape($nilai['nama_mapel']); ?> - <?php echo escape($nilai['nama_sesi']); ?></p>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-3 fw-bold text-primary"><?php echo number_format($nilai['nilai'], 2); ?></div>
                    <small class="text-muted">Nilai Akhir</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-3 fw-bold text-info"><?php echo count($soal_list); ?></div>
                    <small class="text-muted">Total Soal</small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-3 fw-bold text-success">
                        <?php 
                        $benar = 0;
                        foreach ($soal_list as $soal) {
                            if ($soal['tipe_soal'] === 'esai') continue;
                            $jawaban = $soal['jawaban'] ?? '';
                            $kunci = $soal['kunci_jawaban'] ?? '';
                            if (strtoupper(trim($jawaban)) === strtoupper(trim($kunci))) {
                                $benar++;
                            }
                        }
                        echo $benar;
                        ?>
                    </div>
                    <small class="text-muted">Benar</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Review Jawaban</h5>
    </div>
    <div class="card-body">
        <?php foreach ($soal_list as $index => $soal): 
            $opsi = $soal['opsi_json'] ? json_decode($soal['opsi_json'], true) : [];
            
            // Filter opsi hanya A-D (remove E and above)
            if (is_array($opsi)) {
                $filtered_opsi = [];
                $allowed_keys = ['A', 'B', 'C', 'D'];
                foreach ($allowed_keys as $key) {
                    if (isset($opsi[$key]) && !empty($opsi[$key])) {
                        $filtered_opsi[$key] = $opsi[$key];
                    }
                }
                $opsi = $filtered_opsi;
            }
            
            $jawaban = $soal['jawaban'] ?? '';
            $kunci = $soal['kunci_jawaban'] ?? '';
            $is_correct = false;
            
            if ($soal['tipe_soal'] !== 'esai') {
                if (strtoupper(trim($jawaban)) === strtoupper(trim($kunci))) {
                    $is_correct = true;
                }
            }
        ?>
        <div class="question-review mb-4 p-3 border rounded <?php echo $is_correct ? 'bg-success bg-opacity-10' : 'bg-danger bg-opacity-10'; ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <h5>Soal #<?php echo $index + 1; ?></h5>
                <div>
                    <?php if ($is_correct): ?>
                        <span class="badge bg-success"><i class="fas fa-check"></i> Benar</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="fas fa-times"></i> Salah</span>
                    <?php endif; ?>
                    <?php if ($soal['is_ragu']): ?>
                        <span class="badge bg-warning"><i class="fas fa-question-circle"></i> Ragu-ragu</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-2">
                <strong>Tipe:</strong> 
                <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></span>
            </div>
            
            <div class="mb-3">
                <?php echo nl2br(escape($soal['pertanyaan'])); ?>
            </div>
            
            <?php if ($soal['tipe_soal'] === 'pilihan_ganda'): ?>
                <div class="mb-2">
                    <?php foreach ($opsi as $key => $value): 
                        // Handle both old format (string) and new format (object with text and image)
                        $option_text = '';
                        $option_image = null;
                        
                        if (is_array($value)) {
                            // New format: object with text and image
                            $option_text = $value['text'] ?? '';
                            $option_image = $value['image'] ?? null;
                        } else {
                            // Old format: just text (backward compatible)
                            $option_text = $value;
                        }
                    ?>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="radio" disabled
                               <?php echo $jawaban === $key ? 'checked' : ''; ?>>
                        <label class="form-check-label <?php 
                            echo $key === $kunci ? 'text-success fw-bold' : 
                                ($jawaban === $key && $jawaban !== $kunci ? 'text-danger' : ''); 
                        ?>">
                            <strong><?php echo $key; ?>.</strong> 
                            <?php if (!empty($option_text)): ?>
                                <?php echo escape($option_text); ?>
                            <?php endif; ?>
                            <?php if (!empty($option_image)): ?>
                                <div class="mt-2">
                                    <img src="<?php echo UPLOAD_URL . '/soal/' . escape($option_image); ?>" 
                                         alt="Gambar Opsi <?php echo $key; ?>" 
                                         class="img-thumbnail" 
                                         style="max-width: 300px; max-height: 200px; cursor: pointer;"
                                         onclick="openMediaModal('<?php echo UPLOAD_URL . '/soal/' . escape($option_image); ?>', 'gambar');">
                                </div>
                            <?php endif; ?>
                            <?php if ($key === $kunci): ?>
                                <i class="fas fa-check-circle"></i> (Kunci)
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'benar_salah'): ?>
                <div class="mb-2">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" disabled <?php echo $jawaban === 'Benar' ? 'checked' : ''; ?>>
                        <label class="form-check-label <?php echo $kunci === 'Benar' ? 'text-success fw-bold' : ''; ?>">
                            Benar <?php if ($kunci === 'Benar'): ?><i class="fas fa-check-circle"></i> (Kunci)<?php endif; ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" disabled <?php echo $jawaban === 'Salah' ? 'checked' : ''; ?>>
                        <label class="form-check-label <?php echo $kunci === 'Salah' ? 'text-success fw-bold' : ''; ?>">
                            Salah <?php if ($kunci === 'Salah'): ?><i class="fas fa-check-circle"></i> (Kunci)<?php endif; ?>
                        </label>
                    </div>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'isian_singkat'): ?>
                <div class="mb-2">
                    <strong>Jawaban Anda:</strong> 
                    <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                        <?php echo escape($jawaban ?: '-'); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Kunci Jawaban:</strong> 
                    <span class="text-success"><?php echo escape($kunci); ?></span>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'matching'): ?>
                <div class="mb-2">
                    <strong>Jawaban Anda:</strong> 
                    <?php 
                    $jawaban_json = json_decode($soal['jawaban_json'], true);
                    if ($jawaban_json) {
                        echo '<ul>';
                        foreach ($jawaban_json as $j) {
                            echo '<li>' . escape($j) . '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'esai'): ?>
                <div class="mb-2">
                    <strong>Jawaban Anda:</strong>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(escape($jawaban ?: '-')); ?>
                    </div>
                </div>
                <?php if ($kunci): ?>
                <div class="mb-2">
                    <strong>Kunci Jawaban (Referensi):</strong>
                    <div class="p-3 bg-light rounded">
                        <?php echo nl2br(escape($kunci)); ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="text-center mt-4">
    <a href="<?php echo base_url('siswa/ujian/list.php'); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Ujian
    </a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
