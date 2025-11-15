APP_PATH="/www/wwwroot/8.215.192.2" && echo "=== UJAN Update v1.0.19 ===" && BACKUP_DIR="${APP_PATH}/../backups_ujian_$(date +%Y%m%d_%H%M%S)" && mkdir -p "$BACKUP_DIR" && cp -r "$APP_PATH"/* "$BACKUP_DIR/" 2>/dev/null && echo "✓ Backup: $BACKUP_DIR" || echo "⚠ Backup gagal" && cd "$APP_PATH" || exit 1 && git fetch origin --tags && git fetch origin main && git checkout main && git pull origin main && git checkout v1.0.19 2>/dev/null || git checkout main && chmod -R 755 "$APP_PATH" 2>/dev/null && chmod -R 777 "$APP_PATH/assets/uploads" "$APP_PATH/cache" "$APP_PATH/backups" 2>/dev/null && chown -R www-data:www-data "$APP_PATH" 2>/dev/null || chown -R apache:apache "$APP_PATH" 2>/dev/null && rm -rf "$APP_PATH/cache"/* 2>/dev/null && echo "✓ Update selesai! Versi: v1.0.19"






