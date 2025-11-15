#!/bin/bash
# Initialize Git Repository di Server
# HATI-HATI: Script ini akan menginisialisasi git di folder yang ada

APP_PATH="/www/wwwroot/8.215.192.2"
GIT_REPO="https://github.com/adiprayitno160-svg/ujian.git"

echo "=== Initialize Git Repository ==="
echo "⚠️  PERINGATAN: Script ini akan:"
echo "   1. Backup folder saat ini"
echo "   2. Inisialisasi git repository"
echo "   3. Clone dari GitHub (akan overwrite file yang ada)"
echo ""
read -p "Lanjutkan? (y/n): " confirm

if [ "$confirm" != "y" ]; then
    echo "Dibatalkan."
    exit 1
fi

# Backup
BACKUP_DIR="${APP_PATH}_backup_$(date +%Y%m%d_%H%M%S)"
echo "→ Backup ke: $BACKUP_DIR"
cp -r "$APP_PATH" "$BACKUP_DIR" 2>/dev/null

# Masuk ke parent directory
cd "$(dirname "$APP_PATH")" || exit 1
FOLDER_NAME=$(basename "$APP_PATH")

# Rename folder lama
echo "→ Rename folder lama..."
mv "$FOLDER_NAME" "${FOLDER_NAME}_old_$(date +%Y%m%d_%H%M%S)"

# Clone dari GitHub
echo "→ Clone dari GitHub..."
git clone "$GIT_REPO" "$FOLDER_NAME"

# Checkout ke tag v1.0.23
cd "$FOLDER_NAME" || exit 1
git checkout v1.0.23

# Copy file penting dari backup (config, uploads, dll)
echo "→ Restore file penting dari backup..."
if [ -d "${APP_PATH}_old_$(date +%Y%m%d_%H%M%S)/config" ]; then
    cp "${APP_PATH}_old_$(date +%Y%m%d_%H%M%S)/config/database.php" "$APP_PATH/config/" 2>/dev/null
    cp "${APP_PATH}_old_$(date +%Y%m%d_%H%M%S)/config/ai_config.php" "$APP_PATH/config/" 2>/dev/null
fi

if [ -d "${APP_PATH}_old_$(date +%Y%m%d_%H%M%S)/assets/uploads" ]; then
    cp -r "${APP_PATH}_old_$(date +%Y%m%d_%H%M%S)/assets/uploads"/* "$APP_PATH/assets/uploads/" 2>/dev/null
fi

# Set permissions
chmod -R 755 "$APP_PATH"
chmod -R 777 "$APP_PATH/assets/uploads" "$APP_PATH/cache" "$APP_PATH/backups" 2>/dev/null
chown -R www-data:www-data "$APP_PATH" 2>/dev/null || chown -R apache:apache "$APP_PATH" 2>/dev/null

echo ""
echo "✓ Selesai! Git repository sudah diinisialisasi."
echo "✓ Versi: v1.0.23"

