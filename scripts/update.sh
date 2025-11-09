#!/bin/bash
#
# Script Update Aplikasi UJAN via SSH
# Sistem Ujian dan Pekerjaan Rumah (UJAN)
#
# Usage: ./update.sh [version] [branch]
#   version: Versi yang ingin diupdate (optional, default: latest)
#   branch: Branch yang ingin diupdate (optional, default: master)
#
# Example:
#   ./update.sh              # Update ke versi terbaru dari branch master
#   ./update.sh v1.0.3       # Update ke versi v1.0.3
#   ./update.sh latest master  # Update ke versi terbaru dari branch master

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_URL="https://github.com/adiprayitno160-svg/ujian.git"
REPO_DIR=$(pwd)
BACKUP_DIR="../backups"
BRANCH="${2:-master}"
VERSION="${1:-latest}"

# Functions
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Git is installed
if ! command -v git &> /dev/null; then
    print_error "Git tidak ditemukan. Silakan install Git terlebih dahulu."
    exit 1
fi

# Check if we're in a Git repository
if [ ! -d ".git" ]; then
    print_error "Direktori ini bukan Git repository."
    print_info "Menginisialisasi Git repository..."
    git init
    git remote add origin $REPO_URL 2>/dev/null || print_warning "Remote origin sudah ada"
fi

# Check if remote exists
if ! git remote | grep -q origin; then
    print_info "Menambahkan remote origin..."
    git remote add origin $REPO_URL
fi

# Get current branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
print_info "Current branch: $CURRENT_BRANCH"
print_info "Target branch: $BRANCH"
print_info "Target version: $VERSION"

# Backup database before update
print_info "Membuat backup database..."
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/database_backup_$BACKUP_DATE.sql"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Try to backup database (adjust DB credentials as needed)
if command -v mysqldump &> /dev/null; then
    if [ -f "config/database.php" ]; then
        # Try to extract DB credentials from config file
        DB_NAME=$(grep -oP "DB_NAME.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
        DB_USER=$(grep -oP "DB_USER.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
        DB_PASS=$(grep -oP "DB_PASS.*?'\K[^']+" config/database.php 2>/dev/null || echo "")
        DB_HOST=$(grep -oP "DB_HOST.*?'\K[^']+" config/database.php 2>/dev/null || echo "localhost")
        
        if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ]; then
            print_info "Backing up database: $DB_NAME"
            if [ -z "$DB_PASS" ]; then
                mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || print_warning "Gagal backup database (mungkin perlu password)"
            else
                mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE" 2>/dev/null || print_warning "Gagal backup database"
            fi
            
            if [ -f "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ]; then
                print_success "Database backup berhasil: $BACKUP_FILE"
            else
                print_warning "Backup database gagal atau file kosong"
            fi
        else
            print_warning "Tidak dapat membaca konfigurasi database. Skip backup."
        fi
    else
        print_warning "File config/database.php tidak ditemukan. Skip backup."
    fi
else
    print_warning "mysqldump tidak ditemukan. Skip backup database."
fi

# Stash local changes
print_info "Menyimpan perubahan lokal..."
git stash push -m "Stash before update $BACKUP_DATE" 2>/dev/null || print_warning "Tidak ada perubahan untuk di-stash"

# Fetch latest changes
print_info "Fetching latest changes from origin/$BRANCH..."
git fetch origin "$BRANCH" || {
    print_error "Gagal fetch dari origin/$BRANCH"
    exit 1
}

# Update to specific version or latest
if [ "$VERSION" != "latest" ]; then
    print_info "Checking out version: $VERSION"
    
    # Check if version is a tag
    if git rev-parse "$VERSION" >/dev/null 2>&1; then
        git checkout "$VERSION" || {
            print_error "Gagal checkout ke $VERSION"
            exit 1
        }
        print_success "Berhasil checkout ke $VERSION"
    else
        # Try to fetch tags first
        print_info "Fetching tags..."
        git fetch --tags origin || print_warning "Gagal fetch tags"
        
        if git rev-parse "$VERSION" >/dev/null 2>&1; then
            git checkout "$VERSION" || {
                print_error "Gagal checkout ke $VERSION"
                exit 1
            }
            print_success "Berhasil checkout ke $VERSION"
        else
            print_error "Version $VERSION tidak ditemukan"
            print_info "Menggunakan versi terbaru dari branch $BRANCH"
            git checkout "$BRANCH" || git checkout -b "$BRANCH" "origin/$BRANCH"
            git reset --hard "origin/$BRANCH"
        fi
    fi
else
    # Update to latest from branch
    print_info "Updating to latest from branch $BRANCH..."
    
    # Switch to target branch
    if git show-ref --verify --quiet refs/heads/"$BRANCH"; then
        git checkout "$BRANCH" || {
            print_error "Gagal checkout ke branch $BRANCH"
            exit 1
        }
    else
        print_info "Branch $BRANCH tidak ada secara lokal, membuat dari origin/$BRANCH"
        git checkout -b "$BRANCH" "origin/$BRANCH" || {
            print_error "Gagal membuat branch $BRANCH dari origin/$BRANCH"
            exit 1
        }
    fi
    
    # Reset to remote branch
    git reset --hard "origin/$BRANCH" || {
        print_error "Gagal reset ke origin/$BRANCH"
        exit 1
    }
    
    print_success "Berhasil update ke latest dari branch $BRANCH"
fi

# Get current version after update
CURRENT_VERSION=$(git describe --tags --abbrev=0 2>/dev/null || echo "unknown")
CURRENT_COMMIT=$(git rev-parse --short HEAD 2>/dev/null || echo "unknown")

print_info "Current version: $CURRENT_VERSION"
print_info "Current commit: $CURRENT_COMMIT"

# Set file permissions
print_info "Mengatur file permissions..."
chmod -R 755 . 2>/dev/null || print_warning "Gagal mengatur permissions"
chmod -R 777 cache 2>/dev/null || print_warning "Cache directory tidak ditemukan"
chmod -R 777 assets/uploads 2>/dev/null || print_warning "Uploads directory tidak ditemukan"

# Run database migrations if any
if [ -d "migrations" ] && [ "$(ls -A migrations/*.sql 2>/dev/null)" ]; then
    print_info "Menjalankan database migrations..."
    # Note: Adjust this based on your migration system
    print_warning "Silakan jalankan migrations secara manual jika diperlukan"
fi

# Clear cache
print_info "Membersihkan cache..."
rm -rf cache/*.json 2>/dev/null || true
rm -rf cache/github_releases.json 2>/dev/null || true

print_success "Update selesai!"
print_info "Version: $CURRENT_VERSION"
print_info "Commit: $CURRENT_COMMIT"
if [ -f "$BACKUP_FILE" ]; then
    print_info "Backup: $BACKUP_FILE"
fi

# Show what changed
print_info "Perubahan terakhir:"
git log --oneline -5 2>/dev/null || print_warning "Tidak dapat menampilkan log"

echo ""
print_success "Aplikasi berhasil diupdate!"
print_warning "Jangan lupa untuk:"
print_warning "1. Cek konfigurasi database di config/database.php"
print_warning "2. Jalankan migrations jika ada"
print_warning "3. Set permissions: chmod -R 777 cache assets/uploads"
print_warning "4. Clear cache browser jika diperlukan"
print_warning "5. Test aplikasi untuk memastikan semua berfungsi"
print_info ""
print_info "Untuk melihat perubahan: git log --oneline -10"
print_info "Untuk rollback: git checkout <commit-hash> atau git reset --hard HEAD~1"
