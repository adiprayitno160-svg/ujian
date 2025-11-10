#!/bin/bash
# Script untuk sync server production yang sudah ada dengan GitHub
# Usage: bash sync_existing_server_with_github.sh

set -e

REPO_PATH="/www/wwwroot/8.215.192.2"
BRANCH="main"

echo "=========================================="
echo "  Sync Server dengan GitHub"
echo "=========================================="
echo ""

cd "$REPO_PATH"

# 1. Backup file penting dulu
echo "[1] Creating backup..."
BACKUP_DIR="/www/backup/ujian_before_sync_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r config admin admin_guru api includes "$BACKUP_DIR/" 2>/dev/null || true
echo "✓ Backup created at: $BACKUP_DIR"
echo ""

# 2. Stash atau commit perubahan lokal (jika ada)
echo "[2] Handling local changes..."
# Add semua file ke staging
git add -A

# Cek apakah ada perubahan
if git diff --cached --quiet; then
    echo "No changes to commit"
else
    echo "Committing local changes..."
    git commit -m "Backup: Local changes before sync with GitHub - $(date +%Y-%m-%d\ %H:%M:%S)" || true
fi
echo ""

# 3. Fetch dari GitHub
echo "[3] Fetching from GitHub..."
git fetch origin "$BRANCH" --allow-unrelated-histories 2>/dev/null || git fetch origin
echo "✓ Fetch completed"
echo ""

# 4. Merge dengan remote (allow unrelated histories)
echo "[4] Merging with remote branch..."
# Cek apakah origin/main ada
if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    echo "Merging with origin/$BRANCH..."
    git merge "origin/$BRANCH" --allow-unrelated-histories -m "Merge: Sync with GitHub main branch" || {
        echo "⚠ Merge conflict detected"
        echo "Resolving conflicts by keeping remote version..."
        # Jika ada conflict, kita pilih remote version untuk file yang conflict
        git checkout --theirs . 2>/dev/null || true
        git add -A
        git commit -m "Resolve: Keep remote version for conflicts" || true
    }
    echo "✓ Merge completed"
else
    echo "⚠ origin/$BRANCH not found, skipping merge"
fi
echo ""

# 5. Rename branch master ke main (jika perlu)
echo "[5] Setting up branch..."
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ "$CURRENT_BRANCH" != "$BRANCH" ]; then
    echo "Renaming branch from $CURRENT_BRANCH to $BRANCH..."
    git branch -m "$BRANCH"
fi
echo "✓ Branch set to $BRANCH"
echo ""

# 6. Setup tracking
echo "[6] Setting up tracking..."
if git show-ref --verify --quiet "refs/remotes/origin/$BRANCH"; then
    git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" || true
    echo "✓ Tracking set to origin/$BRANCH"
fi
echo ""

# 7. Set permissions
echo "[7] Setting permissions..."
chown -R www:www .git 2>/dev/null || true
chmod -R 755 .git 2>/dev/null || true
echo "✓ Permissions set"
echo ""

# 8. Verifikasi
echo "[8] Verification..."
echo "Current branch:"
git branch -vv
echo ""
echo "Status:"
git status --short | head -20
echo ""

echo "=========================================="
echo "  Sync Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Check if there are any conflicts: git status"
echo "2. Test the application: http://8.215.192.2"
echo "3. Test update feature: http://8.215.192.2/admin-about"
echo ""

