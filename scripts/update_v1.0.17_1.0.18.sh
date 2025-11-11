#!/bin/bash
# Update Script UJAN v1.0.17 & v1.0.18
# Script lengkap untuk update kedua versi

set -e  # Exit on error

# ============================================
# KONFIGURASI - SESUAIKAN DENGAN SERVER ANDA
# ============================================
APP_PATH="/www/wwwroot/8.215.192.2"
# Jika path berbeda, ubah di atas:
# APP_PATH="/var/www/html/ujian"
# APP_PATH="/home/user/public_html/ujian"

# Warna output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}=========================================="
echo "  UJAN Update Script"
echo "  Versi: 1.0.17 & 1.0.18"
echo "==========================================${NC}"
echo ""

# Cek apakah path ada
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}Error: Path $APP_PATH tidak ditemukan!${NC}"
    echo "Silakan edit APP_PATH di script ini."
    exit 1
fi

# 1. Backup
echo -e "${YELLOW}[1/7] Membuat backup...${NC}"
BACKUP_DIR="${APP_PATH}/../backups_ujian_$(date +%Y%m%d_%H%M%S)"
if mkdir -p "$BACKUP_DIR" && cp -r "$APP_PATH"/* "$BACKUP_DIR/" 2>/dev/null; then
    echo -e "${GREEN}✓ Backup berhasil: $BACKUP_DIR${NC}"
else
    echo -e "${YELLOW}⚠ Warning: Backup gagal, lanjutkan update...${NC}"
fi
echo ""

# 2. Masuk ke direktori
echo -e "${YELLOW}[2/7] Masuk ke direktori aplikasi...${NC}"
cd "$APP_PATH" || { echo -e "${RED}Error: Gagal masuk ke $APP_PATH${NC}"; exit 1; }
echo -e "${GREEN}✓ Direktori: $(pwd)${NC}"
echo ""

# 3. Cek Git
echo -e "${YELLOW}[3/7] Mengecek Git repository...${NC}"
if [ ! -d ".git" ]; then
    echo -e "${RED}Error: Bukan Git repository!${NC}"
    echo "Inisialisasi Git terlebih dahulu atau clone dari GitHub."
    exit 1
fi
echo -e "${GREEN}✓ Git repository ditemukan${NC}"
echo ""

# 4. Fetch dari GitHub
echo -e "${YELLOW}[4/7] Mengambil update dari GitHub...${NC}"
if ! git fetch origin main; then
    echo -e "${RED}Error: Gagal fetch dari GitHub${NC}"
    echo "Pastikan koneksi internet dan akses GitHub tersedia."
    exit 1
fi
echo -e "${GREEN}✓ Fetch berhasil${NC}"
echo ""

# 5. Update ke v1.0.18 (versi terbaru)
echo -e "${YELLOW}[5/7] Update ke versi v1.0.18...${NC}"
git checkout main 2>/dev/null || true
git pull origin main

# Coba checkout ke tag v1.0.18, jika tidak ada gunakan main
if git rev-parse --verify v1.0.18 >/dev/null 2>&1; then
    git checkout v1.0.18
    echo -e "${GREEN}✓ Update ke v1.0.18 selesai${NC}"
else
    echo -e "${YELLOW}⚠ Tag v1.0.18 tidak ditemukan, menggunakan main${NC}"
    git checkout main
fi
echo ""

# 6. Set permissions
echo -e "${YELLOW}[6/7] Mengatur permissions...${NC}"
chmod -R 755 "$APP_PATH" 2>/dev/null || true
chmod -R 777 "$APP_PATH/assets/uploads" 2>/dev/null || true
chmod -R 777 "$APP_PATH/cache" 2>/dev/null || true
chmod -R 777 "$APP_PATH/backups" 2>/dev/null || true

# Set ownership (coba www-data dulu, jika gagal coba apache)
if chown -R www-data:www-data "$APP_PATH" 2>/dev/null; then
    echo -e "${GREEN}✓ Permissions diatur (www-data)${NC}"
elif chown -R apache:apache "$APP_PATH" 2>/dev/null; then
    echo -e "${GREEN}✓ Permissions diatur (apache)${NC}"
else
    echo -e "${YELLOW}⚠ Warning: Gagal set ownership (mungkin perlu sudo)${NC}"
fi
echo ""

# 7. Clear cache
echo -e "${YELLOW}[7/7] Membersihkan cache...${NC}"
rm -rf "$APP_PATH/cache"/* 2>/dev/null || true
echo -e "${GREEN}✓ Cache dibersihkan${NC}"
echo ""

# Selesai
echo -e "${GREEN}=========================================="
echo "  ✓ Update selesai!"
echo "  Versi terbaru: v1.0.18"
echo "==========================================${NC}"
echo ""
echo "Informasi:"
echo "- Backup: $BACKUP_DIR"
echo "- Path: $APP_PATH"
echo "- Versi: $(git describe --tags 2>/dev/null || git rev-parse --short HEAD)"
echo ""
echo "Catatan:"
echo "- Jika ada masalah, restore dari backup"
echo "- Cek log error di server jika diperlukan"
echo ""

