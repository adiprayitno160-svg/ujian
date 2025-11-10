<?php
/**
 * Bank Soal - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

$page_title = 'Bank Soal';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get mapel untuk guru ini (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Get tingkat kelas yang diajar guru ini (via ujian yang dibuat)
$stmt = $pdo->prepare("SELECT DISTINCT u.tingkat_kelas as tingkat
                      FROM ujian u
                      WHERE u.id_guru = ?
                      AND u.tingkat_kelas IS NOT NULL
                      ORDER BY u.tingkat_kelas ASC");
$stmt->execute([$_SESSION['user_id']]);
$tingkat_result = $stmt->fetchAll();
$tingkat_list = array_column($tingkat_result, 'tingkat');
$tingkat_list = array_filter($tingkat_list); // Remove null/empty values

$filter_mapel = intval($_GET['mapel'] ?? 0);
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_tipe = $_GET['tipe'] ?? '';

// Get soal dari bank_soal (hanya yang approved) dengan filter mapel dan tingkat
// Guru hanya bisa lihat soal sesuai mapel yang dia ajar dan tingkat kelas yang dia ajar
$sql = "SELECT bs.*, s.pertanyaan, s.tipe_soal, s.bobot, s.tingkat_kesulitan,
        m.nama_mapel, u.judul as judul_ujian, u2.nama as nama_guru
        FROM bank_soal bs
        INNER JOIN soal s ON bs.id_soal = s.id
        INNER JOIN ujian u ON s.id_ujian = u.id
        INNER JOIN mapel m ON bs.id_mapel = m.id
        INNER JOIN users u2 ON u.id_guru = u2.id
        INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
        WHERE bs.status = 'approved'
        AND gm.id_guru = ?";
$params = [$_SESSION['user_id']];

// Auto-filter: hanya mapel yang diajar guru ini
$mapel_ids = array_column($mapel_list, 'id');
if (empty($mapel_ids)) {
    // No mapel assigned, return empty
    $soal_list = [];
    goto render_page;
}

if ($filter_mapel) {
    $sql .= " AND bs.id_mapel = ?";
    $params[] = $filter_mapel;
} else {
    // Filter by all mapel that guru teaches
    $placeholders = implode(',', array_fill(0, count($mapel_ids), '?'));
    $sql .= " AND bs.id_mapel IN ($placeholders)";
    $params = array_merge($params, $mapel_ids);
}

if ($filter_tingkat) {
    $sql .= " AND bs.tingkat_kelas = ?";
    $params[] = $filter_tingkat;
} else {
    // Auto-filter: hanya tingkat yang diajar guru ini (jika ada)
    if (!empty($tingkat_list)) {
        $placeholders = implode(',', array_fill(0, count($tingkat_list), '?'));
        $sql .= " AND (bs.tingkat_kelas IN ($placeholders) OR bs.tingkat_kelas IS NULL)";
        $params = array_merge($params, $tingkat_list);
    }
}

if ($filter_tipe) {
    $sql .= " AND s.tipe_soal = ?";
    $params[] = $filter_tipe;
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$soal_list = $stmt->fetchAll();

render_page:
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
            <div class="col-md-3">
                <label class="form-label">Filter Mata Pelajaran</label>
                <select class="form-select" name="mapel">
                    <option value="">Semua Mapel Saya</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $filter_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Filter Tingkat Kelas</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua Tingkat</option>
                    <?php foreach ($tingkat_list as $tingkat): ?>
                        <option value="<?php echo $tingkat; ?>" <?php echo $filter_tingkat === $tingkat ? 'selected' : ''; ?>>
                            Kelas <?php echo $tingkat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Hanya menampilkan soal untuk tingkat yang Anda ajar</small>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3 d-flex align-items-end">
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
                <i class="fas fa-info-circle"></i> Tidak ada soal dalam bank soal yang sesuai dengan filter Anda.
                <br><small>Soal yang Anda buat akan otomatis masuk ke bank soal operator untuk proses approval.</small>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Soal</th>
                            <th>Mata Pelajaran</th>
                            <th>Tingkat</th>
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
                            <td>
                                <?php if ($soal['tingkat_kelas']): ?>
                                    <span class="badge bg-info">Kelas <?php echo escape($soal['tingkat_kelas']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo escape($soal['judul_ujian']); ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $soal['tipe_soal'])); ?>
                                </span>
                            </td>
                            <td><?php echo $soal['bobot']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-success" onclick="useSoal(<?php echo $soal['id_soal']; ?>)">
                                    <i class="fas fa-plus"></i> Gunakan
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

<script>
function useSoal(id_soal) {
    // Function to use soal from bank
    // This will be implemented to add soal to current ujian
    if (confirm('Gunakan soal ini untuk ujian yang sedang dibuat?')) {
        // Redirect to create soal page with soal ID
        // This requires implementation in create soal page
        alert('Fitur ini akan mengcopy soal ke ujian Anda');
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

