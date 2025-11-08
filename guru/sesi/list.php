<?php
/**
 * List Sesi - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Daftar Sesi';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get sesi
$stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, m.nama_mapel,
                      (SELECT COUNT(*) FROM sesi_peserta WHERE id_sesi = s.id) as total_peserta
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE u.id_guru = ?
                      ORDER BY s.waktu_mulai DESC");
$stmt->execute([$_SESSION['user_id']]);
$sesi_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar Sesi</h2>
            <a href="<?php echo base_url('guru/sesi/create.php'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Sesi Baru
            </a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Nama Sesi</th>
                        <th>Ujian</th>
                        <th>Mata Pelajaran</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Durasi</th>
                        <th>Peserta</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sesi_list)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Belum ada sesi</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sesi_list as $sesi): ?>
                        <tr>
                            <td><?php echo escape($sesi['nama_sesi']); ?></td>
                            <td><?php echo escape($sesi['judul_ujian']); ?></td>
                            <td><?php echo escape($sesi['nama_mapel']); ?></td>
                            <td><?php echo format_date($sesi['waktu_mulai']); ?></td>
                            <td><?php echo format_date($sesi['waktu_selesai']); ?></td>
                            <td><?php echo $sesi['durasi']; ?> menit</td>
                            <td><?php echo $sesi['total_peserta']; ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $sesi['status'] === 'aktif' ? 'success' : 
                                        ($sesi['status'] === 'selesai' ? 'info' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($sesi['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo base_url('guru/sesi/manage.php?id=' . $sesi['id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-cog"></i> Kelola
                                    </a>
                                    <a href="<?php echo base_url('guru/sesi/delete.php?id=' . $sesi['id']); ?>" 
                                       class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus sesi ini? Semua data terkait akan dihapus.');"
                                       title="Hapus Sesi">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
