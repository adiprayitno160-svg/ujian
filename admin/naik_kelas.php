<?php
/**
 * Naik Kelas - Admin
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Sistem naik kelas otomatis dengan opsi menandai siswa yang tinggal kelas
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('admin');
check_session_timeout();

$page_title = 'Naik Kelas';
$role_css = 'admin';
include __DIR__ . '/../includes/header.php';

global $pdo;

$error = '';
$success = '';

// Handle naik kelas
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'naik_kelas') {
        $tahun_ajaran_lama = sanitize($_POST['tahun_ajaran_lama'] ?? '');
        $tahun_ajaran_baru = sanitize($_POST['tahun_ajaran_baru'] ?? '');
        $tinggal_kelas_ids = $_POST['tinggal_kelas'] ?? []; // Array of user IDs yang tinggal kelas
        
        if (empty($tahun_ajaran_lama) || empty($tahun_ajaran_baru)) {
            $error = 'Tahun ajaran lama dan baru harus diisi';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Get semua siswa aktif di tahun ajaran lama
                $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.nama, u.username, uk.id_kelas, k.tingkat, k.nama_kelas
                                       FROM users u
                                       INNER JOIN user_kelas uk ON u.id = uk.id_user
                                       INNER JOIN kelas k ON uk.id_kelas = k.id
                                       WHERE u.role = 'siswa' 
                                       AND u.status = 'active'
                                       AND uk.tahun_ajaran = ?
                                       ORDER BY k.tingkat, k.nama_kelas, u.nama");
                $stmt->execute([$tahun_ajaran_lama]);
                $all_siswa = $stmt->fetchAll();
                
                $tinggal_kelas_ids = array_map('intval', $tinggal_kelas_ids);
                $processed = 0;
                $tinggal_count = 0;
                
                foreach ($all_siswa as $siswa) {
                    $user_id = intval($siswa['id']);
                    $current_kelas_id = intval($siswa['id_kelas']);
                    $current_tingkat = $siswa['tingkat'];
                    
                    // Jika siswa tinggal kelas, tetap di kelas yang sama
                    if (in_array($user_id, $tinggal_kelas_ids)) {
                        // Update tahun ajaran saja, kelas tetap sama
                        $stmt = $pdo->prepare("UPDATE user_kelas 
                                              SET tahun_ajaran = ? 
                                              WHERE id_user = ? AND id_kelas = ? AND tahun_ajaran = ?");
                        $stmt->execute([$tahun_ajaran_baru, $user_id, $current_kelas_id, $tahun_ajaran_lama]);
                        
                        // Insert to history
                        $stmt = $pdo->prepare("INSERT INTO migrasi_history 
                                              (id_user, id_kelas_lama, id_kelas_baru, tahun_ajaran, semester, keterangan) 
                                              VALUES (?, ?, ?, ?, 'ganjil', 'Tinggal Kelas')");
                        $stmt->execute([$user_id, $current_kelas_id, $current_kelas_id, $tahun_ajaran_baru]);
                        
                        $tinggal_count++;
                    } else {
                        // Naik kelas berdasarkan tingkat
                        $new_tingkat = null;
                        if ($current_tingkat === 'VII') {
                            $new_tingkat = 'VIII';
                        } elseif ($current_tingkat === 'VIII') {
                            $new_tingkat = 'IX';
                        } elseif ($current_tingkat === 'IX') {
                            // Kelas IX tidak naik, tetap di IX
                            $new_tingkat = 'IX';
                        }
                        
                        if ($new_tingkat) {
                            $new_kelas_id = $current_kelas_id; // Default: tetap di kelas yang sama
                            
                            if ($current_tingkat === 'IX') {
                                // Kelas IX tetap di kelas yang sama
                                $new_kelas_id = $current_kelas_id;
                            } else {
                                // Cari kelas dengan tingkat baru di tahun ajaran baru
                                // Coba cari kelas dengan nama yang mirip dulu (misal: VII A -> VIII A)
                                $current_nama_kelas = $siswa['nama_kelas'];
                                
                                // Extract huruf kelas (A, B, C, dll)
                                $huruf_kelas = preg_replace('/[^A-Z]/', '', strtoupper($current_nama_kelas));
                                
                                // Cari kelas dengan tingkat baru dan huruf yang sama
                                $stmt = $pdo->prepare("SELECT id FROM kelas 
                                                      WHERE tingkat = ? 
                                                      AND tahun_ajaran = ? 
                                                      AND status = 'active'
                                                      AND (nama_kelas LIKE ? OR nama_kelas LIKE ?)
                                                      ORDER BY nama_kelas ASC
                                                      LIMIT 1");
                                $pattern1 = '%' . $huruf_kelas . '%';
                                $pattern2 = $new_tingkat . '%';
                                $stmt->execute([$new_tingkat, $tahun_ajaran_baru, $pattern1, $pattern2]);
                                $new_kelas = $stmt->fetch();
                                
                                if ($new_kelas) {
                                    $new_kelas_id = intval($new_kelas['id']);
                                } else {
                                    // Jika tidak ditemukan, ambil kelas pertama dengan tingkat baru
                                    $stmt = $pdo->prepare("SELECT id FROM kelas 
                                                          WHERE tingkat = ? 
                                                          AND tahun_ajaran = ? 
                                                          AND status = 'active'
                                                          ORDER BY nama_kelas ASC
                                                          LIMIT 1");
                                    $stmt->execute([$new_tingkat, $tahun_ajaran_baru]);
                                    $new_kelas = $stmt->fetch();
                                    
                                    if ($new_kelas) {
                                        $new_kelas_id = intval($new_kelas['id']);
                                    }
                                }
                            }
                            
                            // Update user_kelas
                            $stmt = $pdo->prepare("UPDATE user_kelas 
                                                  SET id_kelas = ?, tahun_ajaran = ? 
                                                  WHERE id_user = ? AND id_kelas = ? AND tahun_ajaran = ?");
                            $stmt->execute([$new_kelas_id, $tahun_ajaran_baru, $user_id, $current_kelas_id, $tahun_ajaran_lama]);
                            
                            // Insert to history
                            $stmt = $pdo->prepare("INSERT INTO migrasi_history 
                                                  (id_user, id_kelas_lama, id_kelas_baru, tahun_ajaran, semester, keterangan) 
                                                  VALUES (?, ?, ?, ?, 'ganjil', 'Naik Kelas')");
                            $stmt->execute([$user_id, $current_kelas_id, $new_kelas_id, $tahun_ajaran_baru]);
                        }
                    }
                    
                    $processed++;
                }
                
                $pdo->commit();
                $success = "Naik kelas berhasil! Diproses: $processed siswa, Tinggal kelas: $tinggal_count siswa";
                log_activity('naik_kelas', 'system', $_SESSION['user_id']);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
                error_log("Naik kelas error: " . $e->getMessage());
            }
        }
    }
}

// Get tahun ajaran saat ini
$tahun_ajaran_sekarang = get_tahun_ajaran_aktif();
// Generate tahun ajaran baru (tahun selanjutnya)
$current_year = (int)date('Y');
$tahun_ajaran_baru = ($current_year + 1) . '/' . ($current_year + 2);

// Get siswa per kelas untuk preview
$stmt = $pdo->prepare("SELECT DISTINCT u.id, u.nama, u.username, uk.id_kelas, k.tingkat, k.nama_kelas
                       FROM users u
                       INNER JOIN user_kelas uk ON u.id = uk.id_user
                       INNER JOIN kelas k ON uk.id_kelas = k.id
                       WHERE u.role = 'siswa' 
                       AND u.status = 'active'
                       AND uk.tahun_ajaran = ?
                       ORDER BY k.tingkat, k.nama_kelas, u.nama");
$stmt->execute([$tahun_ajaran_sekarang]);
$siswa_list = $stmt->fetchAll();

// Group siswa by kelas
$siswa_per_kelas = [];
foreach ($siswa_list as $siswa) {
    $kelas_key = $siswa['id_kelas'] . '_' . $siswa['tingkat'];
    if (!isset($siswa_per_kelas[$kelas_key])) {
        $siswa_per_kelas[$kelas_key] = [
            'kelas_id' => $siswa['id_kelas'],
            'nama_kelas' => $siswa['nama_kelas'],
            'tingkat' => $siswa['tingkat'],
            'siswa' => []
        ];
    }
    $siswa_per_kelas[$kelas_key]['siswa'][] = $siswa;
}

// Get history naik kelas
$stmt = $pdo->query("SELECT m.*, 
                    u.nama as nama_siswa,
                    k1.nama_kelas as kelas_lama, k2.nama_kelas as kelas_baru
                    FROM migrasi_history m
                    INNER JOIN users u ON m.id_user = u.id
                    INNER JOIN kelas k1 ON m.id_kelas_lama = k1.id
                    INNER JOIN kelas k2 ON m.id_kelas_baru = k2.id
                    ORDER BY m.created_at DESC
                    LIMIT 100");
$history = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Naik Kelas</h2>
        <p class="text-muted">Tandai siswa yang tinggal kelas. Siswa yang tidak ditandai akan naik kelas otomatis.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-hide="5000">
        <i class="fas fa-check-circle"></i> <?php echo escape($success); ?>
    </div>
<?php endif; ?>

<!-- Form Naik Kelas -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-user-times"></i> Tinggal Kelas</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="naikKelasForm">
            <input type="hidden" name="action" value="naik_kelas">
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label">Tahun Ajaran Lama <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="tahun_ajaran_lama" 
                           value="<?php echo escape($tahun_ajaran_sekarang); ?>" required
                           placeholder="2024/2025">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tahun Ajaran Baru <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="tahun_ajaran_baru" 
                           value="<?php echo escape($tahun_ajaran_baru); ?>" required
                           placeholder="2025/2026">
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong>Informasi:</strong>
                <ul class="mb-0 mt-2">
                    <li><strong>Tandai checkbox</strong> untuk siswa yang <strong>tinggal kelas</strong> (tidak naik kelas)</li>
                    <li>Siswa yang <strong>tidak ditandai</strong> akan naik kelas secara otomatis (VII → VIII, VIII → IX)</li>
                    <li>Siswa kelas IX akan tetap di kelas IX</li>
                    <li>Siswa yang ditandai tinggal kelas akan tetap di kelas yang sama di tahun ajaran baru</li>
                </ul>
            </div>
            
            <hr>
            
            <h5 class="mb-3">Daftar Siswa (Tahun Ajaran: <?php echo escape($tahun_ajaran_sekarang); ?>)</h5>
            
            <?php if (empty($siswa_per_kelas)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> Tidak ada siswa di tahun ajaran ini.
                </div>
            <?php else: ?>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover table-sm">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll" title="Pilih Semua">
                                </th>
                                <th>No</th>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas Saat Ini</th>
                                <th>Tingkat</th>
                                <th>Kelas Baru</th>
                                <th>Tinggal Kelas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            foreach ($siswa_per_kelas as $kelas_data): 
                                foreach ($kelas_data['siswa'] as $siswa):
                                    $new_tingkat = null;
                                    $new_kelas_text = '';
                                    if ($kelas_data['tingkat'] === 'VII') {
                                        $new_tingkat = 'VIII';
                                        $new_kelas_text = 'VIII (akan ditentukan)';
                                    } elseif ($kelas_data['tingkat'] === 'VIII') {
                                        $new_tingkat = 'IX';
                                        $new_kelas_text = 'IX (akan ditentukan)';
                                    } elseif ($kelas_data['tingkat'] === 'IX') {
                                        $new_tingkat = 'IX';
                                        $new_kelas_text = 'IX (tetap)';
                                    }
                            ?>
                            <tr id="row-<?php echo $siswa['id']; ?>">
                                <td>
                                    <input type="checkbox" name="tinggal_kelas[]" 
                                           value="<?php echo $siswa['id']; ?>" 
                                           class="tinggal-kelas-checkbox"
                                           data-siswa-id="<?php echo $siswa['id']; ?>"
                                           title="Centang jika siswa ini tinggal kelas">
                                </td>
                                <td><?php echo $no++; ?></td>
                                <td><?php echo escape($siswa['username']); ?></td>
                                <td><?php echo escape($siswa['nama']); ?></td>
                                <td><?php echo escape($kelas_data['nama_kelas']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo escape($kelas_data['tingkat']); ?></span>
                                </td>
                                <td>
                                    <?php if ($new_tingkat): ?>
                                        <span class="badge bg-<?php echo $new_tingkat === 'IX' ? 'warning' : 'success'; ?>" id="kelas-baru-<?php echo $siswa['id']; ?>">
                                            <?php echo $new_kelas_text; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-success" id="status-<?php echo $siswa['id']; ?>">Naik Kelas</span>
                                </td>
                            </tr>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <p class="text-muted">
                        <strong>Total siswa:</strong> <?php echo count($siswa_list); ?> siswa
                        | <strong>Yang ditandai tinggal kelas:</strong> <span id="tinggalCount">0</span> siswa
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg" 
                        onclick="return confirm('Yakin proses naik/tinggal kelas? Pastikan tahun ajaran sudah benar.')">
                    <i class="fas fa-user-times"></i> Tinggal Kelas
                </button>
                <button type="button" class="btn btn-secondary btn-lg" onclick="clearAll()">
                    <i class="fas fa-times"></i> Hapus Semua Tanda
                </button>
            </div>
        </form>
    </div>
</div>

<!-- History Naik Kelas -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-history"></i> Riwayat Naik Kelas</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas Lama</th>
                        <th>Kelas Baru</th>
                        <th>Tahun Ajaran</th>
                        <th>Keterangan</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">Tidak ada riwayat</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?php echo escape($h['nama_siswa']); ?></td>
                            <td><?php echo escape($h['kelas_lama']); ?></td>
                            <td><?php echo escape($h['kelas_baru']); ?></td>
                            <td><?php echo escape($h['tahun_ajaran']); ?></td>
                            <td>
                                <?php if (isset($h['keterangan'])): ?>
                                    <span class="badge bg-<?php echo $h['keterangan'] === 'Tinggal Kelas' ? 'danger' : 'success'; ?>">
                                        <?php echo escape($h['keterangan']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">Naik Kelas</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo format_date($h['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Update count
function updateTinggalCount() {
    const checked = document.querySelectorAll('.tinggal-kelas-checkbox:checked').length;
    const tinggalCountEl = document.getElementById('tinggalCount');
    if (tinggalCountEl) {
        tinggalCountEl.textContent = checked;
    }
}

// Update status badge when checkbox changes
function updateTinggalStatus(siswaId, isChecked) {
    const statusBadge = document.getElementById('status-' + siswaId);
    const kelasBaruBadge = document.getElementById('kelas-baru-' + siswaId);
    const row = document.getElementById('row-' + siswaId);
    
    if (!statusBadge || !row) return;
    
    if (isChecked) {
        statusBadge.className = 'badge bg-danger';
        statusBadge.textContent = 'Tinggal Kelas';
        if (kelasBaruBadge) {
            // Update kelas baru to show akan tetap di kelas yang sama
            const currentKelas = row.querySelector('td:nth-child(5)').textContent.trim();
            kelasBaruBadge.className = 'badge bg-warning';
            kelasBaruBadge.textContent = currentKelas + ' (tetap)';
        }
        row.style.backgroundColor = '#fff5f5';
    } else {
        statusBadge.className = 'badge bg-success';
        statusBadge.textContent = 'Naik Kelas';
        if (kelasBaruBadge) {
            // Restore original kelas baru
            const tingkatEl = row.querySelector('.badge.bg-primary');
            if (tingkatEl) {
                const tingkat = tingkatEl.textContent.trim();
                if (tingkat === 'VII') {
                    kelasBaruBadge.className = 'badge bg-success';
                    kelasBaruBadge.textContent = 'VIII (akan ditentukan)';
                } else if (tingkat === 'VIII') {
                    kelasBaruBadge.className = 'badge bg-success';
                    kelasBaruBadge.textContent = 'IX (akan ditentukan)';
                } else if (tingkat === 'IX') {
                    kelasBaruBadge.className = 'badge bg-warning';
                    kelasBaruBadge.textContent = 'IX (tetap)';
                }
            }
        }
        row.style.backgroundColor = '';
    }
    updateTinggalCount();
}

// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.tinggal-kelas-checkbox');
    checkboxes.forEach(cb => {
        const siswaId = cb.getAttribute('data-siswa-id');
        if (siswaId) {
            cb.checked = this.checked;
            updateTinggalStatus(parseInt(siswaId), this.checked);
        }
    });
});

function clearAll() {
    if (confirm('Hapus semua tanda tinggal kelas?')) {
        document.querySelectorAll('.tinggal-kelas-checkbox').forEach(cb => {
            const siswaId = cb.getAttribute('data-siswa-id');
            if (siswaId) {
                cb.checked = false;
                updateTinggalStatus(parseInt(siswaId), false);
            }
        });
        const selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = false;
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize count
    updateTinggalCount();
    
    // Add event listeners to all checkboxes
    document.querySelectorAll('.tinggal-kelas-checkbox').forEach(cb => {
        const siswaId = cb.getAttribute('data-siswa-id');
        if (siswaId) {
            // Initialize status for already checked boxes
            if (cb.checked) {
                updateTinggalStatus(parseInt(siswaId), true);
            }
            
            // Add change event listener
            cb.addEventListener('change', function() {
                updateTinggalStatus(parseInt(siswaId), this.checked);
            });
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

