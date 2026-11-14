# One-time / repeat: organize home-page images under home/photos/ and home/gallery/
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$homeDir = Join-Path $root 'home'

$dirs = @(
    'photos/branding',
    'photos/hero',
    'photos/temple',
    'photos/events',
    'gallery',
    'api'
)
foreach ($d in $dirs) {
    New-Item -ItemType Directory -Force -Path (Join-Path $homeDir $d) | Out-Null
}

function Move-IfExists($src, $destDir) {
    if (-not (Test-Path $src)) { return }
    $name = Split-Path $src -Leaf
    $dest = Join-Path $destDir $name
    if ((Resolve-Path $src).Path -eq (Resolve-Path $dest -ErrorAction SilentlyContinue).Path) { return }
    Move-Item -Path $src -Destination $dest -Force
}

$photos = Join-Path $homeDir 'photos'
Move-IfExists (Join-Path $homeDir 'logo.jpg') (Join-Path $photos 'branding')
Move-IfExists (Join-Path $root 'logo.jpg') (Join-Path $photos 'branding')

foreach ($name in @('wallpaper.jpg', 'wallpaper1.jpg')) {
    Move-IfExists (Join-Path $homeDir $name) (Join-Path $photos 'hero')
    Move-IfExists (Join-Path $root $name) (Join-Path $photos 'hero')
}

foreach ($name in @('nilachakra.jpg', 'nilachakra1.jpg', 'nilachakra2.jpg')) {
    Move-IfExists (Join-Path $homeDir $name) (Join-Path $photos 'temple')
    Move-IfExists (Join-Path $root $name) (Join-Path $photos 'temple')
}

$events = @(
    'diwali.jpg', 'holi.jpg', 'dasahara.jpg', 'janmastami.jpg', 'rathayatra.jpg',
    'raksha-bandhan.jpg', 'makara-shankranti.jpg', 'rasa-purnima.webp'
)
$eventsDir = Join-Path $photos 'events'
foreach ($name in $events) {
    Move-IfExists (Join-Path $homeDir $name) $eventsDir
    Move-IfExists (Join-Path $root $name) $eventsDir
    $nested = Join-Path $homeDir "home\$name"
    Move-IfExists $nested $eventsDir
}

# Legacy duplicate home/home/* → photos
$legacy = Join-Path $homeDir 'home'
if (Test-Path $legacy) {
    Get-ChildItem $legacy -File | ForEach-Object {
        $dest = $eventsDir
        if ($_.Name -match '^wallpaper') { $dest = Join-Path $photos 'hero' }
        elseif ($_.Name -match '^nilachakra') { $dest = Join-Path $photos 'temple' }
        Move-IfExists $_.FullName $dest
    }
    if (-not (Get-ChildItem $legacy -Recurse -ErrorAction SilentlyContinue)) {
        Remove-Item $legacy -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$gallery = Join-Path $homeDir 'gallery'
if (Test-Path (Join-Path $root 'gallery')) {
    Get-ChildItem (Join-Path $root 'gallery') -File | ForEach-Object {
        Move-IfExists $_.FullName $gallery
    }
}

Write-Host 'Home photos organized under home/photos and home/gallery.'