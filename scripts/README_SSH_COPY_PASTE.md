# Script Update UJAN - Copy Paste ke SSH Terminal

Dokumentasi untuk script update yang dapat di-copy-paste langsung ke SSH terminal.

## 📋 Daftar Script

### 1. `UPDATE_SSH_SIMPLE.txt` - Versi Paling Sederhana
**Paling mudah digunakan, direkomendasikan untuk pemula.**

**Cara menggunakan:**
1. Masuk ke direktori aplikasi:
   ```bash
   cd /var/www/html/ujian
   # atau
   cd /path/to/ujian
   ```

2. Copy script dari file `UPDATE_SSH_SIMPLE.txt`
3. Paste ke SSH terminal
4. Tekan Enter

**Fitur:**
- ✅ Stash perubahan lokal
- ✅ Fetch dari GitHub
- ✅ Update ke versi terbaru
- ✅ Set permissions
- ✅ Clear cache

---

### 2. `UPDATE_SSH_WITH_BACKUP.txt` - Dengan Backup Database
**Direkomendasikan untuk production server.**

**Cara menggunakan:**
1. Masuk ke direktori aplikasi
2. Copy script dari file `UPDATE_SSH_WITH_BACKUP.txt`
3. Paste ke SSH terminal
4. Tekan Enter

**Fitur:**
- ✅ Backup database otomatis
- ✅ Stash perubahan lokal
- ✅ Fetch dari GitHub
- ✅ Update ke versi terbaru
- ✅ Set permissions
- ✅ Clear cache
- ✅ Progress indicator (1/5, 2/5, dll)

---

### 3. `UPDATE_SSH_COPY_PASTE.txt` - Versi Lengkap dengan Warna
**Versi lengkap dengan output berwarna dan error handling.**

**Cara menggunakan:**
1. Masuk ke direktori aplikasi
2. Copy script dari file `UPDATE_SSH_COPY_PASTE.txt`
3. Paste ke SSH terminal
4. Tekan Enter

**Fitur:**
- ✅ Backup database otomatis
- ✅ Stash perubahan lokal
- ✅ Fetch dari GitHub
- ✅ Update ke versi terbaru
- ✅ Set permissions
- ✅ Clear cache
- ✅ Output berwarna (hijau, merah, kuning, biru)
- ✅ Error handling lengkap
- ✅ Auto-inisialisasi Git jika belum ada

---

### 4. One-Liner - Versi Satu Baris
**Paling cepat, untuk pengguna advanced.**

**Cara menggunakan:**
1. Masuk ke direktori aplikasi
2. Copy baris berikut:
   ```bash
   git stash push -m "Auto stash $(date +%Y%m%d_%H%M%S)" 2>/dev/null || true && git fetch origin master && git checkout master && git reset --hard origin/master && chmod -R 777 cache assets/uploads 2>/dev/null || true && rm -rf cache/*.json 2>/dev/null || true && echo "Update selesai! Version: $(git describe --tags --abbrev=0 2>/dev/null || echo 'unknown')"
   ```
3. Paste ke SSH terminal
4. Tekan Enter

**Fitur:**
- ✅ Semua fitur dasar
- ✅ Satu baris saja
- ✅ Cepat dan efisien

---

## 🚀 Quick Start

### Pilihan 1: Versi Sederhana (Recommended)
```bash
cd /var/www/html/ujian
# Copy script dari UPDATE_SSH_SIMPLE.txt dan paste di sini
```

### Pilihan 2: Dengan Backup Database
```bash
cd /var/www/html/ujian
# Copy script dari UPDATE_SSH_WITH_BACKUP.txt dan paste di sini
```

### Pilihan 3: One-Liner
```bash
cd /var/www/html/ujian
git stash push -m "Auto stash $(date +%Y%m%d_%H%M%S)" 2>/dev/null || true && git fetch origin master && git checkout master && git reset --hard origin/master && chmod -R 777 cache assets/uploads 2>/dev/null || true && rm -rf cache/*.json 2>/dev/null || true && echo "Update selesai!"
```

