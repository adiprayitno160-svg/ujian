# Release Notes v1.0.23

## Tanggal Release
**2025-01-XX**

## Perbaikan Bug (Bug Fixes)

### 1. Fixed: Undefined Variable $search di Naik Kelas
- **Masalah**: Warning "Undefined variable $search" di `admin/naik_kelas.php` line 298
- **Solusi**: 
  - Menambahkan inisialisasi `$search = sanitize($_GET['search'] ?? '');`
  - Menambahkan filter search pada query untuk mencari siswa berdasarkan nama, NISN, NIS, atau kelas
  - Fitur search sekarang berfungsi dengan baik

### 2. Fixed: Tahun Ajaran Tidak Bisa Dipilih di Import Ledger Nilai
- **Masalah**: Dropdown tahun ajaran di modal Import Ledger Nilai tidak bisa dipilih atau kosong
- **Solusi**:
  - Memperbaiki query untuk mendapatkan daftar tahun ajaran dengan multiple fallback:
    1. Query dari tabel `tahun_ajaran`
    2. Query dari tabel `user_kelas` (jika tabel tahun_ajaran kosong)
    3. Menggunakan fungsi `get_all_tahun_ajaran()` sebagai fallback
  - Memastikan struktur data `$tahun_ajaran_list` selalu memiliki key `tahun_ajaran`
  - Normalisasi data untuk memastikan format konsisten

## Fitur Baru

### 3. Script Create Admin User
- **File**: `create_admin.php`, `scripts/create_admin_ssh.php`, `INSERT_ADMIN.sql`
- **Fungsi**: Membantu membuat user admin default setelah ganti database
- **Cara Pakai**:
  - Via Browser: `http://localhost/UJAN/create_admin.php`
  - Via SSH: `php create_admin.php` atau `php create_admin.php username password "Nama"`
  - Via SQL: Copy-paste query dari `INSERT_ADMIN.sql` ke phpMyAdmin
- **Keamanan**: 
  - Untuk live server, script memerlukan token keamanan
  - Auto-detect localhost vs live server

## Perubahan Teknis

### Database
- Tidak ada perubahan struktur database

### Dependencies
- Tidak ada perubahan dependencies

## Catatan Upgrade

### Untuk Upgrade dari v1.0.22 ke v1.0.23:

1. **Backup Database** (Sangat Penting!)
   ```bash
   # Backup database sebelum upgrade
   mysqldump -u root -p ujian > backup_ujian_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Update File**
   - Upload semua file baru ke server
   - Pastikan file berikut ter-update:
     - `admin/naik_kelas.php`
     - `admin/ledger_nilai.php`
     - `config/config.php`

3. **Clear Cache** (jika ada)
   - Hapus cache browser
   - Clear session jika diperlukan

4. **Verifikasi**
   - Test fitur Naik Kelas dengan search
   - Test Import Ledger Nilai dan pastikan tahun ajaran bisa dipilih
   - Pastikan tidak ada error di error log

## File yang Diubah

- `admin/naik_kelas.php` - Fix undefined variable $search dan tambah fitur search
- `admin/ledger_nilai.php` - Fix dropdown tahun ajaran di modal import
- `config/config.php` - Update versi ke 1.0.23
- `create_admin.php` - Script baru untuk create admin user
- `scripts/create_admin_ssh.php` - Script SSH untuk create admin
- `INSERT_ADMIN.sql` - SQL script untuk create admin
- `scripts/create_admin_simple.sql` - SQL script sederhana
- `scripts/create_admin_helper.php` - Helper untuk generate password hash

## Known Issues

Tidak ada known issues pada release ini.

## Support

Jika menemukan bug atau masalah, silakan laporkan melalui:
- Issue tracker (jika menggunakan Git)
- Email support
- Dokumentasi: Lihat file `README.md`

---

**Catatan**: Release ini fokus pada perbaikan bug kritis yang mempengaruhi penggunaan fitur Naik Kelas dan Import Ledger Nilai.

