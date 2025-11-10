<?php
/**
 * About - Informasi Sistem untuk Guru
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Menampilkan informasi lengkap tentang semua fitur dan fungsi sistem untuk Guru
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Only allow access for guru
if ($_SESSION['role'] !== 'guru') {
    redirect('index.php');
}

$page_title = 'Informasi Sistem';
$role_css = 'guru';
include __DIR__ . '/../includes/header.php';

$sekolah = get_sekolah_info();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Informasi Sistem</h2>
        <p class="text-muted">Informasi lengkap tentang semua fitur dan fungsi sistem UJAN untuk Guru</p>
    </div>
</div>

<!-- System Information -->
<div class="row g-4 mb-4">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informasi Sistem</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th width="200">Nama Aplikasi</th>
                        <td><?php echo escape(APP_NAME); ?></td>
                    </tr>
                    <tr>
                        <th>Versi</th>
                        <td><?php echo escape(APP_VERSION ?? '1.0.0'); ?></td>
                    </tr>
                    <tr>
                        <th>PHP Version</th>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <th>Database</th>
                        <td><?php echo DB_NAME; ?></td>
                    </tr>
                    <?php if ($sekolah): ?>
                    <tr>
                        <th>Sekolah</th>
                        <td><?php echo escape($sekolah['nama_sekolah'] ?? '-'); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fitur Guru -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Fitur & Fungsi untuk Guru</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Ujian -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-file-alt"></i> Manajemen Ujian
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat ujian baru dengan soal pilihan ganda</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola soal ujian (tambah, edit, hapus)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Atur durasi dan waktu ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set pengaturan ujian (acak soal, acak jawaban, dll)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat detail dan statistik ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Preview ujian sebelum publish</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sesi Ujian -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-calendar"></i> Manajemen Sesi Ujian
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat sesi ujian dengan jadwal tertentu</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Assign peserta (per kelas atau per individu)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola token akses ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Monitor status sesi (aktif, selesai)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar peserta per sesi</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Aktifkan/nonaktifkan sesi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PR (Pekerjaan Rumah) -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-tasks"></i> Manajemen PR (Pekerjaan Rumah)
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat PR baru dengan soal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set deadline pengumpulan</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Assign PR ke kelas tertentu</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Review jawaban siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Beri nilai dan feedback</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat statistik pengumpulan PR</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tugas -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-clipboard-list"></i> Manajemen Tugas
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat tugas baru dengan instruksi</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set deadline pengumpulan</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Upload file attachment</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Review tugas yang dikumpulkan siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Beri nilai dan feedback</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat statistik pengumpulan tugas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Penilaian Manual -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-star"></i> Penilaian Manual
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Input nilai manual per siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Input nilai Tugas, UTS, UAS</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Hitung nilai akhir otomatis</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set predikat (A, B, C, D)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Submit nilai ke operator</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status penilaian (draft, submitted, approved)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Absensi -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-user-check"></i> Manajemen Absensi
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Input absensi siswa per pertemuan</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Catat kehadiran (hadir, izin, sakit, alpha)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Export data absensi ke Excel</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat rekapitulasi absensi</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Filter absensi berdasarkan periode</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment Soal (untuk guru yang memiliki akses) -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-file-question"></i> Pembuatan Soal Assessment
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat soal assessment (SUMATIP)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set tipe assessment (tengah semester, akhir semester, akhir tahun)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Upload soal ke bank soal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Tunggu approval dari operator</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status soal (pending, approved, rejected)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Kelola Siswa -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-user-graduate"></i> Kelola Siswa
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat detail siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Filter siswa berdasarkan kelas</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai dan statistik siswa</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

