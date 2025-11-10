<?php
/**
 * Create Assessment from Bank Soal - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Operator dapat mengambil soal dari bank_soal untuk membuat assessment
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
    $tipe_asesmen = sanitize($_POST['tipe_asesmen'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $semester = sanitize($_POST['semester'] ?? '');
    $tingkat_kelas = sanitize($_POST['tingkat_kelas'] ?? '');
    $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
    $selected_soal = $_POST['selected_soal'] ?? [];
    $judul = sanitize($_POST['judul'] ?? '');
    $durasi = intval($_POST['durasi'] ?? 120);
    
    if (empty($tipe_asesmen) || !$id_mapel || empty($semester) || empty($tingkat_kelas) || empty($selected_soal)) {
        $error = 'Semua field wajib harus diisi dan minimal pilih 1 soal';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check if assessment already exists
            $stmt = $pdo->prepare("SELECT id FROM ujian 
                                  WHERE tipe_asesmen = ? 
                                  AND tahun_ajaran = ? 
                                  AND semester = ? 
                                  AND id_mapel = ?
                                  AND tingkat_kelas = ?
                                  AND status != 'completed'");
            $stmt->execute([$tipe_asesmen, $tahun_ajaran, $semester, $id_mapel, $tingkat_kelas]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $error = 'Assessment dengan jenis, tahun ajaran, semester, mata pelajaran, dan tingkat kelas yang sama sudah ada';
                $pdo->rollBack();
            } else {
                // Generate judul if not provided
                if (empty($judul)) {
                    $jenis_label = [
                        'sumatip_tengah_semester' => 'SUMATIP Tengah Semester',
                        'sumatip_akhir_semester' => 'SUMATIP Akhir Semester',
                        'sumatip_akhir_tahun' => 'SUMATIP Akhir Tahun'
                    ];
                    $jenis = $jenis_label[$tipe_asesmen] ?? 'SUMATIP';
                    $judul = "$jenis - " . get_mapel($id_mapel)['nama_mapel'] . " - Kelas $tingkat_kelas";
                }
                
                $semester_label = ucfirst($semester);
                $periode = "$jenis - Semester $semester_label $tahun_ajaran";
                
                // Create assessment (ujian)
                $stmt = $pdo->prepare("INSERT INTO ujian 
                                      (judul, deskripsi, id_mapel, id_guru, durasi, tipe_asesmen, tahun_ajaran, semester, 
                                       periode_sumatip, tingkat_kelas, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
                $deskripsi = "Assessment dibuat dari bank soal oleh operator";
                $stmt->execute([
                    $judul,
                    $deskripsi,
                    $id_mapel,
                    $_SESSION['user_id'], // Operator yang membuat
                    $durasi,
                    $tipe_asesmen,
                    $tahun_ajaran,
                    $semester,
                    $periode,
                    $tingkat_kelas
                ]);
                $ujian_id = $pdo->lastInsertId();
                
                // Copy soal from bank_soal to assessment
                $urutan = 1;
                foreach ($selected_soal as $id_soal_bank) {
                    $id_soal_bank = intval($id_soal_bank);
                    
                    // Get soal from bank
                    $stmt = $pdo->prepare("SELECT s.* FROM soal s 
                                          INNER JOIN bank_soal bs ON s.id = bs.id_soal 
                                          WHERE bs.id_soal = ? AND bs.status = 'approved'");
                    $stmt->execute([$id_soal_bank]);
                    $soal = $stmt->fetch();
                    
                    if ($soal) {
                        // Insert soal to assessment
                        $stmt_insert = $pdo->prepare("INSERT INTO soal 
                                                      (id_ujian, pertanyaan, tipe_soal, opsi_json, kunci_jawaban, bobot, urutan, gambar, media_type) 
                                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_insert->execute([
                            $ujian_id,
                            $soal['pertanyaan'],
                            $soal['tipe_soal'],
                            $soal['opsi_json'],
                            $soal['kunci_jawaban'],
                            $soal['bobot'],
                            $urutan++,
                            $soal['gambar'],
                            $soal['media_type']
                        ]);
                        $new_soal_id = $pdo->lastInsertId();
                        
                        // Copy matching items if exists
                        $stmt_matching = $pdo->prepare("SELECT * FROM soal_matching WHERE id_soal = ?");
                        $stmt_matching->execute([$id_soal_bank]);
                        $matching_items = $stmt_matching->fetchAll();
                        
                        foreach ($matching_items as $item) {
                            $stmt_match_insert = $pdo->prepare("INSERT INTO soal_matching (id_soal, item_kiri, item_kanan, urutan) VALUES (?, ?, ?, ?)");
                            $stmt_match_insert->execute([$new_soal_id, $item['item_kiri'], $item['item_kanan'], $item['urutan']]);
                        }
                    }
                }
                
                // Log activity
                log_activity('create_assessment_from_bank_soal', 'ujian', $ujian_id);
                
                $pdo->commit();
                $success = 'Assessment berhasil dibuat dengan ' . count($selected_soal) . ' soal';
                redirect('operator-assessment-sumatip-detail?id=' . $ujian_id);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Create assessment from bank soal error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat assessment: ' . $e->getMessage();
        }
    }
}

$page_title = 'Buat Assessment dari Bank Soal';
include __DIR__ . '/../../../includes/header.php';

// Get filters
$filters = [
    'id_mapel' => intval($_GET['id_mapel'] ?? 0),
    'tingkat_kelas' => $_GET['tingkat_kelas'] ?? '',
    'status' => 'approved', // Only show approved soal
    'tipe_soal' => $_GET['tipe_soal'] ?? ''
];

// Get bank soal (only approved)
$bank_soal_list = get_bank_soal($filters);

// Get mapel for filter
$stmt = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC");
$mapel_list = $stmt->fetchAll();

$tahun_ajaran = get_tahun_ajaran_aktif();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Assessment dari Bank Soal</h2>
        <p class="text-muted">Pilih soal dari bank soal untuk membuat assessment</p>
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

<form method="POST" id="createAssessmentForm">
    <div class="row">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Informasi Assessment</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="tipe_asesmen" class="form-label">Tipe Assessment <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipe_asesmen" name="tipe_asesmen" required>
                            <option value="">Pilih Tipe Assessment</option>
                            <option value="sumatip_tengah_semester">Tengah Semester</option>
                            <option value="sumatip_akhir_semester">Akhir Semester</option>
                            <option value="sumatip_akhir_tahun">Akhir Tahun</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tahun_ajaran" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tahun_ajaran" name="tahun_ajaran" value="<?php echo escape($tahun_ajaran); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="semester" class="form-label">Semester <span class="text-danger">*</span></label>
                        <select class="form-select" id="semester" name="semester" required>
                            <option value="">Pilih Semester</option>
                            <option value="ganjil">Ganjil</option>
                            <option value="genap">Genap</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select class="form-select" id="id_mapel" name="id_mapel" required onchange="filterBankSoal()">
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo $filters['id_mapel'] == $mapel['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tingkat_kelas" class="form-label">Tingkat Kelas <span class="text-danger">*</span></label>
                        <select class="form-select" id="tingkat_kelas" name="tingkat_kelas" required onchange="filterBankSoal()">
                            <option value="">Pilih Tingkat Kelas</option>
                            <option value="VII" <?php echo $filters['tingkat_kelas'] === 'VII' ? 'selected' : ''; ?>>VII</option>
                            <option value="VIII" <?php echo $filters['tingkat_kelas'] === 'VIII' ? 'selected' : ''; ?>>VIII</option>
                            <option value="IX" <?php echo $filters['tingkat_kelas'] === 'IX' ? 'selected' : ''; ?>>IX</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="durasi" class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="durasi" name="durasi" value="120" min="1" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="judul" class="form-label">Judul Assessment (opsional)</label>
                        <input type="text" class="form-control" id="judul" name="judul" placeholder="Akan di-generate otomatis jika kosong">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Filter Bank Soal</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tipe Soal</label>
                        <select class="form-select" id="filter_tipe_soal" onchange="filterBankSoal()">
                            <option value="">Semua</option>
                            <option value="pilihan_ganda">Pilihan Ganda</option>
                            <option value="isian_singkat">Isian Singkat</option>
                            <option value="benar_salah">Benar/Salah</option>
                            <option value="matching">Matching</option>
                            <option value="esai">Esai</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Petunjuk:</strong> Pilih mata pelajaran dan tingkat kelas terlebih dahulu, kemudian pilih soal dari bank soal yang muncul.
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bank Soal List -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Bank Soal (Hanya yang Approved)</h5>
            <span class="badge bg-light text-dark" id="selectedCount">0 soal dipilih</span>
        </div>
        <div class="card-body">
            <?php if (empty($bank_soal_list)): ?>
                <p class="text-muted text-center">Tidak ada soal ditemukan. Pilih mata pelajaran dan tingkat kelas terlebih dahulu.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>No</th>
                                <th>Pertanyaan</th>
                                <th>Mata Pelajaran</th>
                                <th>Tingkat</th>
                                <th>Tipe Soal</th>
                                <th>Guru</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="bankSoalTableBody">
                            <?php foreach ($bank_soal_list as $index => $soal): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_soal[]" value="<?php echo $soal['id_soal']; ?>" 
                                           class="soal-checkbox" onchange="updateSelectedCount()">
                                </td>
                                <td><?php echo $index + 1; ?></td>
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
                                    <button type="button" class="btn btn-sm btn-info" onclick="viewSoal(<?php echo $soal['id_soal']; ?>)">
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
    
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
            <i class="fas fa-save"></i> Buat Assessment
        </button>
        <a href="<?php echo base_url('operator-assessment-bank-soal-list'); ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Batal
        </a>
    </div>
</form>

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
function filterBankSoal() {
    const id_mapel = document.getElementById('id_mapel').value;
    const tingkat_kelas = document.getElementById('tingkat_kelas').value;
    const tipe_soal = document.getElementById('filter_tipe_soal').value;
    
    // Reload page with filters
    const params = new URLSearchParams();
    if (id_mapel) params.set('id_mapel', id_mapel);
    if (tingkat_kelas) params.set('tingkat_kelas', tingkat_kelas);
    if (tipe_soal) params.set('tipe_soal', tipe_soal);
    
    window.location.href = window.location.pathname + '?' + params.toString();
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.soal-checkbox');
    
    checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
    });
    
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.soal-checkbox:checked');
    const count = checked.length;
    
    document.getElementById('selectedCount').textContent = count + ' soal dipilih';
    document.getElementById('submitBtn').disabled = count === 0;
}

function viewSoal(id) {
    // Load soal detail via AJAX
    fetch('<?php echo base_url('api/get_soal_detail.php'); ?>?id=' + id)
        .then(response => response.json())
        .then(data => {
            document.getElementById('soalDetail').innerHTML = data.html;
            new bootstrap.Modal(document.getElementById('modalViewSoal')).show();
        });
}

// Update count on page load
updateSelectedCount();
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>

