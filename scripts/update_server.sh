#!/bin/bash
#
# Script untuk update live server dari GitHub
# Usage: ./update_server.sh
#

echo "=========================================="
echo "Update Live Server dari GitHub"
echo "=========================================="
echo ""

# Set working directory
cd /www/wwwroot/8.215.192.2 || exit 1

echo "1. Checking current version..."
if [ -f "config/config.php" ]; then
    CURRENT_VERSION=$(grep "APP_VERSION" config/config.php | grep -oP "'\K[^']+" | head -1)
    echo "   Current version: $CURRENT_VERSION"
else
    echo "   Warning: config/config.php not found"
fi

echo ""
echo "2. Checking Git status..."
git status --short

echo ""
echo "3. Stashing local changes (if any)..."
git stash push -m "Stash before update $(date +'%Y-%m-%d_%H-%M-%S')"

echo ""
echo "4. Fetching latest changes from GitHub..."
git fetch origin main

echo ""
echo "5. Pulling latest changes..."
git pull origin main

echo ""
echo "6. Checking out latest tag (v1.0.10)..."
git fetch --tags
git checkout v1.0.10 2>/dev/null || git checkout main

echo ""
echo "7. Setting correct permissions..."
chown -R www:www .
chmod -R 755 .
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;

echo ""
echo "8. Verifying update..."
if [ -f "config/config.php" ]; then
    NEW_VERSION=$(grep "APP_VERSION" config/config.php | grep -oP "'\K[^']+" | head -1)
    echo "   New version: $NEW_VERSION"
    if [ "$CURRENT_VERSION" != "$NEW_VERSION" ]; then
        echo "   ✓ Version updated from $CURRENT_VERSION to $NEW_VERSION"
    else
        echo "   ⚠ Version unchanged"
    fi
else
    echo "   ✗ config/config.php not found"
fi

echo ""
echo "9. Checking Git status after update..."
git status --short

echo ""
echo "=========================================="
echo "Update completed!"
echo "=========================================="
echo ""
echo "Please test the application:"
echo "  - Root URL: http://8.215.192.2/"
echo "  - Siswa Login: http://8.215.192.2/siswa-login"
echo "  - Admin Login: http://8.215.192.2/admin-login"
echo ""

