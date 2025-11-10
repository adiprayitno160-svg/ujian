<?php
/**
 * List SUMATIP - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
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

$page_title = 'Daftar SUMATIP';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'tipe_asesmen' => $_GET['tipe_asesmen'] ?? '',
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'semester' => $_GET['semester'] ?? '',
    'id_mapel' => intval($_GET['id_mapel'] ?? 0),
    'status' => $_GET['status'] ?? '',
    'tingkat_kelas' => $_GET['tingkat_kelas'] ?? ''
];

// Get SUMATIP list
$sumatip_list = get_sumatip_list($filters);

// Get mapel for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

// Get tahun ajaran
$tahun_ajaran_list = $pdo->query("SELECT DISTINCT tahun_ajaran FROM ujian WHERE tahun_ajaran IS NOT NULL ORDER BY tahun_ajaran DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar SUMATIP</h2>
            <small class="text-muted">Operator dapat melihat dan mengelola SUMATIP yang dibuat oleh guru</small>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Jenis SUMATIP</label>
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
                <label class="form-label">Mata Pelajaran</label>
                <select class="form-select" name="id_mapel">
                    <option value="">Semua</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filters['id_mapel'] == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua</option>
                    <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $filters['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo base_url('operator-assessment-sumatip-list'); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- SUMATIP List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($sumatip_list)): ?>
            <p class="text-muted text-center">Tidak ada SUMATIP ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Jenis</th>
                            <th>Mata Pelajaran</th>
                            <th>Periode</th>
                            <th>Guru</th>
                            <th>Status</th>
                            <th>Total Soal</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sumatip_list as $sumatip): ?>
                        <tr>
                            <td><?php echo escape($sumatip['judul']); ?></td>
                            <td>
                                <span class="badge <?php echo get_sumatip_badge_class($sumatip['tipe_asesmen']); ?>">
                                    <?php echo get_sumatip_badge_label($sumatip['tipe_asesmen']); ?>
                                </span>
                                <?php if ($sumatip['is_mandatory']): ?>
                                    <span class="badge bg-danger">Wajib</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($sumatip['nama_mapel']); ?></td>
                            <td><?php echo escape($sumatip['periode_sumatip'] ?? '-'); ?></td>
                            <td><?php echo escape($sumatip['nama_guru']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $sumatip['status'] === 'published' ? 'success' : 
                                        ($sumatip['status'] === 'completed' ? 'info' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($sumatip['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $sumatip['total_soal']; ?></td>
                            <td>
                                <a href="<?php echo base_url('operator-assessment-sumatip-detail?id=' . $sumatip['id']); ?>" class="btn btn-sm btn-primary">
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

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

