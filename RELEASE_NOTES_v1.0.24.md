# Release Notes v1.0.24

## Tanggal Release
**2025-01-XX**

## Perbaikan Bug (Bug Fixes)

### 1. Fixed: Import Ledger Nilai - Kode Mata Pelajaran Tidak Ditemukan
- **Masalah**: Error "Mata pelajaran dengan kode 'XXX' tidak ditemukan" saat import Excel
- **Solusi**: 
  - Sistem mapping mata pelajaran dibuat lebih fleksibel dengan 4 metode pencarian:
    1. Exact match kode mapel
    2. Alternatif kode (misal: PA&PBP → PA PBP, PAI, PENDIDIKAN AGAMA)
    3. Partial match di kode mapel
    4. Partial match di nama mapel
  - Menambahkan mapping alternatif untuk semua mata pelajaran standar:
    - PA&PBP: PA PBP, PAI, PENDIDIKAN AGAMA, PENDIDIKAN AGAMA ISLAM
    - P.PANQ: PPKN, PANCASILA, PENDIDIKAN PANCASILA, PKN
    - B.INDO: BAHASA INDONESIA, BHS INDONESIA, BINDO
    - MAT: MATEMATIKA, MATE
    - IPA: ILMU PENGETAHUAN ALAM
    - IPS: ILMU PENGETAHUAN SOSIAL
    - B.INGG: BAHASA INGGRIS, BHS INGGRIS, BING
    - PRAK: PRAKARYA, PRAKARYA DAN KEWIRAUSAHAAN
    - PJOK: PENDIDIKAN JASMANI, PENJAS, PENJASORKES
    - INFOR: INFORMATIKA, TIK, TEKNOLOGI INFORMASI
    - B.JAWA: BAHASA JAWA, BHS JAWA, BJ
  - Sistem sekarang mencari berdasarkan kode dan nama mapel
  - Mengurangi error "tidak ditemukan" secara signifikan

### 2. Fixed: Sistem Update GitHub Menggunakan Release Terbaru
- **Masalah**: Sistem update hanya pull dari branch main, tidak menggunakan tag release terbaru
- **Solusi**:
  - Menambahkan fungsi `checkoutGitTag()` untuk checkout ke tag tertentu
  - Update API sekarang mendukung parameter `tag` dan `use_latest_tag`
  - Halaman update otomatis menggunakan tag terbaru dari GitHub releases
  - Jika ada release terbaru, sistem akan checkout ke tag tersebut (bukan hanya pull branch)
  - Memastikan update selalu ke versi release yang stabil

## Perubahan Teknis

### Database
- Tidak ada perubahan struktur database

### Dependencies
- Tidak ada perubahan dependencies

## Catatan Upgrade

### Untuk Upgrade dari v1.0.23 ke v1.0.24:

1. **Backup Database** (Sangat Penting!)
   ```bash
   # Backup database sebelum upgrade
   mysqldump -u root -p ujian > backup_ujian_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Update File**
   - Upload semua file baru ke server
   - Pastikan file berikut ter-update:
     - `admin/ledger_nilai.php`
     - `api/github_sync.php`
     - `admin/update_system.php`
     - `admin/about.php`
     - `config/config.php`

3. **Clear Cache** (jika ada)
   - Hapus cache browser
   - Clear session jika diperlukan

4. **Verifikasi**
   - Test Import Ledger Nilai dengan berbagai format kode mapel
   - Test Update System dan pastikan menggunakan tag release terbaru
   - Pastikan tidak ada error di error log

## File yang Diubah

- `admin/ledger_nilai.php` - Mapping mapel fleksibel dengan alternatif kode
- `api/github_sync.php` - Fungsi checkout tag & support tag di pull
- `admin/update_system.php` - Gunakan tag terbaru saat update
- `admin/about.php` - Gunakan tag terbaru saat update
- `config/config.php` - Update versi ke 1.0.24

## Known Issues

Tidak ada known issues pada release ini.

## Support

Jika menemukan bug atau masalah, silakan laporkan melalui:
- Issue tracker (jika menggunakan Git)
- Email support
- Dokumentasi: Lihat file `README.md`

---

**Catatan**: Release ini fokus pada perbaikan bug kritis yang mempengaruhi Import Ledger Nilai dan sistem update otomatis.

