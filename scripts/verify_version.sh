#!/bin/bash
# Script untuk verifikasi versi di server
# Jalankan di server via SSH

APP_PATH="/www/wwwroot/8.215.192.2"

echo "=== Verifikasi Versi UJAN ==="
echo ""

# Cek versi di config.php
echo "1. Versi di config.php:"
grep "APP_VERSION" "$APP_PATH/config/config.php" | head -1

echo ""
echo "2. Versi di Git tag:"
cd "$APP_PATH" && git describe --tags 2>/dev/null || echo "Tag tidak ditemukan"

echo ""
echo "3. Commit terakhir:"
cd "$APP_PATH" && git log --oneline -1

echo ""
echo "4. Status Git:"
cd "$APP_PATH" && git status --short

echo ""
echo "=== Selesai ==="

