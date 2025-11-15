<?php
/**
 * Menu Settings - Admin/Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Halaman untuk mengatur visibility menu siswa dan guru
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$error = '';
$success = '';

// Get current settings
$menu_settings = [
    'siswa_dashboard' => 1,
    'siswa_ujian' => 1,
    'siswa_pr' => 1,
    'siswa_tugas' => 1,
    'siswa_progress' => 1,
    'siswa_raport' => 1,
    'siswa_notifications' => 1,
    'siswa_verifikasi_dokumen' => 1,
    'siswa_profile' => 1,
    'siswa_about' => 1,
    'guru_ujian' => 1,
    'guru_pr' => 1,
    'guru_tugas' => 1,
    'guru_penilaian' => 1,
    'guru_assessment' => 1,
    'guru_progress' => 1,
    'guru_notifications' => 1,
    'guru_profile' => 1,
    'guru_about' => 1,
];

// Handle form submission (BEFORE header to allow redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        foreach ($menu_settings as $key => $value) {
            $new_value = isset($_POST[$key]) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                                  VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $description = "Visibility setting for menu: $key";
            $stmt->execute([$key, $new_value, $description, $new_value]);
        }
        
        $pdo->commit();
        log_activity('update_menu_settings', 'system_settings', null);
        
        // Redirect to prevent resubmission and ensure fresh data load
        $_SESSION['menu_settings_success'] = 'Pengaturan menu berhasil disimpan';
        redirect('admin-menu-settings');
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
        error_log("Menu settings error: " . $e->getMessage());
    }
}

// Check for success message from redirect
if (isset($_SESSION['menu_settings_success'])) {
    $success = $_SESSION['menu_settings_success'];
    unset($_SESSION['menu_settings_success']);
}

// Load settings from database
foreach ($menu_settings as $key => $default) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting_value = $stmt->fetchColumn();
        // fetchColumn returns false if no row, null if NULL, or the actual value
        if ($setting_value !== false && $setting_value !== null) {
            // Use value from database, convert to int (0 or 1)
            $menu_settings[$key] = intval($setting_value);
        } else {
            // If not found in database or NULL, use default value
            $menu_settings[$key] = intval($default);
        }
    } catch (PDOException $e) {
        error_log("Error getting menu setting $key: " . $e->getMessage());
        // On error, keep default value
        $menu_settings[$key] = intval($default);
    }
}

$page_title = 'Pengaturan Menu';
$role_css = $_SESSION['role'] === 'admin' ? 'admin' : 'operator';
include __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1">Pengaturan Menu</h2>
                <p class="text-muted mb-0">Kelola visibility menu untuk siswa dan guru</p>
            </div>
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

<form method="POST">
    <div class="row">
        <!-- Menu Siswa -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Menu Siswa</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_dashboard" name="siswa_dashboard" <?php echo $menu_settings['siswa_dashboard'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_dashboard">
                                <strong>Dashboard</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_ujian" name="siswa_ujian" <?php echo $menu_settings['siswa_ujian'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_ujian">
                                <strong>Ujian</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_pr" name="siswa_pr" <?php echo $menu_settings['siswa_pr'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_pr">
                                <strong>PR</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_tugas" name="siswa_tugas" <?php echo $menu_settings['siswa_tugas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_tugas">
                                <strong>Tugas</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_progress" name="siswa_progress" <?php echo $menu_settings['siswa_progress'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_progress">
                                <strong>Progress</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_raport" name="siswa_raport" <?php echo $menu_settings['siswa_raport'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_raport">
                                <strong>Raport</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_notifications" name="siswa_notifications" <?php echo $menu_settings['siswa_notifications'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_notifications">
                                <strong>Notifikasi</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_verifikasi_dokumen" name="siswa_verifikasi_dokumen" <?php echo $menu_settings['siswa_verifikasi_dokumen'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_verifikasi_dokumen">
                                <strong>Verifikasi Dokumen</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_profile" name="siswa_profile" <?php echo $menu_settings['siswa_profile'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_profile">
                                <strong>Profile</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="siswa_about" name="siswa_about" <?php echo $menu_settings['siswa_about'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="siswa_about">
                                <strong>About</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Menu Guru -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Menu Guru</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_ujian" name="guru_ujian" <?php echo $menu_settings['guru_ujian'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_ujian">
                                <strong>Ulangan Harian</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_pr" name="guru_pr" <?php echo $menu_settings['guru_pr'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_pr">
                                <strong>PR</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_tugas" name="guru_tugas" <?php echo $menu_settings['guru_tugas'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_tugas">
                                <strong>Tugas</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_penilaian" name="guru_penilaian" <?php echo $menu_settings['guru_penilaian'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_penilaian">
                                <strong>Penilaian Manual</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_assessment" name="guru_assessment" <?php echo $menu_settings['guru_assessment'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_assessment">
                                <strong>Assessment SUMATIP</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_progress" name="guru_progress" <?php echo $menu_settings['guru_progress'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_progress">
                                <strong>Progress</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_notifications" name="guru_notifications" <?php echo $menu_settings['guru_notifications'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_notifications">
                                <strong>Notifikasi</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_profile" name="guru_profile" <?php echo $menu_settings['guru_profile'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_profile">
                                <strong>Profile</strong>
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="guru_about" name="guru_about" <?php echo $menu_settings['guru_about'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="guru_about">
                                <strong>About</strong>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> Simpan Pengaturan
        </button>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

