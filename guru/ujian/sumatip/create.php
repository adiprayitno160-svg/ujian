<?php
/**
 * Create SUMATIP - Guru (Wizard)
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/functions_sumatip.php';

require_login();
check_session_timeout();

// SUMATIP hanya bisa diakses oleh operator
if (!has_operator_access()) {
    redirect('index.php');
}

global $pdo;

$error = '';
$success = '';
$step = intval($_GET['step'] ?? 1);
$ujian_id = intval($_GET['ujian_id'] ?? 0);

// Handle POST for each step
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Step 1: Pilih Jenis SUMATIP
        $tipe_asesmen = sanitize($_POST['tipe_asesmen'] ?? '');
        if (empty($tipe_asesmen)) {
            $error = 'Jenis SUMATIP harus dipilih';
        } else {
            $_SESSION['sumatip_wizard']['tipe_asesmen'] = $tipe_asesmen;
            $step = 2;
        }
    } elseif ($step == 2) {
        // Step 2: Konfigurasi Periode
        $tahun_ajaran = sanitize($_POST['tahun_ajaran'] ?? '');
        $semester = sanitize($_POST['semester'] ?? '');
        if (empty($tahun_ajaran) || empty($semester)) {
            $error = 'Tahun ajaran dan semester harus diisi';
        } else {
            $_SESSION['sumatip_wizard']['tahun_ajaran'] = $tahun_ajaran;
            $_SESSION['sumatip_wizard']['semester'] = $semester;
            $step = 3;
        }
    } elseif ($step == 3) {
        // Step 3: Template (Optional)
        $id_template = intval($_POST['id_template'] ?? 0);
        $_SESSION['sumatip_wizard']['id_template'] = $id_template;
        $step = 4;
    } elseif ($step == 4) {
        // Step 4: Mata Pelajaran & Kelas
        $id_mapel = intval($_POST['id_mapel'] ?? 0);
        $id_kelas = $_POST['id_kelas'] ?? [];
        $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
        
        if (empty($id_mapel) || empty($id_kelas)) {
            $error = 'Mata pelajaran dan kelas harus diisi';
        } else {
            $_SESSION['sumatip_wizard']['id_mapel'] = $id_mapel;
            $_SESSION['sumatip_wizard']['id_kelas'] = $id_kelas;
            $_SESSION['sumatip_wizard']['is_mandatory'] = $is_mandatory;
            $step = 5;
        }
    } elseif ($step == 5) {
        // Step 5: Pengaturan Ujian
        $judul = sanitize($_POST['judul'] ?? '');
        $deskripsi = sanitize($_POST['deskripsi'] ?? '');
        $durasi = intval($_POST['durasi'] ?? 0);
        $acak_soal = isset($_POST['acak_soal']) ? 1 : 0;
        $acak_opsi = isset($_POST['acak_opsi']) ? 1 : 0;
        $anti_contek = isset($_POST['anti_contek']) ? 1 : 0;
        $min_submit_minutes = intval($_POST['min_submit_minutes'] ?? 0);
        
        if (empty($judul) || $durasi <= 0) {
            $error = 'Judul dan durasi harus diisi';
        } else {
            $_SESSION['sumatip_wizard']['judul'] = $judul;
            $_SESSION['sumatip_wizard']['deskripsi'] = $deskripsi;
            $_SESSION['sumatip_wizard']['durasi'] = $durasi;
            $_SESSION['sumatip_wizard']['acak_soal'] = $acak_soal;
            $_SESSION['sumatip_wizard']['acak_opsi'] = $acak_opsi;
            $_SESSION['sumatip_wizard']['anti_contek'] = $anti_contek;
            $_SESSION['sumatip_wizard']['min_submit_minutes'] = $min_submit_minutes;
            $step = 6;
        }
    } elseif ($step == 6) {
        // Step 6: Create SUMATIP
        try {
            $wizard_data = $_SESSION['sumatip_wizard'] ?? [];
            
            // Get tingkat kelas from first kelas
            $tingkat_kelas = null;
            if (!empty($wizard_data['id_kelas'])) {
                $stmt = $pdo->prepare("SELECT tingkat FROM kelas WHERE id = ?");
                $stmt->execute([$wizard_data['id_kelas'][0]]);
                $kelas = $stmt->fetch();
                $tingkat_kelas = $kelas['tingkat'] ?? null;
            }
            
            $ujian_id = create_sumatip([
                'judul' => $wizard_data['judul'],
                'deskripsi' => $wizard_data['deskripsi'],
                'id_mapel' => $wizard_data['id_mapel'],
                'id_guru' => $_SESSION['user_id'],
                'durasi' => $wizard_data['durasi'],
                'tipe_asesmen' => $wizard_data['tipe_asesmen'],
                'tahun_ajaran' => $wizard_data['tahun_ajaran'],
                'semester' => $wizard_data['semester'],
                'id_template_sumatip' => $wizard_data['id_template'] ?? null,
                'is_mandatory' => $wizard_data['is_mandatory'] ?? 0,
                'tingkat_kelas' => $tingkat_kelas,
                'id_kelas' => $wizard_data['id_kelas']
            ]);
            
            // Update settings (default to 1 if not set, including AI correction)
            update_ujian_settings($ujian_id, [
                'acak_soal' => $wizard_data['acak_soal'] ?? 1,
                'acak_opsi' => $wizard_data['acak_opsi'] ?? 1,
                'anti_contek_enabled' => $wizard_data['anti_contek'] ?? 1,
                'min_submit_minutes' => $wizard_data['min_submit_minutes'] ?? 0,
                'ai_correction_enabled' => 1 // Default enabled
            ]);
            
            // Clear wizard session
            unset($_SESSION['sumatip_wizard']);
            
            redirect('guru/ujian/detail.php?id=' . $ujian_id);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Initialize wizard session if not exists
if (!isset($_SESSION['sumatip_wizard'])) {
    $_SESSION['sumatip_wizard'] = [];
}

$page_title = 'Buat SUMATIP Baru';
$role_css = 'guru';
include __DIR__ . '/../../../includes/header.php';

// Get mapel for this guru (Sistem menggunakan guru mata pelajaran, bukan guru kelas)
// Guru di SMP mengajar mata pelajaran tertentu ke berbagai kelas
$mapel_list = get_mapel_by_guru($_SESSION['user_id']);

// Get kelas
$tahun_ajaran = $_SESSION['sumatip_wizard']['tahun_ajaran'] ?? get_tahun_ajaran_aktif();
$stmt = $pdo->prepare("SELECT * FROM kelas WHERE tahun_ajaran = ? ORDER BY nama_kelas ASC");
$stmt->execute([$tahun_ajaran]);
$kelas_list = $stmt->fetchAll();

// Get template list
$template_list = get_sumatip_template_list([
    'jenis_sumatip' => $_SESSION['sumatip_wizard']['tipe_asesmen'] ?? ''
]);

// Get tahun ajaran list
$tahun_ajaran_list = $pdo->query("SELECT DISTINCT tahun_ajaran FROM kelas WHERE tahun_ajaran IS NOT NULL ORDER BY tahun_ajaran DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat SUMATIP Baru</h2>
        <p class="text-muted">Wizard Step <?php echo $step; ?> dari 6</p>
    </div>
</div>

<!-- Progress Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="progress" style="height: 30px;">
            <?php for ($i = 1; $i <= 6; $i++): ?>
                <div class="progress-bar <?php echo $i <= $step ? 'bg-primary' : 'bg-secondary'; ?>" 
                     style="width: <?php echo 100/6; ?>%">
                    Step <?php echo $i; ?>
                </div>
            <?php endfor; ?>
        </div>
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
            <?php if ($step == 1): ?>
                <!-- Step 1: Pilih Jenis SUMATIP -->
                <h5>Step 1: Pilih Jenis SUMATIP</h5>
                <div class="mb-3">
                    <label class="form-label">Jenis SUMATIP <span class="text-danger">*</span></label>
                    <select class="form-select" name="tipe_asesmen" required>
                        <option value="">Pilih Jenis SUMATIP</option>
                        <option value="sumatip_tengah_semester" <?php echo ($_SESSION['sumatip_wizard']['tipe_asesmen'] ?? '') === 'sumatip_tengah_semester' ? 'selected' : ''; ?>>
                            SUMATIP Tengah Semester
                        </option>
                        <option value="sumatip_akhir_semester" <?php echo ($_SESSION['sumatip_wizard']['tipe_asesmen'] ?? '') === 'sumatip_akhir_semester' ? 'selected' : ''; ?>>
                            SUMATIP Akhir Semester
                        </option>
                        <option value="sumatip_akhir_tahun" <?php echo ($_SESSION['sumatip_wizard']['tipe_asesmen'] ?? '') === 'sumatip_akhir_tahun' ? 'selected' : ''; ?>>
                            SUMATIP Akhir Tahun
                        </option>
                    </select>
                    <small class="text-muted">Pilih jenis SUMATIP yang akan dibuat</small>
                </div>
                
            <?php elseif ($step == 2): ?>
                <!-- Step 2: Konfigurasi Periode -->
                <h5>Step 2: Konfigurasi Periode</h5>
                <div class="mb-3">
                    <label class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                    <select class="form-select" name="tahun_ajaran" required>
                        <option value="">Pilih Tahun Ajaran</option>
                        <?php foreach ($tahun_ajaran_list as $ta): ?>
                            <option value="<?php echo $ta; ?>" <?php echo ($_SESSION['sumatip_wizard']['tahun_ajaran'] ?? '') === $ta ? 'selected' : ''; ?>>
                                <?php echo $ta; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Semester <span class="text-danger">*</span></label>
                    <select class="form-select" name="semester" required>
                        <option value="">Pilih Semester</option>
                        <option value="ganjil" <?php echo ($_SESSION['sumatip_wizard']['semester'] ?? '') === 'ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="genap" <?php echo ($_SESSION['sumatip_wizard']['semester'] ?? '') === 'genap' ? 'selected' : ''; ?>>Genap</option>
                    </select>
                </div>
                
            <?php elseif ($step == 3): ?>
                <!-- Step 3: Template -->
                <h5>Step 3: Template (Optional)</h5>
                <div class="mb-3">
                    <label class="form-label">Template SUMATIP</label>
                    <select class="form-select" name="id_template">
                        <option value="0">Tidak menggunakan template</option>
                        <?php foreach ($template_list as $template): ?>
                            <option value="<?php echo $template['id']; ?>" <?php echo ($_SESSION['sumatip_wizard']['id_template'] ?? 0) == $template['id'] ? 'selected' : ''; ?>>
                                <?php echo escape($template['nama_template']); ?> - Durasi: <?php echo $template['durasi_default']; ?> menit
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Pilih template untuk mengisi pengaturan default</small>
                </div>
                
            <?php elseif ($step == 4): ?>
                <!-- Step 4: Mata Pelajaran & Kelas -->
                <h5>Step 4: Mata Pelajaran & Kelas</h5>
                <div class="mb-3">
                    <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                    
                    <?php if (count($mapel_list) == 1): ?>
                        <?php $single_mapel = $mapel_list[0]; ?>
                        <!-- Jika hanya 1 mata pelajaran, auto-select dan tampilkan sebagai info -->
                        <div class="alert alert-info d-flex align-items-center mb-2">
                            <i class="fas fa-book me-2"></i>
                            <strong>Mata Pelajaran:</strong> 
                            <span class="ms-2 badge bg-primary"><?php echo escape($single_mapel['nama_mapel']); ?></span>
                        </div>
                        <input type="hidden" name="id_mapel" value="<?php echo $single_mapel['id']; ?>">
                    <?php else: ?>
                        <!-- Jika lebih dari 1 mata pelajaran, tampilkan info semua mata pelajaran yang diampu -->
                        <div class="mb-2">
                            <small class="text-muted d-block mb-2">
                                <i class="fas fa-info-circle"></i> 
                                <strong>Mata pelajaran yang Anda ampu:</strong>
                            </small>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <?php foreach ($mapel_list as $mapel): ?>
                                    <span class="badge bg-info"><?php echo escape($mapel['nama_mapel']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <select class="form-select" name="id_mapel" required>
                            <option value="">Pilih Mata Pelajaran</option>
                            <?php foreach ($mapel_list as $mapel): ?>
                                <option value="<?php echo $mapel['id']; ?>" <?php echo ($_SESSION['sumatip_wizard']['id_mapel'] ?? 0) == $mapel['id'] ? 'selected' : ''; ?>>
                                    <?php echo escape($mapel['nama_mapel']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Kelas Target <span class="text-danger">*</span></label>
                    <div class="row">
                        <?php foreach ($kelas_list as $kelas): ?>
                            <div class="col-md-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="id_kelas[]" 
                                           value="<?php echo $kelas['id']; ?>"
                                           <?php echo in_array($kelas['id'], $_SESSION['sumatip_wizard']['id_kelas'] ?? []) ? 'checked' : ''; ?>>
                                    <label class="form-check-label">
                                        <?php echo escape($kelas['nama_kelas']); ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_mandatory" name="is_mandatory" 
                           <?php echo ($_SESSION['sumatip_wizard']['is_mandatory'] ?? 0) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_mandatory">
                        Wajib untuk semua siswa
                    </label>
                </div>
                
            <?php elseif ($step == 5): ?>
                <!-- Step 5: Pengaturan Ujian -->
                <h5>Step 5: Pengaturan Ujian</h5>
                <?php
                // Load template if selected
                $template = null;
                if (!empty($_SESSION['sumatip_wizard']['id_template'])) {
                    $template = get_sumatip_template($_SESSION['sumatip_wizard']['id_template']);
                }
                ?>
                <div class="mb-3">
                    <label class="form-label">Judul <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="judul" required
                           value="<?php echo escape($_SESSION['sumatip_wizard']['judul'] ?? ($template ? 'SUMATIP ' . get_sumatip_badge_label($_SESSION['sumatip_wizard']['tipe_asesmen']) : '')); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" rows="3"><?php echo escape($_SESSION['sumatip_wizard']['deskripsi'] ?? ''); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Durasi (menit) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" name="durasi" required min="1"
                           value="<?php echo $_SESSION['sumatip_wizard']['durasi'] ?? ($template ? $template['durasi_default'] : 120); ?>">
                </div>
                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="acak_soal" name="acak_soal"
                           <?php echo ($_SESSION['sumatip_wizard']['acak_soal'] ?? ($template ? $template['acak_soal_default'] : 1)) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="acak_soal">Acak Urutan Soal</label>
                </div>
                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="acak_opsi" name="acak_opsi"
                           <?php echo ($_SESSION['sumatip_wizard']['acak_opsi'] ?? ($template ? $template['acak_opsi_default'] : 1)) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="acak_opsi">Acak Urutan Opsi</label>
                </div>
                <div class="mb-3 form-check form-switch">
                    <input type="checkbox" class="form-check-input" id="anti_contek" name="anti_contek"
                           <?php echo ($_SESSION['sumatip_wizard']['anti_contek'] ?? ($template ? $template['anti_contek_default'] : 1)) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="anti_contek">Aktifkan Anti Contek</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Minimum Submit (menit)</label>
                    <input type="number" class="form-control" name="min_submit_minutes" min="0"
                           value="<?php echo $_SESSION['sumatip_wizard']['min_submit_minutes'] ?? ($template ? $template['min_submit_minutes_default'] : 0); ?>">
                </div>
                
            <?php elseif ($step == 6): ?>
                <!-- Step 6: Review & Create -->
                <h5>Step 6: Review & Create</h5>
                <div class="alert alert-info">
                    <h6>Summary:</h6>
                    <ul>
                        <li><strong>Jenis:</strong> <?php echo get_sumatip_badge_label($_SESSION['sumatip_wizard']['tipe_asesmen']); ?></li>
                        <li><strong>Periode:</strong> <?php echo $_SESSION['sumatip_wizard']['tahun_ajaran']; ?> - Semester <?php echo ucfirst($_SESSION['sumatip_wizard']['semester']); ?></li>
                        <li><strong>Judul:</strong> <?php echo escape($_SESSION['sumatip_wizard']['judul']); ?></li>
                        <li><strong>Durasi:</strong> <?php echo $_SESSION['sumatip_wizard']['durasi']; ?> menit</li>
                        <li><strong>Kelas:</strong> <?php echo count($_SESSION['sumatip_wizard']['id_kelas'] ?? []); ?> kelas</li>
                    </ul>
                </div>
                <p>Klik "Buat SUMATIP" untuk membuat SUMATIP dengan pengaturan di atas.</p>
            <?php endif; ?>
            
            <div class="d-flex gap-2 mt-4">
                <?php if ($step > 1): ?>
                    <a href="?step=<?php echo $step - 1; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Sebelumnya
                    </a>
                <?php endif; ?>
                
                <?php if ($step < 6): ?>
                    <button type="submit" class="btn btn-primary">
                        Lanjutkan <i class="fas fa-arrow-right"></i>
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Buat SUMATIP
                    </button>
                <?php endif; ?>
                
                <a href="<?php echo base_url('guru-ujian-sumatip-list'); ?>" class="btn btn-danger">
                    <i class="fas fa-times"></i> Batal
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>



