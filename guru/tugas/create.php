<?php
/**
 * Create Tugas - Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_role('guru');
check_session_timeout();

global $pdo;

$error = '';
$success = '';

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul = sanitize($_POST['judul'] ?? '');
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $instruksi_khusus = sanitize($_POST['instruksi_khusus'] ?? '');
    $id_mapel = intval($_POST['id_mapel'] ?? 0);
    $waktu_mulai = $_POST['waktu_mulai'] ?? '';
    $deadline = $_POST['deadline'] ?? '';
    $kelas_ids = $_POST['kelas_ids'] ?? [];
    $poin_maksimal = floatval($_POST['poin_maksimal'] ?? 100);
    $tipe_tugas = sanitize($_POST['tipe_tugas'] ?? 'individu');
    $tipe_tugas_mode = sanitize($_POST['tipe_tugas_mode'] ?? 'file'); // 'file' or 'soal'
    $prioritas = sanitize($_POST['prioritas'] ?? 'sedang'); // tinggi, sedang, rendah
    $kategori = sanitize($_POST['kategori'] ?? '');
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $max_files = intval($_POST['max_files'] ?? 5);
    $max_file_size_mb = intval($_POST['max_file_size'] ?? 10);
    $max_file_size = $max_file_size_mb * 1048576; // Convert MB to bytes
    $allowed_extensions = sanitize($_POST['allowed_extensions'] ?? 'pdf,doc,docx,zip,rar,ppt,pptx');
    $allow_edit_after_submit = isset($_POST['allow_edit_after_submit']) ? 1 : 0;
    $enable_reminder = isset($_POST['enable_reminder']) ? 1 : 0;
    $reminder_days = intval($_POST['reminder_days'] ?? 1);
    $status = sanitize($_POST['status'] ?? 'published');
    $resource_links_json = $_POST['resource_links_json'] ?? '[]';
    $rubric_json = $_POST['rubric_json'] ?? '[]';
    $scheduled_publish_time = $_POST['scheduled_publish_time'] ?? null;
    
    // Validate
    $data = [
        'judul' => $judul,
        'id_mapel' => $id_mapel,
        'deadline' => $deadline,
        'kelas_ids' => $kelas_ids,
        'poin_maksimal' => $poin_maksimal
    ];
    
    $errors = validate_tugas_data($data);
    
    // Validasi: Guru hanya bisa membuat tugas untuk mata pelajaran yang dia ajar
    // Sistem menggunakan guru mata pelajaran (bukan guru kelas)
    if (empty($errors) && !guru_mengajar_mapel($_SESSION['user_id'], $id_mapel)) {
        $errors[] = 'Anda tidak diizinkan membuat tugas untuk mata pelajaran ini. Pastikan Anda telah di-assign ke mata pelajaran ini oleh admin.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert Tugas
            // Check if columns exist
            $check_cols = $pdo->query("SHOW COLUMNS FROM tugas");
            $columns = $check_cols->fetchAll(PDO::FETCH_COLUMN);
            $has_tipe_tugas_mode = in_array('tipe_tugas_mode', $columns);
            $has_waktu_mulai = in_array('waktu_mulai', $columns);
            $has_instruksi_khusus = in_array('instruksi_khusus', $columns);
            $has_prioritas = in_array('prioritas', $columns);
            $has_kategori = in_array('kategori', $columns);
            $has_enable_reminder = in_array('enable_reminder', $columns);
            $has_reminder_days = in_array('reminder_days', $columns);
            
            // Build dynamic INSERT query based on available columns
            $fields = ['judul', 'deskripsi', 'id_mapel', 'id_guru', 'deadline', 'poin_maksimal', 
                      'tipe_tugas', 'allow_late_submission', 'max_files', 'max_file_size', 
                      'allowed_extensions', 'allow_edit_after_submit', 'status'];
            $values = [$judul, $deskripsi, $id_mapel, $_SESSION['user_id'], $deadline, $poin_maksimal,
                      $tipe_tugas, $allow_late_submission, $max_files, $max_file_size,
                      $allowed_extensions, $allow_edit_after_submit, $status];
            
            if ($has_tipe_tugas_mode) {
                $fields[] = 'tipe_tugas_mode';
                $values[] = $tipe_tugas_mode;
            }
            if ($has_waktu_mulai && $waktu_mulai) {
                $fields[] = 'waktu_mulai';
                $values[] = $waktu_mulai;
            }
            if ($has_instruksi_khusus) {
                $fields[] = 'instruksi_khusus';
                $values[] = $instruksi_khusus;
            }
            if ($has_prioritas) {
                $fields[] = 'prioritas';
                $values[] = $prioritas;
            }
            if ($has_kategori) {
                $fields[] = 'kategori';
                $values[] = $kategori;
            }
            if ($has_enable_reminder) {
                $fields[] = 'enable_reminder';
                $values[] = $enable_reminder;
            }
            if ($has_reminder_days) {
                $fields[] = 'reminder_days';
                $values[] = $reminder_days;
            }
            
            // Check for additional columns
            $has_resource_links = in_array('resource_links', $columns);
            $has_rubric = in_array('rubric', $columns);
            $has_scheduled_publish_time = in_array('scheduled_publish_time', $columns);
            
            if ($has_resource_links && !empty($resource_links_json)) {
                $fields[] = 'resource_links';
                $values[] = $resource_links_json;
            }
            if ($has_rubric && !empty($rubric_json)) {
                $fields[] = 'rubric';
                $values[] = $rubric_json;
            }
            if ($has_scheduled_publish_time && $scheduled_publish_time && $status === 'scheduled') {
                $fields[] = 'scheduled_publish_time';
                $values[] = $scheduled_publish_time;
            }
            
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            $stmt = $pdo->prepare("INSERT INTO tugas (" . implode(', ', $fields) . ") VALUES ($placeholders)");
            $stmt->execute($values);
            $tugas_id = $pdo->lastInsertId();
            
            // Assign to kelas
            foreach ($kelas_ids as $kelas_id) {
                $kelas_id = intval($kelas_id);
                $stmt = $pdo->prepare("INSERT INTO tugas_kelas (id_tugas, id_kelas) VALUES (?, ?)");
                $stmt->execute([$tugas_id, $kelas_id]);
            }
            
            // Handle file attachments
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = UPLOAD_PR; // Using same directory as PR
                $files = $_FILES['attachments'];
                $file_count = count($files['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];
                        
                        $upload_result = upload_file($file, $upload_dir, ALLOWED_DOC_TYPES);
                        if ($upload_result['success']) {
                            $stmt = $pdo->prepare("INSERT INTO tugas_attachment 
                                                  (id_tugas, nama_file, file_path, file_size, file_type, urutan) 
                                                  VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $tugas_id,
                                $file['name'],
                                $upload_result['filename'],
                                $file['size'],
                                $file['type'],
                                $i + 1
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Tugas berhasil dibuat';
            log_activity('create_tugas', 'tugas', $tugas_id);
            
            header("Location: " . base_url('guru/tugas/list.php'));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Create Tugas error: " . $e->getMessage());
            $error = 'Terjadi kesalahan saat membuat Tugas';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get mapel for this guru
$stmt = $pdo->prepare("SELECT m.* FROM mapel m
                      INNER JOIN guru_mapel gm ON m.id = gm.id_mapel
                      WHERE gm.id_guru = ?
                      ORDER BY m.nama_mapel ASC");
$stmt->execute([$_SESSION['user_id']]);
$mapel_list = $stmt->fetchAll();

// Get kelas
$stmt = $pdo->query("SELECT * FROM kelas WHERE status = 'active' ORDER BY tahun_ajaran DESC, nama_kelas ASC");
$kelas_list = $stmt->fetchAll();

$page_title = 'Buat Tugas Baru';
$role_css = 'guru';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Buat Tugas Baru</h2>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if (empty($mapel_list)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Anda belum di-assign ke mata pelajaran.</strong><br>
        Sistem menggunakan guru mata pelajaran (bukan guru kelas). 
        Hubungi admin untuk assign mata pelajaran yang akan Anda ajarkan.
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="tugasForm">
                <!-- Quick Actions -->
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="loadFromTemplate()">
                        <i class="fas fa-file-alt"></i> Load Template
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-info" onclick="duplicateFromExisting()">
                        <i class="fas fa-copy"></i> Duplicate dari Tugas Lain
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="previewTugas()">
                        <i class="fas fa-eye"></i> Preview
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="saveAsTemplate()">
                        <i class="fas fa-save"></i> Simpan sebagai Template
                    </button>
                </div>
                
                <div class="mb-3">
                    <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="judul" name="judul" required 
                           placeholder="Contoh: Tugas Matematika - Persamaan Linear">
                    <small class="text-muted">Masukkan judul tugas yang jelas dan deskriptif</small>
                </div>
                
                <div class="mb-3">
                    <label for="deskripsi" class="form-label">Deskripsi Tugas</label>
                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="4" 
                              placeholder="Jelaskan tugas yang harus dikerjakan siswa..."></textarea>
                    <small class="text-muted">Berikan penjelasan umum tentang tugas ini</small>
                </div>
                
                <div class="mb-3">
                    <label for="instruksi_khusus" class="form-label">
                        <i class="fas fa-info-circle"></i> Instruksi Khusus
                    </label>
                    <textarea class="form-control" id="instruksi_khusus" name="instruksi_khusus" rows="3" 
                              placeholder="Instruksi khusus, ketentuan tambahan, atau catatan penting untuk siswa..."></textarea>
                    <small class="text-muted">Instruksi khusus yang akan ditampilkan dengan jelas kepada siswa</small>
                </div>
                
                <!-- Resource Links Section -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-link"></i> Resource Links / Referensi Eksternal
                    </label>
                    <div id="resourceLinks">
                        <div class="input-group mb-2">
                            <input type="text" class="form-control resource-link-title" placeholder="Judul referensi (contoh: Video Tutorial)">
                            <input type="url" class="form-control resource-link-url" placeholder="URL (https://...)">
                            <button type="button" class="btn btn-outline-danger" onclick="removeResourceLink(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addResourceLink()">
                        <i class="fas fa-plus"></i> Tambah Link
                    </button>
                    <small class="text-muted d-block mt-1">Tambahkan link ke video, artikel, atau sumber belajar lainnya</small>
                </div>
                
                <!-- Grading Rubric Section -->
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-clipboard-list"></i> Rubrik Penilaian (Opsional)
                    </label>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Rubrik penilaian membantu siswa memahami kriteria penilaian dan memudahkan guru dalam memberikan nilai yang konsisten.
                    </div>
                    <div id="rubricItems">
                        <div class="card mb-2">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control form-control-sm mb-2 rubric-criteria" 
                                               placeholder="Kriteria (contoh: Ketepatan Jawaban)">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" class="form-control form-control-sm mb-2 rubric-points" 
                                               placeholder="Poin" min="0" step="0.1">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100" 
                                                onclick="removeRubricItem(this)">
                                            <i class="fas fa-times"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                                <textarea class="form-control form-control-sm rubric-description" rows="2" 
                                          placeholder="Deskripsi kriteria penilaian..."></textarea>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addRubricItem()">
                        <i class="fas fa-plus"></i> Tambah Kriteria Rubrik
                    </button>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="id_mapel" class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                            
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
                                <select class="form-select" id="id_mapel" name="id_mapel" required>
                                    <option value="">Pilih Mata Pelajaran</option>
                                    <?php foreach ($mapel_list as $mapel): ?>
                                        <option value="<?php echo $mapel['id']; ?>">
                                            <?php echo escape($mapel['nama_mapel']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Pilih salah satu mata pelajaran yang Anda ampu</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="waktu_mulai" class="form-label">
                                <i class="fas fa-calendar-check"></i> Waktu Mulai
                            </label>
                            <input type="datetime-local" class="form-control" id="waktu_mulai" name="waktu_mulai">
                            <small class="text-muted">Kapan tugas mulai dapat diakses (opsional)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="deadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="deadline" name="deadline" required>
                            <small class="text-muted">Batas waktu pengumpulan tugas</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="tipe_tugas_mode" class="form-label">Tipe Tugas <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipe_tugas_mode" name="tipe_tugas_mode" required onchange="toggleTugasMode()">
                                <option value="file">File Submission</option>
                                <option value="soal">Soal (Pilihan Ganda, Esai, dll)</option>
                            </select>
                            <small class="text-muted">Pilih tipe tugas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="tipe_tugas" class="form-label">Tipe Pengerjaan</label>
                            <select class="form-select" id="tipe_tugas" name="tipe_tugas">
                                <option value="individu">Individu</option>
                                <option value="kelompok">Kelompok</option>
                            </select>
                            <small class="text-muted">Individu atau kelompok</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="poin_maksimal" class="form-label">Poin Maksimal</label>
                            <input type="number" class="form-control" id="poin_maksimal" name="poin_maksimal" 
                                   value="100" min="0" max="100" step="0.1">
                            <small class="text-muted">Nilai maksimal tugas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label for="prioritas" class="form-label">
                                <i class="fas fa-flag"></i> Prioritas
                            </label>
                            <select class="form-select" id="prioritas" name="prioritas">
                                <option value="rendah">Rendah</option>
                                <option value="sedang" selected>Sedang</option>
                                <option value="tinggi">Tinggi</option>
                            </select>
                            <small class="text-muted">Tingkat prioritas tugas</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="kategori" class="form-label">
                                <i class="fas fa-tags"></i> Kategori
                            </label>
                            <input type="text" class="form-control" id="kategori" name="kategori" 
                                   placeholder="Contoh: Tugas Harian, Proyek, Praktikum">
                            <small class="text-muted">Kategori tugas untuk memudahkan pengorganisasian</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" onchange="toggleScheduling()">
                                <option value="published">Published (Langsung)</option>
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled (Terjadwal)</option>
                            </select>
                            <small class="text-muted">Pilih status publikasi tugas</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-bell"></i> Reminder
                            </label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="enable_reminder" 
                                       name="enable_reminder" value="1" onchange="toggleReminder()">
                                <label class="form-check-label" for="enable_reminder">
                                    Aktifkan reminder deadline
                                </label>
                            </div>
                            <div id="reminder_settings" style="display:none;">
                                <input type="number" class="form-control form-control-sm" id="reminder_days" 
                                       name="reminder_days" value="1" min="1" max="30">
                                <small class="text-muted">Hari sebelum deadline untuk mengirim reminder</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Scheduling Section -->
                <div id="scheduling_section" style="display:none;" class="mb-3">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <i class="fas fa-clock"></i> Penjadwalan Publikasi
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="scheduled_publish_time" class="form-label">Waktu Publikasi Otomatis</label>
                                <input type="datetime-local" class="form-control" id="scheduled_publish_time" 
                                       name="scheduled_publish_time">
                                <small class="text-muted">Tugas akan otomatis dipublish pada waktu yang ditentukan</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- File Submission Settings (shown when tipe_tugas_mode = 'file') -->
                <div id="file_settings">
                    <hr>
                    <h5 class="mb-3">Pengaturan File Submission</h5>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_files" class="form-label">Maksimal File</label>
                                <input type="number" class="form-control" id="max_files" name="max_files" 
                                       value="5" min="1" max="20">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_file_size" class="form-label">Maksimal Ukuran File (MB)</label>
                                <input type="number" class="form-control" id="max_file_size" name="max_file_size" 
                                       value="10" min="1" max="100">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="allowed_extensions" class="form-label">Ekstensi File Diizinkan</label>
                                <input type="text" class="form-control" id="allowed_extensions" name="allowed_extensions" 
                                       value="pdf,doc,docx,zip,rar,ppt,pptx" 
                                       placeholder="pdf,doc,docx">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_late_submission" 
                                   name="allow_late_submission" value="1">
                            <label class="form-check-label" for="allow_late_submission">
                                Izinkan submit setelah deadline
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="allow_edit_after_submit" 
                                   name="allow_edit_after_submit" value="1" checked>
                            <label class="form-check-label" for="allow_edit_after_submit">
                                Izinkan edit setelah submit (sebelum deadline)
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">File Lampiran (opsional)</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                               accept=".pdf,.doc,.docx,.zip,.rar,.ppt,.pptx">
                        <small class="text-muted">Bisa upload multiple files</small>
                    </div>
                </div>
                
                <!-- Soal Settings (shown when tipe_tugas_mode = 'soal') -->
                <div id="soal_settings" style="display:none;">
                    <hr>
                    <h5 class="mb-3">Pengaturan Soal</h5>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Setelah membuat tugas, Anda dapat menambahkan soal melalui halaman detail tugas.
                        Tugas dengan tipe soal akan memiliki fitur seperti ujian (pilihan ganda, esai, isian singkat, dll).
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-users"></i> Assign ke Kelas <span class="text-danger">*</span>
                    </label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllKelas()">
                            <i class="fas fa-check-double"></i> Pilih Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllKelas()">
                            <i class="fas fa-times"></i> Batal Pilih Semua
                        </button>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background: #f8f9fa;">
                        <?php 
                        $current_tahun = get_tahun_ajaran_aktif();
                        $grouped_kelas = [];
                        foreach ($kelas_list as $kelas) {
                            $tahun = $kelas['tahun_ajaran'];
                            if (!isset($grouped_kelas[$tahun])) {
                                $grouped_kelas[$tahun] = [];
                            }
                            $grouped_kelas[$tahun][] = $kelas;
                        }
                        krsort($grouped_kelas); // Sort tahun descending
                        ?>
                        <?php foreach ($grouped_kelas as $tahun => $kelas_tahun): ?>
                            <div class="mb-3">
                                <strong class="text-primary d-block mb-2">
                                    <i class="fas fa-calendar-alt"></i> Tahun Ajaran: <?php echo escape($tahun); ?>
                                    <?php if ($tahun === $current_tahun): ?>
                                        <span class="badge bg-success ms-2">Aktif</span>
                                    <?php endif; ?>
                                </strong>
                                <?php foreach ($kelas_tahun as $kelas): ?>
                                <div class="form-check ms-3 mb-2">
                                    <input class="form-check-input kelas-checkbox" type="checkbox" name="kelas_ids[]" 
                                           value="<?php echo $kelas['id']; ?>" id="kelas_<?php echo $kelas['id']; ?>">
                                    <label class="form-check-label" for="kelas_<?php echo $kelas['id']; ?>">
                                        <?php echo escape($kelas['nama_kelas']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i> Pilih kelas yang akan menerima tugas ini
                    </small>
                </div>
                
                <!-- Hidden fields for resource links and rubric -->
                <input type="hidden" id="resource_links_json" name="resource_links_json" value="">
                <input type="hidden" id="rubric_json" name="rubric_json" value="">
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Buat Tugas
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="saveDraft()">
                        <i class="fas fa-file-alt"></i> Simpan Draft
                    </button>
                    <a href="<?php echo base_url('guru/tugas/list.php'); ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
function toggleTugasMode() {
    const mode = document.getElementById('tipe_tugas_mode').value;
    const fileSettings = document.getElementById('file_settings');
    const soalSettings = document.getElementById('soal_settings');
    
    if (mode === 'soal') {
        if (fileSettings) fileSettings.style.display = 'none';
        if (soalSettings) soalSettings.style.display = 'block';
    } else {
        if (fileSettings) fileSettings.style.display = 'block';
        if (soalSettings) soalSettings.style.display = 'none';
    }
}

function toggleReminder() {
    const enableReminder = document.getElementById('enable_reminder').checked;
    const reminderSettings = document.getElementById('reminder_settings');
    if (reminderSettings) {
        reminderSettings.style.display = enableReminder ? 'block' : 'none';
    }
}

function selectAllKelas() {
    document.querySelectorAll('.kelas-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllKelas() {
    document.querySelectorAll('.kelas-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Validate deadline is after waktu_mulai
function validateDates() {
    const waktuMulai = document.getElementById('waktu_mulai').value;
    const deadline = document.getElementById('deadline').value;
    
    if (waktuMulai && deadline && waktuMulai >= deadline) {
        alert('Warning: Deadline harus setelah waktu mulai!');
        return false;
    }
    return true;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleTugasMode();
    toggleReminder();
    
    // Set minimum deadline to today
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const minDateTime = now.toISOString().slice(0, 16);
    document.getElementById('deadline').setAttribute('min', minDateTime);
    document.getElementById('waktu_mulai').setAttribute('min', minDateTime);
    
    // Validate on form submit
    const form = document.getElementById('tugasForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateDates()) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// Auto-validate when dates change
document.getElementById('deadline')?.addEventListener('change', validateDates);
document.getElementById('waktu_mulai')?.addEventListener('change', validateDates);

// Resource Links Functions
function addResourceLink() {
    const container = document.getElementById('resourceLinks');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="text" class="form-control resource-link-title" placeholder="Judul referensi (contoh: Video Tutorial)">
        <input type="url" class="form-control resource-link-url" placeholder="URL (https://...)">
        <button type="button" class="btn btn-outline-danger" onclick="removeResourceLink(this)">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

function removeResourceLink(btn) {
    btn.closest('.input-group').remove();
}

// Rubric Functions
function addRubricItem() {
    const container = document.getElementById('rubricItems');
    const div = document.createElement('div');
    div.className = 'card mb-2';
    div.innerHTML = `
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control form-control-sm mb-2 rubric-criteria" 
                           placeholder="Kriteria (contoh: Ketepatan Jawaban)">
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control form-control-sm mb-2 rubric-points" 
                           placeholder="Poin" min="0" step="0.1">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" 
                            onclick="removeRubricItem(this)">
                        <i class="fas fa-times"></i> Hapus
                    </button>
                </div>
            </div>
            <textarea class="form-control form-control-sm rubric-description" rows="2" 
                      placeholder="Deskripsi kriteria penilaian..."></textarea>
        </div>
    `;
    container.appendChild(div);
}

function removeRubricItem(btn) {
    btn.closest('.card').remove();
}

// Scheduling
function toggleScheduling() {
    const status = document.getElementById('status').value;
    const schedulingSection = document.getElementById('scheduling_section');
    if (status === 'scheduled') {
        schedulingSection.style.display = 'block';
    } else {
        schedulingSection.style.display = 'none';
    }
}

// Save resource links and rubric before submit
document.querySelector('form').addEventListener('submit', function(e) {
    // Collect resource links
    const resourceLinks = [];
    document.querySelectorAll('#resourceLinks .input-group').forEach(group => {
        const title = group.querySelector('.resource-link-title').value.trim();
        const url = group.querySelector('.resource-link-url').value.trim();
        if (title && url) {
            resourceLinks.push({ title, url });
        }
    });
    document.getElementById('resource_links_json').value = JSON.stringify(resourceLinks);
    
    // Collect rubric items
    const rubricItems = [];
    document.querySelectorAll('#rubricItems .card').forEach(card => {
        const criteria = card.querySelector('.rubric-criteria').value.trim();
        const points = parseFloat(card.querySelector('.rubric-points').value) || 0;
        const description = card.querySelector('.rubric-description').value.trim();
        if (criteria) {
            rubricItems.push({ criteria, points, description });
        }
    });
    document.getElementById('rubric_json').value = JSON.stringify(rubricItems);
});

// Template Functions
function loadFromTemplate() {
    // TODO: Implement template loading from database or localStorage
    alert('Fitur Load Template akan segera tersedia. Template akan disimpan di database.');
}

function duplicateFromExisting() {
    window.location.href = '<?php echo base_url("guru/tugas/list.php?action=duplicate"); ?>';
}

function saveAsTemplate() {
    const judul = document.getElementById('judul').value;
    if (!judul) {
        alert('Judul harus diisi terlebih dahulu');
        return;
    }
    // TODO: Implement template saving
    alert('Fitur Simpan Template akan segera tersedia. Template akan disimpan di database.');
}

function previewTugas() {
    // Collect form data
    const formData = {
        judul: document.getElementById('judul').value,
        deskripsi: document.getElementById('deskripsi').value,
        instruksi_khusus: document.getElementById('instruksi_khusus').value,
        deadline: document.getElementById('deadline').value,
        poin_maksimal: document.getElementById('poin_maksimal').value
    };
    
    // Open preview in new window
    const previewWindow = window.open('', '_blank', 'width=800,height=600');
    previewWindow.document.write(`
        <html>
        <head>
            <title>Preview Tugas</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="p-4">
            <h2>${formData.judul || 'Judul Tugas'}</h2>
            <hr>
            <div class="mb-3">
                <strong>Deskripsi:</strong>
                <p>${formData.deskripsi || '-'}</p>
            </div>
            <div class="mb-3">
                <strong>Instruksi Khusus:</strong>
                <p>${formData.instruksi_khusus || '-'}</p>
            </div>
            <div class="mb-3">
                <strong>Deadline:</strong> ${formData.deadline || '-'}
            </div>
            <div class="mb-3">
                <strong>Poin Maksimal:</strong> ${formData.poin_maksimal || 100}
            </div>
            <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
        </body>
        </html>
    `);
}

function saveDraft() {
    document.getElementById('status').value = 'draft';
    document.querySelector('form').submit();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

