@echo off
echo Creating release v1.0.21...
echo.

echo Staging all changes...
git add -A
if %errorlevel% neq 0 (
    echo Error staging files
    pause
    exit /b 1
)

echo Committing changes...
git commit -m "Fix: Perbaikan redirect loop pada exam mode restriction (v1.0.21)" -m "- Menambahkan constant ON_EXAM_PAGE untuk mencegah redirect loop" -m "- Memperbaiki deteksi halaman exam menggunakan SCRIPT_NAME dan REQUEST_URI" -m "- Menggunakan clean URL untuk redirect (siswa-ujian-take)" -m "- Menambahkan pengecekan ganda untuk mencegah infinite redirect" -m "- Memperbarui clear_exam_mode() untuk menghapus flag on_exam_page" -m "- Memperbarui logout() untuk membersihkan semua flag exam"
if %errorlevel% neq 0 (
    echo Error committing changes
    pause
    exit /b 1
)

echo Creating tag v1.0.21...
git tag -a v1.0.21 -m "Release v1.0.21: Fix redirect loop pada exam mode restriction"
if %errorlevel% neq 0 (
    echo Error creating tag
    pause
    exit /b 1
)

echo Pushing to remote...
git push origin main
if %errorlevel% neq 0 (
    echo Error pushing to main
    pause
    exit /b 1
)

git push origin v1.0.21
if %errorlevel% neq 0 (
    echo Error pushing tag
    pause
    exit /b 1
)

echo.
echo Release v1.0.21 created and pushed successfully!
pause

