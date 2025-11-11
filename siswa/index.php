<?php
/**
 * Dashboard - Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('siswa');
check_session_timeout();

// Check if student is in exam mode - redirect to exam if they try to access other pages
if (function_exists('check_exam_mode_restriction')) {
    check_exam_mode_restriction();
}

// Redirect to new dashboard
redirect('siswa-dashboard');
exit;

// Get statistics
global $pdo;

if (!isset($_SESSION['user_id'])) {
    redirect('login.php');
}

$siswa_id = $_SESSION['user_id'];
$tahun_ajaran = get_tahun_ajaran_aktif();

// Get upcoming ujian
$stmt = $pdo->prepare("SELECT DISTINCT s.*, u.judul, u.durasi, m.nama_mapel
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE s.status = 'aktif'
                      AND s.waktu_mulai > NOW()
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
                      ORDER BY s.waktu_mulai ASC
                      LIMIT 5");
$stmt->execute([$siswa_id, $siswa_id, $tahun_ajaran]);
$upcoming_ujian = $stmt->fetchAll();

// Get active ujian
$stmt = $pdo->prepare("SELECT DISTINCT s.*, u.judul, u.durasi, m.nama_mapel
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE s.status = 'aktif'
                      AND s.waktu_mulai <= NOW()
                      AND s.waktu_selesai >= NOW()
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
$stmt->execute([$siswa_id, $siswa_id, $tahun_ajaran]);
$active_ujian = $stmt->fetchAll();

// Get PR
$stmt = $pdo->prepare("SELECT p.*, m.nama_mapel, u.nama as nama_guru,
                      (SELECT status FROM pr_submission WHERE id_pr = p.id AND id_siswa = ?) as submission_status
                      FROM pr p
                      INNER JOIN mapel m ON p.id_mapel = m.id
                      INNER JOIN users u ON p.id_guru = u.id
                      INNER JOIN pr_kelas pk ON p.id = pk.id_pr
                      INNER JOIN user_kelas uk ON pk.id_kelas = uk.id_kelas
                      WHERE uk.id_user = ?
                      AND p.deadline >= NOW()
                      ORDER BY p.deadline ASC
                      LIMIT 5");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$pr_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <p class="text-muted mb-0">Selamat datang, <strong><?php echo escape($_SESSION['nama']); ?></strong>!</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-opacity-10 rounded p-3">
                            <i class="fas fa-file-alt fa-2x text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Ujian Aktif</h6>
                        <h3 class="mb-0"><?php echo count($active_ujian); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-opacity-10 rounded p-3">
                            <i class="fas fa-calendar fa-2x text-info"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Ujian Mendatang</h6>
                        <h3 class="mb-0"><?php echo count($upcoming_ujian); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-opacity-10 rounded p-3">
                            <i class="fas fa-tasks fa-2x text-warning"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">PR</h6>
                        <h3 class="mb-0"><?php echo count($pr_list); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-opacity-10 rounded p-3">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-0">Selesai</h6>
                        <h3 class="mb-0">-</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($active_ujian)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-alt"></i> Ujian Aktif</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ujian</th>
                                <th>Mata Pelajaran</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th>Durasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_ujian as $ujian): ?>
                            <tr>
                                <td><?php echo escape($ujian['judul']); ?></td>
                                <td><?php echo escape($ujian['nama_mapel']); ?></td>
                                <td><?php echo format_date($ujian['waktu_mulai']); ?></td>
                                <td><?php echo format_date($ujian['waktu_selesai']); ?></td>
                                <td><?php echo $ujian['durasi']; ?> menit</td>
                                <td>
                                    <?php
                                    $now = new DateTime();
                                    $mulai = new DateTime($ujian['waktu_mulai']);
                                    // Tombol Mulai hanya aktif jika waktu_mulai sudah tiba
                                    if ($now >= $mulai) {
                                        echo '<a href="' . base_url('siswa/ujian/take.php?id=' . $ujian['id']) . '" class="btn btn-sm btn-primary">';
                                        echo '<i class="fas fa-play"></i> Mulai';
                                        echo '</a>';
                                    } else {
                                        echo '<span class="text-muted" title="Ujian akan dimulai pada ' . format_date($ujian['waktu_mulai']) . '">';
                                        echo '<i class="fas fa-clock"></i> Belum Waktunya';
                                        echo '</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($upcoming_ujian)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar"></i> Ujian Mendatang</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Ujian</th>
                                <th>Mata Pelajaran</th>
                                <th>Waktu Mulai</th>
                                <th>Durasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_ujian as $ujian): ?>
                            <tr>
                                <td><?php echo escape($ujian['judul']); ?></td>
                                <td><?php echo escape($ujian['nama_mapel']); ?></td>
                                <td><?php echo format_date($ujian['waktu_mulai']); ?></td>
                                <td><?php echo $ujian['durasi']; ?> menit</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($pr_list)): ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Pekerjaan Rumah</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pr_list as $pr): ?>
                            <tr>
                                <td><?php echo escape($pr['judul']); ?></td>
                                <td><?php echo escape($pr['nama_mapel']); ?></td>
                                <td><?php echo escape($pr['nama_guru'] ?? 'N/A'); ?></td>
                                <td><?php echo format_date($pr['deadline']); ?></td>
                                <td>
                                    <?php if ($pr['submission_status'] === 'sudah_dikumpulkan'): ?>
                                        <span class="badge bg-success">Sudah Dikumpulkan</span>
                                    <?php elseif ($pr['submission_status'] === 'dinilai'): ?>
                                        <span class="badge bg-info">Dinilai</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Belum Dikumpulkan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('siswa/pr/submit.php?id=' . $pr['id']); ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-upload"></i> Submit
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

