#!/bin/bash
# =============================================================================
# Script Update UJAN - Copy Paste ke SSH Terminal
# Sistem Ujian dan Pekerjaan Rumah (UJAN)
# =============================================================================
# 
# CARA PENGGUNAAN:
# 1. Copy seluruh script ini
# 2. Paste ke SSH terminal
# 3. Tekan Enter untuk menjalankan
#
# Atau simpan sebagai file dan jalankan:
#   curl -sSL https://raw.githubusercontent.com/adiprayitno160-svg/ujian/master/scripts/update_ssh.sh | bash
#
# =============================================================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Config
REPO_URL="https://github.com/adiprayitno160-svg/ujian.git"
BACKUP_DIR="../backups"
BRANCH="master"

echo -e "${CYAN}=============================================================================${NC}"
echo -e "${CYAN}  Script Update Aplikasi UJAN${NC}"
echo -e "${CYAN}=============================================================================${NC}"
echo ""

# Check Git
if ! command -v git &> /dev/null; then
    echo -e "${RED}[ERROR] Git tidak ditemukan. Install Git terlebih dahulu.${NC}"
    exit 1
fi

# Get current directory
CURRENT_DIR=$(pwd)
echo -e "${BLUE}[INFO]${NC} Current directory: $CURRENT_DIR"

# Check if Git repo
if [ ! -d ".git" ]; then
    echo -e "${YELLOW}[WARNING]${NC} Bukan Git repository."
    read -p "Inisialisasi Git repository? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git init
        git remote add origin $REPO_URL 2>/dev/null || echo -e "${YELLOW}[WARNING]${NC} Remote sudah ada"
    else
        exit 1
    fi
fi

# Check remote
if ! git remote | grep -q origin; then
    echo -e "${BLUE}[INFO]${NC} Menambahkan remote origin..."
    git remote add origin $REPO_URL
fi

# Backup database
echo -e "${BLUE}[INFO]${NC} Membuat backup database..."
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

if command -v mysqldump &> /dev/null && [ -f "config/database.php" ]; then
    DB_NAME=$(grep -oP "DB_NAME.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
    DB_USER=$(grep -oP "DB_USER.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
    DB_PASS=$(grep -oP "DB_PASS.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
    DB_HOST=$(grep -oP "DB_HOST.*?'\K[^']+" config/database.php 2>/dev/null || echo "localhost")
    
    if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
        BACKUP_FILE="$BACKUP_DIR/database_backup_$BACKUP_DATE.sql"
        if [ -z "$DB_PASS" ]; then
            mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null && \
            echo -e "${GREEN}[SUCCESS]${NC} Backup database: $BACKUP_FILE" || \
            echo -e "${YELLOW}[WARNING]${NC} Gagal backup database"
        else
            mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null && \
            echo -e "${GREEN}[SUCCESS]${NC} Backup database: $BACKUP_FILE" || \
            echo -e "${YELLOW}[WARNING]${NC} Gagal backup database"
        fi
    fi
else
    echo -e "${YELLOW}[WARNING]${NC} Skip backup database"
fi

# Stash changes
echo -e "${BLUE}[INFO]${NC} Menyimpan perubahan lokal..."
git stash push -m "Auto stash before update $BACKUP_DATE" 2>/dev/null || echo -e "${YELLOW}[WARNING]${NC} Tidak ada perubahan"

# Fetch latest
echo -e "${BLUE}[INFO]${NC} Fetching dari origin/$BRANCH..."
git fetch origin $BRANCH || {
    echo -e "${RED}[ERROR]${NC} Gagal fetch dari GitHub"
    exit 1
}

# Get current version
CURRENT_VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "unknown")
echo -e "${BLUE}[INFO]${NC} Current version: $CURRENT_VERSION"

# Update
echo -e "${BLUE}[INFO]${NC} Updating ke latest dari branch $BRANCH..."
if git show-ref --verify --quiet refs/heads/$BRANCH; then
    git checkout $BRANCH
else
    git checkout -b $BRANCH origin/$BRANCH
fi

git reset --hard origin/$BRANCH || {
    echo -e "${RED}[ERROR]${NC} Gagal update"
    exit 1
}

# Get new version
NEW_VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "unknown")
NEW_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")

echo ""
echo -e "${GREEN}[SUCCESS]${NC} Update berhasil!"
echo -e "${BLUE}[INFO]${NC} Version: $NEW_VERSION"
echo -e "${BLUE}[INFO]${NC} Commit: $NEW_COMMIT"

# Set permissions
echo -e "${BLUE}[INFO]${NC} Mengatur permissions..."
chmod -R 755 . 2>/dev/null || true
chmod -R 777 cache 2>/dev/null || true
chmod -R 777 assets/uploads 2>/dev/null || true

# Clear cache
echo -e "${BLUE}[INFO]${NC} Membersihkan cache..."
rm -rf cache/*.json 2>/dev/null || true

echo ""
echo -e "${CYAN}=============================================================================${NC}"
echo -e "${GREEN}  Update Selesai!${NC}"
echo -e "${CYAN}=============================================================================${NC}"
echo -e "${YELLOW}Jangan lupa:${NC}"
echo "  1. Cek config/database.php"
echo "  2. Set permissions: chmod -R 777 cache assets/uploads"
echo "  3. Test aplikasi"
echo ""

