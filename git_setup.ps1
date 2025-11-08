# Quick Git Setup & Push
# Sistem Ujian dan Pekerjaan Rumah (UJAN)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Git Setup & Push to GitHub" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Change to project directory
$projectPath = "C:\xampp\htdocs\UJAN"
if (Test-Path $projectPath) {
    Set-Location $projectPath
    Write-Host "Working directory: $projectPath" -ForegroundColor Green
} else {
    Write-Host "Error: Project path not found: $projectPath" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Step 1: Check Git
Write-Host "[1] Checking Git..." -ForegroundColor Yellow
try {
    $gitVersion = git --version 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "OK: Git is installed" -ForegroundColor Green
    } else {
        Write-Host "ERROR: Git is NOT installed!" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "ERROR: Git is NOT installed!" -ForegroundColor Red
    exit 1
}
Write-Host ""

# Step 2: Setup Git Config
Write-Host "[2] Checking Git Configuration..." -ForegroundColor Yellow
$userName = git config --global user.name 2>&1
$userEmail = git config --global user.email 2>&1

if ([string]::IsNullOrWhiteSpace($userName) -or [string]::IsNullOrWhiteSpace($userEmail)) {
    Write-Host "Git is not configured. Please enter:" -ForegroundColor Yellow
    $newName = Read-Host "Git Username"
    $newEmail = Read-Host "Git Email"
    
    if (-not [string]::IsNullOrWhiteSpace($newName) -and -not [string]::IsNullOrWhiteSpace($newEmail)) {
        git config --global user.name $newName
        git config --global user.email $newEmail
        Write-Host "OK: Git configured" -ForegroundColor Green
    } else {
        Write-Host "ERROR: Username and email are required!" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "OK: Git configured" -ForegroundColor Green
    Write-Host "  Name: $userName" -ForegroundColor White
    Write-Host "  Email: $userEmail" -ForegroundColor White
}
Write-Host ""

# Step 3: Initialize Repository
Write-Host "[3] Checking Repository..." -ForegroundColor Yellow
if (-not (Test-Path ".git")) {
    Write-Host "Initializing repository..." -ForegroundColor Yellow
    git init
    Write-Host "OK: Repository initialized" -ForegroundColor Green
} else {
    Write-Host "OK: Repository exists" -ForegroundColor Green
}
Write-Host ""

# Step 4: Setup Remote
Write-Host "[4] Checking Remote..." -ForegroundColor Yellow
$remoteCheck = git remote get-url origin 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host "Adding remote..." -ForegroundColor Yellow
    git remote add origin https://github.com/adiprayitno160-svg/ujian.git
    if ($LASTEXITCODE -ne 0) {
        git remote set-url origin https://github.com/adiprayitno160-svg/ujian.git
    }
    Write-Host "OK: Remote added" -ForegroundColor Green
} else {
    Write-Host "OK: Remote exists" -ForegroundColor Green
}
Write-Host ""

# Step 5: Add Files
Write-Host "[5] Adding files..." -ForegroundColor Yellow
git add .
Write-Host "OK: Files added" -ForegroundColor Green
Write-Host ""

# Step 6: Commit
Write-Host "[6] Committing..." -ForegroundColor Yellow
$headExists = git rev-parse --verify HEAD 2>&1
if ($LASTEXITCODE -ne 0) {
    $commitMsg = "Initial commit - Sistem Ujian dan Pekerjaan Rumah (UJAN)"
} else {
    $commitMsg = "Update sistem - $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
}

git commit -m $commitMsg
if ($LASTEXITCODE -eq 0) {
    Write-Host "OK: Commit successful" -ForegroundColor Green
} else {
    $status = git status --porcelain
    if ([string]::IsNullOrWhiteSpace($status)) {
        Write-Host "INFO: No changes to commit" -ForegroundColor Yellow
    } else {
        Write-Host "ERROR: Failed to commit" -ForegroundColor Red
        exit 1
    }
}
Write-Host ""

# Step 7: Push
Write-Host "[7] Ready to Push" -ForegroundColor Yellow
Write-Host ""
Write-Host "IMPORTANT: You will need GitHub credentials:" -ForegroundColor Yellow
Write-Host "  Username: adiprayitno160-svg" -ForegroundColor White
Write-Host "  Password: your_personal_access_token" -ForegroundColor White
Write-Host ""
Write-Host "Get token from: https://github.com/settings/tokens" -ForegroundColor Cyan
Write-Host ""

$confirm = Read-Host "Push to GitHub now? (y/n)"
if ($confirm -eq "y" -or $confirm -eq "Y") {
    Write-Host ""
    Write-Host "Pushing to GitHub..." -ForegroundColor Yellow
    
    $branch = git rev-parse --abbrev-ref HEAD 2>&1
    if ($LASTEXITCODE -ne 0) {
        $branch = "main"
    }
    
    git push -u origin $branch
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "SUCCESS: Pushed to GitHub!" -ForegroundColor Green
        Write-Host "Repository: https://github.com/adiprayitno160-svg/ujian" -ForegroundColor Cyan
    } else {
        Write-Host ""
        Write-Host "ERROR: Push failed" -ForegroundColor Red
        Write-Host "Check your credentials and try again" -ForegroundColor Yellow
    }
} else {
    Write-Host "Push cancelled. You can push later with:" -ForegroundColor Yellow
    Write-Host "  git push -u origin main" -ForegroundColor White
}

Write-Host ""

