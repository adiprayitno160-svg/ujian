cd /www/wwwroot/8.215.192.2
git fetch origin --tags
git checkout v1.0.19
chmod -R 755 . 2>/dev/null
chmod -R 777 assets/uploads cache backups 2>/dev/null
chown -R www-data:www-data . 2>/dev/null || chown -R apache:apache . 2>/dev/null
rm -rf cache/* 2>/dev/null
echo "✓ Update selesai! Versi: $(git describe --tags 2>/dev/null || echo 'v1.0.19')"





