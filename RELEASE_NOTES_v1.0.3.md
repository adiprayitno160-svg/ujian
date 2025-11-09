# Release Notes v1.0.3

## Fixes
- **Sistem Update Otomatis:** 
  - Perbaikan timeout handling dengan timeout yang lebih pendek (5s untuk API, 8s untuk JavaScript)
  - Tambah fallback ke Git tags lokal saat GitHub API timeout atau tidak tersedia
  - Perbaikan error handling dengan pesan yang lebih informatif
  - Support untuk cache dan fallback mechanism yang lebih robust

- **Form Tanggal Lahir:** 
  - Form tanggal lahir dipindahkan dari halaman siswa ke halaman admin
  - Siswa hanya bisa melihat tanggal lahir, tidak bisa mengubah
  - Admin dapat mengisi dan mengubah tanggal lahir siswa di halaman manage siswa

- **Pilihan Ganda:** 
  - Batasi pilihan ganda hanya sampai D (opsi E dihapus)
  - Filter opsi E pada soal yang sudah ada saat ditampilkan ke siswa
  - Update di semua form create/edit soal (ujian dan PR)

- **Fitur Check Plagiarisme:** 
  - Hapus fitur check plagiarisme dari ujian
  - Fitur check plagiarisme hanya tersedia untuk PR (Pekerjaan Rumah)

## New Features
- **Script Update SSH:** 
  - Tambah script `update.sh` untuk Linux/Unix/Mac (Bash)
  - Tambah script `update.ps1` untuk Windows (PowerShell)
  - Script melakukan backup database otomatis sebelum update
  - Support untuk update ke versi tertentu atau latest
  - Support untuk update dari branch tertentu
  - Auto stash perubahan lokal sebelum update
  - Clear cache setelah update

## Improvements
- **Version Check System:**
  - Sistem check versi menggunakan GitHub Releases API dengan fallback ke Git tags
  - Cache mechanism untuk mengurangi request ke GitHub API
  - Fast mode untuk Git tags (tidak fetch dari remote) untuk menghindari timeout
  - Notifikasi update di header untuk admin
  - UI yang lebih baik untuk check update di halaman About

- **Error Handling:**
  - Pesan error yang lebih informatif dan user-friendly
  - Saran untuk troubleshooting
  - Support untuk berbagai tipe error (timeout, 404, connection error)

## Technical Changes
- Tambah file `includes/version_check.php` untuk sistem check versi
- Update `api/github_sync.php` dengan endpoint baru untuk check versi
- Update `admin/about.php` dengan UI untuk check update
- Update `includes/header.php` dengan notifikasi update
- Tambah folder `scripts/` dengan script update SSH

## Migration Notes
- Tidak ada perubahan database schema
- Pastikan folder `cache/` memiliki permission write
- Pastikan Git terinstall untuk menggunakan script update SSH
- Untuk menggunakan script update, berikan permission execute: `chmod +x scripts/update.sh`

## Known Issues
- Script update memerlukan Git terinstall
- Backup database memerlukan mysqldump terinstall
- Pastikan koneksi internet stabil untuk check update dari GitHub API

## Installation
1. Pull update dari GitHub: `git pull origin master`
2. Atau gunakan script update: `./scripts/update.sh` (Linux/Mac) atau `.\scripts\update.ps1` (Windows)
3. Clear cache jika diperlukan: `rm -rf cache/*.json`
4. Test aplikasi untuk memastikan semua berfungsi

## Upgrade dari v1.0.2
1. Backup database: `mysqldump -u username -p database_name > backup.sql`
2. Pull update: `git pull origin master` atau gunakan script update
3. Clear cache: `rm -rf cache/*.json`
4. Test aplikasi

