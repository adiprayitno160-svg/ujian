<?php
/**
 * Absensi Ujian Harian - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk guru melihat absensi siswa dalam ujian harian
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

$page_title = 'Absensi Ujian Harian';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get filters
$sesi_id = intval($_GET['sesi_id'] ?? 0);
$filter_status = $_GET['status'] ?? '';

// Get sesi list untuk filter (hanya ujian harian, bukan assessment)
// Tampilkan semua sesi ujian harian yang dibuat oleh guru ini
$stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, m.nama_mapel,
                      (SELECT COUNT(*) FROM sesi_peserta WHERE id_sesi = s.id) as total_peserta
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE u.id_guru = ?
                      AND (u.tipe_asesmen IS NULL OR u.tipe_asesmen = '' OR u.tipe_asesmen NOT IN ('sumatip', 'sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun'))
                      ORDER BY s.waktu_mulai DESC, s.id DESC");
$stmt->execute([$_SESSION['user_id']]);
$sesi_list = $stmt->fetchAll();

$absensi_list = [];
$sesi_info = null;
$stats = [
    'total' => 0,
    'hadir' => 0,
    'tidak_hadir' => 0,
    'selesai' => 0,
    'sedang_mengerjakan' => 0
];

if ($sesi_id) {
    // Get sesi info
    $stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, u.durasi, m.nama_mapel, m.kode_mapel
                          FROM sesi_ujian s
                          INNER JOIN ujian u ON s.id_ujian = u.id
                          INNER JOIN mapel m ON u.id_mapel = m.id
                          WHERE s.id = ? AND u.id_guru = ?");
    $stmt->execute([$sesi_id, $_SESSION['user_id']]);
    $sesi_info = $stmt->fetch();
    
    if ($sesi_info) {
        $tahun_ajaran = get_tahun_ajaran_aktif();
        
        // Get all peserta dari sesi (individual dan kelas)
        // First, get individual assignments
        $sql_individual = "SELECT DISTINCT 
                u.id as id_siswa, 
                u.nama as nama_siswa, 
                u.username as nis
                FROM sesi_peserta sp
                INNER JOIN users u ON sp.id_user = u.id
                WHERE sp.id_sesi = ?
                AND sp.tipe_assign = 'individual'
                AND u.role = 'siswa'
                AND u.status = 'active'";
        
        $stmt = $pdo->prepare($sql_individual);
        $stmt->execute([$sesi_id]);
        $peserta_individual = $stmt->fetchAll();
        
        // Get kelas assignments
        $sql_kelas = "SELECT DISTINCT 
                u.id as id_siswa, 
                u.nama as nama_siswa, 
                u.username as nis
                FROM sesi_peserta sp
                INNER JOIN user_kelas uk ON sp.id_kelas = uk.id_kelas
                INNER JOIN users u ON uk.id_user = u.id
                WHERE sp.id_sesi = ?
                AND sp.tipe_assign = 'kelas'
                AND uk.tahun_ajaran = ?
                AND u.role = 'siswa'
                AND u.status = 'active'";
        
        $stmt = $pdo->prepare($sql_kelas);
        $stmt->execute([$sesi_id, $tahun_ajaran]);
        $peserta_kelas = $stmt->fetchAll();
        
        // Merge and get unique siswa
        $all_siswa_ids = [];
        $siswa_map = [];
        
        foreach ($peserta_individual as $p) {
            if (!in_array($p['id_siswa'], $all_siswa_ids)) {
                $all_siswa_ids[] = $p['id_siswa'];
                $siswa_map[$p['id_siswa']] = $p;
            }
        }
        
        foreach ($peserta_kelas as $p) {
            if (!in_array($p['id_siswa'], $all_siswa_ids)) {
                $all_siswa_ids[] = $p['id_siswa'];
                $siswa_map[$p['id_siswa']] = $p;
            }
        }
        
        if (empty($all_siswa_ids)) {
            $absensi_list = [];
        } else {
            // Get detailed info for each siswa
            $placeholders = implode(',', array_fill(0, count($all_siswa_ids), '?'));
            $sql = "SELECT 
                    u.id as id_siswa,
                    u.nama as nama_siswa,
                    u.username as nis,
                    k.nama_kelas,
                    k.id as id_kelas,
                    n.status as status_nilai,
                    n.waktu_mulai,
                    n.waktu_selesai,
                    n.waktu_submit,
                    n.nilai,
                    a.status_absen,
                    a.waktu_absen,
                    a.metode_absen
                    FROM users u
                    LEFT JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
                    LEFT JOIN kelas k ON uk.id_kelas = k.id
                    LEFT JOIN nilai n ON n.id_sesi = ? AND n.id_siswa = u.id
                    LEFT JOIN absensi_ujian a ON a.id_sesi = ? AND a.id_siswa = u.id
                    WHERE u.id IN ($placeholders)
                    ORDER BY u.nama ASC";
            
            $params = array_merge([$tahun_ajaran, $sesi_id, $sesi_id], $all_siswa_ids);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $absensi_list = $stmt->fetchAll();
        }
        
        // Calculate stats
        $stats['total'] = count($absensi_list);
        foreach ($absensi_list as $absensi) {
            if ($absensi['status_nilai']) {
                if ($absensi['status_nilai'] === 'selesai') {
                    $stats['selesai']++;
                    $stats['hadir']++;
                } elseif ($absensi['status_nilai'] === 'sedang_mengerjakan') {
                    $stats['sedang_mengerjakan']++;
                    $stats['hadir']++;
                } else {
                    $stats['tidak_hadir']++;
                }
            } elseif ($absensi['status_absen'] === 'hadir') {
                $stats['hadir']++;
            } else {
                $stats['tidak_hadir']++;
            }
        }
        
        // Filter by status if needed
        if ($filter_status) {
            $absensi_list = array_filter($absensi_list, function($item) use ($filter_status) {
                if ($filter_status === 'hadir') {
                    return $item['status_nilai'] === 'selesai' || 
                           $item['status_nilai'] === 'sedang_mengerjakan' || 
                           $item['status_absen'] === 'hadir';
                } elseif ($filter_status === 'tidak_hadir') {
                    return !$item['status_nilai'] && $item['status_absen'] !== 'hadir';
                } elseif ($filter_status === 'selesai') {
                    return $item['status_nilai'] === 'selesai';
                }
                return true;
            });
        }
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Absensi Ujian Harian</h2>
        <p class="text-muted">Lihat absensi siswa untuk ujian harian yang Anda buat</p>
    </div>
</div>

<!-- Filter Sesi -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Pilih Sesi Ujian</label>
                <select class="form-select" name="sesi_id" onchange="this.form.submit()">
                    <option value="">-- Pilih Sesi --</option>
                    <?php if (empty($sesi_list)): ?>
                        <option value="" disabled>Tidak ada sesi ujian harian</option>
                    <?php else: ?>
                        <?php foreach ($sesi_list as $sesi): ?>
                            <option value="<?php echo $sesi['id']; ?>" <?php echo $sesi_id == $sesi['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($sesi['judul_ujian']); ?> - 
                                <?php echo escape($sesi['nama_mapel']); ?> - 
                                <?php echo escape($sesi['nama_sesi'] ?? 'Sesi'); ?> - 
                                <?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if (empty($sesi_list)): ?>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle"></i> Belum ada sesi ujian harian. Buat ujian dan sesi terlebih dahulu.
                    </small>
                <?php endif; ?>
            </div>
            <?php if ($sesi_id): ?>
            <div class="col-md-3">
                <label class="form-label">Filter Status</label>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="hadir" <?php echo $filter_status === 'hadir' ? 'selected' : ''; ?>>Hadir</option>
                    <option value="tidak_hadir" <?php echo $filter_status === 'tidak_hadir' ? 'selected' : ''; ?>>Tidak Hadir</option>
                    <option value="selesai" <?php echo $filter_status === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($sesi_info): ?>
    <!-- Sesi Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sesi</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Ujian:</strong> <?php echo escape($sesi_info['judul_ujian']); ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?php echo escape($sesi_info['nama_mapel']); ?></p>
                    <p><strong>Nama Sesi:</strong> <?php echo escape($sesi_info['nama_sesi']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Waktu Mulai:</strong> <?php echo format_date($sesi_info['waktu_mulai']); ?></p>
                    <p><strong>Waktu Selesai:</strong> <?php echo format_date($sesi_info['waktu_selesai']); ?></p>
                    <p><strong>Durasi:</strong> <?php echo $sesi_info['durasi']; ?> menit</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="fas fa-users fa-2x text-primary"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Total Peserta</h6>
                            <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
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
                            <h6 class="text-muted mb-0">Hadir</h6>
                            <h3 class="mb-0"><?php echo $stats['hadir']; ?></h3>
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
                            <div class="bg-danger bg-opacity-10 rounded p-3">
                                <i class="fas fa-times-circle fa-2x text-danger"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Tidak Hadir</h6>
                            <h3 class="mb-0"><?php echo $stats['tidak_hadir']; ?></h3>
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
                                <i class="fas fa-check-double fa-2x text-info"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-0">Selesai</h6>
                            <h3 class="mb-0"><?php echo $stats['selesai']; ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Absensi List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Absensi</h5>
                <div>
                    <a href="<?php echo base_url('guru-absensi-export?sesi_id=' . $sesi_id); ?>" class="btn btn-danger btn-sm" target="_blank">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    <button class="btn btn-light btn-sm" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($absensi_list)): ?>
                <p class="text-muted text-center">Tidak ada peserta ditemukan</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Status Kehadiran</th>
                                <th>Status Ujian</th>
                                <th>Waktu Login</th>
                                <th>Waktu Selesai</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($absensi_list as $absensi): 
                                // Determine status
                                $status_kehadiran = 'tidak_hadir';
                                $status_label = 'Tidak Hadir';
                                $status_badge = 'danger';
                                
                                if ($absensi['status_nilai'] === 'selesai') {
                                    $status_kehadiran = 'selesai';
                                    $status_label = 'Selesai';
                                    $status_badge = 'success';
                                } elseif ($absensi['status_nilai'] === 'sedang_mengerjakan') {
                                    $status_kehadiran = 'sedang_mengerjakan';
                                    $status_label = 'Sedang Mengerjakan';
                                    $status_badge = 'warning';
                                } elseif ($absensi['status_absen'] === 'hadir' || $absensi['status_nilai']) {
                                    $status_kehadiran = 'hadir';
                                    $status_label = 'Hadir';
                                    $status_badge = 'info';
                                }
                            ?>
                                <tr class="<?php echo $status_kehadiran === 'tidak_hadir' ? 'table-danger' : ''; ?>">
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo escape($absensi['nis']); ?></td>
                                    <td>
                                        <strong><?php echo escape($absensi['nama_siswa']); ?></strong>
                                        <?php if ($status_kehadiran === 'tidak_hadir'): ?>
                                            <span class="badge bg-danger ms-2">TIDAK HADIR</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo escape($absensi['nama_kelas'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $status_badge; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($absensi['status_nilai']): ?>
                                            <span class="badge bg-secondary">
                                                <?php echo ucfirst(str_replace('_', ' ', $absensi['status_nilai'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Waktu login/mulai ujian
                                        if ($absensi['waktu_mulai']) {
                                            echo format_date($absensi['waktu_mulai'], 'd/m/Y H:i');
                                        } elseif ($absensi['waktu_absen']) {
                                            echo format_date($absensi['waktu_absen'], 'd/m/Y H:i');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Waktu selesai ujian (prioritaskan waktu_selesai, jika tidak ada gunakan waktu_submit)
                                        if ($absensi['waktu_selesai']) {
                                            echo format_date($absensi['waktu_selesai'], 'd/m/Y H:i');
                                        } elseif ($absensi['waktu_submit']) {
                                            echo format_date($absensi['waktu_submit'], 'd/m/Y H:i');
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($absensi['nilai'] !== null): ?>
                                            <strong><?php echo number_format($absensi['nilai'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Tidak Hadir -->
                <?php 
                $tidak_hadir_list = array_filter($absensi_list, function($item) {
                    return !$item['status_nilai'] && $item['status_absen'] !== 'hadir';
                });
                if (!empty($tidak_hadir_list)): 
                ?>
                    <div class="alert alert-danger mt-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6><i class="fas fa-exclamation-triangle"></i> Siswa yang Tidak Hadir:</h6>
                                <ul class="mb-0">
                                    <?php foreach ($tidak_hadir_list as $item): ?>
                                        <li><strong><?php echo escape($item['nama_siswa']); ?></strong> (NIS: <?php echo escape($item['nis']); ?>) - Kelas: <?php echo escape($item['nama_kelas'] ?? '-'); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div>
                                <a href="<?php echo base_url('guru/absensi/retake.php?sesi_id=' . $sesi_id); ?>" class="btn btn-warning">
                                    <i class="fas fa-redo"></i> Buat Retake Exam
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <p class="text-muted">Pilih sesi ujian untuk melihat absensi</p>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    .card-header, .btn, .card:first-child, .row.g-4 {
        display: none !important;
    }
    .table-danger {
        background-color: #f8d7da !important;
    }
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

