# Script untuk membuat release baru di GitHub (PowerShell)
# Usage: .\create_release.ps1 -Version "1.0.8" [-ReleaseName "Release v1.0.8"] [-ReleaseBody "Description"]

param(
    [Parameter(Mandatory=$true)]
    [string]$Version,
    
    [Parameter(Mandatory=$false)]
    [string]$ReleaseName = "",
    
    [Parameter(Mandatory=$false)]
    [string]$ReleaseBody = ""
)

# Validate version format
if ($Version -notmatch '^\d+\.\d+\.\d+$') {
    Write-Host "Error: Invalid version format. Use X.Y.Z format (e.g., 1.0.8)" -ForegroundColor Red
    exit 1
}

$TagName = "v$Version"
if ([string]::IsNullOrEmpty($ReleaseName)) {
    $ReleaseName = "Release $TagName"
}
if ([string]::IsNullOrEmpty($ReleaseBody)) {
    $ReleaseBody = "Release $TagName`n`nPerbaikan dan peningkatan fitur."
}

Write-Host "Creating release $TagName..." -ForegroundColor Cyan

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoDir = Split-Path -Parent $ScriptDir

Set-Location $RepoDir

# Check if git is available
try {
    $null = git --version 2>&1
} catch {
    Write-Host "Error: Git is not installed" -ForegroundColor Red
    exit 1
}

# Check if we're in a git repository
if (-not (Test-Path ".git")) {
    Write-Host "Error: Not a git repository" -ForegroundColor Red
    exit 1
}

# Check if tag already exists
$tagExists = $false
try {
    $null = git rev-parse "$TagName" 2>&1
    $tagExists = $true
} catch {
    $tagExists = $false
}

if ($tagExists) {
    Write-Host "Warning: Tag $TagName already exists" -ForegroundColor Yellow
    $response = Read-Host "Do you want to delete and recreate it? (y/N)"
    if ($response -eq 'y' -or $response -eq 'Y') {
        git tag -d "$TagName" 2>&1 | Out-Null
        git push origin ":refs/tags/$TagName" 2>&1 | Out-Null
    } else {
        Write-Host "Aborted" -ForegroundColor Yellow
        exit 1
    }
}

# Create annotated tag
Write-Host "Creating tag $TagName..." -ForegroundColor Cyan
try {
    git tag -a "$TagName" -m "$ReleaseName"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create tag"
    }
} catch {
    Write-Host "Error: Failed to create tag" -ForegroundColor Red
    exit 1
}

# Push tag to remote
Write-Host "Pushing tag to remote..." -ForegroundColor Cyan
try {
    git push origin "$TagName"
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to push tag"
    }
} catch {
    Write-Host "Error: Failed to push tag" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "Tag $TagName created and pushed successfully!" -ForegroundColor Green
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Go to https://github.com/adiprayitno160-svg/ujian/releases/new"
Write-Host "2. Select tag: $TagName"
Write-Host "3. Release title: $ReleaseName"
Write-Host "4. Description: $ReleaseBody"
Write-Host "5. Click 'Publish release'"
Write-Host ""
Write-Host "Or use GitHub CLI (if installed):" -ForegroundColor Cyan
Write-Host "gh release create $TagName --title `"$ReleaseName`" --notes `"$ReleaseBody`""

