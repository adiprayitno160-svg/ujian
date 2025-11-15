<?php
/**
 * List SUMATIP - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

// SUMATIP hanya bisa diakses oleh operator
if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Daftar SUMATIP';
$role_css = 'guru';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'id_guru' => $_SESSION['user_id'],
    'tipe_asesmen' => $_GET['tipe_asesmen'] ?? '',
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'semester' => $_GET['semester'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Get SUMATIP list
$sumatip_list = get_sumatip_list($filters);

// Get tahun ajaran - ambil dari tabel tahun_ajaran (Kelola Tahun Ajaran)
$tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
$tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Daftar SUMATIP</h2>
            <a href="<?php echo base_url('guru-ujian-sumatip-create'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat SUMATIP Baru
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Jenis SUMATIP</label>
                <select class="form-select" name="tipe_asesmen">
                    <option value="">Semua</option>
                    <option value="sumatip_tengah_semester" <?php echo $filters['tipe_asesmen'] === 'sumatip_tengah_semester' ? 'selected' : ''; ?>>Tengah Semester</option>
                    <option value="sumatip_akhir_semester" <?php echo $filters['tipe_asesmen'] === 'sumatip_akhir_semester' ? 'selected' : ''; ?>>Akhir Semester</option>
                    <option value="sumatip_akhir_tahun" <?php echo $filters['tipe_asesmen'] === 'sumatip_akhir_tahun' ? 'selected' : ''; ?>>Akhir Tahun</option>
                </select>
            </div>
            <div class="col-md-3">
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
                    <a href="<?php echo base_url('guru-ujian-sumatip-list'); ?>" class="btn btn-secondary">Reset</a>
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
                                <a href="<?php echo base_url('guru/ujian/detail.php?id=' . $sumatip['id']); ?>" class="btn btn-sm btn-primary">
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



