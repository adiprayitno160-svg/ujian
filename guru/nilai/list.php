<?php
/**
 * List Nilai - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Daftar Nilai';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get ujian
$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

// Get nilai
$stmt = $pdo->prepare("SELECT n.*, u.nama as nama_siswa, u.username, s.nama_sesi
                      FROM nilai n
                      INNER JOIN users u ON n.id_siswa = u.id
                      LEFT JOIN sesi_ujian s ON n.id_sesi = s.id
                      WHERE n.id_ujian = ?
                      ORDER BY n.nilai DESC, u.nama ASC");
$stmt->execute([$ujian_id]);
$nilai_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Daftar Nilai</h2>
                <p class="text-muted mb-0">Ujian: <?php echo escape($ujian['judul']); ?></p>
            </div>
            <a href="<?php echo base_url('guru/nilai/export.php?ujian_id=' . $ujian_id); ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-4 fw-bold text-primary"><?php echo count($nilai_list); ?></div>
                    <small class="text-muted">Total Peserta</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-4 fw-bold text-success">
                        <?php 
                        $selesai = count(array_filter($nilai_list, fn($n) => $n['status'] === 'selesai'));
                        echo $selesai;
                        ?>
                    </div>
                    <small class="text-muted">Selesai</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-4 fw-bold text-info">
                        <?php 
                        $nilai_array = array_filter(array_column($nilai_list, 'nilai'), fn($n) => $n !== null);
                        $avg = !empty($nilai_array) ? array_sum($nilai_array) / count($nilai_array) : 0;
                        echo number_format($avg, 2);
                        ?>
                    </div>
                    <small class="text-muted">Rata-rata</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center p-3 bg-light rounded">
                    <div class="fs-4 fw-bold text-warning">
                        <?php 
                        $nilai_array = array_filter(array_column($nilai_list, 'nilai'), fn($n) => $n !== null);
                        echo !empty($nilai_array) ? number_format(max($nilai_array), 2) : '-';
                        ?>
                    </div>
                    <small class="text-muted">Nilai Tertinggi</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($nilai_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada nilai
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Siswa</th>
                            <th>Username</th>
                            <th>Sesi</th>
                            <th>Nilai</th>
                            <th>Status</th>
                            <th>Waktu Mulai</th>
                            <th>Waktu Selesai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nilai_list as $index => $nilai): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo escape($nilai['nama_siswa']); ?></td>
                            <td><?php echo escape($nilai['username']); ?></td>
                            <td><?php echo escape($nilai['nama_sesi'] ?? '-'); ?></td>
                            <td>
                                <?php if ($nilai['nilai'] !== null): ?>
                                    <strong><?php echo number_format($nilai['nilai'], 2); ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $nilai['status'] === 'selesai' ? 'success' : 
                                        ($nilai['status'] === 'sedang_mengerjakan' ? 'warning' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $nilai['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo format_date($nilai['waktu_mulai']); ?></td>
                            <td><?php echo format_date($nilai['waktu_selesai']); ?></td>
                            <td>
                                <a href="<?php echo base_url('guru/nilai/detail.php?id=' . $nilai['id']); ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Detail
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

