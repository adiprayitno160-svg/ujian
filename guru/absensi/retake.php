<?php
/**
 * Retake Exam - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk guru membuat retake exam untuk siswa yang tidak hadir
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/functions_sumatip.php';

require_role('guru');
check_session_timeout();

$page_title = 'Retake Exam';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$sesi_id = intval($_GET['sesi_id'] ?? 0);
$error = '';
$success = '';

// Handle POST - create retake sesi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sesi_id) {
    $siswa_ids = $_POST['siswa_ids'] ?? [];
    $waktu_mulai = $_POST['waktu_mulai'] ?? '';
    $waktu_selesai = $_POST['waktu_selesai'] ?? '';
    
    if (empty($siswa_ids) || empty($waktu_mulai) || empty($waktu_selesai)) {
        $error = 'Semua field wajib harus diisi';
    } elseif (strtotime($waktu_selesai) <= strtotime($waktu_mulai)) {
        $error = 'Waktu selesai harus lebih besar dari waktu mulai';
    } else {
        try {
            $retake_sesi_id = create_retake_sesi($sesi_id, $siswa_ids, $waktu_mulai, $waktu_selesai, $_SESSION['user_id']);
            $success = 'Retake sesi berhasil dibuat. Siswa dapat mengikuti ujian retake pada waktu yang telah ditentukan.';
            log_activity('create_retake_sesi', 'sesi_ujian', $retake_sesi_id);
        } catch (Exception $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Get sesi list untuk filter (hanya ujian harian, bukan assessment)
$stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, m.nama_mapel
                      FROM sesi_ujian s
                      INNER JOIN ujian u ON s.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      WHERE u.id_guru = ?
                      AND (u.tipe_asesmen IS NULL OR u.tipe_asesmen = '')
                      ORDER BY s.waktu_mulai DESC");
$stmt->execute([$_SESSION['user_id']]);
$sesi_list = $stmt->fetchAll();

$sesi_info = null;
$students_need_retake = [];

if ($sesi_id) {
    // Get sesi info
    $stmt = $pdo->prepare("SELECT s.*, u.judul as judul_ujian, u.durasi, m.nama_mapel
                          FROM sesi_ujian s
                          INNER JOIN ujian u ON s.id_ujian = u.id
                          INNER JOIN mapel m ON u.id_mapel = m.id
                          WHERE s.id = ? AND u.id_guru = ?");
    $stmt->execute([$sesi_id, $_SESSION['user_id']]);
    $sesi_info = $stmt->fetch();
    
    if ($sesi_info) {
        // Get students who need retake
        $students_need_retake = get_students_need_retake($sesi_id);
    }
}
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Retake Exam - Ujian Harian</h2>
        <p class="text-muted">Buat retake exam untuk siswa yang tidak hadir pada ujian harian</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<!-- Filter Sesi -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Pilih Sesi Ujian</label>
                <select class="form-select" name="sesi_id" onchange="this.form.submit()">
                    <option value="">-- Pilih Sesi --</option>
                    <?php foreach ($sesi_list as $sesi): ?>
                        <option value="<?php echo $sesi['id']; ?>" <?php echo $sesi_id == $sesi['id'] ? 'selected' : ''; ?>>
                            <?php echo escape($sesi['judul_ujian']); ?> - 
                            <?php echo escape($sesi['nama_mapel']); ?> - 
                            <?php echo format_date($sesi['waktu_mulai'], 'd/m/Y H:i'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($sesi_info && !empty($students_need_retake)): ?>
    <!-- Sesi Info -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sesi</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Ujian:</strong> <?php echo escape($sesi_info['judul_ujian']); ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?php echo escape($sesi_info['nama_mapel']); ?></p>
                    <p><strong>Nama Sesi:</strong> <?php echo escape($sesi_info['nama_sesi']); ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Waktu Mulai:</strong> <?php echo format_date($sesi_info['waktu_mulai']); ?></p>
                    <p><strong>Waktu Selesai:</strong> <?php echo format_date($sesi_info['waktu_selesai']); ?></p>
                    <p><strong>Durasi:</strong> <?php echo $sesi_info['durasi']; ?> menit</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Students Need Retake -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Siswa yang Perlu Retake</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="sesi_id" value="<?php echo $sesi_id; ?>">
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Total:</strong> <?php echo count($students_need_retake); ?> siswa tidak hadir dan perlu mengulang ujian.
                </div>
                
                <div class="table-responsive mb-3">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                </th>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_need_retake as $index => $student): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="siswa_ids[]" value="<?php echo $student['id']; ?>" class="siswa-checkbox">
                                </td>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo escape($student['username']); ?></td>
                                <td><?php echo escape($student['nama']); ?></td>
                                <td><?php echo escape($student['kelas']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="waktu_mulai" class="form-label">Waktu Mulai Retake <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="waktu_mulai" name="waktu_mulai" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="waktu_selesai" class="form-label">Waktu Selesai Retake <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="waktu_selesai" name="waktu_selesai" required>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Buat Retake Sesi
                    </button>
                    <a href="<?php echo base_url('guru/absensi/list.php?sesi_id=' . $sesi_id); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali ke Absensi
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php elseif ($sesi_info && empty($students_need_retake)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h5>Semua siswa sudah hadir</h5>
            <p class="text-muted">Tidak ada siswa yang perlu retake untuk sesi ini.</p>
            <a href="<?php echo base_url('guru/absensi/list.php?sesi_id=' . $sesi_id); ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Absensi
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
            <p class="text-muted">Pilih sesi ujian untuk melihat siswa yang perlu retake</p>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.siswa-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

// Validate form submission
document.querySelector('form')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.siswa-checkbox:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Pilih minimal 1 siswa untuk retake');
        return false;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


