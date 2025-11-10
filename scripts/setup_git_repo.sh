#!/bin/bash
# Script untuk setup Git repository dan remote origin
# Usage: bash setup_git_repo.sh

set -e

REPO_URL="https://github.com/adiprayitno160-svg/ujian.git"
BRANCH="master"

echo "=========================================="
echo "  Setup Git Repository"
echo "=========================================="
echo ""

# Check if Git is installed
if ! command -v git &> /dev/null; then
    echo "✗ Git tidak terinstall!"
    echo "Install Git terlebih dahulu:"
    echo "  Ubuntu/Debian: sudo apt-get install git"
    echo "  CentOS/RHEL: sudo yum install git"
    exit 1
fi

echo "✓ Git terinstall"
echo ""

# Initialize Git if not already a repo
if [ ! -d ".git" ]; then
    echo "Inisialisasi Git repository..."
    git init
    echo "✓ Git repository diinisialisasi"
else
    echo "✓ Git repository sudah ada"
fi
echo ""

# Remove existing origin if any
if git remote | grep -q origin; then
    echo "Menghapus remote origin yang lama..."
    git remote remove origin
    echo "✓ Remote origin lama dihapus"
fi

# Add remote origin
echo "Menambahkan remote origin..."
git remote add origin $REPO_URL
echo "✓ Remote origin ditambahkan: $REPO_URL"
echo ""

# Verify remote
echo "Memverifikasi remote..."
REMOTE_URL=$(git remote get-url origin)
echo "  Remote URL: $REMOTE_URL"
echo ""

# Test connection
echo "Menguji koneksi ke GitHub..."
if git ls-remote --heads origin master &> /dev/null; then
    echo "✓ Koneksi ke GitHub berhasil"
else
    echo "✗ Koneksi ke GitHub gagal!"
    echo ""
    echo "Kemungkinan masalah:"
    echo "  1. Koneksi internet terputus"
    echo "  2. Firewall memblokir GitHub"
    echo "  3. Repository tidak ada atau private"
    echo ""
    echo "Coba manual:"
    echo "  git ls-remote origin master"
    exit 1
fi
echo ""

# Fetch from remote
echo "Fetching dari GitHub..."
git fetch origin $BRANCH || {
    echo "✗ Gagal fetch dari GitHub"
    echo ""
    echo "Coba manual:"
    echo "  git fetch origin master"
    exit 1
}
echo "✓ Fetch berhasil"
echo ""

# Checkout branch
echo "Checking out branch $BRANCH..."
if git show-ref --verify --quiet refs/heads/$BRANCH; then
    git checkout $BRANCH
else
    git checkout -b $BRANCH origin/$BRANCH
fi
echo "✓ Branch $BRANCH aktif"
echo ""

# Set up tracking
echo "Mengatur upstream tracking..."
git branch --set-upstream-to=origin/$BRANCH $BRANCH || true
echo "✓ Upstream tracking diatur"
echo ""

echo "=========================================="
echo "  Setup Selesai!"
echo "=========================================="
echo ""
echo "Sekarang Anda bisa:"
echo "  git pull origin master"
echo "  git fetch origin master"
echo ""



