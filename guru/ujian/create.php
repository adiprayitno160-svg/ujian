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
        // Validasi: Guru hanya bisa membuat ujian untuk mata pelajaran yang dia ajar
        // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
        if (!guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
            $error = 'Anda tidak diizinkan membuat ujian untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
        } else {
            try {
                // Use default min_submit_minutes from config
                $min_submit_minutes = DEFAULT_MIN_SUBMIT_MINUTES;
                
                $stmt = $pdo->prepare("INSERT INTO ujian (judul, deskripsi, id_mapel, id_guru, durasi, min_submit_minutes, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, 'draft')");
                $stmt->execute([$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $durasi, $min_submit_minutes]);
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
}

$page_title = 'Buat Ujian Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

// Get mapel for this guru (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);
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
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Anda belum di-assign ke mata pelajaran.</strong><br>
        Sistem menggunakan guru mata pelajaran (bukan guru kelas). 
        Hubungi admin untuk assign mata pelajaran yang akan Anda ajarkan.
    </div>
<?php else: ?>
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle"></i> 
        <strong>Tips:</strong> Anda juga bisa membuat ujian dari arsip soal yang sudah tersedia. 
        <a href="<?php echo base_url('guru/ujian/create_from_pool.php'); ?>" class="alert-link">
            Buat Ujian dari Arsip Soal
        </a>
    </div>
    
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
                    
                    <?php if (count($mapel_list) == 1): ?>
                        <?php $single_mapel = $mapel_list[0]; ?>
                        <!-- Jika hanya 1 mata pelajaran, auto-select dan tampilkan sebagai info -->
                        <div class="alert alert-info d-flex align-items-center mb-2">
                            <i class="fas fa-book me-2"></i>
                            <strong>Mata Pelajaran:</strong> 
                            <span class="ms-2 badge bg-primary"><?php echo escape($single_mapel['nama_mapel']); ?> (<?php echo escape($single_mapel['kode_mapel']); ?>)</span>
                        </div>
                        <input type="hidden" name="id_mapel" value="<?php echo $single_mapel['id']; ?>">
                    <?php else: ?>
                        <!-- Jika lebih dari 1 mata pelajaran, tampilkan info semua mata pelajaran yang diampu -->
                        <div class="mb-2">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Mata pelajaran yang Anda ampu:</strong>
                            </small>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($mapel_list as $mapel): ?>
                                    <span class="badge bg-info"><?php echo escape($mapel['nama_mapel']); ?> (<?php echo escape($mapel['kode_mapel']); ?>)</span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <select class="form-select" id="id_mapel" name="id_mapel" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>">
                                    <?php echo escape($mapel['nama_mapel']); ?> (<?php echo escape($mapel['kode_mapel']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                    <?php endif; ?>
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
