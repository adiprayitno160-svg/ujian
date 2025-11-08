# Push to GitHub via PowerShell
# Sistem Ujian dan Pekerjaan Rumah (UJAN)
# 
# Cara penggunaan:
# 1. Buka PowerShell
# 2. cd ke folder project: cd C:\xampp\htdocs\UJAN
# 3. Jalankan: .\push_to_github.ps1
# Atau: powershell -ExecutionPolicy Bypass -File push_to_github.ps1

$ErrorActionPreference = "Continue"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Push to GitHub" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Change to project directory
$projectPath = $PSScriptRoot
if (-not $projectPath) {
    $projectPath = Get-Location
}
Set-Location $projectPath

Write-Host "Project path: $projectPath" -ForegroundColor Yellow
Write-Host ""

# Step 1: Check Git
Write-Host "[1/6] Checking Git..." -ForegroundColor Yellow
$gitVersion = git --version 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Git is NOT installed!" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Git is installed" -ForegroundColor Green
Write-Host ""

# Step 2: Check Git Config
Write-Host "[2/6] Checking Git Configuration..." -ForegroundColor Yellow
$userName = git config --global user.name 2>&1
$userEmail = git config --global user.email 2>&1

if (-not $userName -or -not $userEmail) {
    Write-Host "✗ Git is not configured!" -ForegroundColor Red
    Write-Host "Please run: .\setup_git.ps1" -ForegroundColor Yellow
    Write-Host "Or visit: http://localhost/UJAN/setup_git_config.php" -ForegroundColor Yellow
    exit 1
}
Write-Host "✓ Git configured:" -ForegroundColor Green
Write-Host "  Name:  $userName" -ForegroundColor White
Write-Host "  Email: $userEmail" -ForegroundColor White
Write-Host ""

# Step 3: Initialize Repository (if needed)
Write-Host "[3/6] Checking Repository..." -ForegroundColor Yellow
if (-not (Test-Path ".git")) {
    Write-Host "Initializing repository..." -ForegroundColor Yellow
    git init
    if ($LASTEXITCODE -ne 0) {
        Write-Host "✗ Failed to initialize repository" -ForegroundColor Red
        exit 1
    }
    Write-Host "✓ Repository initialized" -ForegroundColor Green
} else {
    Write-Host "✓ Repository already initialized" -ForegroundColor Green
}
Write-Host ""

# Step 4: Setup Remote
Write-Host "[4/6] Checking Remote..." -ForegroundColor Yellow
$remoteUrl = git config --get remote.origin.url 2>&1
if (-not $remoteUrl -or $remoteUrl -match "error") {
    Write-Host "Adding remote..." -ForegroundColor Yellow
    git remote add origin https://github.com/adiprayitno160-svg/ujian.git
    if ($LASTEXITCODE -ne 0) {
        # Maybe already exists, try to set URL
        git remote set-url origin https://github.com/adiprayitno160-svg/ujian.git
    }
    Write-Host "✓ Remote added" -ForegroundColor Green
} else {
    Write-Host "✓ Remote already configured: $remoteUrl" -ForegroundColor Green
}
Write-Host ""

# Step 5: Add and Commit
Write-Host "[5/6] Adding and Committing Files..." -ForegroundColor Yellow

# Add all files
Write-Host "Adding files..." -ForegroundColor Yellow
git add .
if ($LASTEXITCODE -ne 0) {
    Write-Host "✗ Failed to add files" -ForegroundColor Red
    exit 1
}

# Check if there are changes
$status = git status --porcelain
if (-not $status) {
    Write-Host "ℹ No changes to commit" -ForegroundColor Yellow
} else {
    # Check if first commit
    $headCheck = git rev-parse --verify HEAD 2>&1
    $isFirstCommit = $LASTEXITCODE -ne 0
    
    if ($isFirstCommit) {
        $commitMessage = "Initial commit - Sistem Ujian dan Pekerjaan Rumah (UJAN)"
    } else {
        $commitMessage = "Update sistem - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
    }
    
    Write-Host "Committing with message: $commitMessage" -ForegroundColor Yellow
    git commit -m $commitMessage
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ Commit successful" -ForegroundColor Green
    } else {
        Write-Host "✗ Failed to commit" -ForegroundColor Red
        exit 1
    }
}
Write-Host ""

# Step 6: Push
Write-Host "[6/6] Pushing to GitHub..." -ForegroundColor Yellow
Write-Host ""
Write-Host "⚠ IMPORTANT: You will be prompted for GitHub credentials" -ForegroundColor Yellow
Write-Host "   Username: adiprayitno160-svg" -ForegroundColor White
Write-Host "   Password: your_personal_access_token" -ForegroundColor White
Write-Host ""
Write-Host "If you don't have a Personal Access Token:" -ForegroundColor Yellow
Write-Host "1. Go to: https://github.com/settings/tokens" -ForegroundColor White
Write-Host "2. Generate new token (classic)" -ForegroundColor White
Write-Host "3. Select scope: repo" -ForegroundColor White
Write-Host "4. Copy the token and use it as password" -ForegroundColor White
Write-Host ""

$confirm = Read-Host "Ready to push? (y/n)"
if ($confirm -ne "y" -and $confirm -ne "Y") {
    Write-Host "Push cancelled." -ForegroundColor Yellow
    exit 0
}

# Get current branch
$currentBranch = git rev-parse --abbrev-ref HEAD 2>&1
if (-not $currentBranch -or $currentBranch -match "error") {
    $currentBranch = "main"
}

Write-Host ""
Write-Host "Pushing to origin/$currentBranch..." -ForegroundColor Yellow
git push -u origin $currentBranch

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "✓ Successfully pushed to GitHub!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Repository: https://github.com/adiprayitno160-svg/ujian" -ForegroundColor White
} else {
    Write-Host ""
    Write-Host "✗ Failed to push" -ForegroundColor Red
    Write-Host ""
    Write-Host "Possible reasons:" -ForegroundColor Yellow
    Write-Host "1. Authentication failed (check your token)" -ForegroundColor White
    Write-Host "2. Repository doesn't exist or is private" -ForegroundColor White
    Write-Host "3. Network/firewall issue" -ForegroundColor White
    Write-Host ""
    Write-Host "You can also push manually:" -ForegroundColor Yellow
    Write-Host "  git push -u origin $currentBranch" -ForegroundColor White
}

Write-Host ""

