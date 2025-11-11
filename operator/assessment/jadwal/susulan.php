<?php
/**
 * Buat Jadwal Susulan - Operator Assessment
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$id_jadwal = intval($_GET['id_jadwal'] ?? 0);

if (!$id_jadwal) {
    redirect('operator-assessment-jadwal-list');
}

// Get jadwal utama
$stmt = $pdo->prepare("SELECT ja.*, u.judul as judul_ujian, m.nama_mapel, k.nama_kelas
                      FROM jadwal_assessment ja
                      INNER JOIN ujian u ON ja.id_ujian = u.id
                      INNER JOIN mapel m ON u.id_mapel = m.id
                      INNER JOIN kelas k ON ja.id_kelas = k.id
                      WHERE ja.id = ?");
$stmt->execute([$id_jadwal]);
$jadwal_utama = $stmt->fetch();

if (!$jadwal_utama) {
    redirect('operator-assessment-jadwal-list');
}

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $waktu_mulai = $_POST['waktu_mulai'] ?? '';
    $waktu_selesai = $_POST['waktu_selesai'] ?? '';
    $keterangan = sanitize($_POST['keterangan'] ?? '');
    $create_sesi = isset($_POST['create_sesi']) ? 1 : 0;
    
    if (empty($tanggal) || empty($waktu_mulai) || empty($waktu_selesai)) {
        $error = 'Semua field wajib harus diisi';
    } else {
        try {
            $jadwal_id = create_jadwal_assessment([
                'id_ujian' => $jadwal_utama['id_ujian'],
                'id_kelas' => $jadwal_utama['id_kelas'],
                'tanggal' => $tanggal,
                'waktu_mulai' => $waktu_mulai,
                'waktu_selesai' => $waktu_selesai,
                'is_susulan' => 1,
                'id_jadwal_utama' => $id_jadwal,
                'create_sesi' => $create_sesi,
                'created_by' => $_SESSION['user_id']
            ]);
            
            // Update keterangan
            if ($keterangan) {
                $stmt = $pdo->prepare("UPDATE jadwal_assessment SET keterangan = ? WHERE id = ?");
                $stmt->execute([$keterangan, $jadwal_id]);
            }
            
            $success = 'Jadwal susulan berhasil dibuat';
            log_activity('create_jadwal_susulan', 'jadwal_assessment', $jadwal_id);
            redirect('operator-assessment-jadwal-list?success=susulan_created');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Buat Jadwal Susulan';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Jadwal Susulan</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<!-- Info Jadwal Utama -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Jadwal Utama</h5>
    </div>
    <div class="card-body">
        <table class="table table-borderless">
            <tr>
                <th width="200">Mata Pelajaran</th>
                <td><?php echo escape($jadwal_utama['nama_mapel']); ?></td>
            </tr>
            <tr>
                <th>Kelas</th>
                <td><?php echo escape($jadwal_utama['nama_kelas']); ?></td>
            </tr>
            <tr>
                <th>Tanggal</th>
                <td><?php echo date('d/m/Y', strtotime($jadwal_utama['tanggal'])); ?></td>
            </tr>
            <tr>
                <th>Waktu</th>
                <td><?php echo date('H:i', strtotime($jadwal_utama['waktu_mulai'])); ?> - <?php echo date('H:i', strtotime($jadwal_utama['waktu_selesai'])); ?></td>
            </tr>
        </table>
    </div>
</div>

<!-- Form Jadwal Susulan -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Susulan <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="tanggal" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Waktu Mulai <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" name="waktu_mulai" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Waktu Selesai <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" name="waktu_selesai" required>
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea class="form-control" name="keterangan" rows="3"></textarea>
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="create_sesi" name="create_sesi" checked>
                <label class="form-check-label" for="create_sesi">
                    Buat sesi ujian otomatis
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Buat Jadwal Susulan
                </button>
                <a href="<?php echo base_url('operator-assessment-jadwal-list'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>





