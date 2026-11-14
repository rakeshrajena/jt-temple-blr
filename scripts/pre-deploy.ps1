# Pre-deploy: sync public home to repo root and print a short checklist.
$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent

& (Join-Path $PSScriptRoot 'sync-home-to-root.ps1')
& (Join-Path $PSScriptRoot 'verify-deploy-links.ps1')

Write-Host ''
Write-Host '=== Pre-deploy checklist ===' -ForegroundColor Cyan
Write-Host '  [ ] Booking/.env and crm/.env configured on server (not committed)'
Write-Host '  [ ] CRM Settings -> Identity (logo, phones, address)'
Write-Host '  [ ] Streaming/.streaming live URL if using Live Darshan'
Write-Host '  [ ] home/photos/hero and home/gallery media uploaded'
Write-Host '  [ ] Delete install.php + remove INSTALL_TOKEN after migrate'
Write-Host '  [ ] Smoke test: /, /gallery, /Booking/book, /Booking/donation/, /Streaming/'
Write-Host ''
Write-Host "Deploy guide: $root\docs\DEPLOY-PRODUCTION.md" -ForegroundColor Green