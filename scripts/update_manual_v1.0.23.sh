#!/bin/bash
# Manual Update Script - UJAN v1.0.23
# Untuk server yang tidak menggunakan git

APP_PATH="/www/wwwroot/8.215.192.2"
echo "=== Manual Update v1.0.23 ==="

# Backup config.php
echo "→ Backup config.php..."
cp "$APP_PATH/config/config.php" "$APP_PATH/config/config.php.backup_$(date +%Y%m%d_%H%M%S)"

# Update APP_VERSION di config.php
echo "→ Update APP_VERSION ke 1.0.23..."
sed -i "s/define('APP_VERSION', '1.0.22');/define('APP_VERSION', '1.0.23');/g" "$APP_PATH/config/config.php"
sed -i 's/define("APP_VERSION", "1.0.22");/define("APP_VERSION", "1.0.23");/g' "$APP_PATH/config/config.php"

# Verifikasi
echo ""
echo "→ Verifikasi versi:"
grep "APP_VERSION" "$APP_PATH/config/config.php" | head -1

echo ""
echo "✓ Update selesai!"

