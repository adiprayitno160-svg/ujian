#!/bin/bash
# Script untuk cek setup Git di server
# Usage: bash check_git_setup.sh

echo "=========================================="
echo "  Cek Setup Git Repository"
echo "=========================================="
echo ""

# Get current directory
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"
echo ""

# Check if Git is installed
echo "[1] Checking Git installation..."
if command -v git &> /dev/null; then
    GIT_VERSION=$(git --version)
    echo "  ✓ Git installed: $GIT_VERSION"
else
    echo "  ✗ Git tidak terinstall!"
    echo "    Install dengan: apt-get install git"
    exit 1
fi
echo ""

# Check if we're in a Git repository
echo "[2] Checking if this is a Git repository..."
if [ -d ".git" ]; then
    echo "  ✓ Ini adalah Git repository"
else
    echo "  ✗ Bukan Git repository!"
    echo "    Perlu inisialisasi Git repository"
    exit 1
fi
echo ""

# Check remote origin
echo "[3] Checking remote origin..."
if git remote | grep -q origin; then
    REMOTE_URL=$(git remote get-url origin)
    echo "  ✓ Remote origin: $REMOTE_URL"
    
    # Check if remote is accessible
    echo "  Testing connection to GitHub..."
    if git ls-remote --heads origin main &> /dev/null; then
        echo "  ✓ Koneksi ke GitHub berhasil (branch main)"
    else
        echo "  ✗ Koneksi ke GitHub gagal atau branch main tidak ada!"
        echo "    Cek koneksi internet atau remote URL"
    fi
else
    echo "  ✗ Remote origin tidak ada!"
    echo "    Tambahkan dengan:"
    echo "    git remote add origin https://github.com/adiprayitno160-svg/ujian.git"
fi
echo ""

# Check current branch
echo "[4] Checking current branch..."
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "unknown")
echo "  Current branch: $CURRENT_BRANCH"

# Check if branch is tracking remote
TRACKING=$(git branch -vv | grep "* $CURRENT_BRANCH" | grep -o "\[origin/.*\]" || echo "")
if [ ! -z "$TRACKING" ]; then
    echo "  ✓ Branch tracking: $TRACKING"
else
    echo "  ⚠ Branch tidak tracking remote"
    echo "    Setup dengan: git branch --set-upstream-to=origin/$CURRENT_BRANCH $CURRENT_BRANCH"
fi
echo ""

# Check for uncommitted changes
echo "[5] Checking for uncommitted changes..."
if [ -n "$(git status --porcelain)" ]; then
    echo "  ⚠ Ada perubahan yang belum di-commit:"
    git status --short | head -5
    echo "    Stash dengan: git stash"
else
    echo "  ✓ Tidak ada perubahan yang belum di-commit"
fi
echo ""

# Summary
echo "=========================================="
echo "  Summary"
echo "=========================================="
echo ""
if [ -d ".git" ] && git remote | grep -q origin; then
    echo "✓ Git repository sudah setup"
    echo "✓ Remote origin sudah dikonfigurasi"
    echo ""
    echo "Sistem siap untuk update dari GitHub!"
    echo ""
    echo "Untuk test update:"
    echo "  1. Buka halaman admin/about di browser"
    echo "  2. Sistem akan check update otomatis"
    echo "  3. Jika ada update, klik button 'Update Sekarang'"
else
    echo "✗ Git repository belum setup dengan benar"
    echo ""
    echo "Langkah setup:"
    echo "  1. git init"
    echo "  2. git remote add origin https://github.com/adiprayitno160-svg/ujian.git"
    echo "  3. git fetch origin"
    echo "  4. git checkout -b main origin/main"
    echo "  5. git branch --set-upstream-to=origin/main main"
fi
echo ""

