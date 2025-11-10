<?php
/**
 * Data Residu - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Data Residu - Verifikasi Dokumen';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';

global $pdo;

// Get residu data
$tahun_ajaran = get_tahun_ajaran_aktif();
$query = "SELECT u.id, u.nama, u.username as nis, 
          vds.*, k.nama_kelas
          FROM users u
          INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          INNER JOIN kelas k ON uk.id_kelas = k.id
          INNER JOIN verifikasi_data_siswa vds ON u.id = vds.id_siswa
          WHERE u.role = 'siswa' AND k.tingkat = 'IX' AND vds.status_overall = 'residu'
          ORDER BY u.nama ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$tahun_ajaran]);
$residu_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <a href="<?php echo base_url('admin/verifikasi_dokumen/index.php'); ?>" class="btn btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
        <h3 class="fw-bold">Data Residu</h3>
        <p class="text-muted">Siswa dengan data tidak cocok antara dokumen</p>
    </div>
</div>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 
    <strong>Data Residu:</strong> Data residu adalah data yang tidak cocok antara dokumen 1 dengan yang lain (ijazah, KK, akte). 
    Siswa yang setelah upload ulang (maksimal 1x) masih memiliki nama yang tidak sesuai antara dokumen akan masuk ke data residu. 
    Silakan hubungi siswa untuk penanganan lebih lanjut.
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Kesesuaian</th>
                        <th>Upload Ulang</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($residu_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada data residu</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($residu_list as $siswa): ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                                <td>
                                    <small>
                                        Anak: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_anak'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                            <?php echo $siswa['kesesuaian_nama_anak'] === 'sesuai' ? '✓' : '✗'; ?>
                                        </span><br>
                                        Ayah: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_ayah'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                            <?php echo $siswa['kesesuaian_nama_ayah'] === 'sesuai' ? '✓' : '✗'; ?>
                                        </span><br>
                                        Ibu: <span class="badge bg-<?php echo $siswa['kesesuaian_nama_ibu'] === 'sesuai' ? 'success' : 'danger'; ?>">
                                            <?php echo $siswa['kesesuaian_nama_ibu'] === 'sesuai' ? '✓' : '✗'; ?>
                                        </span>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $siswa['jumlah_upload_ulang'] ?? 0; ?> / <?php echo VERIFIKASI_MAX_UPLOAD_ULANG; ?>
                                </td>
                                <td>
                                    <?php if ($siswa['catatan_admin']): ?>
                                        <small><?php echo escape(substr($siswa['catatan_admin'], 0, 50)); ?>...</small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo base_url('admin/verifikasi_dokumen/detail.php?id=' . $siswa['id']); ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

