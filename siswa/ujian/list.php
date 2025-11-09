<?php
/**
 * List Ujian - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Daftar Ujian';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$siswa_id = $_SESSION['user_id'];
$tahun_ajaran = get_tahun_ajaran_aktif();

// Get available sesi
// Check both individual assignment and kelas assignment
$stmt = $pdo->prepare("SELECT DISTINCT s.*, u.judul, u.durasi, m.nama_mapel,
                      (SELECT status FROM nilai WHERE id_sesi = s.id AND id_siswa = ?) as status_nilai
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE s.status = 'aktif'
                      AND EXISTS (
                          SELECT 1 FROM sesi_peserta sp
                          WHERE sp.id_sesi = s.id
                          AND (
                              -- Individual assignment
                              (sp.id_user = ? AND sp.tipe_assign = 'individual')
                              OR
                              -- Kelas assignment - check if siswa is in the assigned kelas
                              (sp.tipe_assign = 'kelas' AND sp.id_kelas IN (
                                  SELECT id_kelas FROM user_kelas 
                                  WHERE id_user = ? AND tahun_ajaran = ?
                              ))
                          )
                      )
                      ORDER BY s.waktu_mulai ASC");
$stmt->execute([$siswa_id, $siswa_id, $siswa_id, $tahun_ajaran]);
$sesi_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Daftar Ujian</h2>
    </div>
</div>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($_SESSION['error_message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($sesi_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada ujian yang tersedia
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ujian</th>
                            <th>Mata Pelajaran</th>
                            <th>Sesi</th>
                            <th>Waktu Mulai</th>
                            <th>Waktu Selesai</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sesi_list as $sesi): ?>
                        <tr>
                            <td><?php echo escape($sesi['judul']); ?></td>
                            <td><?php echo escape($sesi['nama_mapel']); ?></td>
                            <td><?php echo escape($sesi['nama_sesi']); ?></td>
                            <td><?php echo format_date($sesi['waktu_mulai']); ?></td>
                            <td><?php echo format_date($sesi['waktu_selesai']); ?></td>
                            <td><?php echo $sesi['durasi']; ?> menit</td>
                            <td>
                                <?php 
                                $now = new DateTime();
                                $mulai = new DateTime($sesi['waktu_mulai']);
                                $selesai = new DateTime($sesi['waktu_selesai']);
                                
                                if ($now < $mulai) {
                                    echo '<span class="badge bg-info">Belum Dimulai</span>';
                                } elseif ($now >= $mulai && $now <= $selesai) {
                                    if ($sesi['status_nilai'] === 'selesai') {
                                        echo '<span class="badge bg-success">Selesai</span>';
                                    } else {
                                        echo '<span class="badge bg-success">Sedang Berlangsung</span>';
                                    }
                                } else {
                                    echo '<span class="badge bg-secondary">Selesai</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Tombol Mulai hanya aktif jika waktu_mulai sudah tiba
                                if ($now >= $mulai && $now <= $selesai && $sesi['status_nilai'] !== 'selesai') {
                                    echo '<a href="' . base_url('siswa/ujian/take.php?id=' . $sesi['id']) . '" class="btn btn-sm btn-primary">';
                                    echo '<i class="fas fa-play"></i> Mulai';
                                    echo '</a>';
                                } elseif ($sesi['status_nilai'] === 'selesai') {
                                    echo '<a href="' . base_url('siswa/ujian/hasil.php?id=' . $sesi['id']) . '" class="btn btn-sm btn-info">';
                                    echo '<i class="fas fa-eye"></i> Lihat Hasil';
                                    echo '</a>';
                                } elseif ($now < $mulai) {
                                    echo '<span class="text-muted" title="Ujian akan dimulai pada ' . format_date($sesi['waktu_mulai']) . '">';
                                    echo '<i class="fas fa-clock"></i> Belum Waktunya';
                                    echo '</span>';
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
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
