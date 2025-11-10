<?php
/**
 * About - Informasi Sistem untuk Operator
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Menampilkan informasi lengkap tentang semua fitur dan fungsi sistem untuk Guru dan Operator
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
check_session_timeout();

// Allow access for operator, admin, and guru (guru can see their features too)
if (!has_operator_access() && $_SESSION['role'] !== 'guru') {
    redirect('index.php');
}

$page_title = 'Informasi Sistem';
// Use appropriate role_css based on user role
$role_css = $_SESSION['role'] === 'guru' ? 'guru' : 'operator';
include __DIR__ . '/../includes/header.php';

$sekolah = get_sekolah_info();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Informasi Sistem</h2>
        <p class="text-muted">Informasi lengkap tentang semua fitur dan fungsi sistem UJAN untuk Guru dan Operator</p>
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
                    
                    <!-- Penilaian Manual -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-clipboard-list"></i> Penilaian Manual
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
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fitur Operator -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-cog"></i> Fitur & Fungsi untuk Operator</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Kelola Siswa & Kelas -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-user-graduate"></i> Kelola Siswa & Kelas
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola data siswa (tambah, edit, hapus)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Import data siswa dari Excel</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Assign siswa ke kelas</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola kelas (tambah, edit, hapus)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set tahun ajaran dan semester</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar siswa per kelas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Template Raport -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-file-alt"></i> Template Raport
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Buat dan edit template raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Set format raport (format nilai, predikat)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Preview template raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Generate raport per siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Export raport ke PDF</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ledger Nilai Manual -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-book"></i> Ledger Nilai Manual
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Verifikasi nilai manual dari guru</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Approve/reject nilai yang dikirim guru</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Aktifkan nilai manual untuk digunakan di raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat ledger semua nilai manual</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Filter berdasarkan tahun ajaran, semester, kelas</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sesi -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-calendar"></i> Manajemen Sesi
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola semua sesi ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Assign peserta ke sesi</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola token akses</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Monitor status sesi secara real-time</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Aktifkan/nonaktifkan sesi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Monitoring -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-chart-line"></i> Monitoring Real-time
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Monitor ujian secara real-time</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat statistik peserta yang sedang ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Track progress ujian per peserta</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat waktu tersisa per peserta</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Deteksi masalah selama ujian</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Assessment -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-clipboard-check"></i> Manajemen Assessment (SUMATIP)
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola SUMATIP (tengah semester, akhir semester, akhir tahun)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Buat assessment dari bank soal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Approve/reject soal dari guru</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola jadwal assessment</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Buat jadwal ujian susulan</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Input nilai assessment</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Generate berita acara</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola absensi assessment</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Raport -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-file-alt"></i> Manajemen Raport
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Generate raport per siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat detail raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Export raport ke PDF</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Print raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Filter raport berdasarkan kelas, semester</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Verifikasi Dokumen -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-file-shield"></i> Verifikasi Dokumen
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Verifikasi dokumen siswa (khusus kelas IX)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Approve/reject dokumen yang diupload siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status verifikasi per siswa</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Export data verifikasi</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Arsip Soal -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-info">
                                    <i class="fas fa-database"></i> Arsip Soal
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Kelola arsip soal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat semua soal yang pernah dibuat</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Search dan filter soal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Restore soal dari arsip</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cara Penggunaan -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-book"></i> Panduan Penggunaan</h5>
            </div>
            <div class="card-body">
                <div class="accordion" id="panduanAccordion">
                    <!-- Panduan Guru -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panduanGuru">
                                <i class="fas fa-chalkboard-teacher me-2"></i> Panduan untuk Guru
                            </button>
                        </h2>
                        <div id="panduanGuru" class="accordion-collapse collapse show" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>1. Membuat Ujian</h6>
                                <p>Untuk membuat ujian baru, klik menu "Ujian" > "Buat Ujian Baru". Isi informasi ujian (judul, mata pelajaran, durasi), lalu tambahkan soal. Setelah selesai, klik "Publish" untuk mempublikasikan ujian.</p>
                                
                                <h6>2. Membuat Sesi Ujian</h6>
                                <p>Untuk membuat sesi ujian, klik menu "Sesi" > "Buat Sesi". Pilih ujian yang akan digunakan, set waktu mulai dan selesai, lalu assign peserta (per kelas atau per individu).</p>
                                
                                <h6>3. Input Penilaian Manual</h6>
                                <p>Untuk input nilai manual, klik menu "Penilaian Manual". Pilih tahun ajaran, semester, mata pelajaran, dan kelas. Input nilai untuk setiap siswa, lalu klik "Kumpulkan ke Operator" untuk mengirim nilai.</p>
                                
                                <h6>4. Input Absensi</h6>
                                <p>Untuk input absensi, klik menu "Absensi". Pilih kelas dan pertemuan, lalu catat kehadiran siswa. Data absensi dapat di-export ke Excel.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panduan Operator -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panduanOperator">
                                <i class="fas fa-user-cog me-2"></i> Panduan untuk Operator
                            </button>
                        </h2>
                        <div id="panduanOperator" class="accordion-collapse collapse" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>1. Kelola Siswa dan Kelas</h6>
                                <p>Untuk mengelola siswa, klik menu "Kelola Siswa". Anda dapat menambah, edit, atau menghapus data siswa. Untuk mengelola kelas, klik menu "Kelola Kelas".</p>
                                
                                <h6>2. Verifikasi Nilai Manual</h6>
                                <p>Untuk verifikasi nilai manual dari guru, klik menu "Ledger Nilai Manual". Review nilai yang dikirim guru, lalu approve atau reject. Setelah approve, aktifkan nilai untuk digunakan di raport.</p>
                                
                                <h6>3. Kelola Assessment (SUMATIP)</h6>
                                <p>Untuk mengelola assessment, klik menu "Assessment". Buat assessment baru dari bank soal, approve soal dari guru, set jadwal, dan input nilai assessment.</p>
                                
                                <h6>4. Generate Raport</h6>
                                <p>Untuk generate raport, klik menu "Raport". Pilih siswa, lalu klik "Generate Raport". Raport dapat di-export ke PDF atau di-print.</p>
                                
                                <h6>5. Monitoring Real-time</h6>
                                <p>Untuk memonitor ujian secara real-time, klik menu "Monitoring". Anda dapat melihat statistik peserta yang sedang ujian, track progress, dan waktu tersisa.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Informasi Kontak -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-headset"></i> Bantuan & Dukungan</h5>
            </div>
            <div class="card-body">
                <p>Jika Anda memerlukan bantuan atau memiliki pertanyaan tentang penggunaan sistem, silakan hubungi administrator.</p>
                <p class="mb-0"><strong>Catatan:</strong> Pastikan untuk selalu logout setelah menggunakan sistem untuk menjaga keamanan data.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

