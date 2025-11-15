@echo off
echo ========================================
echo Commit and Push v1.0.24
echo ========================================
echo.

cd /d "%~dp0.."

echo Adding files...
git add -A

echo.
echo Committing...
git commit -m "Release v1.0.24: Fix import ledger nilai mapping mapel fleksibel dan sistem update menggunakan release terbaru"

echo.
echo Creating tag...
git tag -a v1.0.24 -m "Release v1.0.24 - Fix import ledger nilai dan sistem update GitHub"

echo.
echo Pushing to GitHub...
git push origin main
git push origin v1.0.24

echo.
echo ========================================
echo Done!
echo ========================================
pause

