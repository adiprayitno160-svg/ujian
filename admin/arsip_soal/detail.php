<?php
/**
 * Detail Arsip Soal - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role(['admin', 'operator']);
check_session_timeout();

global $pdo;

$pool_id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT ps.*, m.nama_mapel, u.nama as created_by_name
                       FROM arsip_soal ps
                       INNER JOIN mapel m ON ps.id_mapel = m.id
                       INNER JOIN users u ON ps.created_by = u.id
                       WHERE ps.id = ?");
$stmt->execute([$pool_id]);
$pool = $stmt->fetch();

if (!$pool) {
    redirect('admin/arsip_soal/list.php');
}

// Get soal list
$stmt = $pdo->prepare("SELECT * FROM arsip_soal_item 
                       WHERE id_arsip_soal = ? 
                       ORDER BY urutan ASC, id ASC");
$stmt->execute([$pool_id]);
$soal_list = $stmt->fetchAll();

$page_title = 'Detail Arsip Soal - ' . escape($pool['nama_pool']);
$role_css = 'admin';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold">Detail Arsip Soal</h2>
                <p class="text-muted mb-0"><?php echo escape($pool['nama_pool']); ?></p>
            </div>
            <div>
                <a href="<?php echo base_url('admin/arsip_soal/list.php'); ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Kembali
                </a>
                <a href="<?php echo base_url('admin/arsip_soal/import.php?id=' . $pool_id); ?>" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import Soal
                </a>
                <a href="<?php echo base_url('admin/arsip_soal/edit.php?id=' . $pool_id); ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Arsip
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Arsip Info -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td width="30%"><strong>Nama Arsip</strong></td>
                        <td>: <?php echo escape($pool['nama_pool']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Mata Pelajaran</strong></td>
                        <td>: <?php echo escape($pool['nama_mapel']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tingkat Kelas</strong></td>
                        <td>: <?php echo $pool['tingkat_kelas'] ? 'Kelas ' . escape($pool['tingkat_kelas']) : '-'; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td width="30%"><strong>Status</strong></td>
                        <td>
                            <?php
                            $status_badge = [
                                'draft' => 'secondary',
                                'aktif' => 'success',
                                'arsip' => 'dark'
                            ];
                            $status_label = [
                                'draft' => 'Draft',
                                'aktif' => 'Aktif',
                                'arsip' => 'Arsip'
                            ];
                            $badge_class = $status_badge[$pool['status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>">
                                <?php echo $status_label[$pool['status']] ?? ucfirst($pool['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Total Soal</strong></td>
                        <td>: <strong><?php echo $pool['total_soal']; ?> soal</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Dibuat Oleh</strong></td>
                        <td>: <?php echo escape($pool['created_by_name']); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal</strong></td>
                        <td>: <?php echo date('d/m/Y H:i', strtotime($pool['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
            <?php if ($pool['deskripsi']): ?>
                <div class="col-12">
                    <strong>Deskripsi:</strong>
                    <p class="text-muted"><?php echo nl2br(escape($pool['deskripsi'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Soal List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-list"></i> Daftar Soal (<?php echo count($soal_list); ?> soal)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($soal_list)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Belum ada soal dalam arsip ini. 
                <a href="<?php echo base_url('admin/arsip_soal/import.php?id=' . $pool_id); ?>">Import soal sekarang</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th width="50">No</th>
                            <th>Pertanyaan</th>
                            <th width="150">Tipe Soal</th>
                            <th width="100">Bobot</th>
                            <th width="100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($soal_list as $index => $soal): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <?php 
                                    $pertanyaan = strip_tags($soal['pertanyaan']);
                                    echo escape(mb_substr($pertanyaan, 0, 100)) . (mb_strlen($pertanyaan) > 100 ? '...' : ''); 
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $tipe_labels = [
                                        'pilihan_ganda' => 'Pilihan Ganda',
                                        'benar_salah' => 'Benar/Salah',
                                        'essay' => 'Essay',
                                        'matching' => 'Matching',
                                        'isian_singkat' => 'Isian Singkat'
                                    ];
                                    echo $tipe_labels[$soal['tipe_soal']] ?? ucfirst($soal['tipe_soal']);
                                    ?>
                                </td>
                                <td><?php echo number_format($soal['bobot'], 2); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info" 
                                            onclick="showSoalDetail(<?php echo htmlspecialchars(json_encode($soal, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <a href="<?php echo base_url('admin/arsip_soal/delete_soal.php?id=' . $soal['id'] . '&pool_id=' . $pool_id); ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus soal ini?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Soal Detail -->
<div class="modal fade" id="soalDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Soal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="soalDetailContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function showSoalDetail(soal) {
    let content = '<div class="soal-detail">';
    content += '<p><strong>Pertanyaan:</strong></p>';
    content += '<div class="border p-3 mb-3">' + soal.pertanyaan + '</div>';
    
    if (soal.tipe_soal === 'pilihan_ganda' && soal.opsi_json) {
        const opsi = JSON.parse(soal.opsi_json);
        content += '<p><strong>Opsi Jawaban:</strong></p>';
        content += '<ul>';
        for (let key in opsi) {
            content += '<li>' + key + '. ' + opsi[key] + '</li>';
        }
        content += '</ul>';
    }
    
    content += '<p><strong>Kunci Jawaban:</strong> <span class="badge bg-success">' + soal.kunci_jawaban + '</span></p>';
    content += '<p><strong>Bobot:</strong> ' + soal.bobot + '</p>';
    content += '<p><strong>Tingkat Kesulitan:</strong> ' + (soal.tingkat_kesulitan || '-') + '</p>';
    content += '</div>';
    
    document.getElementById('soalDetailContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('soalDetailModal')).show();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

