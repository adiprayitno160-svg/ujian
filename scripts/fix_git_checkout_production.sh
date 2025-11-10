#!/bin/bash
# Script untuk fix Git checkout di production dengan file lokal
# Usage: bash fix_git_checkout_production.sh

set -e

REPO_PATH="/www/wwwroot/8.215.192.2"
BRANCH="main"

echo "=========================================="
echo "  Fix Git Checkout di Production"
echo "=========================================="
echo ""

cd "$REPO_PATH"

# 1. Setup Git user
echo "[1] Setting up Git user..."
git config --global user.name "Server"
git config --global user.email "server@localhost"
echo "✓ Git user configured"
echo ""

# 2. Backup file penting
echo "[2] Backing up important files..."
mkdir -p /www/backup/ujian_before_checkout_$(date +%Y%m%d_%H%M%S)
cp config/database.php /www/backup/ujian_before_checkout_$(date +%Y%m%d_%H%M%S)/ 2>/dev/null || true
echo "✓ Backup created"
echo ""

# 3. Add semua file lokal ke staging
echo "[3] Staging local files..."
git add -A
echo "✓ Files staged"
echo ""

# 4. Commit file lokal sebagai backup
echo "[4] Committing local files as backup..."
git commit -m "Backup: Local production files before sync - $(date +%Y-%m-%d\ %H:%M:%S)" || {
    echo "⚠ No changes to commit (files already committed)"
}
echo ""

# 5. Fetch dari GitHub
echo "[5] Fetching from GitHub..."
git fetch origin "$BRANCH"
echo "✓ Fetch completed"
echo ""

# 6. Merge dengan remote (bukan checkout langsung)
echo "[6] Merging with remote branch..."
git merge "origin/$BRANCH" --allow-unrelated-histories -m "Merge: Sync with GitHub main branch" -X ours || {
    echo "⚠ Merge conflict, resolving..."
    # Jika conflict, kita keep file dari remote untuk file core
    git checkout --theirs config/config.php admin/about.php admin_guru/login.php api/github_sync.php 2>/dev/null || true
    git add -A
    git commit -m "Resolve: Merge conflicts resolved" || true
}
echo "✓ Merge completed"
echo ""

# 7. Rename branch jika masih master
echo "[7] Setting up branch..."
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    git branch -m "$BRANCH"
fi
echo "✓ Branch set to $BRANCH"
echo ""

# 8. Setup tracking
echo "[8] Setting up tracking..."
git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" || true
echo "✓ Tracking configured"
echo ""

# 9. Restore file penting dari backup
echo "[9] Restoring important files..."
if [ -f "/www/backup/ujian_before_checkout_$(date +%Y%m%d)/database.php" ]; then
    cp "/www/backup/ujian_before_checkout_$(date +%Y%m%d)/database.php" config/database.php
    echo "✓ database.php restored"
fi
echo ""

# 10. Set permissions
echo "[10] Setting permissions..."
chown -R www:www .git
chmod -R 755 .git
echo "✓ Permissions set"
echo ""

# 11. Verifikasi
echo "[11] Verification..."
echo "Current branch:"
git branch -vv
echo ""
echo "Status:"
git status --short | head -10
echo ""

echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""

