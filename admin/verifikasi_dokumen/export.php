<?php
/**
 * Export Laporan Verifikasi Dokumen - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/verifikasi_functions.php';

require_role('admin');
check_session_timeout();

global $pdo;

// Get all data
$tahun_ajaran = get_tahun_ajaran_aktif();
$query = "SELECT u.id, u.nama, u.username as nis, 
          vds.*, k.nama_kelas
          FROM users u
          INNER JOIN user_kelas uk ON u.id = uk.id_user AND uk.tahun_ajaran = ?
          INNER JOIN kelas k ON uk.id_kelas = k.id
          LEFT JOIN verifikasi_data_siswa vds ON u.id = vds.id_siswa
          WHERE u.role = 'siswa' AND k.tingkat = 'IX'
          ORDER BY u.nama ASC";

$stmt = $pdo->prepare($query);
$stmt->execute([$tahun_ajaran]);
$siswa_list = $stmt->fetchAll();

// Get all documents for detail
foreach ($siswa_list as &$siswa) {
    $stmt = $pdo->prepare("SELECT * FROM siswa_dokumen_verifikasi WHERE id_siswa = ?");
    $stmt->execute([$siswa['id']]);
    $dokumen_list = $stmt->fetchAll();
    
    $siswa['dokumen'] = [
        'ijazah' => null,
        'kk' => null,
        'akte' => null
    ];
    
    foreach ($dokumen_list as $doc) {
        $siswa['dokumen'][$doc['jenis_dokumen']] = $doc;
    }
    
    // Get detail ketidaksesuaian
    if ($siswa['detail_ketidaksesuaian']) {
        $siswa['detail_ketidaksesuaian'] = json_decode($siswa['detail_ketidaksesuaian'], true);
    }
}

// Export format
$format = $_GET['format'] ?? 'html';

if ($format === 'excel') {
    // Export to Excel (CSV)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="verifikasi_dokumen_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, [
        'NIS',
        'Nama',
        'Kelas',
        'Status',
        'Nama Anak (Ijazah)',
        'Nama Anak (KK)',
        'Nama Anak (Akte)',
        'Kesesuaian Nama Anak',
        'Nama Ayah (KK)',
        'Nama Ayah (Akte)',
        'Kesesuaian Nama Ayah',
        'Nama Ibu (KK)',
        'Nama Ibu (Akte)',
        'Kesesuaian Nama Ibu',
        'Detail Ketidaksesuaian',
        'Upload Ulang',
        'Catatan Admin'
    ]);
    
    // Data
    foreach ($siswa_list as $siswa) {
        $detail = '';
        if (!empty($siswa['detail_ketidaksesuaian']) && is_array($siswa['detail_ketidaksesuaian'])) {
            $details = [];
            foreach ($siswa['detail_ketidaksesuaian'] as $d) {
                $details[] = $d['masalah'];
            }
            $detail = implode('; ', $details);
        }
        
        fputcsv($output, [
            $siswa['nis'],
            $siswa['nama'],
            $siswa['nama_kelas'] ?? '-',
            $siswa['status_overall'] ?? 'belum_lengkap',
            $siswa['nama_anak_ijazah'] ?? '-',
            $siswa['nama_anak_kk'] ?? '-',
            $siswa['nama_anak_akte'] ?? '-',
            $siswa['kesesuaian_nama_anak'] ?? 'belum_dicek',
            $siswa['nama_ayah_kk'] ?? '-',
            $siswa['nama_ayah_akte'] ?? '-',
            $siswa['kesesuaian_nama_ayah'] ?? 'belum_dicek',
            $siswa['nama_ibu_kk'] ?? '-',
            $siswa['nama_ibu_akte'] ?? '-',
            $siswa['kesesuaian_nama_ibu'] ?? 'belum_dicek',
            $detail,
            ($siswa['jumlah_upload_ulang'] ?? 0) . ' / ' . VERIFIKASI_MAX_UPLOAD_ULANG,
            $siswa['catatan_admin'] ?? '-'
        ]);
    }
    
    fclose($output);
    exit;
}

// HTML export
$page_title = 'Export Laporan Verifikasi Dokumen';
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h3 class="fw-bold">Export Laporan Verifikasi Dokumen</h3>
        <p class="text-muted">Laporan lengkap verifikasi dokumen siswa kelas IX</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <a href="?format=excel" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export ke Excel (CSV)
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Status</th>
                        <th>Nama Anak</th>
                        <th>Nama Ayah</th>
                        <th>Nama Ibu</th>
                        <th>Kesesuaian</th>
                        <th>Detail Ketidaksesuaian</th>
                        <th>Upload Ulang</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswa_list as $siswa): ?>
                        <tr>
                            <td><?php echo escape($siswa['nis']); ?></td>
                            <td><?php echo escape($siswa['nama']); ?></td>
                            <td><?php echo escape($siswa['nama_kelas'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    $status = $siswa['status_overall'] ?? 'belum_lengkap';
                                    echo $status === 'valid' ? 'success' : ($status === 'residu' ? 'dark' : 'warning'); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    Ijazah: <?php echo escape($siswa['nama_anak_ijazah'] ?? '-'); ?><br>
                                    KK: <?php echo escape($siswa['nama_anak_kk'] ?? '-'); ?><br>
                                    Akte: <?php echo escape($siswa['nama_anak_akte'] ?? '-'); ?>
                                </small>
                            </td>
                            <td>
                                <small>
                                    KK: <?php echo escape($siswa['nama_ayah_kk'] ?? '-'); ?><br>
                                    Akte: <?php echo escape($siswa['nama_ayah_akte'] ?? '-'); ?>
                                </small>
                            </td>
                            <td>
                                <small>
                                    KK: <?php echo escape($siswa['nama_ibu_kk'] ?? '-'); ?><br>
                                    Akte: <?php echo escape($siswa['nama_ibu_akte'] ?? '-'); ?>
                                </small>
                            </td>
                            <td>
                                <small>
                                    Anak: <?php echo $siswa['kesesuaian_nama_anak'] === 'sesuai' ? '✓' : '✗'; ?><br>
                                    Ayah: <?php echo $siswa['kesesuaian_nama_ayah'] === 'sesuai' ? '✓' : '✗'; ?><br>
                                    Ibu: <?php echo $siswa['kesesuaian_nama_ibu'] === 'sesuai' ? '✓' : '✗'; ?>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($siswa['detail_ketidaksesuaian']) && is_array($siswa['detail_ketidaksesuaian'])): ?>
                                    <ul class="small mb-0">
                                        <?php foreach ($siswa['detail_ketidaksesuaian'] as $detail): ?>
                                            <li><?php echo escape($detail['masalah']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $siswa['jumlah_upload_ulang'] ?? 0; ?> / <?php echo VERIFIKASI_MAX_UPLOAD_ULANG; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>



