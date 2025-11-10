<?php
/**
 * Detail Nilai - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Detail Nilai';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$nilai_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT n.*, u.nama as nama_siswa, u.username, uj.judul, uj.id_mapel, m.nama_mapel, s.nama_sesi
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      INNER JOIN ujian uj ON n.id_ujian = uj.id
                      INNER JOIN mapel m ON uj.id_mapel = m.id
                      LEFT JOIN sesi_ujian s ON n.id_sesi = s.id
                      WHERE n.id = ? AND uj.id_guru = ?");
$stmt->execute([$nilai_id, $_SESSION['user_id']]);
$nilai = $stmt->fetch();

if (!$nilai) {
    redirect('guru/nilai/list.php');
}

// Get soal and answers
$stmt = $pdo->prepare("SELECT s.*, js.jawaban, js.jawaban_json, js.is_ragu
                      FROM soal s
                      LEFT JOIN jawaban_siswa js ON s.id = js.id_soal 
                      AND js.id_sesi = ? AND js.id_ujian = ? AND js.id_siswa = ?
                      WHERE s.id_ujian = ?
                      ORDER BY s.urutan ASC, s.id ASC");
$stmt->execute([$nilai['id_sesi'], $nilai['id_ujian'], $nilai['id_siswa'], $nilai['id_ujian']]);
$soal_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Detail Nilai</h2>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h4><?php echo escape($nilai['judul']); ?></h4>
        <p class="text-muted"><?php echo escape($nilai['nama_mapel']); ?> - <?php echo escape($nilai['nama_sesi'] ?? '-'); ?></p>
        
        <table class="table table-borderless">
            <tr>
                <th width="150">Nama Siswa</th>
                <td><?php echo escape($nilai['nama_siswa']); ?></td>
            </tr>
            <tr>
                <th>Username</th>
                <td><?php echo escape($nilai['username']); ?></td>
            </tr>
            <tr>
                <th>Nilai</th>
                <td><strong class="fs-4"><?php echo number_format($nilai['nilai'], 2); ?></strong></td>
            </tr>
            <tr>
                <th>Status</th>
                <td>
                    <span class="badge bg-<?php 
                        echo $nilai['status'] === 'selesai' ? 'success' : 
                            ($nilai['status'] === 'sedang_mengerjakan' ? 'warning' : 'secondary'); 
                    ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $nilai['status'])); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Waktu Mulai</th>
                <td><?php echo format_date($nilai['waktu_mulai']); ?></td>
            </tr>
            <tr>
                <th>Waktu Selesai</th>
                <td><?php echo format_date($nilai['waktu_selesai']); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Review Jawaban</h5>
    </div>
    <div class="card-body">
        <?php foreach ($soal_list as $index => $soal): 
            $opsi = $soal['opsi_json'] ? json_decode($soal['opsi_json'], true) : [];
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
                    <strong>Jawaban Siswa:</strong> 
                    <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                        <?php echo escape($jawaban ?: '-'); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Kunci Jawaban:</strong> 
                    <span class="text-success"><?php echo escape($kunci); ?></span>
                </div>
            <?php elseif ($soal['tipe_soal'] === 'esai'): ?>
                <div class="mb-2">
                    <strong>Jawaban Siswa:</strong>
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
            <?php else: ?>
                <div class="mb-2">
                    <strong>Jawaban Siswa:</strong> 
                    <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                        <?php echo escape($jawaban ?: '-'); ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Kunci Jawaban:</strong> 
                    <span class="text-success"><?php echo escape($kunci); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="text-center mt-4">
    <a href="<?php echo base_url('guru/nilai/list.php?ujian_id=' . $nilai['id_ujian']); ?>" class="btn btn-primary">
        <i class="fas fa-arrow-left"></i> Kembali
    </a>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



