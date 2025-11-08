<?php
/**
 * Create Ujian - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$error = '';
$success = '';

// Handle POST first (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $durasi = intval($_POST['durasi'] ?? 0);
    
    if (empty($judul) || !$id_mapel || $durasi <= 0) {
        $error = 'Judul, mata pelajaran, dan durasi harus diisi';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO ujian (judul, deskripsi, id_mapel, id_guru, durasi, status) 
                                  VALUES (?, ?, ?, ?, ?, 'draft')");
            $stmt->execute([$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $durasi]);
            $ujian_id = $pdo->lastInsertId();
            
            log_activity('create_ujian', 'ujian', $ujian_id);
            
            // Redirect to detail page (before any output)
            redirect('guru/ujian/detail.php?id=' . $ujian_id);
        } catch (PDOException $e) {
            error_log("Create ujian error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat ujian';
        }
    }
}

$page_title = 'Buat Ujian Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

// Get mapel for this guru
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Ujian Baru</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (empty($mapel_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Anda belum di-assign ke mata pelajaran. Hubungi admin untuk assign mata pelajaran.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Ujian <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" required 
                           placeholder="Contoh: Ujian Tengah Semester Matematika">
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" 
                              placeholder="Deskripsi ujian..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    <select class="form-select" id="id_mapel" name="id_mapel" required>
                        <option value="">Pilih Mata Pelajaran</option>
                        <?php foreach ($mapel_list as $mapel): ?>
                            <option value="<?php echo $mapel['id']; ?>">
                                <?php echo escape($mapel['nama_mapel']); ?> (<?php echo escape($mapel['kode_mapel']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="durasi" class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="durasi" name="durasi" required min="1" 
                           placeholder="Contoh: 90">
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat Ujian
                    </button>
                    <a href="<?php echo base_url('guru/ujian/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
