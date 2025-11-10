<?php
/**
 * Check Plagiarisme - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/plagiarisme_check.php';

require_role('guru');
check_session_timeout();

$page_title = 'Check Plagiarisme';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$ujian_id = intval($_GET['ujian_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM ujian WHERE id = ? AND id_guru = ?");
$stmt->execute([$ujian_id, $_SESSION['user_id']]);
$ujian = $stmt->fetch();

if (!$ujian) {
    redirect('guru/ujian/list.php');
}

$error = '';
$success = '';
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check') {
    try {
        require_once __DIR__ . '/../../includes/plagiarisme_check.php';
        $include_sections = isset($_POST['include_sections']) && $_POST['include_sections'] === '1';
        $threshold = floatval($_POST['threshold'] ?? 60);
        $results = batch_check_plagiarisme($ujian_id, $threshold, $include_sections);
        $success = 'Plagiarisme check selesai';
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}

// Get existing results if any
if (empty($results)) {
    $stmt = $pdo->prepare("SELECT pc.*, 
                          js1.jawaban as jawaban_1, js2.jawaban as jawaban_2,
                          u1.nama as nama_siswa1, u2.nama as nama_siswa2,
                          s.pertanyaan
                          FROM plagiarisme_check pc
                          LEFT JOIN jawaban_siswa js1 ON pc.id_siswa1 = js1.id_siswa AND pc.id_soal = js1.id_soal AND pc.id_ujian = js1.id_ujian
                          LEFT JOIN jawaban_siswa js2 ON pc.id_siswa2 = js2.id_siswa AND pc.id_soal = js2.id_soal AND pc.id_ujian = js2.id_ujian
                          LEFT JOIN users u1 ON pc.id_siswa1 = u1.id
                          LEFT JOIN users u2 ON pc.id_siswa2 = u2.id
                          LEFT JOIN soal s ON pc.id_soal = s.id
                          WHERE pc.id_ujian = ? 
                          ORDER BY pc.similarity_score DESC");
    $stmt->execute([$ujian_id]);
    $existing_results = $stmt->fetchAll();
    
    if (!empty($existing_results)) {
        // Parse section_analysis if available
        foreach ($existing_results as &$result) {
            if (isset($result['section_analysis']) && !empty($result['section_analysis'])) {
                $result['section_analysis'] = json_decode($result['section_analysis'], true);
            }
        }
        $results = $existing_results;
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Check Plagiarisme</h2>
                <p class="text-muted mb-0">Ujian: <?php echo escape($ujian['judul']); ?></p>
            </div>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="check">
                <div class="d-flex gap-2 align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="include_sections" value="1" id="includeSections" checked>
                        <label class="form-check-label" for="includeSections">
                            Analisis per Bagian
                        </label>
                    </div>
                    <select name="threshold" class="form-select" style="width: auto;">
                        <option value="60">Threshold: 60%</option>
                        <option value="70">Threshold: 70%</option>
                        <option value="80">Threshold: 80%</option>
                        <option value="90">Threshold: 90%</option>
                    </select>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Check Plagiarisme
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<?php if (!empty($results)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-list"></i> Hasil Check Plagiarisme</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Siswa 1</th>
                        <th>Siswa 2</th>
                        <th>Soal</th>
                        <th>Similarity Score</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): 
                        $id_siswa1 = $result['id_siswa1'] ?? null;
                        $id_siswa2 = $result['id_siswa2'] ?? null;
                        $id_soal = $result['id_soal'] ?? null;
                        
                        $nama_siswa1 = $result['nama_siswa1'] ?? '-';
                        $nama_siswa2 = $result['nama_siswa2'] ?? '-';
                        $pertanyaan_soal = $result['pertanyaan'] ?? '-';
                        
                        $similarity = floatval($result['similarity_score'] ?? 0);
                        // If similarity is stored as percentage (0-100), convert to decimal
                        if ($similarity > 1) {
                            $similarity = $similarity / 100;
                        }
                        $is_suspicious = $similarity >= 0.8;
                    ?>
                    <tr class="<?php echo $is_suspicious ? 'table-danger' : ($similarity >= 0.6 ? 'table-warning' : ''); ?>">
                        <td><?php echo escape($nama_siswa1); ?></td>
                        <td><?php echo escape($nama_siswa2); ?></td>
                        <td>
                            <small><?php echo escape(substr($pertanyaan_soal, 0, 50)); ?>...</small>
                        </td>
                        <td>
                            <strong class="<?php echo $is_suspicious ? 'text-danger' : ($similarity >= 0.6 ? 'text-warning' : 'text-success'); ?>">
                                <?php echo number_format($similarity * 100, 2); ?>%
                            </strong>
                        </td>
                        <td>
                            <?php if ($is_suspicious): ?>
                                <span class="badge bg-danger">Sangat Mencurigakan</span>
                            <?php elseif ($similarity >= 0.6): ?>
                                <span class="badge bg-warning">Mencurigakan</span>
                            <?php else: ?>
                                <span class="badge bg-success">Normal</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info" 
                                    onclick="showDetail(<?php echo htmlspecialchars(json_encode($result, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">
                                <i class="fas fa-eye"></i> Detail
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Plagiarisme</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showDetail(result) {
    let similarityScore = result.similarity_score;
    if (similarityScore > 1) {
        similarityScore = similarityScore / 100;
    }
    
    let content = `
        <div class="mb-3">
            <strong>Similarity Score:</strong> 
            <span class="badge bg-${similarityScore >= 0.8 ? 'danger' : (similarityScore >= 0.6 ? 'warning' : 'success')}">
                ${(similarityScore * 100).toFixed(2)}%
            </span>
        </div>
        <div class="mb-3">
            <strong>Jawaban Siswa 1:</strong>
            <div class="p-3 bg-light rounded" style="max-height: 200px; overflow-y: auto;">${escapeHtml(result.jawaban_1 || '-')}</div>
        </div>
        <div class="mb-3">
            <strong>Jawaban Siswa 2:</strong>
            <div class="p-3 bg-light rounded" style="max-height: 200px; overflow-y: auto;">${escapeHtml(result.jawaban_2 || '-')}</div>
        </div>
    `;
    
    // Add section analysis if available
    if (result.section_analysis && result.section_analysis.sections && result.section_analysis.sections.length > 0) {
        content += `
            <div class="mb-3">
                <strong>Analisis per Bagian:</strong>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Bagian</th>
                                <th>Similarity</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
        `;
        
        result.section_analysis.sections.forEach(function(section, index) {
            const sectionScore = section.similarity / 100;
            const statusClass = sectionScore >= 0.8 ? 'danger' : (sectionScore >= 0.6 ? 'warning' : 'success');
            const statusText = sectionScore >= 0.8 ? 'Sangat Mencurigakan' : (sectionScore >= 0.6 ? 'Mencurigakan' : 'Normal');
            
            content += `
                <tr>
                    <td>
                        <small>${escapeHtml(section.section_text)}</small>
                    </td>
                    <td>
                        <span class="badge bg-${statusClass}">${section.similarity.toFixed(2)}%</span>
                    </td>
                    <td>${statusText}</td>
                </tr>
            `;
        });
        
        content += `
                        </tbody>
                    </table>
                </div>
                <small class="text-muted">
                    Total Bagian: ${result.section_analysis.total_sections_str1} (Siswa 1) vs ${result.section_analysis.total_sections_str2} (Siswa 2)
                </small>
            </div>
        `;
    }
    
    document.getElementById('detailContent').innerHTML = content;
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
}

function escapeHtml(text) {
    if (!text) return '-';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; }).replace(/\n/g, '<br>');
}
</script>
<?php else: ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Belum ada hasil check plagiarisme. Klik tombol "Check Plagiarisme" untuk memulai.
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

