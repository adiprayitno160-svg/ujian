# PowerShell Guide - Git Setup & Push

## Cara Menggunakan PowerShell Script

### Method 1: Jalankan Script Langsung

Buka PowerShell, lalu:

```powershell
cd C:\xampp\htdocs\UJAN
powershell -ExecutionPolicy Bypass -File git_setup.ps1
```

### Method 2: Manual Commands (Lebih Mudah)

Jika script tidak bekerja, gunakan command manual:

```powershell
# 1. Masuk ke folder project
cd C:\xampp\htdocs\UJAN

# 2. Setup Git config (jika belum)
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# 3. Initialize repository (jika belum)
git init

# 4. Setup remote (jika belum)
git remote add origin https://github.com/adiprayitno160-svg/ujian.git
# Atau jika sudah ada:
git remote set-url origin https://github.com/adiprayitno160-svg/ujian.git

# 5. Add semua files
git add .

# 6. Commit
git commit -m "Initial commit - Sistem UJAN"

# 7. Push
git push -u origin main
```

Saat push, masukkan:
- **Username**: `adiprayitno160-svg`
- **Password**: `your_personal_access_token`

## Setup Personal Access Token

1. Buka: https://github.com/settings/tokens
2. Klik "Generate new token (classic)"
3. Beri nama: "UJAN System"
4. Pilih scope: `repo` (full control)
5. Generate token
6. **Copy token** (hanya muncul sekali!)

## Troubleshooting

### Error: "execution of scripts is disabled"

Jalankan ini sekali:
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

Atau gunakan:
```powershell
powershell -ExecutionPolicy Bypass -File git_setup.ps1
```

### Error: "file not found"

Pastikan Anda sudah di folder yang benar:
```powershell
cd C:\xampp\htdocs\UJAN
ls git_setup.ps1
```

### Error: "authentication failed"

- Pastikan menggunakan Personal Access Token, bukan password
- Token harus memiliki permission `repo`
- Username harus: `adiprayitno160-svg`

### Error: "repository not found"

- Pastikan repository sudah dibuat di GitHub
- Atau repository adalah private dan token memiliki akses

## Quick Commands Reference

```powershell
# Check status
git status

# Check config
git config --global user.name
git config --global user.email

# Check remote
git remote -v

# View commits
git log --oneline

# Check branch
git branch
```

## Tips

1. **Simpan token dengan aman** - Token hanya muncul sekali saat dibuat
2. **Check status sebelum push** - `git status`
3. **Gunakan commit message yang jelas**
4. **Jangan commit file sensitif** - Sudah ada di .gitignore

## Next Steps

Setelah push berhasil:
- ✅ Repository tersinkron dengan GitHub
- ✅ Bisa menggunakan fitur di Admin About page
- ✅ Bisa pull/push via web interface

