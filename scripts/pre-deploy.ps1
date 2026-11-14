# Pre-deploy: sync public home to repo root and print a short checklist.
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent

& (Join-Path $PSScriptRoot 'sync-home-to-root.ps1')
& (Join-Path $PSScriptRoot 'verify-deploy-links.ps1')

$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if ($php) {
    & $php (Join-Path $root 'crm\bin\verify_env_sync.php')
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
} else {
    Write-Host 'WARN: php not in PATH — run crm/bin/verify_env_sync.php manually' -ForegroundColor Yellow
}

Write-Host ''
Write-Host '=== Pre-deploy checklist ===' -ForegroundColor Cyan
Write-Host '  [ ] Booking/.env and crm/.env configured on server (not committed)'
Write-Host '  [ ] CRM Settings -> Identity (logo, phones, address)'
Write-Host '  [ ] Streaming/.streaming live URL if using Live Darshan'
Write-Host '  [ ] home/photos/hero and home/gallery media uploaded'
Write-Host '  [ ] Delete install.php + remove INSTALL_TOKEN after migrate'
Write-Host '  [ ] php crm/bin/preflight_deploy.php && php crm/bin/prod_url_smoke.php'
Write-Host '  [ ] Smoke test: /, /gallery, /Booking/book, /Booking/donation/, /crm/, /crm/manage_data/, /Streaming/'
Write-Host ''
Write-Host "Deploy guide: $root\docs\DEPLOY-PRODUCTION.md" -ForegroundColor Green