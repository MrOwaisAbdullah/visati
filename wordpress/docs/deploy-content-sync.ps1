<#
.SYNOPSIS
  Rebuild deploy zips + a SAFE, table-scoped DB export for syncing Elementor
  page/menu edits from Local to Hostinger (visati.visati.ae) without touching
  applications, inquiries, documents, payments, or Settings.
.DESCRIPTION
  Only exports wp_posts / wp_postmeta / wp_terms / wp_term_taxonomy /
  wp_term_relationships / wp_termmeta - the tables that hold Elementor pages,
  CPT content (visa types/FAQs/testimonials/jobs), and nav menus. Everything
  else on the live site (wp_visati_applications, wp_visati_inquiries,
  wp_visati_documents, wp_visati_payments, wp_visati_status_history,
  wp_options, wp_users) is never touched by this export/import.
.USAGE
  .\deploy-content-sync.ps1
#>
$ErrorActionPreference = "Stop"

$LOCAL_WP   = "C:\Users\K TECH\Local Sites\visati\app\public"
$THEME_DIR  = "$LOCAL_WP\wp-content\themes\visati"
$PLUGIN_DIR = "$LOCAL_WP\wp-content\plugins\visati-core"
$BUILD_DIR  = "D:\GIAIC\Visati\wordpress\docs"

# The correct mysqldump - matches the site's actual DB engine (MySQL 8.4).
# Do NOT use the mariadb-10.6.23 bundle's mysqldump - it lacks the
# caching_sha2_password auth plugin MySQL 8.4 requires and will fail with
# "Plugin caching_sha2_password could not be loaded".
$mysqldump = "C:\Users\K TECH\AppData\Roaming\Local\lightning-services\mysql-8.4.0\bin\win64\bin\mysqldump.exe"

function Write-Step($msg) { Write-Host "`n>> $msg" -ForegroundColor Cyan }
function Write-OK($msg)   { Write-Host "   OK: $msg" -ForegroundColor Green }

# 1. Rebuild theme + plugin zips (captures every current local change)
Write-Step "Zipping theme..."
$themeZip = "$BUILD_DIR\deploy-theme.zip"
if (Test-Path $themeZip) { Remove-Item $themeZip -Force }
Compress-Archive -Path "$THEME_DIR\*" -DestinationPath $themeZip -Force
Write-OK "Theme: $([math]::Round((Get-Item $themeZip).Length / 1KB, 1)) KB"

Write-Step "Zipping plugin..."
$pluginZip = "$BUILD_DIR\deploy-plugin.zip"
if (Test-Path $pluginZip) { Remove-Item $pluginZip -Force }
Compress-Archive -Path "$PLUGIN_DIR\*" -DestinationPath $pluginZip -Force
Write-OK "Plugin: $([math]::Round((Get-Item $pluginZip).Length / 1KB, 1)) KB"

# 2. Scoped DB export - pages/menus/CPT content ONLY
Write-Step "Exporting content tables only (posts/postmeta/terms/menus)..."
$wpConfig = Get-Content "$LOCAL_WP\wp-config.php" -Raw
$null = $wpConfig -match "define\(\s*'DB_PASSWORD'\s*,\s*'([^']*)'"
$dbPass = $Matches[1]

$dbOut = "$BUILD_DIR\deploy-content-sync.sql"
if (Test-Path $dbOut) { Remove-Item $dbOut -Force }

# mysqldump prints a harmless "using a password on the command line" warning to
# stderr. With $ErrorActionPreference = "Stop" (set at the top of this script),
# PowerShell treats ANY stderr line from a native exe as a terminating error and
# kills the process mid-write, leaving a 0-byte .sql file. Relax to "Continue"
# just for this one call so the export actually finishes.
$prevEAP = $ErrorActionPreference
$ErrorActionPreference = "Continue"
& $mysqldump --host=127.0.0.1 --port=10005 --user=root --password=$dbPass `
    --add-drop-table --single-transaction `
    --result-file="$dbOut" `
    local wp_posts wp_postmeta wp_terms wp_term_taxonomy wp_term_relationships wp_termmeta 2>&1 |
    ForEach-Object { if ($_ -notmatch "Using a password on the command line") { Write-Host $_ } }
$ErrorActionPreference = $prevEAP

if ((Test-Path $dbOut) -and (Get-Item $dbOut).Length -gt 0) {
    Write-OK "Content export: $([math]::Round((Get-Item $dbOut).Length / 1KB, 1)) KB (6 tables only - no applications/inquiries/settings/users)"
} else {
    Write-Host "   FAILED - check DB credentials / mysqldump path above" -ForegroundColor Red
}

Write-Host "`nFiles ready at: $BUILD_DIR"
Write-Host "  deploy-theme.zip"
Write-Host "  deploy-plugin.zip"
Write-Host "  deploy-content-sync.sql"
Write-Host "`nNext: upload all three, then run the SSH block from the chat."
