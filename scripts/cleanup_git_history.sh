#!/bin/bash
# Script untuk cleanup commit lokal (opsional)
# HATI-HATI: Hanya jalankan jika file penting sudah di-backup!

REPO_PATH="/www/wwwroot/8.215.192.2"

cd "$REPO_PATH"

echo "=========================================="
echo "  Cleanup Git History (Optional)"
echo "=========================================="
echo ""
echo "⚠ WARNING: This will reset local commits!"
echo "Make sure important files are backed up!"
echo ""
read -p "Continue? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted"
    exit 1
fi

# Backup database.php
cp config/database.php /tmp/database.php.backup

# Reset ke origin/main (hapus commit lokal)
git reset --hard origin/main

# Restore database.php
cp /tmp/database.php.backup config/database.php

# Set permissions
chown -R www:www .git
chmod -R 755 .git

echo "✓ Cleanup completed"
echo "Current status:"
git branch -vv
git status

