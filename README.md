# Sistem Ujian dan Pekerjaan Rumah (UJAN)

Sistem ujian dan pekerjaan rumah berbasis web dengan PHP dan MySQL, dirancang mirip sistem ANBK dengan fitur keamanan ketat.

## Fitur Utama

### Tipe Soal
- ✅ Pilihan Ganda (Multiple Choice)
- ✅ Isian Singkat (Fill in the Blank)
- ✅ Benar atau Salah (True/False)
- ✅ Menghubungkan/Mencocokkan (Matching)
- ✅ Respons Uraian/Esai (Essay)

### Role & Fitur

#### Admin
- Manajemen Users (Admin, Guru, Operator, Siswa)
- Manajemen Kelas & Mata Pelajaran
- Pengaturan Sekolah (Nama, Logo, Alamat)
- Approve Migrasi Kelas
- System Logs

#### Guru
- Buat & Kelola Ujian dengan semua tipe soal
- Jadwal Sesi Ujian
- Bank Soal dengan tagging
- Kelola PR (Pekerjaan Rumah)
- Review & Nilai
- Analisis Butir Soal
- Plagiarisme Check
- AI Correction (Google Gemini)
- Kontrol Token
- Kontrol semua fitur ujian (acak soal, anti contek, dll)

#### Operator
- Kelola Sesi Ujian
- Assign Peserta (Individual/Kelas)
- Kontrol Token
- Real-time Monitoring
- Analisis & Plagiarisme
- Kontrol Fitur Ujian

#### Siswa
- Kerjakan Ujian dengan timer
- Submit PR
- Lihat Hasil
- Fitur Ragu-Ragu (Before & After)
- Auto-save
- Resume ujian jika terputus

### Fitur Keamanan

- **Anti Contek**: Deteksi tab switch, copy-paste, screenshot, multiple device, idle
- **Device Fingerprinting**: Identifikasi dan tracking perangkat
- **App Lock**: Lock aplikasi lain saat ujian
- **Plagiarisme Check**: Deteksi kesamaan jawaban
- **Token System**: Kontrol akses dengan token
- **Session Security**: Timeout dan validation

### Fitur Tambahan

- Auto-save setiap 30 detik
- Offline mode dengan localStorage backup
- Analisis Butir Soal (Tingkat Kesukaran, Daya Beda, Efektivitas Distraktor)
- AI Correction dengan Google Gemini
- Migrasi Naik Kelas
- Real-time Monitoring
- Export/Import Excel
- Responsive Design (Mobile-friendly)

## Requirements

- PHP 7.4+ (recommended: PHP 8.0+)
- MySQL 5.7+ atau MariaDB 10.3+
- Apache/Nginx dengan mod_rewrite
- PHP Extensions: PDO, PDO_MySQL, GD/Imagick, mbstring, zip, json, curl
- Minimum 2GB RAM (recommended: 4GB+)
- SSL Certificate (HTTPS recommended untuk production)

## Installation

1. **Clone atau extract project ke web server**
   ```bash
   cd C:\xampp\htdocs\UJAN
   ```

2. **Import Database**
   - Buka phpMyAdmin atau MySQL client
   - Import file `database.sql`
   - Database akan dibuat otomatis dengan nama `ujian`

3. **Konfigurasi Database**
   - Edit `config/database.php`
   - Sesuaikan DB_HOST, DB_USER, DB_PASS sesuai environment Anda

4. **Set Permissions**
   ```bash
   chmod 755 assets/uploads
   chmod 755 assets/uploads/soal
   chmod 755 assets/uploads/pr
   chmod 755 assets/uploads/profile
   ```

5. **Konfigurasi AI (Optional)**
   - Edit `config/ai_config.php`
   - Masukkan Google Gemini API key jika ingin menggunakan AI Correction

6. **Akses Aplikasi**
   - Buka browser: `http://localhost/UJAN`
   - Login default admin:
     - Username: `admin`
     - Password: `admin123`

## Struktur File

```
UJAN/
├── config/              # Konfigurasi
│   ├── database.php
│   ├── config.php
│   └── ai_config.php
├── includes/            # Core functions
│   ├── auth.php
│   ├── functions.php
│   ├── security.php
│   ├── analisis_butir.php
│   ├── plagiarisme_check.php
│   ├── ai_correction.php
│   ├── header.php
│   └── footer.php
├── admin/              # Halaman Admin
├── guru/               # Halaman Guru
├── operator/           # Halaman Operator
├── siswa/              # Halaman Siswa
├── api/                # API Endpoints
├── assets/             # CSS, JS, Images
│   ├── css/
│   ├── js/
│   └── uploads/
├── database.sql        # Database structure
└── README.md
```

## Penggunaan

### Login
- **Siswa**: `/siswa/login.php`
- **Guru**: `/guru/login.php`
- **Operator**: `/operator/login.php`
- **Admin**: `/admin/login.php`

### Membuat Ujian (Guru)
1. Login sebagai Guru
2. Klik "Buat Ujian Baru"
3. Isi informasi ujian
4. Tambahkan soal (pilih tipe soal)
5. Buat Sesi untuk jadwal ujian
6. Assign peserta ke sesi

### Mengerjakan Ujian (Siswa)
1. Login sebagai Siswa
2. Pilih ujian yang tersedia
3. Masukkan token (jika diperlukan)
4. Kerjakan soal
5. Gunakan fitur "Ragu-ragu" untuk menandai soal
6. Klik "Selesai" setelah selesai

### Analisis Butir Soal (Guru)
1. Setelah ujian selesai
2. Buka menu "Analisis Butir Soal"
3. Pilih ujian
4. Lihat analisis: Tingkat Kesukaran, Daya Beda, Efektivitas Distraktor

### Plagiarisme Check (Guru/Operator)
1. Buka menu "Plagiarisme"
2. Pilih ujian
3. Klik "Check Plagiarisme"
4. Review hasil similarity

## Security Best Practices

1. **Ganti password default** setelah instalasi
2. **Gunakan HTTPS** di production
3. **Update API keys** secara berkala
4. **Backup database** secara rutin
5. **Monitor security logs** untuk aktivitas mencurigakan
6. **Set proper file permissions**

## Troubleshooting

### Database Connection Error
- Pastikan MySQL service running
- Check credentials di `config/database.php`
- Pastikan database `ujian` sudah dibuat

### Upload Error
- Check folder permissions: `assets/uploads`
- Check `php.ini`: `upload_max_filesize` dan `post_max_size`

### Session Error
- Check `session.save_path` di `php.ini`
- Pastikan folder session writable

## Development

### Menambah Fitur Baru
1. Buat file di folder role yang sesuai
2. Include `header.php` dan `footer.php`
3. Gunakan helper functions dari `includes/functions.php`
4. Follow pattern yang sudah ada

### Database Changes
1. Update `database.sql`
2. Buat migration script jika perlu
3. Update model functions jika ada perubahan struktur

## Support

Untuk pertanyaan atau issue, silakan buat issue di repository atau hubungi administrator.

## License

Copyright © 2024 - Sistem Ujian dan Pekerjaan Rumah (UJAN)

## Credits

- Bootstrap 5.3.x
- Font Awesome 6.x
- Chart.js 4.x
- Google Fonts (Inter, Poppins)
- Google Gemini API

---

**Note**: Sistem ini masih dalam tahap pengembangan. Beberapa fitur mungkin belum sepenuhnya diimplementasikan. Silakan kembangkan sesuai kebutuhan.

