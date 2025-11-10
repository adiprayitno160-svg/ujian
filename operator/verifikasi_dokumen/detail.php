<?php
/**
 * Detail Verifikasi Dokumen - Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk operator melihat detail berkas verifikasi (read-only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

$page_title = 'Detail Verifikasi Dokumen';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id_siswa = intval($_GET['id'] ?? 0);

if (!$id_siswa) {
    redirect('operator-verifikasi-dokumen-index');
}

// Get student data
$stmt = $pdo->prepare("SELECT u.*, k.nama_kelas FROM users u
                      INNER JOIN user_kelas uk ON u.id = uk.id_user
                      INNER JOIN kelas k ON uk.id_kelas = k.id
                      WHERE u.id = ? AND u.role = 'siswa'");
$stmt->execute([$id_siswa]);
$siswa = $stmt->fetch();

if (!$siswa) {
    redirect('operator-verifikasi-dokumen-index');
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
        <a href="<?php echo base_url('operator-verifikasi-dokumen-index'); ?>" class="btn btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h2 class="fw-bold">Detail Verifikasi Dokumen</h2>
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
                            ($verifikasi_data['status_overall'] === 'residu' ? 'dark' : 
                            ($verifikasi_data['status_overall'] === 'tidak_valid' ? 'danger' : 'warning')); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $verifikasi_data['status_overall'])); ?>
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Belum Ada Data</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Upload Ulang</th>
                <td>
                    <?php echo $verifikasi_data['jumlah_upload_ulang'] ?? 0; ?> / <?php echo VERIFIKASI_MAX_UPLOAD_ULANG; ?>
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
                    <?php 
                    $file_readable = is_file_verifikasi_readable($dokumen['ijazah']['file_path']);
                    if (!$file_readable): 
                    ?>
                        <div class="alert alert-danger small mb-3">
                            <i class="fas fa-exclamation-triangle"></i> <strong>File Bermasalah:</strong> File tidak bisa dibaca
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $dokumen['ijazah']['status_verifikasi'] === 'valid' ? 'success' : 
                            ($dokumen['ijazah']['status_verifikasi'] === 'tidak_valid' ? 'danger' : 'warning'); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $dokumen['ijazah']['status_verifikasi'])); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['ijazah']['nama_anak'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <?php if ($file_readable): ?>
                            <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['ijazah']['file_path']); ?>" 
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Lihat Dokumen
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-exclamation-triangle"></i> File Tidak Tersedia
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($dokumen['ijazah']['keterangan_admin']): ?>
                        <div class="alert alert-info small mb-0">
                            <strong>Catatan:</strong><br>
                            <?php echo escape($dokumen['ijazah']['keterangan_admin']); ?>
                        </div>
                    <?php endif; ?>
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
                    <?php 
                    $file_readable = is_file_verifikasi_readable($dokumen['kk']['file_path']);
                    if (!$file_readable): 
                    ?>
                        <div class="alert alert-danger small mb-3">
                            <i class="fas fa-exclamation-triangle"></i> <strong>File Bermasalah:</strong> File tidak bisa dibaca
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $dokumen['kk']['status_verifikasi'] === 'valid' ? 'success' : 
                            ($dokumen['kk']['status_verifikasi'] === 'tidak_valid' ? 'danger' : 'warning'); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $dokumen['kk']['status_verifikasi'])); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_anak'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Ayah:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_ayah'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Ibu:</strong><br>
                        <span><?php echo escape($dokumen['kk']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <?php if ($file_readable): ?>
                            <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['kk']['file_path']); ?>" 
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Lihat Dokumen
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-exclamation-triangle"></i> File Tidak Tersedia
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($dokumen['kk']['keterangan_admin']): ?>
                        <div class="alert alert-info small mb-0">
                            <strong>Catatan:</strong><br>
                            <?php echo escape($dokumen['kk']['keterangan_admin']); ?>
                        </div>
                    <?php endif; ?>
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
                    <?php 
                    $file_readable = is_file_verifikasi_readable($dokumen['akte']['file_path']);
                    if (!$file_readable): 
                    ?>
                        <div class="alert alert-danger small mb-3">
                            <i class="fas fa-exclamation-triangle"></i> <strong>File Bermasalah:</strong> File tidak bisa dibaca
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <strong>Status:</strong><br>
                        <span class="badge bg-<?php 
                            echo $dokumen['akte']['status_verifikasi'] === 'valid' ? 'success' : 
                            ($dokumen['akte']['status_verifikasi'] === 'tidak_valid' ? 'danger' : 'warning'); 
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $dokumen['akte']['status_verifikasi'])); ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Anak:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_anak'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Ayah:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_ayah'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <strong>Nama Ibu:</strong><br>
                        <span><?php echo escape($dokumen['akte']['nama_ibu'] ?? '-'); ?></span>
                    </div>
                    <div class="mb-3">
                        <?php if ($file_readable): ?>
                            <a href="<?php echo base_url('assets/uploads/verifikasi/' . $dokumen['akte']['file_path']); ?>" 
                               target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> Lihat Dokumen
                            </a>
                        <?php else: ?>
                            <button class="btn btn-sm btn-secondary" disabled>
                                <i class="fas fa-exclamation-triangle"></i> File Tidak Tersedia
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if ($dokumen['akte']['keterangan_admin']): ?>
                        <div class="alert alert-info small mb-0">
                            <strong>Catatan:</strong><br>
                            <?php echo escape($dokumen['akte']['keterangan_admin']); ?>
                        </div>
                    <?php endif; ?>
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

<!-- Catatan Admin -->
<?php if ($verifikasi_data && $verifikasi_data['catatan_admin']): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-sticky-note"></i> Catatan Admin</h5>
    </div>
    <div class="card-body">
        <p><?php echo nl2br(escape($verifikasi_data['catatan_admin'])); ?></p>
        <?php if ($verifikasi_data['diverifikasi_oleh']): ?>
            <small class="text-muted">
                Diverifikasi oleh: <?php 
                    $stmt_user = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
                    $stmt_user->execute([$verifikasi_data['diverifikasi_oleh']]);
                    $user = $stmt_user->fetch();
                    echo escape($user['nama'] ?? '-');
                ?> 
                pada <?php echo format_date($verifikasi_data['tanggal_verifikasi'], 'd/m/Y H:i'); ?>
            </small>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- History -->
<?php if (!empty($history)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Riwayat</h5>
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
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo format_date($h['created_at'], 'd/m/Y H:i'); ?></td>
                            <td><?php echo escape($h['action']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo escape($h['status_sebelum'] ?? '-'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo escape($h['status_sesudah'] ?? '-'); ?></span>
                            </td>
                            <td><?php echo escape($h['user_nama'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

