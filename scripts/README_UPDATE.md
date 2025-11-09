# Script Update Aplikasi UJAN

Script untuk update aplikasi UJAN via SSH atau command line.

## File Script

1. **update.sh** - Script untuk Linux/Unix/Mac (Bash)
2. **update.ps1** - Script untuk Windows (PowerShell)

## Cara Penggunaan

### Linux/Unix/Mac (Bash)

```bash
# Berikan permission execute
chmod +x scripts/update.sh

# Update ke versi terbaru
./scripts/update.sh

# Update ke versi tertentu
./scripts/update.sh v1.0.3

# Update dari branch tertentu
./scripts/update.sh latest main
```

### Windows (PowerShell)

```powershell
# Update ke versi terbaru
.\scripts\update.ps1

# Update ke versi tertentu
.\scripts\update.ps1 -Version v1.0.3

# Update dari branch tertentu
.\scripts\update.ps1 -Version latest -Branch main
```

## Fitur Script

1. **Backup Database Otomatis**
   - Membuat backup database sebelum update
   - Backup disimpan di folder `backups/`
   - Format: `database_backup_YYYYMMDD_HHMMSS.sql`

2. **Stash Perubahan Lokal**
   - Menyimpan perubahan lokal sebelum update
   - Dapat di-restore setelah update jika diperlukan

3. **Update dari GitHub**
   - Fetch latest changes dari repository
   - Update ke versi tertentu atau latest
   - Support untuk tags dan branches

4. **File Permissions**
   - Mengatur permissions untuk cache dan uploads
   - Memastikan aplikasi dapat berjalan dengan benar

5. **Clear Cache**
   - Membersihkan cache setelah update
   - Memastikan perubahan terbaru ter-load

## Parameter

### update.sh

- `version` (optional): Versi yang ingin diupdate (default: latest)
- `branch` (optional): Branch yang ingin diupdate (default: main)

### update.ps1

- `-Version` (optional): Versi yang ingin diupdate (default: latest)
- `-Branch` (optional): Branch yang ingin diupdate (default: main)

## Contoh Penggunaan

### Update ke Versi Terbaru

```bash
# Linux/Unix/Mac
./scripts/update.sh

# Windows
.\scripts\update.ps1
```

### Update ke Versi Tertentu

```bash
# Linux/Unix/Mac
./scripts/update.sh v1.0.3

# Windows
.\scripts\update.ps1 -Version v1.0.3
```

### Update dari Branch Development

```bash
# Linux/Unix/Mac
./scripts/update.sh latest develop

# Windows
.\scripts\update.ps1 -Version latest -Branch develop
```

## Troubleshooting

### Error: Git tidak ditemukan

**Solusi**: Install Git terlebih dahulu
- Linux: `sudo apt-get install git` atau `sudo yum install git`
- Mac: `brew install git`
- Windows: Download dari https://git-scm.com/

### Error: Gagal fetch dari origin

**Solusi**: 
1. Cek koneksi internet
2. Cek remote URL: `git remote -v`
3. Update remote URL jika perlu: `git remote set-url origin <new-url>`

### Error: Permission denied

**Solusi**: 
```bash
# Linux/Unix/Mac
chmod +x scripts/update.sh
sudo chown -R www-data:www-data .

# Windows
# Run PowerShell as Administrator
```

### Error: Database backup gagal

**Solusi**:
1. Pastikan mysqldump terinstall
2. Cek konfigurasi database di `config/database.php`
3. Pastikan user database memiliki permission untuk backup

## Catatan Penting

1. **Backup**: Script akan membuat backup database otomatis, tapi pastikan backup juga dilakukan secara manual sebelum update penting
2. **Permissions**: Pastikan script memiliki permission untuk menulis ke direktori aplikasi
3. **Migrations**: Jika ada database migrations, jalankan secara manual setelah update
4. **Testing**: Setelah update, test aplikasi untuk memastikan semua berfungsi dengan baik

## Restore dari Backup

Jika terjadi masalah setelah update, Anda dapat restore dari backup:

```bash
# Restore database
mysql -u username -p database_name < backups/database_backup_YYYYMMDD_HHMMSS.sql

# Restore perubahan lokal (jika di-stash)
git stash list
git stash apply stash@{0}
```

## Support

Jika mengalami masalah, cek:
1. Log error aplikasi
2. Log Git: `git log --oneline -10`
3. Status Git: `git status`
4. Remote repository: `git remote -v`

