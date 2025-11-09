# Script Update Aplikasi UJAN via SSH/PowerShell
# Sistem Ujian dan Pekerjaan Rumah (UJAN)
#
# Usage: .\update.ps1 [version] [branch]
#   version: Versi yang ingin diupdate (optional, default: latest)
#   branch: Branch yang ingin diupdate (optional, default: main)
#
# Example:
#   .\update.ps1              # Update ke versi terbaru dari branch main
#   .\update.ps1 v1.0.3       # Update ke versi v1.0.3
#   .\update.ps1 latest main  # Update ke versi terbaru dari branch main

param(
    [string]$Version = "latest",
    [string]$Branch = "main"
)

# Configuration
$RepoUrl = "https://github.com/adiprayitno160-svg/ujian.git"
$RepoDir = Get-Location
$BackupDir = "..\backups"

# Functions
function Write-Info {
    param([string]$Message)
    Write-Host "[INFO] $Message" -ForegroundColor Blue
}

function Write-Success {
    param([string]$Message)
    Write-Host "[SUCCESS] $Message" -ForegroundColor Green
}

function Write-Warning {
    param([string]$Message)
    Write-Host "[WARNING] $Message" -ForegroundColor Yellow
}

function Write-Error {
    param([string]$Message)
    Write-Host "[ERROR] $Message" -ForegroundColor Red
}

# Check if Git is installed
try {
    $null = git --version
} catch {
    Write-Error "Git tidak ditemukan. Silakan install Git terlebih dahulu."
    exit 1
}

# Check if we're in a Git repository
if (-not (Test-Path ".git")) {
    Write-Error "Direktori ini bukan Git repository."
    Write-Info "Menginisialisasi Git repository..."
    git init
    git remote add origin $RepoUrl 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Remote origin sudah ada"
    }
}

# Check if remote exists
$remotes = git remote
if ($remotes -notcontains "origin") {
    Write-Info "Menambahkan remote origin..."
    git remote add origin $RepoUrl
}

# Get current branch
$CurrentBranch = git rev-parse --abbrev-ref HEAD
Write-Info "Current branch: $CurrentBranch"
Write-Info "Target branch: $Branch"
Write-Info "Target version: $Version"

# Backup database before update
Write-Info "Membuat backup database..."
$BackupDate = Get-Date -Format "yyyyMMdd_HHmmss"
$BackupFile = "$BackupDir\database_backup_$BackupDate.sql"

# Create backup directory if it doesn't exist
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
}

# Try to backup database
if (Get-Command mysqldump -ErrorAction SilentlyContinue) {
    $ConfigFile = "config\database.php"
    if (Test-Path $ConfigFile) {
        $ConfigContent = Get-Content $ConfigFile -Raw
        
        # Extract DB credentials (simple regex, adjust as needed)
        $DBName = if ($ConfigContent -match "DB_NAME.*?'([^']+)'") { $Matches[1] } else { "" }
        $DBUser = if ($ConfigContent -match "DB_USER.*?'([^']+)'") { $Matches[1] } else { "" }
        $DBPass = if ($ConfigContent -match "DB_PASS.*?'([^']+)'") { $Matches[1] } else { "" }
        $DBHost = if ($ConfigContent -match "DB_HOST.*?'([^']+)'") { $Matches[1] } else { "localhost" }
        
        if ($DBName -and $DBUser) {
            Write-Info "Backing up database: $DBName"
            if ($DBPass) {
                & mysqldump -h $DBHost -u $DBUser -p"$DBPass" $DBName > $BackupFile 2>$null
            } else {
                & mysqldump -h $DBHost -u $DBUser $DBName > $BackupFile 2>$null
            }
            
            if (Test-Path $BackupFile -and (Get-Item $BackupFile).Length -gt 0) {
                Write-Success "Database backup berhasil: $BackupFile"
            } else {
                Write-Warning "Backup database gagal atau file kosong"
            }
        } else {
            Write-Warning "Tidak dapat membaca konfigurasi database. Skip backup."
        }
    } else {
        Write-Warning "File config\database.php tidak ditemukan. Skip backup."
    }
} else {
    Write-Warning "mysqldump tidak ditemukan. Skip backup database."
}

