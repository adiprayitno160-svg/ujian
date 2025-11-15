#!/bin/bash
# Quick Update Script SSH - UJAN v1.0.23
# Copy paste script ini langsung ke terminal SSH server

APP_PATH="/www/wwwroot/8.215.192.2"
echo "=== UJAN Quick Update v1.0.23 ==="

# Backup cepat (optional - comment jika tidak perlu)
BACKUP_DIR="${APP_PATH}/../backups_ujian_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR" && cp -r "$APP_PATH"/* "$BACKUP_DIR/" 2>/dev/null && echo "✓ Backup: $BACKUP_DIR" || echo "⚠ Backup gagal, lanjutkan..."

# Update dari GitHub
cd "$APP_PATH" || exit 1
echo "→ Fetching tags and updates..."
git fetch origin --tags
git fetch origin main
git checkout main
git pull origin main
git checkout v1.0.23 2>/dev/null || git checkout main

# Set permissions
echo "→ Setting permissions..."
chmod -R 755 "$APP_PATH" 2>/dev/null
chmod -R 777 "$APP_PATH/assets/uploads" "$APP_PATH/cache" "$APP_PATH/backups" 2>/dev/null
chown -R www-data:www-data "$APP_PATH" 2>/dev/null || chown -R apache:apache "$APP_PATH" 2>/dev/null

# Clear cache
echo "→ Clearing cache..."
rm -rf "$APP_PATH/cache"/* 2>/dev/null

echo ""
echo "✓ Update selesai! Versi: v1.0.23"
echo "✓ Perbaikan:"
echo "  - Fix undefined variable \$search di Naik Kelas"
echo "  - Fix dropdown tahun ajaran di Import Ledger Nilai"

