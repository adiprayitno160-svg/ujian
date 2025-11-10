<?php
/**
 * Bank Soal List - Operator Assessment
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

$page_title = 'Bank Soal';
include __DIR__ . '/../../../includes/header.php';

global $pdo;

// Get filters
$filters = [
    'id_mapel' => intval($_GET['id_mapel'] ?? 0),
    'tingkat_kelas' => $_GET['tingkat_kelas'] ?? '',
    'status' => $_GET['status'] ?? '',
    'tipe_soal' => $_GET['tipe_soal'] ?? ''
];

// Get bank soal
$bank_soal_list = get_bank_soal($filters);

// Get mapel for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Bank Soal</h2>
            <a href="<?php echo base_url('operator-assessment-bank-soal-create-assessment'); ?>" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Buat Assessment dari Bank Soal
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
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
                <label class="form-label">Tingkat Kelas</label>
                <select class="form-select" name="tingkat_kelas">
                    <option value="">Semua</option>
                    <option value="VII" <?php echo $filters['tingkat_kelas'] === 'VII' ? 'selected' : ''; ?>>VII</option>
                    <option value="VIII" <?php echo $filters['tingkat_kelas'] === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                    <option value="IX" <?php echo $filters['tingkat_kelas'] === 'IX' ? 'selected' : ''; ?>>IX</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua</option>
                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $filters['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipe Soal</label>
                <select class="form-select" name="tipe_soal">
                    <option value="">Semua</option>
                    <option value="pilihan_ganda" <?php echo $filters['tipe_soal'] === 'pilihan_ganda' ? 'selected' : ''; ?>>Pilihan Ganda</option>
                    <option value="isian_singkat" <?php echo $filters['tipe_soal'] === 'isian_singkat' ? 'selected' : ''; ?>>Isian Singkat</option>
                    <option value="esai" <?php echo $filters['tipe_soal'] === 'esai' ? 'selected' : ''; ?>>Esai</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="<?php echo base_url('operator-assessment-bank-soal-list'); ?>" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bank Soal List -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($bank_soal_list)): ?>
            <p class="text-muted text-center">Tidak ada soal ditemukan</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Pertanyaan</th>
                            <th>Mata Pelajaran</th>
                            <th>Tingkat</th>
                            <th>Tipe Soal</th>
                            <th>Guru</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bank_soal_list as $soal): ?>
                        <tr>
                            <td><?php echo escape(substr(strip_tags($soal['pertanyaan']), 0, 100)); ?>...</td>
                            <td><?php echo escape($soal['nama_mapel']); ?></td>
                            <td><?php echo escape($soal['tingkat_kelas'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?>
                                </span>
                            </td>
                            <td><?php echo escape($soal['nama_guru']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $soal['status'] === 'approved' ? 'success' : 
                                        ($soal['status'] === 'rejected' ? 'danger' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst($soal['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($soal['status'] === 'pending'): ?>
                                    <a href="<?php echo base_url('operator-assessment-bank-soal-approve?id=' . $soal['id_soal']); ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="<?php echo base_url('operator-assessment-bank-soal-approve?id=' . $soal['id_soal'] . '&action=reject'); ?>" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-info" onclick="viewSoal(<?php echo $soal['id_soal']; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal View Soal -->
<div class="modal fade" id="modalViewSoal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="soalDetail">
                <!-- Soal detail will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewSoal(id) {
    // Load soal detail via AJAX
    fetch('<?php echo base_url('api/get_soal_detail.php'); ?>?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('soalDetail').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('modalViewSoal')).show();
        });
}
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



