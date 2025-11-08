# Quick Start - PowerShell

## Setup Git & Push ke GitHub via PowerShell

Karena Anda sudah install GitHub CLI dan bisa menggunakan PowerShell, berikut cara cepatnya:

### 1. Setup Git Configuration

Buka PowerShell, lalu jalankan:

```powershell
cd C:\xampp\htdocs\UJAN
.\setup_git.ps1
```

Atau jika ada error execution policy:

```powershell
powershell -ExecutionPolicy Bypass -File setup_git.ps1
```

Isi:
- **Git Username**: Nama Anda (contoh: "Adi Prayitno")
- **Git Email**: Email GitHub Anda

### 2. Push ke GitHub

Setelah config selesai, jalankan:

```powershell
.\push_to_github.ps1
```

Atau:

```powershell
powershell -ExecutionPolicy Bypass -File push_to_github.ps1
```

Script akan:
1. ✅ Check Git installation
2. ✅ Check Git config
3. ✅ Initialize repository (jika belum)
4. ✅ Setup remote
5. ✅ Add & commit files
6. ✅ Push ke GitHub

**Saat diminta credentials:**
- Username: `adiprayitno160-svg`
- Password: `your_personal_access_token`

### 3. Setup Personal Access Token (Jika Belum)

1. Buka: https://github.com/settings/tokens
2. Klik "Generate new token (classic)"
3. Beri nama: "UJAN System"
4. Pilih scope: `repo` (full control)
5. Generate token
6. **Copy token** (hanya muncul sekali!)

## Manual Commands (Alternatif)

Jika script tidak bekerja, gunakan command manual:

```powershell
# Navigate to project
cd C:\xampp\htdocs\UJAN

# Setup Git config
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Initialize (jika belum)
git init
git remote add origin https://github.com/adiprayitno160-svg/ujian.git

# Add files
git add .

# Commit
git commit -m "Initial commit - Sistem UJAN"

# Push
git push -u origin main
```

## Troubleshooting

### Error: "execution of scripts is disabled"
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

### Error: "authentication failed"
- Pastikan menggunakan Personal Access Token, bukan password
- Token harus memiliki permission `repo`

### Error: "repository not found"
- Pastikan repository sudah dibuat di GitHub
- Atau repository adalah private dan token memiliki akses

## File Scripts

1. **setup_git.ps1** - Setup Git username & email
2. **push_to_github.ps1** - Push ke GitHub dengan auto-setup

## Tips

- Simpan Personal Access Token dengan aman
- Jangan commit file sensitif (sudah ada di .gitignore)
- Selalu check status sebelum push: `git status`

## Next Steps

Setelah push pertama berhasil:
- ✅ Repository tersinkron dengan GitHub
- ✅ Bisa menggunakan fitur Pull/Push di Admin About
- ✅ Bisa backup dan push database ke GitHub

Selamat! 🎉

