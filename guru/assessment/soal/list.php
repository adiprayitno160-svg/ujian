<?php
/**
 * List Assessment Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk guru melihat daftar soal assessment yang telah dibuat
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

// Check if guru has permission to create assessment soal
if (!can_create_assessment_soal()) {
    redirect('guru/index.php');
}

$page_title = 'Daftar Soal Assessment';
$role_css = 'guru';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filter_tipe = $_GET['tipe_asesmen'] ?? '';
$filter_mapel = intval($_GET['id_mapel'] ?? 0);
$filter_semester = $_GET['semester'] ?? '';
$filter_tingkat = $_GET['tingkat_kelas'] ?? '';

// Get tahun ajaran aktif
$tahun_ajaran = get_tahun_ajaran_aktif();

// Build query
$sql = "SELECT s.*, u.tipe_asesmen, u.tahun_ajaran, u.semester, u.tingkat_kelas, 
        u.judul as judul_ujian, m.nama_mapel
        FROM soal s
        INNER JOIN ujian u ON s.id_ujian = u.id
        INNER JOIN mapel m ON u.id_mapel = m.id
        WHERE u.id_guru = ? 
        AND u.tipe_asesmen IS NOT NULL
        AND u.tahun_ajaran = ?";
$params = [$_SESSION['user_id'], $tahun_ajaran];

if ($filter_tipe) {
    $sql .= " AND u.tipe_asesmen = ?";
    $params[] = $filter_tipe;
}

if ($filter_mapel) {
    $sql .= " AND u.id_mapel = ?";
    $params[] = $filter_mapel;
}

if ($filter_semester) {
    $sql .= " AND u.semester = ?";
    $params[] = $filter_semester;
}

if ($filter_tingkat) {
    $sql .= " AND u.tingkat_kelas = ?";
    $params[] = $filter_tingkat;
}

$sql .= " ORDER BY u.created_at DESC, s.urutan ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$soal_list = $stmt->fetchAll();

// Get mapel untuk filter
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Group soal by ujian
$soal_by_ujian = [];
foreach ($soal_list as $soal) {
    $ujian_key = $soal['id_ujian'];
    if (!isset($soal_by_ujian[$ujian_key])) {
        $soal_by_ujian[$ujian_key] = [
            'ujian' => [
                'id' => $soal['id_ujian'],
                'judul' => $soal['judul_ujian'],
                'tipe_asesmen' => $soal['tipe_asesmen'],
                'semester' => $soal['semester'],
                'tingkat_kelas' => $soal['tingkat_kelas'],
                'nama_mapel' => $soal['nama_mapel']
            ],
            'soal' => []
        ];
    }
    $soal_by_ujian[$ujian_key]['soal'][] = $soal;
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar Soal Assessment</h2>
            <a href="<?php echo base_url('guru-assessment-soal-create'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Soal Baru
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tipe Assessment</label>
                <select class="form-select" name="tipe_asesmen">
                    <option value="">Semua</option>
                    <option value="sumatip_tengah_semester" <?php echo $filter_tipe === 'sumatip_tengah_semester' ? 'selected' : ''; ?>>Tengah Semester</option>
                    <option value="sumatip_akhir_semester" <?php echo $filter_tipe === 'sumatip_akhir_semester' ? 'selected' : ''; ?>>Akhir Semester</option>
                    <option value="sumatip_akhir_tahun" <?php echo $filter_tipe === 'sumatip_akhir_tahun' ? 'selected' : ''; ?>>Akhir Tahun</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mata Pelajaran</label>
                <select class="form-select" name="id_mapel">
                    <option value="">Semua</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester">
                    <option value="">Semua</option>
                    <option value="ganjil" <?php echo $filter_semester === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $filter_semester === 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat Kelas</label>
                <select class="form-select" name="tingkat_kelas">
                    <option value="">Semua</option>
                    <option value="VII" <?php echo $filter_tingkat === 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $filter_tingkat === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $filter_tingkat === 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo base_url('guru-assessment-soal-list'); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Soal List -->
<?php if (empty($soal_by_ujian)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
            <p class="text-muted">Belum ada soal assessment</p>
            <a href="<?php echo base_url('guru-assessment-soal-create'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Soal Pertama
            </a>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($soal_by_ujian as $group): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0"><?php echo escape($group['ujian']['judul']); ?></h5>
                        <small>
                            <?php 
                            $tipe_label = [
                                'sumatip_tengah_semester' => 'Tengah Semester',
                                'sumatip_akhir_semester' => 'Akhir Semester',
                                'sumatip_akhir_tahun' => 'Akhir Tahun'
                            ];
                            echo $tipe_label[$group['ujian']['tipe_asesmen']] ?? $group['ujian']['tipe_asesmen'];
                            ?> - 
                            Semester <?php echo ucfirst($group['ujian']['semester']); ?> - 
                            Kelas <?php echo escape($group['ujian']['tingkat_kelas']); ?>
                        </small>
                    </div>
                    <span class="badge bg-light text-dark"><?php echo count($group['soal']); ?> Soal</span>
                </div>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php foreach ($group['soal'] as $index => $soal): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <strong>Soal #<?php echo $index + 1; ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?></span>
                                    <p class="mb-0 mt-2"><?php echo escape(substr(strip_tags($soal['pertanyaan']), 0, 150)); ?><?php echo strlen(strip_tags($soal['pertanyaan'])) > 150 ? '...' : ''; ?></p>
                                </div>
                                <div>
                                    <a href="<?php echo base_url('guru/soal/edit.php?id=' . $soal['id']); ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>


