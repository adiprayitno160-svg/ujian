<?php
/**
 * Generate Berita Acara - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Generate berita acara otomatis untuk ujian tengah/akhir semester/tahunan
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate') {
        $id_sesi = intval($_POST['id_sesi'] ?? 0);
        $pengawas = $_POST['pengawas'] ?? [];
        $catatan = sanitize($_POST['catatan'] ?? '');
        
        if (!$id_sesi) {
            $error = 'Sesi harus dipilih';
        } else {
            try {
                // Get sesi info
                $stmt = $pdo->prepare("SELECT s.*, u.id as ujian_id, u.judul, u.tipe_asesmen, u.tahun_ajaran, u.semester, 
                                      u.tingkat_kelas, m.nama_mapel, ja.id as jadwal_id, ja.id_kelas, k.nama_kelas
                                      FROM sesi_ujian s
                                      INNER JOIN ujian u ON s.id_ujian = u.id
                                      INNER JOIN mapel m ON u.id_mapel = m.id
                                      LEFT JOIN jadwal_assessment ja ON s.id = ja.id_sesi
                                      LEFT JOIN kelas k ON ja.id_kelas = k.id
                                      WHERE s.id = ?");
                $stmt->execute([$id_sesi]);
                $sesi = $stmt->fetch();
                
                if (!$sesi) {
                    $error = 'Sesi tidak ditemukan';
                } else {
                    // Check if berita acara already exists
                    $stmt = $pdo->prepare("SELECT id FROM berita_acara WHERE id_sesi = ?");
                    $stmt->execute([$id_sesi]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $error = 'Berita acara untuk sesi ini sudah ada';
                    } else {
                        // Get absensi data
                        $stmt = $pdo->prepare("SELECT status_absen, COUNT(*) as count 
                                              FROM absensi_ujian 
                                              WHERE id_sesi = ? 
                                              GROUP BY status_absen");
                        $stmt->execute([$id_sesi]);
                        $absensi_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        
                        $total_hadir = $absensi_data['hadir'] ?? 0;
                        $total_tidak_hadir = $absensi_data['tidak_hadir'] ?? 0;
                        $total_izin = $absensi_data['izin'] ?? 0;
                        $total_sakit = $absensi_data['sakit'] ?? 0;
                        
                        // Get total peserta
                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id_siswa) as total 
                                              FROM sesi_peserta sp
                                              WHERE sp.id_sesi = ?");
                        $stmt->execute([$id_sesi]);
                        $total_peserta = $stmt->fetch()['total'] ?? 0;
                        
                        // Insert berita acara
                        $pdo->beginTransaction();
                        
                        $stmt = $pdo->prepare("INSERT INTO berita_acara 
                                              (id_ujian, id_sesi, id_kelas, id_jadwal_assessment, tanggal, waktu_mulai, waktu_selesai,
                                               pengawas, total_peserta, total_hadir, total_tidak_hadir, total_izin, total_sakit, catatan, created_by)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $pengawas_json = json_encode($pengawas);
                        $tanggal = date('Y-m-d', strtotime($sesi['waktu_mulai']));
                        $waktu_mulai = date('H:i:s', strtotime($sesi['waktu_mulai']));
                        $waktu_selesai = date('H:i:s', strtotime($sesi['waktu_selesai']));
                        
                        $stmt->execute([
                            $sesi['ujian_id'],
                            $id_sesi,
                            $sesi['id_kelas'],
                            $sesi['jadwal_id'],
                            $tanggal,
                            $waktu_mulai,
                            $waktu_selesai,
                            $pengawas_json,
                            $total_peserta,
                            $total_hadir,
                            $total_tidak_hadir,
                            $total_izin,
                            $total_sakit,
                            $catatan,
                            $_SESSION['user_id']
                        ]);
                        
                        $berita_acara_id = $pdo->lastInsertId();
                        
                        log_activity('generate_berita_acara', 'berita_acara', $berita_acara_id);
                        
                        $pdo->commit();
                        $success = 'Berita acara berhasil di-generate';
                        redirect('operator-assessment-berita-acara-detail?id=' . $berita_acara_id);
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Generate berita acara error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat generate berita acara: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Generate Berita Acara';
include __DIR__ . '/../../../includes/header.php';

// Get filters
$filters = [
    'tipe_asesmen' => $_GET['tipe_asesmen'] ?? '',
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'semester' => $_GET['semester'] ?? '',
    'tanggal' => $_GET['tanggal'] ?? date('Y-m-d')
];

// Get sesi list (only for assessment)
$sql = "SELECT s.*, u.judul, u.tipe_asesmen, u.tahun_ajaran, u.semester, m.nama_mapel, k.nama_kelas,
        (SELECT COUNT(*) FROM berita_acara WHERE id_sesi = s.id) as has_berita_acara
        FROM sesi_ujian s
        INNER JOIN ujian u ON s.id_ujian = u.id
        INNER JOIN mapel m ON u.id_mapel = m.id
        LEFT JOIN jadwal_assessment ja ON s.id = ja.id_sesi
        LEFT JOIN kelas k ON ja.id_kelas = k.id
        WHERE u.tipe_asesmen IN ('sumatip_tengah_semester', 'sumatip_akhir_semester', 'sumatip_akhir_tahun')
        AND s.status IN ('aktif', 'selesai')";

$params = [];

if (!empty($filters['tipe_asesmen'])) {
    $sql .= " AND u.tipe_asesmen = ?";
    $params[] = $filters['tipe_asesmen'];
}

if (!empty($filters['tahun_ajaran'])) {
    $sql .= " AND u.tahun_ajaran = ?";
    $params[] = $filters['tahun_ajaran'];
}

if (!empty($filters['semester'])) {
    $sql .= " AND u.semester = ?";
    $params[] = $filters['semester'];
}

if (!empty($filters['tanggal'])) {
    $sql .= " AND DATE(s.waktu_mulai) = ?";
    $params[] = $filters['tanggal'];
}

$sql .= " ORDER BY s.waktu_mulai DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sesi_list = $stmt->fetchAll();

// Get tahun ajaran list - ambil dari tabel tahun_ajaran (Kelola Tahun Ajaran)
$tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
$tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Generate Berita Acara</h2>
        <p class="text-muted">Generate berita acara otomatis untuk ujian assessment</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Tipe Assessment</label>
                <select class="form-select" name="tipe_asesmen">
                    <option value="">Semua</option>
                    <option value="sumatip_tengah_semester" <?php echo $filters['tipe_asesmen'] === 'sumatip_tengah_semester' ? 'selected' : ''; ?>>Tengah Semester</option>
                    <option value="sumatip_akhir_semester" <?php echo $filters['tipe_asesmen'] === 'sumatip_akhir_semester' ? 'selected' : ''; ?>>Akhir Semester</option>
                    <option value="sumatip_akhir_tahun" <?php echo $filters['tipe_asesmen'] === 'sumatip_akhir_tahun' ? 'selected' : ''; ?>>Akhir Tahun</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran">
                    <option value="">Semua</option>
                    <?php foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo $ta; ?>" <?php echo $filters['tahun_ajaran'] === $ta ? 'selected' : ''; ?>>
                            <?php echo $ta; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="">Semua</option>
                    <option value="ganjil" <?php echo $filters['semester'] === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $filters['semester'] === 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" name="tanggal" value="<?php echo escape($filters['tanggal']); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo base_url('operator-assessment-berita-acara-generate'); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sesi List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($sesi_list)): ?>
            <p class="text-muted text-center">Tidak ada sesi ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Ujian</th>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Tanggal & Waktu</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sesi_list as $sesi): ?>
                        <tr>
                            <td><?php echo escape($sesi['judul']); ?></td>
                            <td><?php echo escape($sesi['nama_mapel']); ?></td>
                            <td><?php echo escape($sesi['nama_kelas'] ?? '-'); ?></td>
                            <td>
                                <?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?> - 
                                <?php echo format_date($sesi['waktu_selesai'], 'H:i'); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo get_sumatip_badge_class($sesi['tipe_asesmen']); ?>">
                                    <?php echo get_sumatip_badge_label($sesi['tipe_asesmen']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sesi['has_berita_acara']): ?>
                                    <span class="badge bg-success">Berita Acara Ada</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Belum Ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$sesi['has_berita_acara']): ?>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="generateBeritaAcara(<?php echo $sesi['id']; ?>)">
                                        <i class="fas fa-file-alt"></i> Generate
                                    </button>
                                <?php else: ?>
                                    <a href="<?php echo base_url('operator-assessment-berita-acara-detail?id_sesi=' . $sesi['id']); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Generate Berita Acara -->
<div class="modal fade" id="modalGenerate" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Berita Acara</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="generateForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="id_sesi" id="modal_id_sesi">
                    
                    <div class="mb-3">
                        <label class="form-label">Pengawas (pisahkan dengan koma)</label>
                        <input type="text" class="form-control" name="pengawas[]" placeholder="Nama Pengawas 1, Nama Pengawas 2">
                        <small class="text-muted">Untuk multiple pengawas, pisahkan dengan koma</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="3" placeholder="Catatan tambahan (opsional)"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Berita acara akan di-generate otomatis berdasarkan data absensi yang ada.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Generate Berita Acara
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateBeritaAcara(id_sesi) {
    document.getElementById('modal_id_sesi').value = id_sesi;
    new bootstrap.Modal(document.getElementById('modalGenerate')).show();
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>






