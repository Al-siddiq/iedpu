#Requires -Version 5.1
<#
Generate all raster browser/PWA assets from the official, committed IEDPU.png.
Windows PowerShell and System.Drawing only; no network, npm or image redesign.
#>
[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$public = Join-Path $root 'public'
$source = Join-Path $public 'assets\IEDPU.png'
$out = Join-Path $public 'assets\icons'
if (!(Test-Path -LiteralPath $source -PathType Leaf)) { throw "Official logo missing: $source" }
New-Item -ItemType Directory -Path $out -Force | Out-Null
$logo = [System.Drawing.Image]::FromFile($source)
try {
    if ($logo.Width -lt 1 -or $logo.Height -lt 1) { throw 'Invalid logo dimensions.' }
    Write-Host "Official source: $($logo.Width)x$($logo.Height)"
    function Save-Logo([string]$Name, [int]$Size, [double]$Fraction) {
        $bitmap = [System.Drawing.Bitmap]::new($Size, $Size, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
        try {
            $graphics.Clear([System.Drawing.Color]::White)
            $graphics.CompositingQuality = [System.Drawing.Drawing2D.CompositingQuality]::HighQuality
            $graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
            $graphics.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
            $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
            $scale = [Math]::Min(($Size * $Fraction) / $logo.Width, ($Size * $Fraction) / $logo.Height)
            $w = [single]($logo.Width * $scale)
            $h = [single]($logo.Height * $scale)
            $rect = [System.Drawing.RectangleF]::new([single](($Size-$w)/2), [single](($Size-$h)/2), $w, $h)
            $graphics.DrawImage($logo, $rect)
            $target = Join-Path $out $Name
            $bitmap.Save($target, [System.Drawing.Imaging.ImageFormat]::Png)
            Write-Host "[PASS] $Name ($Size x $Size)"
        } finally { $graphics.Dispose(); $bitmap.Dispose() }
    }
    Save-Logo 'favicon-16.png' 16 0.94
    Save-Logo 'favicon-32.png' 32 0.94
    Save-Logo 'favicon-48.png' 48 0.94
    Save-Logo 'apple-touch-icon.png' 180 0.86
    Save-Logo 'portal-192.png' 192 0.90
    Save-Logo 'portal-512.png' 512 0.90
    # The square bounding box fits completely in the central 80% diameter safe circle.
    Save-Logo 'portal-maskable-512.png' 512 0.55
    # Standard ICO container with three embedded PNG images.
    $sizes = @(16,32,48)
    $payloads = @()
    foreach ($size in $sizes) { $payloads += ,([System.IO.File]::ReadAllBytes((Join-Path $out "favicon-$size.png"))) }
    $stream = [System.IO.MemoryStream]::new()
    $writer = [System.IO.BinaryWriter]::new($stream)
    try {
        $writer.Write([uint16]0); $writer.Write([uint16]1); $writer.Write([uint16]$sizes.Length)
        $offset = 6 + 16 * $sizes.Length
        for ($i=0; $i -lt $sizes.Length; $i++) {
            $writer.Write([byte]$sizes[$i]); $writer.Write([byte]$sizes[$i])
            $writer.Write([byte]0); $writer.Write([byte]0)
            $writer.Write([uint16]1); $writer.Write([uint16]32)
            $writer.Write([uint32]$payloads[$i].Length); $writer.Write([uint32]$offset)
            $offset += $payloads[$i].Length
        }
        foreach ($bytes in $payloads) { $writer.Write([byte[]]$bytes) }
        $writer.Flush()
        [System.IO.File]::WriteAllBytes((Join-Path $public 'favicon.ico'), $stream.ToArray())
    } finally { $writer.Dispose(); $stream.Dispose() }
    Write-Host '[PASS] favicon.ico (16/32/48)'
    Write-Host 'Review the eight generated assets and commit them before P1 acceptance.'
} finally { $logo.Dispose() }
