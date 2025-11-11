<?php
/**
 * Detail Verifikasi Dokumen - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Detail Verifikasi Dokumen';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id_siswa = intval($_GET['id'] ?? 0);

if (!$id_siswa) {
    redirect('admin/verifikasi_dokumen/index.php');
}

// Get student data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? AND u.role = 'siswa'");
$stmt->execute([$id_siswa]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('admin/verifikasi_dokumen/index.php');
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

// Get history
$stmt = $pdo->prepare("SELECT vh.*, u.nama as user_nama FROM verifikasi_data_history vh
                      LEFT JOIN users u ON vh.dilakukan_oleh = u.id
                      WHERE vh.id_siswa = ?
                      ORDER BY vh.created_at DESC");
$stmt->execute([$id_siswa]);
$history = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <a href="<?php echo base_url('admin/verifikasi_dokumen/index.php'); ?>" class="btn btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h3 class="fw-bold">Detail Verifikasi Dokumen</h3>
        <p class="text-muted"><?php echo escape($siswa['nama']); ?> - <?php echo escape($siswa['nama_kelas']); ?></p>
    </div>
</div>

<!-- Student Info -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5>Informasi Siswa</h5>
        <table class="table table-borderless">
            <tr>
                <th width="150">NIS</th>
                <td><?php echo escape($siswa['username']); ?></td>
            </tr>
            <tr>
                <th>Nama</th>
                <td><?php echo escape($siswa['nama']); ?></td>
            </tr>
            <tr>
                <th>Kelas</th>
                <td><?php echo escape($siswa['nama_kelas']); ?></td>
            </tr>
            <tr>
                <th>Status Verifikasi</th>
                <td>
                    <?php if ($verifikasi_data): ?>
                        <span class="badge bg-<?php 
                            echo $verifikasi_data['status_overall'] === 'valid' ? 'success' : 
                            ($verifikasi_data['status_overall'] === 'residu' ? 'dark' : 'warning'); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $verifikasi_data['status_overall'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Belum Ada Data</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- Documents -->
<div class="row g-4 mb-4">
    <!-- Ijazah -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-certificate"></i> Ijazah</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['ijazah']): ?>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['ijazah']['nama_anak'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['ijazah']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- KK -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-id-card"></i> Kartu Keluarga</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['kk']): ?>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_anak'] ?? '-'); ?></span><br>
                        <strong>Nama Ayah:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_ayah'] ?? '-'); ?></span><br>
                        <strong>Nama Ibu:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['kk']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Akte -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-birthday-cake"></i> Akte Kelahiran</h5>
            </div>
            <div class="card-body">
                <?php if ($dokumen['akte']): ?>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_anak'] ?? '-'); ?></span><br>
                        <strong>Nama Ayah:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_ayah'] ?? '-'); ?></span><br>
                        <strong>Nama Ibu:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['akte']['file_path']); ?>" 
                           target="_blank" class="btn btn-sm btn-success">
                            <i class="fas fa-eye"></i> Lihat Dokumen
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Belum diupload</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Validation Result -->
<?php if ($validation && count($dokumen_list) >= 3): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-<?php echo $validation['valid'] ? 'success' : 'danger'; ?> text-white">
        <h5 class="mb-0"><i class="fas fa-check-circle"></i> Hasil Validasi</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <strong>Nama Anak:</strong><br>
                Ijazah: <?php echo escape($validation['data']['nama_anak_ijazah'] ?? '-'); ?><br>
                KK: <?php echo escape($validation['data']['nama_anak_kk'] ?? '-'); ?><br>
                Akte: <?php echo escape($validation['data']['nama_anak_akte'] ?? '-'); ?><br>
                <span class="badge bg-<?php echo $validation['kesesuaian']['nama_anak'] === 'sesuai' ? 'success' : 'danger'; ?>">
                    <?php echo $validation['kesesuaian']['nama_anak'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                </span>
            </div>
            <div class="col-md-4">
                <strong>Nama Ayah:</strong><br>
                KK: <?php echo escape($validation['data']['nama_ayah_kk'] ?? '-'); ?><br>
                Akte: <?php echo escape($validation['data']['nama_ayah_akte'] ?? '-'); ?><br>
                <span class="badge bg-<?php echo $validation['kesesuaian']['nama_ayah'] === 'sesuai' ? 'success' : 'danger'; ?>">
                    <?php echo $validation['kesesuaian']['nama_ayah'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                </span>
            </div>
            <div class="col-md-4">
                <strong>Nama Ibu:</strong><br>
                KK: <?php echo escape($validation['data']['nama_ibu_kk'] ?? '-'); ?><br>
                Akte: <?php echo escape($validation['data']['nama_ibu_akte'] ?? '-'); ?><br>
                <span class="badge bg-<?php echo $validation['kesesuaian']['nama_ibu'] === 'sesuai' ? 'success' : 'danger'; ?>">
                    <?php echo $validation['kesesuaian']['nama_ibu'] === 'sesuai' ? 'Sesuai ✅' : 'Tidak Sesuai ❌'; ?>
                </span>
            </div>
        </div>
        
        <?php if (!empty($validation['detail_ketidaksesuaian'])): ?>
            <hr>
            <h6>Detail Ketidaksesuaian:</h6>
            <ul>
                <?php foreach ($validation['detail_ketidaksesuaian'] as $detail): ?>
                    <li>
                        <strong><?php echo escape($detail['field']); ?> - <?php echo escape($detail['dokumen']); ?>:</strong><br>
                        <?php echo escape($detail['masalah']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Verification Form -->
<?php if ($verifikasi_data && count($dokumen_list) >= 3): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-check-double"></i> Verifikasi Admin</h5>
    </div>
    <div class="card-body">
        <form id="verifikasiForm">
            <input type="hidden" name="id_siswa" value="<?php echo $id_siswa; ?>">
            
            <div class="mb-3">
                <label class="form-label">Status Verifikasi</label>
                <div>
                    <button type="button" class="btn btn-success" onclick="setVerifikasi('valid')">
                        <i class="fas fa-check"></i> Set Valid
                    </button>
                    <button type="button" class="btn btn-danger" onclick="setVerifikasi('tidak_valid')">
                        <i class="fas fa-times"></i> Set Tidak Valid
                    </button>
                    <?php if ($verifikasi_data['jumlah_upload_ulang'] >= VERIFIKASI_MAX_UPLOAD_ULANG): ?>
                    <button type="button" class="btn btn-dark" onclick="setResidu()">
                        <i class="fas fa-ban"></i> Set Residu
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="catatan" class="form-label">Catatan</label>
                <textarea class="form-control" id="catatan" name="catatan" rows="3" 
                          placeholder="Catatan untuk siswa..."><?php echo escape($verifikasi_data['catatan_admin'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary" id="submitBtn" style="display:none;">
                <i class="fas fa-save"></i> Simpan Verifikasi
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- History -->
<?php if (!empty($history)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Riwayat Perubahan</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Action</th>
                        <th>Status Sebelum</th>
                        <th>Status Sesudah</th>
                        <th>Dilakukan Oleh</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo format_date($h['created_at'], 'd/m/Y H:i'); ?></td>
                            <td><?php echo escape($h['action']); ?></td>
                            <td><?php echo escape($h['status_sebelum'] ?? '-'); ?></td>
                            <td><?php echo escape($h['status_sesudah'] ?? '-'); ?></td>
                            <td><?php echo escape($h['user_nama'] ?? '-'); ?></td>
                            <td><?php echo escape($h['keterangan'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let currentStatus = '';

function setVerifikasi(status) {
    currentStatus = status;
    document.getElementById('submitBtn').style.display = 'block';
}

function setResidu() {
    if (confirm('Set data ke residu? Tindakan ini tidak dapat dibatalkan.')) {
        const formData = new FormData();
        formData.append('action', 'set_residu');
        formData.append('id_siswa', <?php echo $id_siswa; ?>);
        formData.append('catatan', document.getElementById('catatan').value);
        
        fetch('<?php echo base_url("api/verifikasi_verify.php"); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Data berhasil di-set ke residu');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

document.getElementById('verifikasiForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!currentStatus) {
        alert('Pilih status verifikasi terlebih dahulu');
        return;
    }
    
    const formData = new FormData(this);
    formData.append('action', 'verify');
    formData.append('status', currentStatus);
    
    fetch('<?php echo base_url("api/verifikasi_verify.php"); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Verifikasi berhasil disimpan');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


