# Release Notes v1.0.25

## Tanggal Release
**2025-01-XX**

## Perbaikan Bug (Bug Fixes)

### 1. Fixed: Import Ledger Nilai - Mapping Kode Mapel ke Database
- **Masalah**: Error "Mata pelajaran dengan kode tidak ditemukan" karena kode Excel berbeda dengan kode di database
- **Solusi**: 
  - Memperbaiki mapping alternatif untuk mencocokkan kode Excel dengan kode database:
    - `PA&PBP` → `PAI` (Pendidikan Agama Islam)
    - `P.PANQ` → `PPKN` (Pendidikan Pancasila)
    - `B.INDO` → `BIN` (Bahasa Indonesia)
    - `B.INGG` → `ING` (Bahasa Inggris)
    - `PRAK` → `SENI` (Seni Rupa/Prakarya)
    - `INFOR` → `INF` (Informatika)
    - `B.JAWA` → `BJW` (Bahasa Jawa)
  - Sistem sekarang bisa mencocokkan kode Excel dengan kode database yang sebenarnya
  - Mengurangi error "tidak ditemukan" secara signifikan

## Perubahan Teknis

### Database
- Tidak ada perubahan struktur database

### Dependencies
- Tidak ada perubahan dependencies

## Catatan Upgrade

### Untuk Upgrade dari v1.0.24 ke v1.0.25:

1. **Backup Database** (Sangat Penting!)
   ```bash
   # Backup database sebelum upgrade
   mysqldump -u root -p ujian > backup_ujian_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **Update File**
   - Upload semua file baru ke server
   - Pastikan file berikut ter-update:
     - `admin/ledger_nilai.php`
     - `config/config.php`

3. **Clear Cache** (jika ada)
   - Hapus cache browser
   - Clear session jika diperlukan

4. **Verifikasi**
   - Test Import Ledger Nilai dengan kode mapel yang benar
   - Pastikan tidak ada error "tidak ditemukan"
   - Pastikan tidak ada error di error log

## File yang Diubah

- `admin/ledger_nilai.php` - Perbaikan mapping kode mapel Excel ke database
- `config/config.php` - Update versi ke 1.0.25

## Known Issues

Tidak ada known issues pada release ini.

## Support

Jika menemukan bug atau masalah, silakan laporkan melalui:
- Issue tracker (jika menggunakan Git)
- Email support
- Dokumentasi: Lihat file `README.md`

---

**Catatan**: Release ini fokus pada perbaikan mapping kode mapel untuk Import Ledger Nilai agar sesuai dengan kode yang ada di database.

