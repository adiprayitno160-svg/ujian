<?php
/**
 * Review PR Detail - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$pr_id = intval($_GET['id'] ?? 0);
$siswa_id = intval($_GET['siswa_id'] ?? 0);

// Get PR
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      WHERE p.id = ? AND p.id_guru = ?");
$stmt->execute([$pr_id, $_SESSION['user_id']]);
$pr = $stmt->fetch();

if (!$pr) {
    redirect('guru/pr/list.php');
}

// Get student info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$siswa_id]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('guru/pr/review.php?id=' . $pr_id);
}

// Get soal and answers
$stmt = $pdo->prepare("SELECT ps.*, pj.jawaban, pj.is_ragu 
                      FROM pr_soal ps
                      LEFT JOIN pr_jawaban pj ON ps.id = pj.id_soal AND pj.id_siswa = ?
                      WHERE ps.id_pr = ?
                      ORDER BY ps.urutan ASC, ps.id ASC");
$stmt->execute([$siswa_id, $pr_id]);
$soal_jawaban = $stmt->fetchAll();

$page_title = 'Review Jawaban PR';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Review Jawaban PR</h2>
                <p class="text-muted mb-0">
                    <strong>PR:</strong> <?php echo escape($pr['judul']); ?> | 
                    <strong>Siswa:</strong> <?php echo escape($siswa['nama']); ?>
                </p>
            </div>
            <a href="<?php echo base_url('guru/pr/review.php?id=' . $pr_id); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="200">Mata Pelajaran</th>
                <td><?php echo escape($pr['nama_mapel']); ?></td>
            </tr>
            <tr>
                <th>Deadline</th>
                <td><?php echo format_date($pr['deadline']); ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Jawaban Siswa</h5>
    </div>
    <div class="card-body">
        <?php if (empty($soal_jawaban)): ?>
            <p class="text-muted text-center">Belum ada soal atau jawaban</p>
        <?php else: ?>
            <?php foreach ($soal_jawaban as $index => $item): 
                $opsi = $item['opsi_json'] ? json_decode($item['opsi_json'], true) : [];
            ?>
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <strong>Soal #<?php echo $index + 1; ?></strong>
                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $item['tipe_soal'])); ?></span>
                    <?php if ($item['is_ragu']): ?>
                        <span class="badge bg-warning ms-2">Ragu-ragu</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Pertanyaan:</strong>
                        <p><?php echo nl2br(escape($item['pertanyaan'])); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Jawaban Siswa:</strong>
                        <?php if ($item['jawaban']): ?>
                            <?php if ($item['tipe_soal'] === 'pilihan_ganda' || $item['tipe_soal'] === 'benar_salah'): ?>
                                <p class="alert alert-info mb-0">
                                    <strong><?php echo escape($item['jawaban']); ?></strong>
                                    <?php if (isset($opsi[$item['jawaban']])): ?>
                                        - <?php echo escape($opsi[$item['jawaban']]); ?>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="alert alert-info mb-0"><?php echo nl2br(escape($item['jawaban'])); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted">Belum dijawab</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($item['kunci_jawaban']): ?>
                    <div class="mb-3">
                        <strong>Kunci Jawaban:</strong>
                        <p class="alert alert-success mb-0"><?php echo escape($item['kunci_jawaban']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

