<?php
/**
 * Create Jadwal Assessment - Operator Assessment
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

$error = '';
$success = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_ujian = intval($_POST['id_ujian'] ?? 0);
    $id_kelas = intval($_POST['id_kelas'] ?? 0);
    $tanggal = $_POST['tanggal'] ?? '';
    $waktu_mulai = $_POST['waktu_mulai'] ?? '';
    $waktu_selesai = $_POST['waktu_selesai'] ?? '';
    $create_sesi = isset($_POST['create_sesi']) ? 1 : 0;
    
    if (empty($id_ujian) || empty($id_kelas) || empty($tanggal) || empty($waktu_mulai) || empty($waktu_selesai)) {
        $error = 'Semua field wajib harus diisi';
    } else {
        try {
            $jadwal_id = create_jadwal_assessment([
                'id_ujian' => $id_ujian,
                'id_kelas' => $id_kelas,
                'tanggal' => $tanggal,
                'waktu_mulai' => $waktu_mulai,
                'waktu_selesai' => $waktu_selesai,
                'create_sesi' => $create_sesi,
                'created_by' => $_SESSION['user_id']
            ]);
            
            $success = 'Jadwal berhasil dibuat';
            log_activity('create_jadwal_assessment', 'jadwal_assessment', $jadwal_id);
            redirect('operator-assessment-jadwal-list?success=created');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get ujian list
$stmt = $pdo->query("SELECT u.*, m.nama_mapel FROM ujian u INNER JOIN mapel m ON u.id_mapel = m.id ORDER BY u.created_at DESC");
$ujian_list = $stmt->fetchAll();

// Get kelas list
$tahun_ajaran = get_tahun_ajaran_aktif();
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? ORDER BY nama_kelas ASC");
$stmt->execute([$tahun_ajaran]);
$kelas_list = $stmt->fetchAll();

$page_title = 'Buat Jadwal Assessment';
include __DIR__ . '/../../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Jadwal Assessment</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Ujian/SUMATIP <span class="text-danger">*</span></label>
                <select class="form-select" name="id_ujian" required>
                    <option value="">Pilih Ujian/SUMATIP</option>
                    <?php foreach ($ujian_list as $ujian): ?>
                        <option value="<?php echo $ujian['id']; ?>">
                            <?php echo escape($ujian['judul']); ?> - <?php echo escape($ujian['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Kelas <span class="text-danger">*</span></label>
                <select class="form-select" name="id_kelas" required>
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                        <option value="<?php echo $kelas['id']; ?>">
                            <?php echo escape($kelas['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
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
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="create_sesi" name="create_sesi" checked>
                <label class="form-check-label" for="create_sesi">
                    Buat sesi ujian otomatis
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Buat Jadwal
                </button>
                <a href="<?php echo base_url('operator-assessment-jadwal-list'); ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