---

## 📝 Langkah-Langkah Detail

### 1. Persiapkan SSH Access
```bash
# Login ke server via SSH
ssh user@your-server.com
```

### 2. Masuk ke Direktori Aplikasi
```bash
cd /var/www/html/ujian
# atau
cd /path/to/your/ujian/directory
```

### 3. Verifikasi Git Repository
```bash
# Cek apakah ini Git repository
git status

# Cek remote origin
git remote -v

# Jika belum ada remote, tambahkan:
git remote add origin https://github.com/adiprayitno160-svg/ujian.git
```

### 4. Copy-Paste Script
- Buka file script yang diinginkan (misalnya `UPDATE_SSH_SIMPLE.txt`)
- Copy seluruh isi file
- Paste ke SSH terminal
- Tekan Enter

### 5. Verifikasi Update
```bash
# Cek versi terbaru
git describe --tags --abbrev=0

# Cek commit terbaru
git log --oneline -5

# Cek status
git status
```

---

## ⚠️ Catatan Penting

### Sebelum Update:
1. **Backup Database** - Pastikan database sudah di-backup (script dengan backup akan melakukannya otomatis)
2. **Cek Koneksi** - Pastikan koneksi internet stabil
3. **Cek Disk Space** - Pastikan ada ruang disk yang cukup
4. **Cek Permissions** - Pastikan user memiliki permission untuk write di direktori aplikasi

### Setelah Update:
1. **Cek Config** - Verifikasi `config/database.php` masih benar
2. **Set Permissions** - Pastikan permissions untuk cache dan uploads:
   ```bash
   chmod -R 777 cache
   chmod -R 777 assets/uploads
   ```
3. **Clear Cache** - Clear cache browser jika diperlukan
4. **Test Aplikasi** - Test semua fitur untuk memastikan berfungsi dengan baik

### Troubleshooting:

#### Error: "fatal: not a git repository"
```bash
# Inisialisasi Git repository
git init
git remote add origin https://github.com/adiprayitno160-svg/ujian.git
```

#### Error: "fatal: remote origin already exists"
```bash
# Hapus remote lama dan tambahkan yang baru
git remote remove origin
git remote add origin https://github.com/adiprayitno160-svg/ujian.git
```

#### Error: "Permission denied"
```bash
# Cek permissions
ls -la

# Set permissions
chmod -R 755 .
chmod -R 777 cache
chmod -R 777 assets/uploads
```

#### Error: "fatal: refusing to merge unrelated histories"
```bash
# Force merge
git pull origin master --allow-unrelated-histories
```

---

## 🔄 Rollback (Mengembalikan ke Versi Sebelumnya)

Jika ada masalah setelah update, Anda bisa rollback:

```bash
# Lihat commit history
git log --oneline -10

# Rollback ke commit tertentu
git reset --hard <commit-hash>

# Atau rollback ke tag tertentu
git checkout v1.0.2

# Atau gunakan stash jika ada perubahan yang di-stash
git stash list
git stash pop
```

---

## 📞 Support

Jika mengalami masalah:
1. Cek log error di `error_log` atau `/var/log/apache2/error.log`
2. Cek Git status: `git status`
3. Cek Git log: `git log --oneline -10`
4. Hubungi administrator sistem

---

## 📚 Referensi

- [Git Documentation](https://git-scm.com/doc)
- [GitHub CLI Documentation](https://cli.github.com/manual/)
- [Bash Scripting Guide](https://www.gnu.org/software/bash/manual/)

---

## ✅ Checklist Update

- [ ] Backup database sudah dilakukan
- [ ] Koneksi internet stabil
- [ ] Disk space cukup
- [ ] Permissions sudah benar
- [ ] Script sudah di-copy-paste
- [ ] Update berhasil
- [ ] Config database masih benar
- [ ] Permissions cache/uploads sudah di-set
- [ ] Aplikasi sudah di-test
- [ ] Tidak ada error di log

---

**Selamat menggunakan script update UJAN! 🚀**

