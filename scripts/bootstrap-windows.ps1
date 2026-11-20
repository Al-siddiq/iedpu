[CmdletBinding()]
param(
    [string]$PhpPath = ""
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "IEDPU P1-WP01 bootstrap" -ForegroundColor Cyan
Write-Host "Project: $ProjectRoot"

if ([string]::IsNullOrWhiteSpace($PhpPath)) {
    $PhpCommand = Get-Command php -ErrorAction SilentlyContinue
    if ($PhpCommand) {
        $PhpPath = $PhpCommand.Source
    } elseif (Test-Path "C:\xampp\php\php.exe") {
        $PhpPath = "C:\xampp\php\php.exe"
    } else {
        throw "PHP CLI was not found. Add your XAMPP PHP directory to PATH or pass -PhpPath."
    }
}

if (-not (Test-Path $PhpPath)) {
    throw "PHP executable not found: $PhpPath"
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.development.example" ".env"
    Write-Warning "Created .env from the development example. Review database name, username and password before continuing."
}

Write-Host "`n1/6 - Checking PHP and required extensions"
& $PhpPath "scripts/check-environment.php" "--preflight"
if ($LASTEXITCODE -ne 0) {
    throw "PHP preflight failed. Correct the reported PHP version/extensions before continuing."
}

Write-Host "`n2/6 - Checking Composer availability"
$ComposerCommand = Get-Command composer -ErrorAction SilentlyContinue
if (-not $ComposerCommand) {
    throw "Composer was not found. Install Composer for Windows and configure it to use this XAMPP PHP executable: $PhpPath"
}
composer --version
if ($LASTEXITCODE -ne 0) {
    throw "Composer could not start."
}

Write-Host "`n3/6 - Validating the manifest before lock refresh"
composer validate --no-check-publish --no-check-lock
if ($LASTEXITCODE -ne 0) {
    throw "composer.json validation failed."
}

Write-Host "`n4/6 - Resolving and installing the approved locked dependencies"
composer update --with-all-dependencies --no-interaction --no-scripts
if ($LASTEXITCODE -ne 0) {
    throw "Composer dependency resolution failed. Do not deploy until this is resolved."
}

Write-Host "`n5/6 - Verifying installed platform requirements"
composer validate --strict
if ($LASTEXITCODE -ne 0) {
    throw "Composer manifest/lock validation failed."
}
composer check-platform-reqs
if ($LASTEXITCODE -ne 0) {
    throw "The active PHP runtime does not satisfy the installed packages."
}

Write-Host "`n6/7 - Verifying CodeIgniter and the full local environment"
& $PhpPath "scripts/check-environment.php"
if ($LASTEXITCODE -ne 0) {
    throw "The full environment readiness check failed."
}
& $PhpPath "spark" "--version"
if ($LASTEXITCODE -ne 0) {
    throw "CodeIgniter Spark did not start."
}
& $PhpPath "spark" "routes"
if ($LASTEXITCODE -ne 0) {
    throw "CodeIgniter route listing failed."
}

Write-Host "`n7/7 - Verifying the configured database connection (read-only)"
& $PhpPath "scripts/check-database-connection.php"
if ($LASTEXITCODE -ne 0) {
    throw "The configured database connection failed. Check the local .env and create the empty iedpu_dev database."
}

Write-Host "`nWP01 local dependency bootstrap completed. Review git status and commit the regenerated composer.lock; vendor/ must remain untracked." -ForegroundColor Green
