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
$filter_search = trim($_GET['search'] ?? '');
$filter_kesulitan = $_GET['kesulitan'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_bobot_min = $_GET['bobot_min'] ?? '';
$filter_bobot_max = $_GET['bobot_max'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';

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

if ($filter_search) {
    $sql .= " AND (s.pertanyaan LIKE ? OR s.kunci_jawaban LIKE ? OR u.judul LIKE ?)";
    $search_term = '%' . $filter_search . '%';
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($filter_kesulitan) {
    $sql .= " AND s.tingkat_kesulitan = ?";
    $params[] = $filter_kesulitan;
}

if ($filter_date_from) {
    $sql .= " AND DATE(s.created_at) >= ?";
    $params[] = $filter_date_from;
}

if ($filter_date_to) {
    $sql .= " AND DATE(s.created_at) <= ?";
    $params[] = $filter_date_to;
}

if ($filter_bobot_min !== '') {
    $sql .= " AND s.bobot >= ?";
    $params[] = floatval($filter_bobot_min);
}

if ($filter_bobot_max !== '') {
    $sql .= " AND s.bobot <= ?";
    $params[] = floatval($filter_bobot_max);
}

// Sort order validation
$valid_sort_fields = ['created_at', 'bobot', 'tingkat_kesulitan', 'pertanyaan'];
$valid_sort_orders = ['ASC', 'DESC'];
$sort_by = in_array($sort_by, $valid_sort_fields) ? $sort_by : 'created_at';
$sort_order = in_array(strtoupper($sort_order), $valid_sort_orders) ? strtoupper($sort_order) : 'DESC';

$sql .= " ORDER BY s." . $sort_by . " " . $sort_order;

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
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-search"></i> Pencarian & Filter Lanjutan</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <!-- Search Box -->
            <div class="col-md-12">
                <label class="form-label"><i class="fas fa-search"></i> Pencarian Kata Kunci</label>
                <input type="text" class="form-control" name="search" placeholder="Cari berdasarkan pertanyaan, kunci jawaban, atau judul ujian..." value="<?php echo escape($filter_search); ?>">
            </div>
            
            <!-- Basic Filters -->
            <div class="col-md-3">
                <label class="form-label">Mata Pelajaran</label>
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
                <label class="form-label">Tingkat Kelas</label>
                <select class="form-select" name="tingkat">
                    <option value="">Semua Tingkat</option>
                    <?php foreach ($tingkat_list as $tingkat): ?>
                        <option value="<?php echo $tingkat; ?>" <?php echo $filter_tingkat === $tingkat ? 'selected' : ''; ?>>
                            Kelas <?php echo $tingkat; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipe Soal</label>
                <select class="form-select" name="tipe">
                    <option value="">Semua</option>
                    <option value="pilihan_ganda" <?php echo $filter_tipe === 'pilihan_ganda' ? 'selected' : ''; ?>>Pilihan Ganda</option>
                    <option value="isian_singkat" <?php echo $filter_tipe === 'isian_singkat' ? 'selected' : ''; ?>>Isian Singkat</option>
                    <option value="benar_salah" <?php echo $filter_tipe === 'benar_salah' ? 'selected' : ''; ?>>Benar/Salah</option>
                    <option value="matching" <?php echo $filter_tipe === 'matching' ? 'selected' : ''; ?>>Matching</option>
                    <option value="esai" <?php echo $filter_tipe === 'esai' ? 'selected' : ''; ?>>Esai</option>
                    <option value="uraian_singkat" <?php echo $filter_tipe === 'uraian_singkat' ? 'selected' : ''; ?>>Uraian Singkat</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tingkat Kesulitan</label>
                <select class="form-select" name="kesulitan">
                    <option value="">Semua</option>
                    <option value="mudah" <?php echo $filter_kesulitan === 'mudah' ? 'selected' : ''; ?>>Mudah</option>
                    <option value="sedang" <?php echo $filter_kesulitan === 'sedang' ? 'selected' : ''; ?>>Sedang</option>
                    <option value="sulit" <?php echo $filter_kesulitan === 'sulit' ? 'selected' : ''; ?>>Sulit</option>
                </select>
            </div>
            
            <!-- Date Range -->
            <div class="col-md-3">
                <label class="form-label">Tanggal Dari</label>
                <input type="date" class="form-control" name="date_from" value="<?php echo escape($filter_date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Sampai</label>
                <input type="date" class="form-control" name="date_to" value="<?php echo escape($filter_date_to); ?>">
            </div>
            
            <!-- Bobot Range -->
            <div class="col-md-3">
                <label class="form-label">Bobot Minimal</label>
                <input type="number" class="form-control" name="bobot_min" step="0.1" min="0" placeholder="0" value="<?php echo escape($filter_bobot_min); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Bobot Maksimal</label>
                <input type="number" class="form-control" name="bobot_max" step="0.1" min="0" placeholder="100" value="<?php echo escape($filter_bobot_max); ?>">
            </div>
            
            <!-- Sort Options -->
            <div class="col-md-3">
                <label class="form-label">Urutkan Berdasarkan</label>
                <select class="form-select" name="sort_by">
                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Tanggal Dibuat</option>
                    <option value="bobot" <?php echo $sort_by === 'bobot' ? 'selected' : ''; ?>>Bobot</option>
                    <option value="tingkat_kesulitan" <?php echo $sort_by === 'tingkat_kesulitan' ? 'selected' : ''; ?>>Tingkat Kesulitan</option>
                    <option value="pertanyaan" <?php echo $sort_by === 'pertanyaan' ? 'selected' : ''; ?>>Pertanyaan (A-Z)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Urutan</label>
                <select class="form-select" name="sort_order">
                    <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Terbaru/Tertinggi</option>
                    <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Terlama/Terendah</option>
                </select>
            </div>
            
            <!-- Action Buttons -->
            <div class="col-md-12 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Terapkan Filter
                </button>
                <a href="<?php echo base_url('guru/soal/bank_soal.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </a>
                <div class="ms-auto">
                    <span class="badge bg-info">Total: <?php echo count($soal_list); ?> soal</span>
                </div>
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

