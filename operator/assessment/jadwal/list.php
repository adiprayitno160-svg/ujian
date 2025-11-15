<?php
/**
 * Jadwal Assessment List - Operator Assessment
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

$page_title = 'Jadwal Assessment';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif(),
    'tingkat' => $_GET['tingkat'] ?? '',
    'id_kelas' => intval($_GET['id_kelas'] ?? 0),
    'status' => $_GET['status'] ?? 'aktif',
    'is_susulan' => $_GET['is_susulan'] ?? ''
];

// Get jadwal
$jadwal_list = get_jadwal_assessment($filters);

// Get kelas
$sql_kelas = "SELECT * FROM kelas WHERE tahun_ajaran = ?";
$params_kelas = [$filters['tahun_ajaran']];
if ($filters['tingkat']) {
    $sql_kelas .= " AND tingkat = ?";
    $params_kelas[] = $filters['tingkat'];
}
$sql_kelas .= " ORDER BY nama_kelas ASC";
$stmt = $pdo->prepare($sql_kelas);
$stmt->execute($params_kelas);
$kelas_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Jadwal Assessment</h2>
            <a href="<?php echo base_url('operator-assessment-jadwal-create'); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Buat Jadwal
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran">
                    <?php 
                    // Get tahun ajaran - ambil dari tabel tahun_ajaran (Kelola Tahun Ajaran)
                    $tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
                    $tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');
                    foreach ($tahun_ajaran_list as $ta): ?>
                        <option value="<?php echo $ta; ?>" <?php echo $filters['tahun_ajaran'] === $ta ? 'selected' : ''; ?>>
                            <?php echo $ta; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tingkat</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua</option>
                    <option value="VII" <?php echo $filters['tingkat'] === 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $filters['tingkat'] === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $filters['tingkat'] === 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas">
                    <option value="">Semua</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>" <?php echo $filters['id_kelas'] == $kelas['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua</option>
                    <option value="aktif" <?php echo $filters['status'] === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="nonaktif" <?php echo $filters['status'] === 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                    <option value="selesai" <?php echo $filters['status'] === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis</label>
                <select class="form-select" name="is_susulan">
                    <option value="">Semua</option>
                    <option value="0" <?php echo $filters['is_susulan'] === '0' ? 'selected' : ''; ?>>Utama</option>
                    <option value="1" <?php echo $filters['is_susulan'] === '1' ? 'selected' : ''; ?>>Susulan</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo base_url('operator-assessment-jadwal-list'); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Jadwal List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($jadwal_list)): ?>
            <p class="text-muted text-center">Tidak ada jadwal ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mata Pelajaran</th>
                            <th>Kelas</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Status</th>
                            <th>Jenis</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jadwal_list as $jadwal): ?>
                        <tr>
                            <td><?php echo escape($jadwal['nama_mapel']); ?></td>
                            <td><?php echo escape($jadwal['nama_kelas']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($jadwal['tanggal'])); ?></td>
                            <td><?php echo date('H:i', strtotime($jadwal['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal['waktu_selesai'])); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $jadwal['status'] === 'aktif' ? 'success' : 
                                        ($jadwal['status'] === 'selesai' ? 'info' : 'secondary'); 
                                ?>">
                                    <?php echo ucfirst($jadwal['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($jadwal['is_susulan']): ?>
                                    <span class="badge bg-warning">Susulan</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">Utama</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($jadwal['status'] === 'nonaktif' || $jadwal['status'] === 'selesai'): ?>
                                    <a href="<?php echo base_url('operator-assessment-jadwal-susulan?id_jadwal=' . $jadwal['id']); ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-redo"></i> Buat Susulan
                                    </a>
                                <?php endif; ?>
                                <?php if ($jadwal['status'] === 'aktif'): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deactivateJadwal(<?php echo $jadwal['id']; ?>)">
                                        <i class="fas fa-ban"></i> Nonaktifkan
                                    </button>
                                <?php endif; ?>
                                <?php if ($jadwal['id_sesi']): ?>
                                    <a href="<?php echo base_url('operator/sesi/manage.php?id=' . $jadwal['id_sesi']); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-cog"></i> Manage Sesi
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

<script>
function deactivateJadwal(id) {
    if (confirm('Apakah Anda yakin ingin menonaktifkan jadwal ini?')) {
        window.location.href = '<?php echo base_url('operator-assessment-jadwal-deactivate'); ?>?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>








