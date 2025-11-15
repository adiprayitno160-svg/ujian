# Release v1.0.22

**Tanggal Release:** 2025-01-XX

## 🎯 Perubahan Utama

### ✅ Perbaikan Fitur
- **Perbaikan Kontrol Menu Visibility**: Memperbaiki logika pengecekan visibility menu untuk siswa dan guru. Menu yang dimatikan di pengaturan sekarang benar-benar tidak muncul di halaman.
- **Perbaikan Display Menu**: Semua kondisi pengecekan menu visibility diperbaiki untuk memastikan menu hanya muncul jika setting = 1.

### 🧹 Pembersihan Kode
- **Hapus File Tidak Berguna**: Menghapus 18 file yang tidak diperlukan:
  - File test (test.php, test_gemini_api.php, admin/test_*.php)
  - File dengan nama aneh (file error/accidental)
  - File ZIP backup
  - File dokumentasi yang tidak diperlukan
  - File utility yang tidak digunakan

### 📦 Database
- **Export Database Structure**: Menambahkan file `database_structure.sql` untuk version control. File ini berisi struktur 85 tabel tanpa data.

### 🔧 Perbaikan Teknis
- **Perbaikan Logika Menu Visibility**: Mengganti operator `??` dengan pengecekan eksplisit `isset()` dan `== 1` untuk memastikan menu visibility bekerja dengan benar.
- **Perbaikan Referensi File**: Menghapus referensi ke file test yang sudah dihapus.

## 📝 Detail Perubahan

### File yang Ditambahkan
- `database_structure.sql` - Struktur database untuk version control
- `scripts/export_database_structure.php` - Script untuk export struktur database

### File yang Dihapus
- `test.php`
- `test_gemini_api.php`
- `admin/test_ai_api.php`
- `admin/test_ai_verifikasi.php`
- `admin/test_api_key_quick.php`
- `check_routing.php`
- `remove_anti_contek.php`
- `reset_fraud_sesi_9.php`
- `export_database.php`
- `create_release_v1.0.21.ps1`
- `release_v1.0.21.bat`
- File dengan nama aneh (error files)
- File ZIP backup
- File dokumentasi yang tidak diperlukan

### File yang Dimodifikasi
- `config/config.php` - Update versi ke 1.0.22
- `includes/header.php` - Perbaikan logika menu visibility
- `admin/list_gemini_models.php` - Hapus referensi ke file test yang sudah dihapus
- `.gitignore` - Tambah exception untuk database_structure.sql

## 🚀 Cara Update

1. Backup database terlebih dahulu
2. Pull perubahan terbaru dari repository:
   ```bash
   git pull origin main
   ```
3. Update database structure jika ada perubahan:
   ```bash
   # Import database_structure.sql jika diperlukan
   mysql -u username -p database_name < database_structure.sql
   ```
4. Clear cache browser jika diperlukan

## ⚠️ Catatan Penting

- File sensitif (config/database.php, config/ai_config.php) tetap tidak di-commit ke repository
- Database structure file hanya berisi struktur tabel, tidak termasuk data
- Pastikan untuk backup database sebelum melakukan update

## 📊 Statistik

- **Total File Berubah**: 108 files
- **Insertions**: 7,683 lines
- **Deletions**: 1,923 lines
- **File Baru**: 15 files
- **File Dihapus**: 18 files

---

**Dibuat oleh:** Sistem UJAN  
**Repository:** https://github.com/adiprayitno160-svg/ujian.git

