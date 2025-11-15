cd /www/wwwroot/8.215.192.2
git fetch origin --tags
git fetch origin main
git reset --hard origin/main
git checkout v1.0.19 2>/dev/null || git checkout main
chmod -R 755 . 2>/dev/null
chmod -R 777 assets/uploads cache backups 2>/dev/null
chown -R www-data:www-data . 2>/dev/null || chown -R apache:apache . 2>/dev/null
rm -rf cache/* 2>/dev/null
echo "✓ Update selesai! Versi: v1.0.19"






