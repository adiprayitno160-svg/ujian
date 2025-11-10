@echo off
echo === Release v1.0.14 ===
echo.

echo 1. Adding all changes...
git add -A
echo.

echo 2. Committing changes...
git commit -m "Release v1.0.14: Add Review Mode, Dashboard, Notifications, PWA, Bulk Operations, Templates, PDF Export, and all new features"
echo.

echo 3. Creating tag v1.0.14...
git tag -a v1.0.14 -m "Release v1.0.14: Add Review Mode, Dashboard, Notifications, PWA, Bulk Operations, Templates, PDF Export"
echo.

echo 4. Pushing to GitHub...
git push origin main
git push origin v1.0.14
echo.

echo === Release v1.0.14 Complete ===
pause

