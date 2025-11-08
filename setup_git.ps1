# Setup Git Configuration via PowerShell
# Sistem Ujian dan Pekerjaan Rumah (UJAN)
# 
# Cara penggunaan:
# 1. Buka PowerShell
# 2. cd ke folder project: cd C:\xampp\htdocs\UJAN
# 3. Jalankan: .\setup_git.ps1
# Atau: powershell -ExecutionPolicy Bypass -File setup_git.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Setup Git Configuration" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if Git is installed
Write-Host "Checking Git installation..." -ForegroundColor Yellow
$gitVersion = git --version 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Git is installed: $gitVersion" -ForegroundColor Green
} else {
    Write-Host "✗ Git is NOT installed!" -ForegroundColor Red
    Write-Host "Please install Git from: https://git-scm.com/download/win" -ForegroundColor Yellow
    exit 1
}

Write-Host ""

# Get current config
Write-Host "Current Git Configuration:" -ForegroundColor Yellow
$currentName = git config --global user.name 2>&1
$currentEmail = git config --global user.email 2>&1

if ($currentName -and $currentEmail) {
    Write-Host "  Name:  $currentName" -ForegroundColor Green
    Write-Host "  Email: $currentEmail" -ForegroundColor Green
    Write-Host ""
    $change = Read-Host "Do you want to change it? (y/n)"
    if ($change -ne "y" -and $change -ne "Y") {
        Write-Host "Configuration unchanged." -ForegroundColor Yellow
        exit 0
    }
} else {
    Write-Host "  Git is not configured yet." -ForegroundColor Yellow
    Write-Host ""
}

# Get user input
Write-Host "Please enter your Git configuration:" -ForegroundColor Cyan
$userName = Read-Host "Git Username (e.g., Adi Prayitno)"
$userEmail = Read-Host "Git Email (e.g., your.email@example.com)"

if ([string]::IsNullOrWhiteSpace($userName) -or [string]::IsNullOrWhiteSpace($userEmail)) {
    Write-Host "✗ Username and email are required!" -ForegroundColor Red
    exit 1
}

# Set Git config
Write-Host ""
Write-Host "Setting Git configuration..." -ForegroundColor Yellow

git config --global user.name "$userName"
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Username set successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to set username" -ForegroundColor Red
    exit 1
}

git config --global user.email "$userEmail"
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Email set successfully" -ForegroundColor Green
} else {
    Write-Host "✗ Failed to set email" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Git Configuration Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Yellow
Write-Host "1. Run: .\push_to_github.ps1" -ForegroundColor White
Write-Host "   Or use the web interface: http://localhost/UJAN/push_to_github.php" -ForegroundColor White
Write-Host ""

