# Script untuk create release v1.0.21
# Fix redirect loop pada exam mode restriction

# Disable pager
$env:GIT_PAGER = "cat"
git config --global core.pager "cat"

# Stage all changes
Write-Host "Staging all changes..."
git add -A

# Commit changes
Write-Host "Committing changes..."
git commit -m "Fix: Perbaikan redirect loop pada exam mode restriction (v1.0.21)" -m "- Menambahkan constant ON_EXAM_PAGE untuk mencegah redirect loop" -m "- Memperbaiki deteksi halaman exam menggunakan SCRIPT_NAME dan REQUEST_URI" -m "- Menggunakan clean URL untuk redirect (siswa-ujian-take)" -m "- Menambahkan pengecekan ganda untuk mencegah infinite redirect" -m "- Memperbarui clear_exam_mode() untuk menghapus flag on_exam_page" -m "- Memperbarui logout() untuk membersihkan semua flag exam"

# Create tag
Write-Host "Creating tag v1.0.21..."
git tag -a v1.0.21 -m "Release v1.0.21: Fix redirect loop pada exam mode restriction"

# Push to remote
Write-Host "Pushing to remote..."
git push origin main
git push origin v1.0.21

Write-Host "Release v1.0.21 created and pushed successfully!"

