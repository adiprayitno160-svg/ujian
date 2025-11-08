# Sistem Routing - Clean URLs

Sistem ini menggunakan clean URLs tanpa ekstensi `.php`.

## Format URL

### Sebelum (dengan .php):
- `http://localhost/UJAN/admin/login.php`
- `http://localhost/UJAN/siswa/ujian/list.php`
- `http://localhost/UJAN/admin/manage_users.php`

### Sesudah (clean URLs):
- `http://localhost/UJAN/admin-login`
- `http://localhost/UJAN/siswa-ujian-list`
- `http://localhost/UJAN/admin-manage-users`

## Cara Kerja

1. **Router** (`router.php`) menangani semua request
2. **.htaccess** mengarahkan semua request ke `router.php`
3. **Fungsi `base_url()`** otomatis mengkonversi path dengan `.php` atau `/` menjadi clean URL

## Contoh Penggunaan

### Di PHP Code:
```php
// Format lama (masih bekerja, otomatis dikonversi)
base_url('admin/login.php')
base_url('siswa/ujian/list.php')

// Format baru (langsung clean URL)
base_url('admin-login')
base_url('siswa-ujian-list')
```

### Di HTML:
```html
<!-- Format lama (otomatis dikonversi) -->
<a href="<?php echo base_url('admin/manage_users.php'); ?>">Users</a>

<!-- Format baru -->
<a href="<?php echo base_url('admin-manage-users'); ?>">Users</a>
```

## Route yang Tersedia

### Login
- `admin-login` atau `guru-login` → Login Admin/Guru
- `siswa-login` → Login Siswa
- `operator-login` → Login Operator

### Admin
- `admin` → Dashboard Admin
- `admin-manage-users` → Kelola Users
- `admin-manage-kelas` → Kelola Kelas
- `admin-manage-mapel` → Kelola Mata Pelajaran
- `admin-sekolah-settings` → Pengaturan Sekolah
- `admin-migrasi-kelas` → Migrasi Kelas

### Guru
- `guru` → Dashboard Guru
- `guru-ujian-list` → Daftar Ujian
- `guru-ujian-create` → Buat Ujian
- `guru-sesi-list` → Daftar Sesi
- dll.

### Siswa
- `siswa` → Dashboard Siswa
- `siswa-ujian-list` → Daftar Ujian
- `siswa-pr-list` → Daftar PR
- dll.

### Operator
- `operator` → Dashboard Operator
- `operator-sesi-list` → Daftar Sesi
- dll.

## Catatan

- Semua link yang menggunakan `base_url()` dengan format lama (`.php` atau `/`) akan **otomatis dikonversi** ke clean URL
- Query string tetap didukung: `admin-manage-users?id=1`
- File assets (CSS, JS, images) tidak terpengaruh
- API endpoints tetap menggunakan format asli

