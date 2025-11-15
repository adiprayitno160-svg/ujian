# Sistem Verifikasi Dokumen Siswa Kelas IX

## Deskripsi
Sistem verifikasi dokumen untuk siswa kelas IX dengan fitur OCR (Optical Character Recognition) menggunakan Google Gemini Vision API. Sistem ini memverifikasi kesesuaian nama siswa, nama ayah, dan nama ibu di dokumen Ijazah, Kartu Keluarga (KK), dan Akte Kelahiran.

## Fitur Utama

### 1. OCR Scan Otomatis
- Scan dokumen secara otomatis menggunakan Gemini Vision API
- Ekstraksi data: nama anak, nama ayah, nama ibu
- Support format: PDF, JPG, PNG

### 2. Validasi Strict (Exact Match)
- Validasi nama harus sama persis di semua dokumen
- Tidak ada toleransi untuk typo atau alias
- Validasi otomatis: nama anak (Ijazah = KK = Akte), nama ayah (KK = Akte), nama ibu (KK = Akte)

### 3. Upload Ulang
- Siswa dapat upload ulang dokumen jika tidak valid
- Maksimal 1x upload ulang
- Setelah upload ulang, jika masih tidak valid → masuk data residu

### 4. Data Residu
- Data siswa yang setelah upload ulang masih tidak valid
- Perlu penanganan manual oleh admin

### 5. Riwayat Perubahan
- Audit trail lengkap untuk semua perubahan
- Mencatat: upload, upload ulang, verifikasi, dll

### 6. Notifikasi
- Notifikasi dalam sistem untuk siswa dan admin
- Notifikasi saat status verifikasi berubah

### 7. Export Laporan
- Export laporan ke Excel (CSV)
- Data lengkap dengan detail ketidaksesuaian

## Instalasi

### 1. Import Database
```sql
-- Import migration file
SOURCE database/migration_verifikasi_dokumen.sql;
```

### 2. Konfigurasi Gemini API
1. Login sebagai admin
2. Buka menu "Verifikasi Dokumen" > "Pengaturan"
3. Aktifkan Gemini OCR
4. Masukkan Gemini API Key
5. Pilih model (gemini-1.5-flash atau gemini-1.5-pro)
6. Set deadline (opsional)
7. Simpan pengaturan

### 3. Setup Folder Upload
Pastikan folder `assets/uploads/verifikasi` dapat ditulis:
```bash
chmod 755 assets/uploads/verifikasi
```

## Cara Menggunakan

### Untuk Admin

#### 1. Pengaturan
- Buka: Admin > Verifikasi Dokumen > Pengaturan
- Aktifkan Gemini OCR
- Masukkan API Key
- Set deadline (opsional)

#### 2. Verifikasi Dokumen
- Buka: Admin > Verifikasi Dokumen
- Lihat daftar siswa kelas IX
- Klik "Detail" untuk verifikasi
- Set status: Valid / Tidak Valid / Residu
- Tambah catatan jika perlu

#### 3. Data Residu
- Buka: Admin > Verifikasi Dokumen > Residu
- Lihat siswa dengan data residu
- Hubungi siswa untuk penanganan lebih lanjut

#### 4. Export Laporan
- Buka: Admin > Verifikasi Dokumen > Export
- Klik "Export ke Excel (CSV)"
- File akan didownload dengan data lengkap

### Untuk Siswa (Kelas IX)

#### 1. Upload Dokumen
- Buka: Siswa > Verifikasi Dokumen
- Upload 3 dokumen: Ijazah, KK, Akte
- Sistem akan otomatis scan dokumen
- Hasil scan ditampilkan (read-only)

#### 2. Validasi Otomatis
- Sistem otomatis validasi kesesuaian nama
- Jika sesuai: status "Menunggu Verifikasi Admin"
- Jika tidak sesuai: status "Tidak Valid", bisa upload ulang

#### 3. Upload Ulang
- Jika tidak valid, siswa bisa upload ulang (maksimal 1x)
- Upload ulang dokumen yang benar
- Setelah upload ulang, jika masih tidak valid → masuk residu

## Struktur Database

### Tabel: `verifikasi_settings`
Pengaturan sistem verifikasi dokumen

### Tabel: `siswa_dokumen_verifikasi`
Data dokumen yang diupload siswa

### Tabel: `verifikasi_data_siswa`
Ringkasan data verifikasi per siswa

### Tabel: `verifikasi_data_history`
Riwayat perubahan (audit trail)

### Tabel: `notifikasi_verifikasi`
Notifikasi untuk siswa dan admin

## Cascade Delete

Ketika siswa dihapus dari sistem:
- Data verifikasi akan ikut terhapus (ON DELETE CASCADE)
- File dokumen tetap ada di server (perlu cleanup manual jika diperlukan)
- History tetap tersimpan untuk audit

## Notifikasi

Sistem notifikasi dalam aplikasi:
- Upload berhasil
- Verifikasi valid
- Verifikasi tidak valid
- Upload ulang diperlukan
- Deadline mendekati
- Deadline terlewat

## Validasi Strict

Aturan validasi:
1. Nama anak harus sama persis di Ijazah, KK, dan Akte
2. Nama ayah harus sama persis di KK dan Akte
3. Nama ibu harus sama persis di KK dan Akte
4. Tidak ada toleransi untuk typo atau alias
5. Case-sensitive setelah uppercase (BUDI = budi = Budi)

## Troubleshooting

### OCR Gagal
- Pastikan Gemini API Key valid
- Pastikan Gemini OCR diaktifkan
- Pastikan dokumen jelas dan tidak blur
- Cek log error di server

### Validasi Tidak Valid
- Pastikan nama di semua dokumen sama persis
- Tidak ada typo atau alias
- Nama lengkap harus sama

### Menu Tidak Muncul
- Pastikan siswa kelas IX
- Pastikan menu aktif (cek di pengaturan admin)
- Refresh halaman

## Catatan Penting

1. **Data Residu**: Siswa yang setelah upload ulang masih tidak valid akan masuk ke data residu. Perlu penanganan manual.

2. **Deadline**: Admin dapat set deadline upload dokumen. Jika deadline terlewat, siswa tidak bisa upload.

3. **Menu Aktif**: Admin dapat hide/show menu verifikasi untuk siswa tertentu.

4. **Export Laporan**: Laporan export berisi data lengkap dengan detail ketidaksesuaian untuk setiap siswa.

5. **Cascade Delete**: Data verifikasi akan terhapus otomatis saat siswa dihapus. File dokumen perlu cleanup manual.

## Support

Jika ada masalah atau pertanyaan, silakan hubungi admin sistem.