# Stash local changes
Write-Info "Menyimpan perubahan lokal..."
git stash push -m "Stash before update $BackupDate" 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Warning "Tidak ada perubahan untuk di-stash"
}

# Fetch latest changes
Write-Info "Fetching latest changes from origin/$Branch..."
git fetch origin $Branch
if ($LASTEXITCODE -ne 0) {
    Write-Error "Gagal fetch dari origin/$Branch"
    exit 1
}

# Update to specific version or latest
if ($Version -ne "latest") {
    Write-Info "Checking out version: $Version"
    
    # Check if version is a tag
    $tagCheck = git rev-parse "$Version" 2>$null
    if ($LASTEXITCODE -eq 0) {
        git checkout $Version
        if ($LASTEXITCODE -eq 0) {
            Write-Success "Berhasil checkout ke $Version"
        } else {
            Write-Error "Gagal checkout ke $Version"
            exit 1
        }
    } else {
        # Try to fetch tags first
        Write-Info "Fetching tags..."
        git fetch --tags origin 2>$null
        
        $tagCheck = git rev-parse "$Version" 2>$null
        if ($LASTEXITCODE -eq 0) {
            git checkout $Version
            if ($LASTEXITCODE -eq 0) {
                Write-Success "Berhasil checkout ke $Version"
            } else {
                Write-Error "Gagal checkout ke $Version"
                exit 1
            }
        } else {
            Write-Error "Version $Version tidak ditemukan"
            Write-Info "Menggunakan versi terbaru dari branch $Branch"
            git checkout $Branch 2>$null
            if ($LASTEXITCODE -ne 0) {
                git checkout -b $Branch "origin/$Branch"
            }
            git reset --hard "origin/$Branch"
        }
    }
} else {
    # Update to latest from branch
    Write-Info "Updating to latest from branch $Branch..."
    
    # Switch to target branch
    $branchCheck = git show-ref --verify --quiet "refs/heads/$Branch" 2>$null
    if ($LASTEXITCODE -eq 0) {
        git checkout $Branch
        if ($LASTEXITCODE -ne 0) {
            Write-Error "Gagal checkout ke branch $Branch"
            exit 1
        }
    } else {
        Write-Info "Branch $Branch tidak ada secara lokal, membuat dari origin/$Branch"
        git checkout -b $Branch "origin/$Branch"
        if ($LASTEXITCODE -ne 0) {
            Write-Error "Gagal membuat branch $Branch dari origin/$Branch"
            exit 1
        }
    }
    
    # Reset to remote branch
    git reset --hard "origin/$Branch"
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Gagal reset ke origin/$Branch"
        exit 1
    }
    
    Write-Success "Berhasil update ke latest dari branch $Branch"
}

# Get current version after update
$CurrentVersion = git describe --tags --abbrev=0 2>$null
if ($LASTEXITCODE -ne 0) {
    $CurrentVersion = "unknown"
}

$CurrentCommit = git rev-parse --short HEAD 2>$null
if ($LASTEXITCODE -ne 0) {
    $CurrentCommit = "unknown"
}

Write-Info "Current version: $CurrentVersion"
Write-Info "Current commit: $CurrentCommit"

# Set file permissions (Unix-like)
if (Get-Command chmod -ErrorAction SilentlyContinue) {
    Write-Info "Mengatur file permissions..."
    chmod -R 755 . 2>$null
    chmod -R 777 cache 2>$null
    chmod -R 777 assets\uploads 2>$null
}

# Clear cache
Write-Info "Membersihkan cache..."
Remove-Item -Path "cache\*.json" -ErrorAction SilentlyContinue
Remove-Item -Path "cache\github_releases.json" -ErrorAction SilentlyContinue

Write-Success "Update selesai!"
Write-Info "Version: $CurrentVersion"
Write-Info "Commit: $CurrentCommit"
Write-Info "Backup: $BackupFile"

# Show what changed
Write-Info "Perubahan terakhir:"
git log --oneline -5

Write-Host ""
Write-Success "Aplikasi berhasil diupdate!"
Write-Warning "Jangan lupa untuk:"
Write-Warning "1. Cek konfigurasi database"
Write-Warning "2. Jalankan migrations jika ada"
Write-Warning "3. Cek log error jika ada masalah"
Write-Warning "4. Test aplikasi untuk memastikan semua berfungsi"

