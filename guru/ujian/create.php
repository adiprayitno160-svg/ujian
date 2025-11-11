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

// Get template if provided
$template_id = intval($_GET['template_id'] ?? 0);
$template = null;
if ($template_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ujian_templates WHERE id = ? AND created_by = ?");
    $stmt->execute([$template_id, $_SESSION['user_id']]);
    $template = $stmt->fetch();
}

// Handle POST first (before any output)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $durasi = intval($_POST['durasi'] ?? 0);
    
    // Get settings from POST (if provided) or use template/default values
    $min_submit_minutes = isset($_POST['min_submit_minutes']) ? intval($_POST['min_submit_minutes']) : ($template ? intval($template['min_submit_minutes'] ?? DEFAULT_MIN_SUBMIT_MINUTES) : DEFAULT_MIN_SUBMIT_MINUTES);
    $acak_soal = isset($_POST['acak_soal']) ? 1 : ($template ? intval($template['acak_soal'] ?? 1) : 1);
    $acak_opsi = isset($_POST['acak_opsi']) ? 1 : ($template ? intval($template['acak_opsi'] ?? 1) : 1);
    $anti_contek_enabled = isset($_POST['anti_contek_enabled']) ? 1 : ($template ? intval($template['anti_contek_enabled'] ?? 1) : 1);
    $ai_correction_enabled = isset($_POST['ai_correction_enabled']) ? 1 : ($template ? intval($template['ai_correction_enabled'] ?? 0) : 0);
    $show_review_mode = 1; // Default enabled
    
    if (empty($judul) || !$id_mapel || $durasi <= 0) {
        $error = 'Judul, mata pelajaran, dan durasi harus diisi';
    } else {
        // Validasi: Guru hanya bisa membuat ujian untuk mata pelajaran yang dia ajar
        // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
        if (!guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
            $error = 'Anda tidak diizinkan membuat ujian untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO ujian (judul, deskripsi, id_mapel, id_guru, durasi, min_submit_minutes, acak_soal, acak_opsi, anti_contek_enabled, ai_correction_enabled, show_review_mode, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')");
                $stmt->execute([$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $durasi, $min_submit_minutes, $acak_soal, $acak_opsi, $anti_contek_enabled, $ai_correction_enabled, $show_review_mode]);
                $ujian_id = $pdo->lastInsertId();
                
                log_activity('create_ujian', 'ujian', $ujian_id);
                
                // Redirect to detail page (before any output)
                redirect('guru/ujian/detail.php?id=' . $ujian_id);
            } catch (PDOException $e) {
                error_log("Create ujian error: " . $e->getMessage());
                
                // Provide more specific error messages
                $error_code = $e->getCode();
                if ($error_code == 23000) {
                    $error = 'Terjadi kesalahan: Data duplikat atau tidak valid. Pastikan semua field diisi dengan benar.';
                } elseif ($error_code == 22001) {
                    $error = 'Terjadi kesalahan: Data terlalu panjang. Pastikan judul dan deskripsi tidak melebihi batas yang diizinkan.';
                } elseif ($error_code == 42000 || $error_code == '42S02') {
                    $error = 'Terjadi kesalahan: Masalah dengan database. Silakan hubungi administrator.';
                } else {
                    $error = 'Terjadi kesalahan saat membuat ujian. Pastikan semua field wajib diisi dengan benar.';
                    if (strpos($e->getMessage(), 'judul') !== false) {
                        $error .= ' Periksa judul ujian.';
                    } elseif (strpos($e->getMessage(), 'id_mapel') !== false) {
                        $error .= ' Periksa mata pelajaran yang dipilih.';
                    } elseif (strpos($e->getMessage(), 'durasi') !== false) {
                        $error .= ' Periksa durasi ujian (harus lebih dari 0).';
                    }
                }
            } catch (Exception $e) {
                error_log("Create ujian error: " . $e->getMessage());
                $error = 'Terjadi kesalahan saat membuat ujian: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$page_title = 'Buat Ulangan Harian Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

// Get mapel for this guru (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Ulangan Harian Baru</h2>
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
                    <label for="judul" class="form-label">Judul Ulangan Harian <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" required 
                           placeholder="Contoh: Ulangan Harian Matematika">
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" 
                              placeholder="Deskripsi ujian..."><?php echo $template ? escape($template['description'] ?? '') : ''; ?></textarea>
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
                           value="<?php echo $template ? $template['durasi'] : '90'; ?>"
                           placeholder="Contoh: 90">
                </div>
                
                <div class="mb-3">
                    <label for="min_submit_minutes" class="form-label">Minimum Waktu Submit (menit)</label>
                    <input type="number" class="form-control" id="min_submit_minutes" name="min_submit_minutes" 
                           value="<?php echo $template ? $template['min_submit_minutes'] : DEFAULT_MIN_SUBMIT_MINUTES; ?>" 
                           min="0" max="60">
                    <small class="text-muted">Siswa harus menunggu minimal X menit setelah mulai ujian sebelum bisa submit. 0 = tidak ada batasan</small>
                </div>
                
                <div class="mb-3">
                    <h6>Pengaturan Soal</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="acak_soal" name="acak_soal" 
                               <?php echo ($template ? ($template['acak_soal'] ?? 1) : 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="acak_soal">
                            Acak Urutan Soal
                        </label>
                        <small class="d-block text-muted">Soal akan diacak untuk setiap siswa</small>
                    </div>
                    
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="acak_opsi" name="acak_opsi" 
                               <?php echo ($template ? ($template['acak_opsi'] ?? 1) : 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="acak_opsi">
                            Acak Urutan Opsi Jawaban
                        </label>
                        <small class="d-block text-muted">Opsi jawaban akan diacak untuk setiap siswa</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <h6>Pengaturan Keamanan</h6>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="anti_contek_enabled" name="anti_contek_enabled" 
                               <?php echo ($template ? ($template['anti_contek_enabled'] ?? 1) : 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="anti_contek_enabled">
                            Aktifkan Fitur Anti Contek
                        </label>
                        <small class="d-block text-muted">Mendeteksi tab switching, copy-paste, dan aktivitas mencurigakan</small>
                    </div>
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
