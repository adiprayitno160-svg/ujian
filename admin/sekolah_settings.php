<?php
/**
 * Sekolah Settings - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Pengaturan Sekolah';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';
$sekolah = get_sekolah_info();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_sekolah = sanitize($_POST['nama_sekolah'] ?? '');
    $alamat = sanitize($_POST['alamat'] ?? '');
    $no_telp = sanitize($_POST['no_telp'] ?? '');
    $website = sanitize($_POST['website'] ?? '');
    $pemerintah_kabupaten = sanitize($_POST['pemerintah_kabupaten'] ?? 'PEMERINTAH KABUPATEN TULUNGAGUNG');
    $dinas_pendidikan = sanitize($_POST['dinas_pendidikan'] ?? 'DINAS PENDIDIKAN');
    $nss = sanitize($_POST['nss'] ?? '');
    $npsn = sanitize($_POST['npsn'] ?? '');
    $kode_pos = sanitize($_POST['kode_pos'] ?? '');
    $kepala_sekolah = sanitize($_POST['kepala_sekolah'] ?? '');
    $nip_kepala_sekolah = sanitize($_POST['nip_kepala_sekolah'] ?? '');
    $siswa_raport_menu_visible = isset($_POST['siswa_raport_menu_visible']) ? 1 : 0;
    
    if (empty($nama_sekolah)) {
        $error = 'Nama sekolah harus diisi';
    } else {
        try {
            // Handle logo upload
            $logo = $sekolah['logo'] ?? null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_file($_FILES['logo'], UPLOAD_PROFILE, ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old logo if exists
                    if ($logo && file_exists(UPLOAD_PROFILE . '/' . $logo)) {
                        delete_file(UPLOAD_PROFILE . '/' . $logo);
                    }
                    $logo = $upload_result['filename'];
                }
            }
            
            // Handle logo kop surat upload
            $logo_kop_surat = $sekolah['logo_kop_surat'] ?? null;
            if (isset($_FILES['logo_kop_surat']) && $_FILES['logo_kop_surat']['error'] === UPLOAD_ERR_OK) {
                $upload_result = upload_file($_FILES['logo_kop_surat'], UPLOAD_PROFILE, ALLOWED_IMAGE_TYPES);
                if ($upload_result['success']) {
                    // Delete old logo kop surat if exists
                    if ($logo_kop_surat && file_exists(UPLOAD_PROFILE . '/' . $logo_kop_surat)) {
                        delete_file(UPLOAD_PROFILE . '/' . $logo_kop_surat);
                    }
                    $logo_kop_surat = $upload_result['filename'];
                }
            }
            
            if ($sekolah) {
                // Update
                $stmt = $pdo->prepare("UPDATE sekolah SET nama_sekolah = ?, alamat = ?, no_telp = ?, website = ?, logo = ?, pemerintah_kabupaten = ?, dinas_pendidikan = ?, nss = ?, npsn = ?, kode_pos = ?, logo_kop_surat = ?, kepala_sekolah = ?, nip_kepala_sekolah = ? WHERE id = ?");
                $stmt->execute([$nama_sekolah, $alamat, $no_telp, $website, $logo, $pemerintah_kabupaten, $dinas_pendidikan, $nss, $npsn, $kode_pos, $logo_kop_surat, $kepala_sekolah, $nip_kepala_sekolah, $sekolah['id']]);
            } else {
                // Insert
                $stmt = $pdo->prepare("INSERT INTO sekolah (nama_sekolah, alamat, no_telp, website, logo, pemerintah_kabupaten, dinas_pendidikan, nss, npsn, kode_pos, logo_kop_surat, kepala_sekolah, nip_kepala_sekolah) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nama_sekolah, $alamat, $no_telp, $website, $logo, $pemerintah_kabupaten, $dinas_pendidikan, $nss, $npsn, $kode_pos, $logo_kop_surat, $kepala_sekolah, $nip_kepala_sekolah]);
            }
            
            // Update system settings for raport menu visibility
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->execute(['siswa_raport_menu_visible', $siswa_raport_menu_visible, 'Tampilkan menu raport di halaman siswa (1=visible, 0=hidden)', $siswa_raport_menu_visible]);
            
            $success = 'Pengaturan sekolah berhasil disimpan';
            log_activity('update_sekolah', 'sekolah', $sekolah['id'] ?? null);
            $sekolah = get_sekolah_info(); // Refresh data
        } catch (PDOException $e) {
            error_log("Update sekolah error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat menyimpan';
        }
    }
}

// Get raport menu visibility setting
$siswa_raport_menu_visible = 1; // Default visible
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'siswa_raport_menu_visible'");
    $stmt->execute();
    $setting = $stmt->fetch();
    if ($setting) {
        $siswa_raport_menu_visible = intval($setting['setting_value']);
    }
} catch (PDOException $e) {
    error_log("Error getting raport menu setting: " . $e->getMessage());
}
?>


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

<div class="row">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-school"></i> Informasi Sekolah</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="nama_sekolah" class="form-label">Nama Sekolah <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_sekolah" name="nama_sekolah" 
                               value="<?php echo escape($sekolah['nama_sekolah'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea class="form-control" id="alamat" name="alamat" rows="3"><?php echo escape($sekolah['alamat'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="no_telp" class="form-label">No. Telepon</label>
                        <input type="text" class="form-control" id="no_telp" name="no_telp" 
                               value="<?php echo escape($sekolah['no_telp'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="website" class="form-label">Website</label>
                        <input type="url" class="form-control" id="website" name="website" 
                               value="<?php echo escape($sekolah['website'] ?? ''); ?>" placeholder="https://example.com">
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo Sekolah</label>
                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF, WebP. Max: 2MB</small>
                        <?php if ($sekolah && !empty($sekolah['logo'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo asset_url('uploads/profile/' . $sekolah['logo']); ?>" alt="Logo" height="100" class="img-thumbnail">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="fas fa-file-alt"></i> Kop Surat Raport</h6>
                    
                    <div class="mb-3">
                        <label for="pemerintah_kabupaten" class="form-label">Pemerintah Kabupaten</label>
                        <input type="text" class="form-control" id="pemerintah_kabupaten" name="pemerintah_kabupaten" 
                               value="<?php echo escape($sekolah['pemerintah_kabupaten'] ?? 'PEMERINTAH KABUPATEN TULUNGAGUNG'); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="dinas_pendidikan" class="form-label">Dinas Pendidikan</label>
                        <input type="text" class="form-control" id="dinas_pendidikan" name="dinas_pendidikan" 
                               value="<?php echo escape($sekolah['dinas_pendidikan'] ?? 'DINAS PENDIDIKAN'); ?>">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="nss" class="form-label">NSS</label>
                                <input type="text" class="form-control" id="nss" name="nss" 
                                       value="<?php echo escape($sekolah['nss'] ?? ''); ?>" placeholder="201051602053">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="npsn" class="form-label">NPSN</label>
                                <input type="text" class="form-control" id="npsn" name="npsn" 
                                       value="<?php echo escape($sekolah['npsn'] ?? ''); ?>" placeholder="20515534">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="kode_pos" class="form-label">Kode Pos</label>
                        <input type="text" class="form-control" id="kode_pos" name="kode_pos" 
                               value="<?php echo escape($sekolah['kode_pos'] ?? ''); ?>" placeholder="66235">
                    </div>
                    
                    <div class="mb-3">
                        <label for="logo_kop_surat" class="form-label">Logo Kop Surat</label>
                        <input type="file" class="form-control" id="logo_kop_surat" name="logo_kop_surat" accept="image/*">
                        <small class="text-muted">Logo untuk kop surat raport (biasanya berbeda dengan logo sekolah). Format: JPG, PNG, GIF, WebP. Max: 2MB</small>
                        <?php if ($sekolah && !empty($sekolah['logo_kop_surat'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo asset_url('uploads/profile/' . $sekolah['logo_kop_surat']); ?>" alt="Logo Kop Surat" height="100" class="img-thumbnail">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="fas fa-user-tie"></i> Kepala Sekolah</h6>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="kepala_sekolah" class="form-label">Nama Kepala Sekolah</label>
                                <input type="text" class="form-control" id="kepala_sekolah" name="kepala_sekolah" 
                                       value="<?php echo escape($sekolah['kepala_sekolah'] ?? ''); ?>" placeholder="Nama Kepala Sekolah">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="nip_kepala_sekolah" class="form-label">NIP Kepala Sekolah</label>
                                <input type="text" class="form-control" id="nip_kepala_sekolah" name="nip_kepala_sekolah" 
                                       value="<?php echo escape($sekolah['nip_kepala_sekolah'] ?? ''); ?>" placeholder="NIP">
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="mb-3"><i class="fas fa-cog"></i> Pengaturan Menu Siswa</h6>
                    
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_raport_menu_visible" 
                                   name="siswa_raport_menu_visible" <?php echo $siswa_raport_menu_visible ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_raport_menu_visible">
                                <strong>Tampilkan Menu Raport di Halaman Siswa</strong>
                            </label>
                        </div>
                        <small class="text-muted">Jika dicentang, menu raport akan ditampilkan di halaman siswa. Jika tidak dicentang, menu raport akan disembunyikan.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
