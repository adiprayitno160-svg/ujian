<?php
/**
 * Assign Peserta - Guru/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
check_session_timeout();

$page_title = 'Assign Peserta';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';

global $pdo;

$id = intval($_GET['id'] ?? 0);
$sesi = get_sesi($id);

if (!$sesi) {
    redirect('guru/sesi/list.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe = sanitize($_POST['tipe'] ?? '');
    
    if ($tipe === 'individual') {
        $user_ids = $_POST['user_ids'] ?? [];
        if (empty($user_ids)) {
            $error = 'Pilih minimal satu siswa';
        } else {
            try {
                $pdo->beginTransaction();
                foreach ($user_ids as $user_id) {
                    $user_id = intval($user_id);
                    // Check if already assigned
                    $stmt = $pdo->prepare("SELECT id FROM sesi_peserta WHERE id_sesi = ? AND id_user = ? AND tipe_assign = 'individual'");
                    $stmt->execute([$id, $user_id]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO sesi_peserta (id_sesi, id_user, tipe_assign) VALUES (?, ?, 'individual')");
                        $stmt->execute([$id, $user_id]);
                    }
                }
                $pdo->commit();
                $success = 'Peserta berhasil di-assign';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($tipe === 'kelas') {
        $kelas_ids = $_POST['kelas_ids'] ?? [];
        if (empty($kelas_ids)) {
            $error = 'Pilih minimal satu kelas';
        } else {
            try {
                $pdo->beginTransaction();
                foreach ($kelas_ids as $kelas_id) {
                    $kelas_id = intval($kelas_id);
                    // Check if already assigned
                    $stmt = $pdo->prepare("SELECT id FROM sesi_peserta WHERE id_sesi = ? AND id_kelas = ? AND tipe_assign = 'kelas'");
                    $stmt->execute([$id, $kelas_id]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("INSERT INTO sesi_peserta (id_sesi, id_kelas, tipe_assign) VALUES (?, ?, 'kelas')");
                        $stmt->execute([$id, $kelas_id]);
                    }
                }
                $pdo->commit();
                $success = 'Kelas berhasil di-assign';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Get siswa list
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'siswa' AND status = 'active' ORDER BY nama ASC");
$siswa_list = $stmt->fetchAll();

// Get kelas list
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h2 class="fw-bold">Assign Peserta: <?php echo escape($sesi['nama_sesi']); ?></h2>
            <a href="<?php echo base_url('guru/sesi/manage.php?id=' . $id); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="3000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="assignTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button">
            <i class="fas fa-user"></i> Individual
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="kelas-tab" data-bs-toggle="tab" data-bs-target="#kelas" type="button">
            <i class="fas fa-users"></i> Kelas
        </button>
    </li>
</ul>

<div class="tab-content" id="assignTabContent">
    <!-- Individual Tab -->
    <div class="tab-pane fade show active" id="individual" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="tipe" value="individual">
                    <div class="mb-3">
                        <label class="form-label">Pilih Siswa</label>
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                            <?php foreach ($siswa_list as $siswa): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="user_ids[]" 
                                       value="<?php echo $siswa['id']; ?>" id="siswa_<?php echo $siswa['id']; ?>">
                                <label class="form-check-label" for="siswa_<?php echo $siswa['id']; ?>">
                                    <?php echo escape($siswa['nama']); ?> (<?php echo escape($siswa['username']); ?>)
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Siswa
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Kelas Tab -->
    <div class="tab-pane fade" id="kelas" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="tipe" value="kelas">
                    <div class="mb-3">
                        <label class="form-label">Pilih Kelas</label>
                        <div style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                            <?php foreach ($kelas_list as $kelas): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="kelas_ids[]" 
                                       value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>">
                                <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                    <?php echo escape($kelas['nama_kelas']); ?> - <?php echo escape($kelas['tahun_ajaran']); ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Kelas
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
