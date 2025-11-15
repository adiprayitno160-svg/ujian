#!/bin/bash
# Script untuk fix versi jika masih 1.0.22
# Jalankan di server via SSH

APP_PATH="/www/wwwroot/8.215.192.2"

echo "=== Fix Versi ke 1.0.23 ==="

cd "$APP_PATH" || exit 1

# Pull update terbaru
echo "→ Pulling update dari GitHub..."
git fetch origin --tags
git fetch origin main
git checkout main
git pull origin main

# Pastikan checkout ke tag v1.0.23
echo "→ Checking out ke tag v1.0.23..."
git checkout v1.0.23 2>/dev/null || git checkout main

# Verifikasi versi
echo ""
echo "→ Verifikasi versi:"
grep "APP_VERSION" config/config.php | head -1

# Clear cache
echo "→ Clearing cache..."
rm -rf cache/* 2>/dev/null

echo ""
echo "✓ Selesai! Refresh browser dan clear cache browser Anda."

