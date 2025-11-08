# Setup Git untuk GitHub Sync

## ✅ Git Sudah Terinstall

Jika Git sudah terinstall, ikuti langkah-langkah berikut:

## 1. Test Git Installation

Buka halaman test:
```
http://localhost/UJAN/test_git.php
```

Atau dari Admin About page, klik tombol "Test Git Installation"

## 2. Konfigurasi Git (Jika Belum)

Buka Command Prompt atau Terminal, lalu jalankan:

```bash
# Set username
git config --global user.name "Your Name"

# Set email
git config --global user.email "your.email@example.com"

# Verify
git config --global user.name
git config --global user.email
```

## 3. Inisialisasi Repository

### Via Web Interface (Recommended)
1. Buka `admin/about.php`
2. Di bagian "Git Status", klik "Initialize Repository"
3. Repository akan otomatis di-setup

### Via Command Line
```bash
cd C:\xampp\htdocs\UJAN

# Initialize repository
git init

# Add remote
git remote add origin https://github.com/adiprayitno160-svg/ujian.git

# Verify
git remote -v
```

## 4. Setup GitHub Authentication

Untuk push ke GitHub, Anda perlu autentikasi. Pilih salah satu:

### Option A: Personal Access Token (Recommended)

1. Buka GitHub → Settings → Developer settings
2. Personal access tokens → Tokens (classic)
3. Generate new token (classic)
4. Beri nama: "UJAN System"
5. Pilih scope: `repo` (full control)
6. Generate token
7. **Simpan token dengan aman!** (hanya muncul sekali)

Saat push via web interface, sistem akan meminta credentials:
- Username: `adiprayitno160-svg`
- Password: `your_personal_access_token`

### Option B: SSH Key

```bash
# Generate SSH key
ssh-keygen -t ed25519 -C "your_email@example.com"

# Copy public key
cat ~/.ssh/id_ed25519.pub

# Add to GitHub: Settings → SSH and GPG keys → New SSH key

# Update remote to use SSH
git remote set-url origin git@github.com:adiprayitno160-svg/ujian.git
```

## 5. Test Connection

Setelah setup, test koneksi:

```bash
# Test connection
git ls-remote origin

# Atau via web: test_git.php
```

## 6. First Commit & Push

Setelah semua setup, lakukan commit pertama:

```bash
cd C:\xampp\htdocs\UJAN

# Add all files
git add .

# Commit
git commit -m "Initial commit"

# Push (akan meminta credentials)
git push -u origin main
```

Atau via web interface:
1. Buka `admin/about.php`
2. Masukkan commit message
3. Klik "Push ke GitHub"

## Troubleshooting

### Error: "fatal: not a git repository"
- Repository belum diinisialisasi
- Jalankan: `git init`

### Error: "remote origin already exists"
- Remote sudah ada
- Update dengan: `git remote set-url origin https://github.com/adiprayitno160-svg/ujian.git`

### Error: "authentication failed"
- Periksa credentials
- Gunakan Personal Access Token, bukan password
- Atau setup SSH key

### Error: "permission denied"
- Repository mungkin private
- Pastikan token/SSH key memiliki akses
- Atau buat repository public

## Quick Start Checklist

- [ ] Git terinstall (`git --version`)
- [ ] Git configured (username & email)
- [ ] Repository initialized (`git init`)
- [ ] Remote added (`git remote add origin`)
- [ ] GitHub authentication setup (Token atau SSH)
- [ ] Test connection berhasil
- [ ] First commit & push berhasil

## Next Steps

Setelah setup selesai:
1. Gunakan fitur "Pull Update" untuk update dari GitHub
2. Gunakan fitur "Push ke GitHub" untuk upload perubahan
3. Gunakan fitur "Backup Database" untuk backup database
4. Gunakan fitur "Upload DB ke GitHub" untuk backup & push database

Semua fitur tersedia di: `admin/about.php`

