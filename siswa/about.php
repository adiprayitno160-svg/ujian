<?php
/**
 * About - Informasi Sistem untuk Siswa
 * Sistem Ujian dan Pekerjaan Rumah (UJAN)
 * Menampilkan informasi lengkap tentang semua fitur dan fungsi sistem untuk Siswa
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_role('siswa');
check_session_timeout();

// Check if student is in exam mode - redirect to exam if they try to access other pages
if (function_exists('check_exam_mode_restriction')) {
    check_exam_mode_restriction(['about.php']);
}

$page_title = 'Informasi Sistem';
$role_css = 'siswa';
include __DIR__ . '/../includes/header.php';

$sekolah = get_sekolah_info();
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="fw-bold">Informasi Sistem</h2>
        <p class="text-muted">Informasi lengkap tentang semua fitur dan fungsi sistem UJAN untuk Siswa</p>
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

<!-- Fitur Siswa -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-user-graduate"></i> Fitur & Fungsi untuk Siswa</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Ujian -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-file-alt"></i> Ujian Online
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar ujian yang tersedia</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Ikuti ujian online sesuai jadwal</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Timer countdown untuk waktu ujian</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Soal acak (jika diaktifkan guru)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Jawaban acak (jika diaktifkan guru)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Submit jawaban secara otomatis saat waktu habis</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat hasil ujian setelah selesai</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat jawaban benar/salah</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai dan skor</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- PR (Pekerjaan Rumah) -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-tasks"></i> PR (Pekerjaan Rumah)
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar PR yang diberikan guru</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat deadline pengumpulan PR</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Kerjakan PR secara online</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Upload file jawaban (jika diperlukan)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Submit PR sebelum deadline</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status PR (belum dikerjakan, sudah dikumpulkan, sudah dinilai)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai dan feedback dari guru</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tugas -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-clipboard-list"></i> Tugas
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat daftar tugas yang diberikan guru</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat detail tugas (instruksi, deadline)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Upload file tugas</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Submit tugas sebelum deadline</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status tugas (belum dikerjakan, sudah dikumpulkan, sudah dinilai)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai dan feedback dari guru</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Raport -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-file-alt"></i> Raport
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat raport nilai per semester</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai per mata pelajaran</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai tugas, UTS, UAS</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat nilai akhir dan predikat</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat absensi per mata pelajaran</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Print raport</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Export raport ke PDF</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Verifikasi Dokumen (Khusus Kelas IX) -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-file-shield"></i> Verifikasi Dokumen (Kelas IX)
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Upload dokumen yang diperlukan (khusus kelas IX)</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat status verifikasi dokumen</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat feedback dari operator</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Re-upload dokumen jika ditolak</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile -->
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title text-primary">
                                    <i class="fas fa-user"></i> Profile
                                </h6>
                                <ul class="list-unstyled mb-0">
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat informasi profile</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat NIS, nama, kelas</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Lihat tahun ajaran aktif</li>
                                    <li><i class="fas fa-check text-success me-2"></i> Ubah password (jika diizinkan)</li>
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
                    <!-- Panduan Ujian -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#panduanUjian">
                                <i class="fas fa-file-alt me-2"></i> Cara Mengikuti Ujian
                            </button>
                        </h2>
                        <div id="panduanUjian" class="accordion-collapse collapse show" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>Langkah-langkah mengikuti ujian:</h6>
                                <ol>
                                    <li>Login ke sistem menggunakan NIS dan password</li>
                                    <li>Klik menu "Ujian" untuk melihat daftar ujian yang tersedia</li>
                                    <li>Pilih ujian yang ingin diikuti (pastikan waktu ujian sudah dimulai)</li>
                                    <li>Klik "Mulai Ujian" untuk memulai ujian</li>
                                    <li>Baca soal dengan teliti dan pilih jawaban yang benar</li>
                                    <li>Pastikan semua soal sudah dijawab sebelum waktu habis</li>
                                    <li>Klik "Submit" untuk mengumpulkan jawaban (atau akan otomatis submit saat waktu habis)</li>
                                    <li>Setelah selesai, Anda dapat melihat hasil ujian (nilai, jawaban benar/salah)</li>
                                </ol>
                                <p class="text-danger"><strong>Penting:</strong> Pastikan koneksi internet stabil selama ujian. Jangan refresh atau menutup browser selama ujian berlangsung.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panduan PR -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panduanPR">
                                <i class="fas fa-tasks me-2"></i> Cara Mengerjakan PR
                            </button>
                        </h2>
                        <div id="panduanPR" class="accordion-collapse collapse" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>Langkah-langkah mengerjakan PR:</h6>
                                <ol>
                                    <li>Klik menu "PR" untuk melihat daftar PR yang diberikan guru</li>
                                    <li>Pilih PR yang ingin dikerjakan</li>
                                    <li>Baca instruksi dan soal dengan teliti</li>
                                    <li>Kerjakan PR sesuai instruksi (jawab soal online atau upload file)</li>
                                    <li>Pastikan semua jawaban sudah lengkap</li>
                                    <li>Klik "Submit" untuk mengumpulkan PR sebelum deadline</li>
                                    <li>Setelah dikumpulkan, Anda dapat melihat status PR dan nilai (setelah dinilai guru)</li>
                                </ol>
                                <p class="text-warning"><strong>Catatan:</strong> Pastikan mengumpulkan PR sebelum deadline. PR yang dikumpulkan setelah deadline mungkin tidak akan diterima.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panduan Tugas -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panduanTugas">
                                <i class="fas fa-clipboard-list me-2"></i> Cara Mengumpulkan Tugas
                            </button>
                        </h2>
                        <div id="panduanTugas" class="accordion-collapse collapse" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>Langkah-langkah mengumpulkan tugas:</h6>
                                <ol>
                                    <li>Klik menu "Tugas" untuk melihat daftar tugas yang diberikan guru</li>
                                    <li>Pilih tugas yang ingin dikerjakan</li>
                                    <li>Baca instruksi dan detail tugas dengan teliti</li>
                                    <li>Kerjakan tugas sesuai instruksi</li>
                                    <li>Upload file tugas (jika diperlukan)</li>
                                    <li>Klik "Submit" untuk mengumpulkan tugas sebelum deadline</li>
                                    <li>Setelah dikumpulkan, Anda dapat melihat status tugas dan nilai (setelah dinilai guru)</li>
                                </ol>
                                <p class="text-warning"><strong>Catatan:</strong> Pastikan file yang diupload sesuai dengan format yang diminta. Ukuran file maksimal sesuai yang ditentukan.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panduan Raport -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panduanRaport">
                                <i class="fas fa-file-alt me-2"></i> Cara Melihat Raport
                            </button>
                        </h2>
                        <div id="panduanRaport" class="accordion-collapse collapse" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>Langkah-langkah melihat raport:</h6>
                                <ol>
                                    <li>Klik menu "Raport" untuk melihat daftar raport</li>
                                    <li>Pilih semester yang ingin dilihat</li>
                                    <li>Lihat nilai per mata pelajaran (tugas, UTS, UAS, nilai akhir, predikat)</li>
                                    <li>Lihat absensi per mata pelajaran</li>
                                    <li>Klik "Print" untuk mencetak raport</li>
                                    <li>Klik "Export PDF" untuk mengunduh raport dalam format PDF</li>
                                </ol>
                                <p class="text-info"><strong>Info:</strong> Raport hanya dapat dilihat setelah operator generate raport untuk semester tersebut.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Panduan Verifikasi Dokumen -->
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#panduanDokumen">
                                <i class="fas fa-file-shield me-2"></i> Cara Upload Dokumen (Kelas IX)
                            </button>
                        </h2>
                        <div id="panduanDokumen" class="accordion-collapse collapse" data-bs-parent="#panduanAccordion">
                            <div class="accordion-body">
                                <h6>Langkah-langkah upload dokumen (khusus kelas IX):</h6>
                                <ol>
                                    <li>Klik menu "Verifikasi Dokumen" (hanya untuk kelas IX)</li>
                                    <li>Lihat daftar dokumen yang diperlukan</li>
                                    <li>Upload dokumen sesuai dengan jenis yang diminta</li>
                                    <li>Pastikan file dokumen jelas dan dapat dibaca</li>
                                    <li>Klik "Submit" untuk mengirim dokumen</li>
                                    <li>Tunggu verifikasi dari operator</li>
                                    <li>Lihat status verifikasi (pending, approved, rejected)</li>
                                    <li>Jika ditolak, perbaiki dan re-upload dokumen</li>
                                </ol>
                                <p class="text-warning"><strong>Catatan:</strong> Pastikan dokumen yang diupload jelas, lengkap, dan sesuai dengan format yang diminta.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tips & Trik -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Tips & Trik</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="alert alert-info mb-0">
                            <h6><i class="fas fa-check-circle me-2"></i> Tips Mengikuti Ujian</h6>
                            <ul class="mb-0">
                                <li>Pastikan koneksi internet stabil sebelum mulai ujian</li>
                                <li>Gunakan browser yang kompatibel (Chrome, Firefox, Edge)</li>
                                <li>Jangan refresh atau menutup browser selama ujian</li>
                                <li>Perhatikan waktu tersisa yang ditampilkan</li>
                                <li>Jawab semua soal sebelum waktu habis</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-warning mb-0">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i> Hal yang Perlu Diperhatikan</h6>
                            <ul class="mb-0">
                                <li>Selalu logout setelah menggunakan sistem</li>
                                <li>Jangan share password dengan siapapun</li>
                                <li>Kumpulkan PR dan tugas sebelum deadline</li>
                                <li>Periksa status pengumpulan setelah submit</li>
                                <li>Hubungi guru jika ada masalah teknis</li>
                            </ul>
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
                <p>Jika Anda mengalami masalah atau memiliki pertanyaan tentang penggunaan sistem, silakan hubungi:</p>
                <ul>
                    <li><strong>Guru:</strong> Untuk pertanyaan tentang ujian, PR, atau tugas</li>
                    <li><strong>Operator:</strong> Untuk pertanyaan tentang raport, dokumen, atau masalah teknis</li>
                    <li><strong>Admin:</strong> Untuk pertanyaan tentang akun atau sistem</li>
                </ul>
                <p class="mb-0"><strong>Catatan:</strong> Pastikan untuk selalu logout setelah menggunakan sistem untuk menjaga keamanan data.</p>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

