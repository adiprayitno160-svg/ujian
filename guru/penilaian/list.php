<?php
/**
 * Penilaian Manual - List Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk guru melihat daftar siswa dan mata pelajaran yang bisa dinilai
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

$page_title = 'Penilaian Manual';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$guru_id = $_SESSION['user_id'];
$tahun_ajaran = sanitize($_GET['tahun_ajaran'] ?? get_tahun_ajaran_aktif());
$semester = $_GET['semester'] ?? 'ganjil';
$id_mapel = intval($_GET['id_mapel'] ?? 0);
$id_kelas = intval($_GET['id_kelas'] ?? 0);

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get mapel yang diajar oleh guru ini
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$guru_id]);
$mapel_list = $stmt->fetchAll();

// Get semua tahun ajaran yang memiliki kelas untuk guru ini
$stmt = $pdo->prepare("
    SELECT DISTINCT k.tahun_ajaran
    FROM kelas k
    INNER JOIN guru_mapel_kelas gmk ON k.id = gmk.id_kelas
    WHERE gmk.id_guru = ?
    AND k.status = 'active'
    AND k.tahun_ajaran IS NOT NULL
    ORDER BY k.tahun_ajaran DESC
");
$stmt->execute([$guru_id]);
$tahun_ajaran_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Jika tidak ada tahun ajaran dari kelas yang di-assign ke guru, 
// ambil dari semua kelas yang ada atau dari tabel tahun_ajaran
if (empty($tahun_ajaran_list)) {
    // Coba ambil dari semua kelas aktif yang ada
    $stmt = $pdo->query("
        SELECT DISTINCT tahun_ajaran
        FROM kelas
        WHERE status = 'active'
        AND tahun_ajaran IS NOT NULL
        ORDER BY tahun_ajaran DESC
    ");
    $tahun_ajaran_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Jika masih kosong, ambil dari tabel tahun_ajaran
    if (empty($tahun_ajaran_list)) {
        $tahun_ajaran_all = get_all_tahun_ajaran('tahun_mulai DESC');
        $tahun_ajaran_list = array_column($tahun_ajaran_all, 'tahun_ajaran');
    }
} else {
    // Jika ada tahun ajaran dari kelas yang di-assign, juga tambahkan tahun ajaran lainnya yang memiliki kelas
    $stmt = $pdo->query("
        SELECT DISTINCT tahun_ajaran
        FROM kelas
        WHERE status = 'active'
        AND tahun_ajaran IS NOT NULL
        ORDER BY tahun_ajaran DESC
    ");
    $all_tahun_ajaran = $stmt->fetchAll(PDO::FETCH_COLUMN);
    // Merge dan hapus duplikat
    $tahun_ajaran_list = array_unique(array_merge($tahun_ajaran_list, $all_tahun_ajaran));
    // Sort descending
    rsort($tahun_ajaran_list);
}

// Validasi tahun ajaran yang dipilih
if (!in_array($tahun_ajaran, $tahun_ajaran_list) && !empty($tahun_ajaran_list)) {
    $tahun_ajaran = $tahun_ajaran_list[0]; // Gunakan tahun ajaran pertama jika tidak valid
}

// Check if classes exist for this year (regardless of teacher assignment)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM kelas
    WHERE tahun_ajaran = ?
    AND status = 'active'
");
$stmt->execute([$tahun_ajaran]);
$total_kelas_available = $stmt->fetch()['total'];

// Check if teacher has mata pelajaran assigned
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM guru_mapel
    WHERE id_guru = ?
");
$stmt->execute([$guru_id]);
$has_mapel = $stmt->fetch()['total'] > 0;

// Get kelas - hanya kelas yang diampu oleh guru ini
// Filter berdasarkan guru_mapel_kelas untuk hanya menampilkan kelas yang di-assign ke guru
$stmt = $pdo->prepare("
    SELECT DISTINCT k.*
    FROM kelas k
    INNER JOIN guru_mapel_kelas gmk ON k.id = gmk.id_kelas
    WHERE gmk.id_guru = ?
    AND k.tahun_ajaran = ? 
    AND k.status = 'active'
    ORDER BY k.nama_kelas ASC
");
$stmt->execute([$guru_id, $tahun_ajaran]);
$kelas_list = $stmt->fetchAll();

// Get siswa berdasarkan filter
$siswa_list = [];
if ($id_mapel && $id_kelas) {
    // Get siswa di kelas tersebut
    $stmt = $pdo->prepare("SELECT u.id, u.username as nis, u.nama, k.nama_kelas
                          FROM users u
                          INNER JOIN user_kelas uk ON u.id = uk.id_user
                          INNER JOIN kelas k ON uk.id_kelas = k.id
                          WHERE u.role = 'siswa' 
                          AND u.status = 'active'
                          AND uk.id_kelas = ?
                          AND uk.tahun_ajaran = ?
                          AND uk.semester = ?
                          ORDER BY u.nama ASC");
    $stmt->execute([$id_kelas, $tahun_ajaran, $semester]);
    $siswa_list = $stmt->fetchAll();
}

// Get existing penilaian untuk ditampilkan
$penilaian_data = [];
if ($id_mapel && $id_kelas && !empty($siswa_list)) {
    $siswa_ids = array_column($siswa_list, 'id');
    $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
    $stmt = $pdo->prepare("SELECT pm.*, 
                          u_activated.nama as activated_by_name
                          FROM penilaian_manual pm
                          LEFT JOIN users u_activated ON pm.activated_by = u_activated.id
                          WHERE pm.id_guru = ?
                          AND pm.id_mapel = ?
                          AND pm.id_kelas = ?
                          AND pm.tahun_ajaran = ?
                          AND pm.semester = ?
                          AND pm.id_siswa IN ($placeholders)");
    $params = array_merge([$guru_id, $id_mapel, $id_kelas, $tahun_ajaran, $semester], $siswa_ids);
    $stmt->execute($params);
    $penilaian_results = $stmt->fetchAll();
    
    foreach ($penilaian_results as $p) {
        $penilaian_data[$p['id_siswa']] = $p;
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Penilaian Manual</h2>
        <p class="text-muted">Input nilai manual untuk siswa berdasarkan mata pelajaran yang Anda ampu</p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<?php if (empty($kelas_list) && !empty($tahun_ajaran_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Tidak ada kelas yang tersedia untuk tahun ajaran <?php echo escape($tahun_ajaran); ?>.</strong>
        <br><br>
        <?php if ($total_kelas_available > 0): ?>
            <p class="mb-2">
                <i class="fas fa-info-circle"></i> 
                Terdeteksi <?php echo $total_kelas_available; ?> kelas aktif untuk tahun ajaran <?php echo escape($tahun_ajaran); ?>, 
                namun kelas tersebut belum di-assign ke akun Anda.
            </p>
            <p class="mb-0">
                <strong>Solusi:</strong> Silakan hubungi administrator untuk meng-assign kelas dan mata pelajaran ke akun Anda melalui menu 
                <strong>Kelola Mata Pelajaran</strong> (Manage Mata Pelajaran).
            </p>
        <?php elseif (!$has_mapel): ?>
            <p class="mb-2">
                <i class="fas fa-info-circle"></i> 
                Anda belum memiliki mata pelajaran yang di-assign.
            </p>
            <p class="mb-0">
                <strong>Solusi:</strong> Silakan hubungi administrator untuk meng-assign mata pelajaran ke akun Anda melalui menu 
                <strong>Kelola Mata Pelajaran</strong> (Manage Mata Pelajaran).
            </p>
        <?php else: ?>
            <p class="mb-0">
                Tidak ada kelas aktif untuk tahun ajaran <?php echo escape($tahun_ajaran); ?> di sistem. 
                Silakan hubungi administrator untuk membuat kelas atau memverifikasi tahun ajaran yang aktif.
            </p>
        <?php endif; ?>
    </div>
<?php elseif (empty($tahun_ajaran_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Tidak ada tahun ajaran yang tersedia.</strong>
        <br><br>
        <p class="mb-0">
            Silakan hubungi administrator untuk mengatur tahun ajaran di sistem.
        </p>
    </div>
<?php endif; ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="<?php echo base_url('guru-penilaian-list'); ?>" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tahun Ajaran</label>
                <select class="form-select" name="tahun_ajaran" id="tahun_ajaran" required>
                    <?php if (!empty($tahun_ajaran_list)): ?>
                        <?php foreach ($tahun_ajaran_list as $ta): ?>
                            <option value="<?php echo escape($ta); ?>" <?php echo $tahun_ajaran === $ta ? 'selected' : ''; ?>>
                                <?php echo escape($ta); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="<?php echo escape($tahun_ajaran); ?>"><?php echo escape($tahun_ajaran); ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Semester</label>
                <select class="form-select" name="semester" id="semester" required>
                    <option value="ganjil" <?php echo $semester == 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo $semester == 'genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mata Pelajaran</label>
                <select class="form-select" name="id_mapel" id="id_mapel" required>
                    <option value="">Pilih Mata Pelajaran</option>
                    <?php foreach ($mapel_list as $mapel): ?>
                        <option value="<?php echo $mapel['id']; ?>" <?php echo $id_mapel == $mapel['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kelas</label>
                <select class="form-select" name="id_kelas" id="id_kelas" required>
                    <option value="">Pilih Kelas</option>
                    <?php if (!empty($kelas_list)): ?>
                        <?php foreach ($kelas_list as $kelas): ?>
                            <option value="<?php echo $kelas['id']; ?>" <?php echo $id_kelas == $kelas['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($kelas['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>Tidak ada kelas tersedia</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Tampilkan Siswa
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($id_mapel && $id_kelas && !empty($siswa_list)): ?>
    <!-- Form Input Nilai -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-list"></i> Input Nilai
                <?php 
                $mapel_nama = '';
                foreach ($mapel_list as $m) {
                    if ($m['id'] == $id_mapel) {
                        $mapel_nama = $m['nama_mapel'];
                        break;
                    }
                }
                $kelas_nama = '';
                foreach ($kelas_list as $k) {
                    if ($k['id'] == $id_kelas) {
                        $kelas_nama = $k['nama_kelas'];
                        break;
                    }
                }
                ?>
                - <?php echo escape($mapel_nama); ?> - <?php echo escape($kelas_nama); ?>
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo base_url('guru/penilaian/save.php'); ?>" id="formPenilaian">
                <input type="hidden" name="id_mapel" value="<?php echo $id_mapel; ?>">
                <input type="hidden" name="id_kelas" value="<?php echo $id_kelas; ?>">
                <input type="hidden" name="tahun_ajaran" value="<?php echo escape($tahun_ajaran); ?>">
                <input type="hidden" name="semester" value="<?php echo escape($semester); ?>">
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">No</th>
                                <th width="10%">NIS</th>
                                <th width="20%">Nama Siswa</th>
                                <th width="12%">Nilai Tugas</th>
                                <th width="12%">Nilai UTS</th>
                                <th width="12%">Nilai UAS</th>
                                <th width="12%">Nilai Akhir</th>
                                <th width="10%">Predikat</th>
                                <th width="10%">Status</th>
                                <th width="10%">Aktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_list as $index => $siswa): ?>
                                <?php 
                                $penilaian = $penilaian_data[$siswa['id']] ?? null;
                                $status = $penilaian['status'] ?? 'draft';
                                $aktif = $penilaian['aktif'] ?? 0;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><strong><?php echo escape($siswa['nis']); ?></strong></td>
                                    <td><?php echo escape($siswa['nama']); ?></td>
                                    <td>
                                        <input type="hidden" name="penilaian[<?php echo $siswa['id']; ?>][id_siswa]" value="<?php echo $siswa['id']; ?>">
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="penilaian[<?php echo $siswa['id']; ?>][nilai_tugas]" 
                                               value="<?php echo $penilaian ? number_format($penilaian['nilai_tugas'], 2, '.', '') : ''; ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="100"
                                               placeholder="0-100"
                                               <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="penilaian[<?php echo $siswa['id']; ?>][nilai_uts]" 
                                               value="<?php echo $penilaian ? number_format($penilaian['nilai_uts'], 2, '.', '') : ''; ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="100"
                                               placeholder="0-100"
                                               <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="penilaian[<?php echo $siswa['id']; ?>][nilai_uas]" 
                                               value="<?php echo $penilaian ? number_format($penilaian['nilai_uas'], 2, '.', '') : ''; ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="100"
                                               placeholder="0-100"
                                               <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               class="form-control form-control-sm" 
                                               name="penilaian[<?php echo $siswa['id']; ?>][nilai_akhir]" 
                                               value="<?php echo $penilaian ? number_format($penilaian['nilai_akhir'], 2, '.', '') : ''; ?>"
                                               step="0.01" 
                                               min="0" 
                                               max="100"
                                               placeholder="0-100"
                                               <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'readonly' : ''; ?>>
                                    </td>
                                    <td>
                                        <select class="form-select form-select-sm" name="penilaian[<?php echo $siswa['id']; ?>][predikat]"
                                                <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'disabled' : ''; ?>>
                                            <option value="">-</option>
                                            <option value="A" <?php echo ($penilaian && $penilaian['predikat'] == 'A') ? 'selected' : ''; ?>>A</option>
                                            <option value="B" <?php echo ($penilaian && $penilaian['predikat'] == 'B') ? 'selected' : ''; ?>>B</option>
                                            <option value="C" <?php echo ($penilaian && $penilaian['predikat'] == 'C') ? 'selected' : ''; ?>>C</option>
                                            <option value="D" <?php echo ($penilaian && $penilaian['predikat'] == 'D') ? 'selected' : ''; ?>>D</option>
                                        </select>
                                        <?php if ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')): ?>
                                            <input type="hidden" name="penilaian[<?php echo $siswa['id']; ?>][predikat]" value="<?php echo escape($penilaian['predikat'] ?? ''); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($status == 'submitted'): ?>
                                            <span class="badge bg-success" title="Sudah dikumpulkan ke operator">
                                                <i class="fas fa-check"></i> Submitted
                                            </span>
                                        <?php elseif ($status == 'approved'): ?>
                                            <span class="badge bg-info" title="Sudah disetujui operator">
                                                <i class="fas fa-check-double"></i> Approved
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Draft">
                                                <i class="fas fa-edit"></i> Draft
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($penilaian && $aktif == 1): ?>
                                            <span class="badge bg-success" title="Nilai manual aktif (diaktifkan oleh operator)">
                                                <i class="fas fa-toggle-on"></i> Aktif
                                            </span>
                                            <?php if (!empty($penilaian['activated_at'])): ?>
                                                <br><small class="text-muted">
                                                    <?php echo date('d/m/Y H:i', strtotime($penilaian['activated_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php elseif ($penilaian && $status == 'submitted'): ?>
                                            <span class="badge bg-warning" title="Menunggu aktivasi oleh operator">
                                                <i class="fas fa-clock"></i> Menunggu
                                            </span>
                                        <?php elseif ($penilaian && $status == 'approved'): ?>
                                            <span class="badge bg-secondary" title="Belum diaktifkan oleh operator">
                                                <i class="fas fa-toggle-off"></i> Tidak Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary" title="Belum dikumpulkan">
                                                <i class="fas fa-minus"></i> -
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="10">
                                        <div class="mb-2">
                                            <label class="form-label small">Keterangan (Opsional):</label>
                                            <textarea class="form-control form-control-sm" 
                                                      name="penilaian[<?php echo $siswa['id']; ?>][keterangan]" 
                                                      rows="2"
                                                      placeholder="Keterangan tambahan..."
                                                      <?php echo ($penilaian && ($penilaian['status'] == 'submitted' || $penilaian['status'] == 'approved')) ? 'readonly' : ''; ?>><?php echo $penilaian ? escape($penilaian['keterangan']) : ''; ?></textarea>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" name="action" value="save">
                        <i class="fas fa-save"></i> Simpan Nilai
                    </button>
                    <button type="submit" class="btn btn-success" name="action" value="submit" onclick="return confirm('Apakah Anda yakin ingin mengumpulkan nilai ini ke operator? Setelah dikumpulkan, nilai tidak bisa diubah lagi.');">
                        <i class="fas fa-paper-plane"></i> Kumpulkan ke Operator
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($id_mapel && $id_kelas): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> Tidak ada siswa di kelas yang dipilih untuk semester ini.
    </div>
<?php endif; ?>

<script>
// Ensure dropdown is enabled and working
document.addEventListener('DOMContentLoaded', function() {
    var tahunAjaranSelect = document.getElementById('tahun_ajaran');
    var kelasSelect = document.getElementById('id_kelas');
    var mapelSelect = document.getElementById('id_mapel');
    
    if (kelasSelect) {
        // Ensure dropdown is enabled
        kelasSelect.removeAttribute('disabled');
        kelasSelect.style.pointerEvents = 'auto';
        kelasSelect.style.cursor = 'pointer';
    }
    
    // Reset kelas selection when tahun ajaran changes
    if (tahunAjaranSelect) {
        tahunAjaranSelect.addEventListener('change', function() {
            // Reset kelas selection when tahun ajaran changes
            if (kelasSelect) {
                kelasSelect.value = '';
                // Optionally reset mapel selection as well
                // if (mapelSelect) {
                //     mapelSelect.value = '';
                // }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

