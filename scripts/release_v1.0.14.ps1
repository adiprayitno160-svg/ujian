# Release v1.0.14 Script
# Sistem Ujian dan Pekerjaan Rumah (UJAN)

Write-Host "=== Release v1.0.14 ===" -ForegroundColor Green

# Change to project directory
Set-Location "C:\xampp\htdocs\UJAN"

# Check git status
Write-Host "`n1. Checking git status..." -ForegroundColor Yellow
git status --short | Select-Object -First 10

# Add all changes
Write-Host "`n2. Adding all changes..." -ForegroundColor Yellow
git add -A

# Commit
Write-Host "`n3. Committing changes..." -ForegroundColor Yellow
git commit -m "Release v1.0.14: Add Review Mode, Dashboard, Notifications, PWA, Bulk Operations, Templates, PDF Export, and all new features

- Review Mode: Review semua soal sebelum submit
- Dashboard & Progress Tracking: Dashboard siswa dengan grafik performa
- Sistem Notifikasi: Notifikasi in-app untuk siswa
- Mobile Optimization (PWA): PWA manifest dan service worker
- Fitur Administratif: Bulk operations dan template ujian
- Export/Import Lanjutan: Export PDF untuk nilai
- Database Migration: Tabel dan kolom baru untuk fitur-fitur baru
- Bug Fixes: Perbaikan berbagai bug dan issue"

# Create tag
Write-Host "`n4. Creating tag v1.0.14..." -ForegroundColor Yellow
git tag -a v1.0.14 -m "Release v1.0.14: Add Review Mode, Dashboard, Notifications, PWA, Bulk Operations, Templates, PDF Export"

# Push to GitHub
Write-Host "`n5. Pushing to GitHub..." -ForegroundColor Yellow
git push origin main
git push origin v1.0.14

Write-Host "`n=== Release v1.0.14 Complete ===" -ForegroundColor Green
Write-Host "Version: 1.0.14" -ForegroundColor Cyan
Write-Host "Tag: v1.0.14" -ForegroundColor Cyan
Write-Host "Branch: main" -ForegroundColor Cyan

