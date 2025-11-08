<?php
/**
 * Bank Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Bank Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get mapel
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();

$filter_mapel = intval($_GET['mapel'] ?? 0);
$filter_tipe = $_GET['tipe'] ?? '';

// Get soal
$sql = "SELECT s.*, u.judul as judul_ujian, m.nama_mapel 
        FROM soal s
        INNER JOIN ujian u ON s.id_ujian = u.id
        INNER JOIN mapel m ON u.id_mapel = m.id
        WHERE u.id_guru = ?";
$params = [$_SESSION['user_id']];

if ($filter_mapel) {
    $sql .= " AND u.id_mapel = ?";
    $params[] = $filter_mapel;
}

if ($filter_tipe) {
    $sql .= " AND s.tipe_soal = ?";
    $params[] = $filter_tipe;
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$soal_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Bank Soal</h2>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Filter Mata Pelajaran</label>
                <select class="form-select" name="mapel">
                    <option value="">Semua</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Filter Tipe Soal</label>
                <select class="form-select" name="tipe">
                    <option value="">Semua</option>
                    <option value="pilihan_ganda" <?php echo $filter_tipe === 'pilihan_ganda' ? 'selected' : ''; ?>>Pilihan Ganda</option>
                    <option value="isian_singkat" <?php echo $filter_tipe === 'isian_singkat' ? 'selected' : ''; ?>>Isian Singkat</option>
                    <option value="benar_salah" <?php echo $filter_tipe === 'benar_salah' ? 'selected' : ''; ?>>Benar/Salah</option>
                    <option value="matching" <?php echo $filter_tipe === 'matching' ? 'selected' : ''; ?>>Matching</option>
                    <option value="esai" <?php echo $filter_tipe === 'esai' ? 'selected' : ''; ?>>Esai</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="<?php echo base_url('guru/soal/bank_soal.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if (empty($soal_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tidak ada soal dalam bank soal
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Soal</th>
                            <th>Mata Pelajaran</th>
                            <th>Ujian</th>
                            <th>Tipe</th>
                            <th>Bobot</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($soal_list as $soal): ?>
                        <tr>
                            <td>
                                <div style="max-width: 400px;">
                                    <?php echo escape(substr($soal['pertanyaan'], 0, 100)); ?>
                                    <?php echo strlen($soal['pertanyaan']) > 100 ? '...' : ''; ?>
                                </div>
                            </td>
                            <td><?php echo escape($soal['nama_mapel']); ?></td>
                            <td><?php echo escape($soal['judul_ujian']); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?>
                                </span>
                            </td>
                            <td><?php echo $soal['bobot']; ?></td>
                            <td>
                                <a href="<?php echo base_url('guru/soal/edit.php?id=' . $soal['id']); ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>

