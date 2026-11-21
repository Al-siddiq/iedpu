#Requires -Version 5.1
<#
.SYNOPSIS
    Installs the exact, locally hosted Vue 3 production global build.
.DESCRIPTION
    Run once on a connected development machine, review the result and commit
    the generated vendor file. Runtime HTML never loads Vue from a CDN.
    Bootstrap 5.3.9 CSS and JS are already committed to this repository.
#>
[CmdletBinding()]
param(
    [string]$VueVersion = '3.5.24'
)

$ErrorActionPreference = 'Stop'
if ($VueVersion -ne '3.5.24') {
    throw 'P1-WP03 locks Vue to 3.5.24. Update the approved dependency record before changing this version.'
}

$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$target = Join-Path $root 'public\assets\js\vendor\vue.global.prod.js'
$folder = Split-Path -Parent $target
if (-not (Test-Path -LiteralPath $folder)) {
    New-Item -ItemType Directory -Path $folder -Force | Out-Null
}

$urls = @(
    "https://cdn.jsdelivr.net/npm/vue@$VueVersion/dist/vue.global.prod.js",
    "https://unpkg.com/vue@$VueVersion/dist/vue.global.prod.js"
)

$temp = "$target.download"
$downloaded = $false
try {
    foreach ($url in $urls) {
        try {
            Write-Host "Fetching Vue $VueVersion from $url"
            Invoke-WebRequest -Uri $url -OutFile $temp -UseBasicParsing -TimeoutSec 40
            $content = [System.IO.File]::ReadAllText($temp, [System.Text.Encoding]::UTF8)
            if ($content.Length -lt 80000) {
                throw 'Downloaded asset is unexpectedly small.'
            }
            if ($content -notmatch [regex]::Escape($VueVersion) -or
                $content -notmatch 'Vue\s*=\s*function|var Vue=') {
                throw 'Downloaded file does not identify the pinned Vue production global build.'
            }
            $downloaded = $true
            break
        }
        catch {
            Write-Warning "Source failed: $($_.Exception.Message)"
        }
    }

    if (-not $downloaded) {
        throw 'Unable to fetch the approved Vue release. Check internet access and retry; do not substitute a CDN runtime script.'
    }

    Move-Item -LiteralPath $temp -Destination $target -Force
    $hash = (Get-FileHash -LiteralPath $target -Algorithm SHA256).Hash
    Write-Host ''
    Write-Host '[PASS] Vue 3 production global build installed locally.'
    Write-Host "File: $target"
    Write-Host "Version: $VueVersion"
    Write-Host "SHA256: $hash"
    Write-Host ''
    Write-Host 'Review the vendor file, then commit it to the P1 branch:'
    Write-Host '  git add public/assets/js/vendor/vue.global.prod.js'
    Write-Host '  git commit -m "build(p1-wp03): lock local Vue 3.5.24 production asset"'
    Write-Host '  git push origin feature/p1-wp01-environment-bootstrap'
}
finally {
    if (Test-Path -LiteralPath $temp) {
        Remove-Item -LiteralPath $temp -Force
    }
}
