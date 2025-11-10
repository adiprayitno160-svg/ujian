<?php
/**
 * Verifikasi Dokumen - Siswa Kelas IX
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('siswa');
check_session_timeout();

$page_title = 'Verifikasi Dokumen';
$role_css = 'siswa';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id_siswa = $_SESSION['user_id'];

// Check if student is in class IX
if (!is_siswa_kelas_IX($id_siswa)) {
    echo '<div class="alert alert-warning">Fitur ini hanya untuk siswa kelas IX</div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Check if menu is active
if (!is_menu_verifikasi_aktif($id_siswa)) {
    echo '<div class="alert alert-info">Menu verifikasi dokumen tidak aktif. Silakan hubungi admin.</div>';
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

// Check deadline
$deadline = get_verifikasi_setting('deadline_verifikasi');
$deadline_passed = false;
if ($deadline) {
    $deadline_date = new DateTime($deadline);
    $now = new DateTime();
    $deadline_passed = $now > $deadline_date;
}

// Get verifikasi data
$stmt = $pdo->prepare("SELECT * FROM verifikasi_data_siswa WHERE id_siswa = ?");
$stmt->execute([$id_siswa]);
$verifikasi_data = $stmt->fetch();

// Get all documents
$stmt = $pdo->prepare("SELECT * FROM siswa_dokumen_verifikasi WHERE id_siswa = ? ORDER BY jenis_dokumen");
$stmt->execute([$id_siswa]);
$dokumen_list = $stmt->fetchAll();

// Group documents by type
$dokumen = [
    'ijazah' => null,
    'kk' => null,
    'akte' => null
];

foreach ($dokumen_list as $doc) {
    $dokumen[$doc['jenis_dokumen']] = $doc;
}

// Get validation result
$validation = validate_all_dokumen($id_siswa);

// Check if can upload ulang
$can_upload_ulang = false;
if ($verifikasi_data) {
    $can_upload_ulang = $verifikasi_data['jumlah_upload_ulang'] < VERIFIKASI_MAX_UPLOAD_ULANG && 
                        in_array($verifikasi_data['status_overall'], ['tidak_valid', 'upload_ulang']);
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Verifikasi Dokumen Kelas IX</h2>
        <p class="text-muted">Upload dokumen Ijazah, Kartu Keluarga (KK), dan Akte Kelahiran untuk verifikasi</p>
    </div>
</div>

<?php if ($deadline_passed && $deadline): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Deadline telah lewat!</strong> Deadline upload dokumen: <?php echo format_date($deadline, 'd/m/Y'); ?>
    </div>
<?php endif; ?>

<?php if ($verifikasi_data): ?>
    <div class="alert alert-<?php echo $verifikasi_data['status_overall'] === 'valid' ? 'success' : ($verifikasi_data['status_overall'] === 'residu' ? 'danger' : 'info'); ?>">
        <strong>Status:</strong> 
        <?php
        $status_text = [
            'belum_lengkap' => 'Belum Lengkap',
            'menunggu_verifikasi' => 'Menunggu Verifikasi Admin',
            'valid' => 'Valid ✅',
            'tidak_valid' => 'Tidak Valid ❌',
            'upload_ulang' => 'Upload Ulang',
            'residu' => 'Data Residu ❌'
        ];
        echo $status_text[$verifikasi_data['status_overall']] ?? $verifikasi_data['status_overall'];
        ?>
        <?php if ($verifikasi_data['catatan_admin']): ?>
            <br><small>Catatan Admin: <?php echo escape($verifikasi_data['catatan_admin']); ?></small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($validation && !$validation['valid'] && !empty($validation['detail_ketidaksesuaian'])): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle"></i> Ketidaksesuaian Ditemukan:</h6>
        <ul class="mb-0">
            <?php foreach ($validation['detail_ketidaksesuaian'] as $detail): ?>
                <li><?php echo escape($detail['masalah']); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Ijazah -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-certificate"></i> Ijazah</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['ijazah']): ?>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php echo $dokumen['ijazah']['status_ocr'] === 'success' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($dokumen['ijazah']['status_ocr']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['ijazah']['nama_anak'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['ijazah']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
                
                <div class="upload-area" data-jenis="ijazah">
                    <input type="file" id="file_ijazah" accept=".pdf,.jpg,.jpeg,.png" 
                           class="d-none" data-jenis="ijazah">
                    <button type="button" class="btn btn-primary w-100" onclick="document.getElementById('file_ijazah').click()">
                        <i class="fas fa-upload"></i> <?php echo $dokumen['ijazah'] ? 'Upload Ulang' : 'Upload Ijazah'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Kartu Keluarga -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-id-card"></i> Kartu Keluarga</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['kk']): ?>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php echo $dokumen['kk']['status_ocr'] === 'success' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($dokumen['kk']['status_ocr']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['kk']['nama_anak'] ?? '-'); ?></span><br>
                        <strong>Nama Ayah:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['kk']['nama_ayah'] ?? '-'); ?></span><br>
                        <strong>Nama Ibu:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['kk']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['kk']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
                
                <div class="upload-area" data-jenis="kk">
                    <input type="file" id="file_kk" accept=".pdf,.jpg,.jpeg,.png" 
                           class="d-none" data-jenis="kk">
                    <button type="button" class="btn btn-info w-100" onclick="document.getElementById('file_kk').click()">
                        <i class="fas fa-upload"></i> <?php echo $dokumen['kk'] ? 'Upload Ulang' : 'Upload KK'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Akte Kelahiran -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-birthday-cake"></i> Akte Kelahiran</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['akte']): ?>
                    <div class="mb-3">
                        <strong>Status:</strong> 
                        <span class="badge bg-<?php echo $dokumen['akte']['status_ocr'] === 'success' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($dokumen['akte']['status_ocr']); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['akte']['nama_anak'] ?? '-'); ?></span><br>
                        <strong>Nama Ayah:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['akte']['nama_ayah'] ?? '-'); ?></span><br>
                        <strong>Nama Ibu:</strong><br>
                        <span class="text-muted"><?php echo escape($dokumen['akte']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['akte']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
                
                <div class="upload-area" data-jenis="akte">
                    <input type="file" id="file_akte" accept=".pdf,.jpg,.jpeg,.png" 
                           class="d-none" data-jenis="akte">
                    <button type="button" class="btn btn-success w-100" onclick="document.getElementById('file_akte').click()">
                        <i class="fas fa-upload"></i> <?php echo $dokumen['akte'] ? 'Upload Ulang' : 'Upload Akte'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Validation Summary -->
<?php if ($validation && count($dokumen_list) >= 3): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-<?php echo $validation['valid'] ? 'success' : 'warning'; ?> text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle"></i> Hasil Validasi</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <strong>Nama Anak:</strong>
                        <span class="badge bg-<?php echo $validation['kesesuaian']['nama_anak'] === 'sesuai' ? 'success' : 'danger'; ?>">
                            <?php echo $validation['kesesuaian']['nama_anak'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Nama Ayah:</strong>
                        <span class="badge bg-<?php echo $validation['kesesuaian']['nama_ayah'] === 'sesuai' ? 'success' : 'danger'; ?>">
                            <?php echo $validation['kesesuaian']['nama_ayah'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <strong>Nama Ibu:</strong>
                        <span class="badge bg-<?php echo $validation['kesesuaian']['nama_ibu'] === 'sesuai' ? 'success' : 'danger'; ?>">
                            <?php echo $validation['kesesuaian']['nama_ibu'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Loading Modal -->
<div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Sedang memindai dokumen...</p>
            </div>
        </div>
    </div>
</div>

<script>
const ocrScanUrl = '<?php echo base_url("api/ocr_scan.php"); ?>';
const uploadUrl = '<?php echo base_url("api/verifikasi_upload.php"); ?>';

// Handle file upload
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
        const file = this.files[0];
        const jenis = this.dataset.jenis;
        
        if (!file) return;
        
        // Show loading modal
        const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
        loadingModal.show();
        
        // Step 1: Scan with OCR
        const formData = new FormData();
        formData.append('file', file);
        formData.append('jenis_dokumen', jenis);
        
        fetch(ocrScanUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                loadingModal.hide();
                alert('Error: ' + data.message);
                return;
            }
            
            // Step 2: Upload document with OCR data
            const uploadFormData = new FormData();
            uploadFormData.append('action', 'upload');
            uploadFormData.append('file', file);
            uploadFormData.append('jenis_dokumen', jenis);
            uploadFormData.append('ocr_data', JSON.stringify(data.data));
            uploadFormData.append('is_upload_ulang', '<?php echo $can_upload_ulang ? "1" : "0"; ?>');
            
            return fetch(uploadUrl, {
                method: 'POST',
                body: uploadFormData
            });
        })
        .then(response => response.json())
        .then(data => {
            loadingModal.hide();
            
            if (data.success) {
                alert('Dokumen berhasil diupload!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            loadingModal.hide();
            console.error('Error:', error);
            alert('Terjadi kesalahan saat upload dokumen');
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

