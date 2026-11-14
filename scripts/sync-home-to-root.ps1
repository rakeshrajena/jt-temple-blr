# Copy static home build artifacts from home/ to repo root (for deploy at public_html root).
# Photos stay at home/photos/ and home/gallery/ (served as-is); this script syncs HTML/JS/CSS only.
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$homeDir = Join-Path $root 'home'
if (-not (Test-Path $homeDir)) {
    Write-Error "Missing home/ folder"
}

$srcAssets = Join-Path $homeDir 'assets'
$dstAssets = Join-Path $root 'assets'
if (Test-Path $srcAssets) {
    New-Item -ItemType Directory -Force -Path $dstAssets | Out-Null
    Copy-Item (Join-Path $srcAssets '*') -Destination $dstAssets -Force
}
foreach ($name in @('index.html', 'site-links.js', 'site-links.css', 'gallery-dynamic.js', 'hero-dynamic.js', 'hero-dynamic.css', 'robots.txt', 'sitemap.xml', 'favicon.ico', 'placeholder.svg')) {
    $src = Join-Path $homeDir $name
    if (Test-Path $src) {
        Copy-Item $src -Destination $root -Force
    }
}

$brandingLogo = Join-Path $homeDir 'photos\branding\logo.jpg'
if (Test-Path $brandingLogo) {
    Copy-Item $brandingLogo -Destination (Join-Path $root 'logo.jpg') -Force
}

# Ensure API + gallery folders exist at deploy root (same paths as in repo).
foreach ($rel in @('photos', 'gallery', 'api')) {
    $src = Join-Path $homeDir $rel
    $dst = Join-Path (Join-Path $root 'home') $rel
    if (-not (Test-Path $src)) { continue }
    $rootHome = Join-Path $root 'home'
    if ((Resolve-Path $src).Path -eq (Resolve-Path $dst -ErrorAction SilentlyContinue).Path) {
        continue
    }
    New-Item -ItemType Directory -Force -Path $dst | Out-Null
    Copy-Item -Path (Join-Path $src '*') -Destination $dst -Recurse -Force
}

Write-Host "Synced home/ HTML, assets, and scripts to repo root."