#!/bin/bash
# Script lengkap untuk setup Git di server
# Usage: bash setup_git_server_complete.sh

set -e

REPO_PATH="/www/wwwroot/8.215.192.2"
REPO_URL="https://github.com/adiprayitno160-svg/ujian.git"
BRANCH="main"

echo "=========================================="
echo "  Setup Git Repository di Server"
echo "=========================================="
echo ""

cd "$REPO_PATH"

# 1. Fix safe.directory (jika belum)
echo "[1] Setting safe.directory..."
git config --global --add safe.directory "$REPO_PATH" 2>/dev/null || true
echo "✓ Safe directory configured"
echo ""

# 2. Cek remote
echo "[2] Checking remote..."
if git remote | grep -q origin; then
    echo "✓ Remote origin exists"
    git remote set-url origin "$REPO_URL" 2>/dev/null || true
else
    echo "Adding remote origin..."
    git remote add origin "$REPO_URL"
    echo "✓ Remote origin added"
fi
git remote -v
echo ""

# 3. Fetch dari GitHub
echo "[3] Fetching from GitHub..."
git fetch origin "$BRANCH" || {
    echo "⚠ Warning: Fetch failed, trying again..."
    git fetch origin
}
echo "✓ Fetch completed"
echo ""

# 4. Checkout branch main
echo "[4] Checking out branch $BRANCH..."
# Cek apakah branch main sudah ada
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
    echo "Branch $BRANCH exists locally"
    git checkout "$BRANCH"
else
    echo "Creating branch $BRANCH from origin/$BRANCH"
    # Cek apakah origin/main ada
    if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
        git checkout -b "$BRANCH" "origin/$BRANCH"
    else
        echo "⚠ Warning: origin/$BRANCH not found, creating new branch"
        git checkout -b "$BRANCH"
    fi
fi
echo "✓ Branch $BRANCH checked out"
echo ""

# 5. Setup tracking
echo "[5] Setting up tracking..."
if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" || true
    echo "✓ Tracking set to origin/$BRANCH"
else
    echo "⚠ Warning: origin/$BRANCH not found, tracking not set"
fi
echo ""

# 6. Set permissions
echo "[6] Setting permissions..."
chown -R www:www .git 2>/dev/null || true
chmod -R 755 .git 2>/dev/null || true
echo "✓ Permissions set"
echo ""

# 7. Verifikasi
echo "[7] Verification..."
echo "Current branch:"
git branch -vv
echo ""
echo "Remote status:"
git remote -v
echo ""
echo "Latest commit:"
git log --oneline -1 2>/dev/null || echo "No commits yet"
echo ""

# 8. Test connection
echo "[8] Testing connection to GitHub..."
if git ls-remote --heads origin "$BRANCH" &>/dev/null; then
    echo "✓ Connection to GitHub successful"
    echo "✓ Branch $BRANCH exists on remote"
else
    echo "⚠ Warning: Cannot connect to GitHub or branch not found"
fi
echo ""

echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Test pull: git pull origin $BRANCH"
echo "2. Check status: git status"
echo "3. Access admin/about page to test update feature"
echo ""

