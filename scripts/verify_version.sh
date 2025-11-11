#!/bin/bash
# Script untuk verify version setelah update

cd /www/wwwroot/8.215.192.2 || exit 1

echo "Checking version..."
echo ""

# Method 1: Using grep with sed
VERSION=$(grep "APP_VERSION" config/config.php | sed "s/.*'\([^']*\)'.*/\1/" | head -1)

# Method 2: Using grep with regex (fallback)
if [ -z "$VERSION" ] || [ "$VERSION" = "APP_VERSION" ]; then
    VERSION=$(grep "APP_VERSION" config/config.php | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)
fi

# Method 3: Using PHP (most reliable)
if [ -z "$VERSION" ] || [ "$VERSION" = "APP_VERSION" ]; then
    VERSION=$(php -r "require 'config/config.php'; echo APP_VERSION;" 2>/dev/null)
fi

# Method 4: Using git tag
if [ -z "$VERSION" ] || [ "$VERSION" = "APP_VERSION" ]; then
    VERSION=$(git describe --tags --abbrev=0 2>/dev/null | sed 's/^v//')
fi

if [ -n "$VERSION" ] && [ "$VERSION" != "APP_VERSION" ]; then
    echo "Version: $VERSION"
else
    echo "Version: unknown (check manually)"
    echo ""
    echo "Manual check:"
    grep "APP_VERSION" config/config.php | head -1
fi

echo ""
echo "Git status:"
git status --short | head -5

echo ""
echo "Git log (last 3 commits):"
git log --oneline -3

echo ""
echo "Current branch:"
git branch --show-current

echo ""
echo "Latest tag:"
git describe --tags --abbrev=0 2>/dev/null || echo "No tags found"

