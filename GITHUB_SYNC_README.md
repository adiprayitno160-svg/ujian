# GitHub Sync - Panduan Penggunaan

## Fitur GitHub Sync untuk Admin

Halaman `admin/about.php` menyediakan fitur untuk:
1. **Update dari GitHub** - Pull update terbaru dari repository
2. **Upload ke GitHub** - Push perubahan ke repository
3. **Backup Database** - Export database ke file SQL
4. **Upload Database ke GitHub** - Backup dan push database ke GitHub

## Prerequisites

### 1. Git harus terinstall di server
```bash
# Check jika Git sudah terinstall
git --version
```

Jika belum terinstall:
- **Windows (XAMPP)**: Download dari https://git-scm.com/download/win
- **Linux**: `sudo apt-get install git` atau `sudo yum install git`

### 2. Konfigurasi Git Credentials

Sebelum push ke GitHub, Anda perlu mengkonfigurasi Git:

```bash
# Set username dan email
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"
```

### 3. Setup GitHub Authentication

Untuk push ke GitHub, Anda perlu autentikasi. Pilih salah satu:

#### Option A: Personal Access Token (Recommended)
1. Buka GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Generate new token dengan permission: `repo`
3. Simpan token dengan aman
4. Saat push, gunakan token sebagai password:
   ```
   Username: your_username
   Password: your_token
   ```

#### Option B: SSH Key
1. Generate SSH key: `ssh-keygen -t ed25519 -C "your_email@example.com"`
2. Add SSH key ke GitHub: Settings → SSH and GPG keys → New SSH key
3. Update remote URL:
   ```bash
   git remote set-url origin git@github.com:adiprayitno160-svg/ujian.git
   ```

## Cara Menggunakan

### 1. Inisialisasi Repository (Pertama Kali)

Jika repository belum diinisialisasi:
1. Buka `admin/about.php`
2. Di bagian "Git Status", klik tombol "Initialize Repository"
3. Repository akan diinisialisasi dan remote akan ditambahkan

### 2. Update dari GitHub (Pull)

1. Buka `admin/about.php`
2. Di bagian "Update dari GitHub":
   - Pilih branch (main/master)
   - Centang "Buat backup otomatis" (recommended)
   - Klik "Pull Update dari GitHub"
3. Sistem akan:
   - Membuat backup database otomatis
   - Fetch dan pull update dari GitHub
   - Menampilkan hasil

### 3. Upload ke GitHub (Push)

1. Buka `admin/about.php`
2. Di bagian "Upload ke GitHub":
   - Masukkan commit message
   - Pilih branch
   - (Opsional) Centang "Include database backup"
   - Klik "Push ke GitHub"
3. Sistem akan:
   - Add semua perubahan
   - Commit dengan message yang Anda berikan
   - Push ke GitHub

### 4. Backup Database

1. Buka `admin/about.php`
2. Di bagian "Database Management":
   - Klik "Backup Database"
3. File backup akan dibuat di folder `backups/`
4. Download link akan muncul setelah backup selesai

### 5. Upload Database ke GitHub

1. Buka `admin/about.php`
2. Di bagian "Database Management":
   - Klik "Upload DB ke GitHub"
3. Masukkan commit message
4. Sistem akan:
   - Membuat backup database
   - Commit dan push ke GitHub

## Keamanan

### File yang Diabaikan (.gitignore)

File berikut **TIDAK** akan di-commit ke GitHub:
- `config/database.php` - Konfigurasi database
- `config/ai_config.php` - API keys
- `assets/uploads/*` - File upload user
- `backups/*` - File backup
- File sensitif lainnya

### Rekomendasi

1. **Jangan commit file sensitif** - Pastikan `.gitignore` sudah benar
2. **Gunakan Personal Access Token** - Lebih aman daripada password
3. **Backup sebelum pull** - Selalu backup database sebelum update
4. **Review perubahan** - Cek Git Status sebelum push

## Troubleshooting

### Error: "Git tidak tersedia di server"
- Install Git di server
- Pastikan Git ada di PATH system

### Error: "Failed to push: authentication failed"
- Periksa Git credentials
- Gunakan Personal Access Token
- Atau setup SSH key

### Error: "Failed to pull: merge conflict"
- Ada konflik antara local dan remote
- Resolve conflict manual atau backup dan reset

### Error: "Repository belum diinisialisasi"
- Klik tombol "Initialize Repository"
- Atau jalankan manual:
  ```bash
  cd C:\xampp\htdocs\UJAN
  git init
  git remote add origin https://github.com/adiprayitno160-svg/ujian.git
  ```

## Manual Commands (Alternative)

Jika fitur web tidak bekerja, Anda bisa menggunakan command line:

```bash
# Navigate to project directory
cd C:\xampp\htdocs\UJAN

# Check status
git status

# Pull from GitHub
git pull origin main

# Add changes
git add .

# Commit
git commit -m "Your commit message"

# Push to GitHub
git push origin main
```

## Support

Jika ada masalah, periksa:
1. Git log: `git log --oneline`
2. Git status: `git status`
3. Remote URL: `git remote -v`
4. Server logs untuk error detail

