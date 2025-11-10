#!/bin/bash
# Script aman untuk setup Git di production server
# Usage: bash setup_git_production_safe.sh

set -e

REPO_PATH="/www/wwwroot/8.215.192.2"
REPO_URL="https://github.com/adiprayitno160-svg/ujian.git"
BRANCH="main"
TEMP_DIR="/tmp/ujian_setup_$(date +%s)"

echo "=========================================="
echo "  Setup Git di Production Server"
echo "=========================================="
echo ""

cd "$REPO_PATH"

# 1. Backup file penting
echo "[1] Backing up important files..."
mkdir -p "$TEMP_DIR/backup"
cp config/database.php "$TEMP_DIR/backup/database.php" 2>/dev/null || true
cp config/config.php "$TEMP_DIR/backup/config.php" 2>/dev/null || true
echo "✓ Backup created"
echo ""

# 2. Setup Git user
echo "[2] Setting up Git user..."
git config --global user.name "Server"
git config --global user.email "server@localhost"
echo "✓ Git user configured"
echo ""

# 3. Hapus .git yang ada (jika ada)
echo "[3] Cleaning up existing Git repository..."
if [ -d ".git" ]; then
    rm -rf .git
    echo "✓ Old .git removed"
fi
echo ""

# 4. Clone dari GitHub ke temp location
echo "[4] Cloning from GitHub..."
cd /tmp
rm -rf ujian_temp 2>/dev/null || true
git clone "$REPO_URL" ujian_temp
cd ujian_temp
git checkout "$BRANCH"
echo "✓ Cloned from GitHub"
echo ""

# 5. Copy .git ke production (dengan cara yang benar)
echo "[5] Copying .git to production..."
cd "$REPO_PATH"
cp -r /tmp/ujian_temp/.git .
echo "✓ .git copied"
echo ""

# 6. Restore file penting dari backup
echo "[6] Restoring important files..."
if [ -f "$TEMP_DIR/backup/database.php" ]; then
    cp "$TEMP_DIR/backup/database.php" config/database.php
    echo "✓ database.php restored"
fi
if [ -f "$TEMP_DIR/backup/config.php" ]; then
    # Hanya restore jika file lokal berbeda (optional)
    # cp "$TEMP_DIR/backup/config.php" config/config.php
    echo "✓ config.php backup available"
fi
echo ""

# 7. Setup Git
echo "[7] Setting up Git..."
git config user.name "Server"
git config user.email "server@localhost"
git branch --set-upstream-to="origin/$BRANCH" "$BRANCH" || true
echo "✓ Git configured"
echo ""

# 8. Set permissions
echo "[8] Setting permissions..."
chown -R www:www .git
chmod -R 755 .git
echo "✓ Permissions set"
echo ""

# 9. Verifikasi
echo "[9] Verification..."
echo "Current branch:"
git branch -vv
echo ""
echo "Status:"
git status --short | head -10
echo ""

# 10. Cleanup
echo "[10] Cleaning up..."
rm -rf /tmp/ujian_temp
rm -rf "$TEMP_DIR"
echo "✓ Cleanup done"
echo ""

echo "=========================================="
echo "  Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Test application: http://8.215.192.2"
echo "2. Test update feature: http://8.215.192.2/admin-about"
echo "3. Check Git status: git status"
echo ""

