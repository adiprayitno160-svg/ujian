#!/bin/bash
# Script untuk mengecek status Git dan masalah pull dari GitHub
# Usage: bash check_git_status.sh

echo "=========================================="
echo "  Diagnostic Git Repository"
echo "=========================================="
echo ""

# Check if Git is installed
echo "[1] Checking Git installation..."
if command -v git &> /dev/null; then
    GIT_VERSION=$(git --version)
    echo "  ✓ Git installed: $GIT_VERSION"
else
    echo "  ✗ Git tidak terinstall!"
    echo "    Install dengan: sudo apt-get install git (Ubuntu/Debian)"
    echo "    atau: sudo yum install git (CentOS/RHEL)"
    exit 1
fi
echo ""

# Check if we're in a Git repository
echo "[2] Checking if this is a Git repository..."
if [ -d ".git" ]; then
    echo "  ✓ Ini adalah Git repository"
else
    echo "  ✗ Bukan Git repository!"
    echo "    Inisialisasi dengan: git init"
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
        echo "  ✓ Koneksi ke GitHub berhasil"
    else
        echo "  ✗ Koneksi ke GitHub gagal!"
        echo "    Mungkin masalah:"
        echo "    - Koneksi internet terputus"
        echo "    - Firewall memblokir GitHub"
        echo "    - URL remote salah"
        echo "    - Butuh authentication"
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

# Check available branches
echo "  Available branches:"
git branch -a 2>/dev/null | head -10 || echo "    (tidak dapat membaca branches)"
echo ""

# Check if there are uncommitted changes
echo "[5] Checking for uncommitted changes..."
if [ -n "$(git status --porcelain)" ]; then
    echo "  ⚠ Ada perubahan yang belum di-commit:"
    git status --short | head -5
    echo "    Stash dengan: git stash"
else
    echo "  ✓ Tidak ada perubahan yang belum di-commit"
fi
echo ""

# Check network connectivity
echo "[6] Checking network connectivity..."
if ping -c 1 github.com &> /dev/null; then
    echo "  ✓ Koneksi ke github.com berhasil"
else
    echo "  ✗ Tidak bisa ping github.com"
    echo "    Cek koneksi internet atau firewall"
fi
echo ""

# Check Git credentials
echo "[7] Checking Git credentials..."
GIT_USER=$(git config user.name 2>/dev/null || echo "not set")
GIT_EMAIL=$(git config user.email 2>/dev/null || echo "not set")
echo "  Git user.name: $GIT_USER"
echo "  Git user.email: $GIT_EMAIL"
echo ""

# Try to fetch
echo "[8] Testing fetch from GitHub..."
if git fetch origin main --dry-run 2>&1 | head -5; then
    echo "  ✓ Fetch test berhasil"
else
    echo "  ✗ Fetch test gagal"
    ERROR_OUTPUT=$(git fetch origin main --dry-run 2>&1)
    echo "    Error: $ERROR_OUTPUT"
fi
echo ""

# Summary
echo "=========================================="
echo "  Summary"
echo "=========================================="
echo ""
echo "Jika pull tidak bisa, coba solusi berikut:"
echo ""
echo "1. Setup remote origin:"
echo "   git remote remove origin"
echo "   git remote add origin https://github.com/adiprayitno160-svg/ujian.git"
echo ""
echo "2. Fetch dan pull:"
echo "   git fetch origin main"
echo "   git pull origin main"
echo ""
echo "3. Jika masih gagal, gunakan script alternatif:"
echo "   - UPDATE_VIA_ZIP.sh (download ZIP dari GitHub)"
echo "   - UPDATE_VIA_CURL.sh (download file via curl)"
echo ""



