# Scan the repo for deploy blockers and remind production URL settings before upload to hosting root.
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent

Write-Host '=== JT-TEMPLE-BLR deploy link check ===' -ForegroundColor Cyan
Write-Host "Repo: $root"
Write-Host ''

$required = @(
    'index.html',
    '.htaccess',
    'home\.htaccess',
    'site-links.js',
    'gallery-dynamic.js',
    'hero-dynamic.js',
    'identity-dynamic.js',
    'home\api\gallery-images.php',
    'home\api\hero-media.php',
    'home\api\temple-identity.php',
    'home\api\favicon.php',
    'Booking\index.php',
    'Booking\install.php',
    'Booking\.env.example',
    'crm\index.php',
    'crm\install.php',
    'crm\install_db_probe.php',
    'crm\env.example',
    'crm\manage_data\index.php',
    'Booking\donation\index.php',
    'Streaming\index.php',
    'maintenance\index.html'
)
$missing = @()
foreach ($rel in $required) {
    if (-not (Test-Path (Join-Path $root $rel))) {
        $missing += $rel
    }
}
if ($missing.Count -gt 0) {
    Write-Host 'MISSING required paths:' -ForegroundColor Red
    $missing | ForEach-Object { Write-Host "  - $_" }
    exit 1
}
Write-Host '[OK] Required files present' -ForegroundColor Green

# Hardcoded local dev paths in web-facing trees (exclude tests, bin, docs, README).
$scanRoots = @('index.html', 'home', 'assets', 'site-links.js', 'gallery-dynamic.js', 'hero-dynamic.js', 'identity-dynamic.js', 'Booking', 'crm', 'Streaming')
$badPattern = 'jt_blr/jt-temple-blr|localhost/jt_blr'
$hits = @()
foreach ($scan in $scanRoots) {
    $path = Join-Path $root $scan
    if (-not (Test-Path $path)) { continue }
    if (Test-Path $path -PathType Leaf) {
        $text = Get-Content -LiteralPath $path -Raw -ErrorAction SilentlyContinue
        if ($text -and $text -match $badPattern) { $hits += $scan }
        continue
    }
    Get-ChildItem -LiteralPath $path -Recurse -File -ErrorAction SilentlyContinue |
        Where-Object {
            $_.FullName -notmatch '\\(tests?|bin|docs|node_modules|storage\\logs)\\' -and
            $_.Extension -match '\.(html?|js|css|php|json|xml|txt|htaccess)$'
        } |
        ForEach-Object {
            $text = Get-Content -LiteralPath $_.FullName -Raw -ErrorAction SilentlyContinue
            if ($text -and $text -match $badPattern) {
                $hits += $_.FullName.Substring($root.Length).TrimStart('\', '/')
            }
        }
}
$hits = $hits | Sort-Object -Unique
if ($hits.Count -gt 0) {
    Write-Host 'WARN: Hardcoded local paths found (fix or exclude from upload):' -ForegroundColor Yellow
    $hits | ForEach-Object { Write-Host "  - $_" }
} else {
    Write-Host '[OK] No jt_blr/localhost hardcodes in web-facing code' -ForegroundColor Green
}

Write-Host ''
Write-Host '=== Upload layout (hosting document root) ===' -ForegroundColor Cyan
Write-Host 'Upload the CONTENTS of jt-temple-blr into public_html/ (not an extra nested folder):'
Write-Host '  public_html/index.html'
Write-Host '  public_html/Booking/'
Write-Host '  public_html/crm/'
Write-Host '  public_html/Streaming/'
Write-Host '  public_html/home/'
Write-Host '  public_html/assets/'
Write-Host '  public_html/crm/manage_data/   (Admin data tool — upload with crm/)'
Write-Host '  public_html/Booking/donation/'
Write-Host ''

Write-Host '=== Production .env URLs (required) ===' -ForegroundColor Cyan
Write-Host '  Booking/.env     APP_URL=https://sjtbengaluru.org/Booking'
Write-Host '  crm/.env         APP_URL=https://sjtbengaluru.org/crm'
Write-Host '  crm/.env         BOOKING_APP_URL=https://sjtbengaluru.org/Booking'
Write-Host '  Booking/.env     ERP_APP_URL=https://sjtbengaluru.org/crm  (if staff SSO)'
Write-Host ''

Write-Host '=== Public home (auto base path) ===' -ForegroundColor Cyan
Write-Host '  index.html sets SJT_PUBLIC_BASE from the browser URL — works at domain root and in subfolders.'
Write-Host '  site-links.js rewrites /#section → #section so About/Services/Events never jump to the wrong host.'
Write-Host ''

Write-Host 'Run: .\scripts\sync-home-to-root.ps1  then upload. Post-deploy smoke: docs\DEPLOY-PRODUCTION.md' -ForegroundColor Green
exit 0