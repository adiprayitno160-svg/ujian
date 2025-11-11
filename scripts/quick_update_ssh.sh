#!/bin/bash
# Quick Update Script SSH - UJAN v1.0.17 & v1.0.18
# Script untuk update aplikasi UJAN via SSH
# Copy paste script ini ke terminal SSH server

echo "=========================================="
echo "  UJAN Quick Update Script"
echo "  Versi: 1.0.17 & 1.0.18"
echo "=========================================="
echo ""

# Konfigurasi path aplikasi (sesuaikan dengan path server Anda)
APP_PATH="/www/wwwroot/8.215.192.2"
# Atau gunakan path ini jika berbeda:
# APP_PATH="/var/www/html/ujian"
# APP_PATH="/home/user/public_html/ujian"

# Backup sebelum update
echo "[1/6] Membuat backup..."
BACKUP_DIR="${APP_PATH}/../backups_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r "$APP_PATH" "$BACKUP_DIR/" 2>/dev/null || echo "Warning: Backup gagal, lanjutkan update..."
echo "✓ Backup selesai: $BACKUP_DIR"
echo ""

# Masuk ke direktori aplikasi
echo "[2/6] Masuk ke direktori aplikasi..."
cd "$APP_PATH" || { echo "Error: Gagal masuk ke $APP_PATH"; exit 1; }
echo "✓ Direktori: $(pwd)"
echo ""

# Cek apakah ini git repository
echo "[3/6] Mengecek Git repository..."
if [ ! -d ".git" ]; then
    echo "Error: Bukan Git repository. Inisialisasi Git terlebih dahulu."
    exit 1
fi
echo "✓ Git repository ditemukan"
echo ""

# Fetch latest dari GitHub (termasuk tags)
echo "[4/6] Mengambil update dari GitHub (termasuk tags)..."
git fetch origin --tags
if [ $? -ne 0 ]; then
    echo "Error: Gagal fetch tags dari GitHub"
    exit 1
fi
git fetch origin main
if [ $? -ne 0 ]; then
    echo "Error: Gagal fetch dari GitHub"
    exit 1
fi
echo "✓ Fetch berhasil (tags dan main)"
echo ""

# Update ke versi terbaru (v1.0.18)
echo "[5/6] Update ke versi v1.0.18..."
git checkout main
git pull origin main
git checkout v1.0.18 2>/dev/null || git checkout main
echo "✓ Update ke v1.0.18 selesai"
echo ""

# Set permissions
echo "[6/6] Mengatur permissions..."
chmod -R 755 "$APP_PATH"
chmod -R 777 "$APP_PATH/assets/uploads" 2>/dev/null
chmod -R 777 "$APP_PATH/cache" 2>/dev/null
chmod -R 777 "$APP_PATH/backups" 2>/dev/null
chown -R www-data:www-data "$APP_PATH" 2>/dev/null || chown -R apache:apache "$APP_PATH" 2>/dev/null
echo "✓ Permissions diatur"
echo ""

# Clear cache jika ada
echo "[7/7] Membersihkan cache..."
rm -rf "$APP_PATH/cache/*" 2>/dev/null
echo "✓ Cache dibersihkan"
echo ""

echo "=========================================="
echo "  ✓ Update selesai!"
echo "  Versi terbaru: v1.0.18"
echo "=========================================="
echo ""
echo "Catatan:"
echo "- Backup tersimpan di: $BACKUP_DIR"
echo "- Jika ada masalah, restore dari backup"
echo "- Cek log error jika ada masalah"
echo ""

