# Apache Hardening Script for WebServer
# Creates backups before every change, verifies service after restart
# Run as Administrator on WebServer

$ErrorActionPreference = "Continue"
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$apacheConf = "C:\xampp\apache\conf\httpd.conf"
$infoConf = "C:\xampp\apache\conf\extra\httpd-info.conf"
$sslConf = "C:\xampp\apache\conf\extra\httpd-ssl.conf"
$htdocs = "C:\xampp\htdocs"

Write-Host "=== Apache Hardening - $timestamp ===" -ForegroundColor Cyan

# --- BACKUP ---
Write-Host "`n[1/8] Creating backups..." -ForegroundColor Yellow
Copy-Item $apacheConf "$apacheConf.bak.$timestamp"
Copy-Item $infoConf "$infoConf.bak.$timestamp"
Copy-Item $sslConf "$sslConf.bak.$timestamp"
if (Test-Path "$htdocs\pages\experience.php") {
    Copy-Item "$htdocs\pages\experience.php" "$htdocs\pages\experience.php.bak.$timestamp"
}
Write-Host "  Backups created with timestamp $timestamp"

# --- 1. DISABLE DIRECTORY INDEXING ---
Write-Host "`n[2/8] Disabling directory indexing..." -ForegroundColor Yellow
$conf = Get-Content $apacheConf -Raw
$conf = $conf -replace 'Options Indexes FollowSymLinks Includes ExecCGI', 'Options FollowSymLinks Includes ExecCGI'
Set-Content $apacheConf $conf -NoNewline
Write-Host "  Removed 'Indexes' from Options directive"

# --- 2. ADD SECURITY DIRECTIVES TO HTTPD.CONF ---
Write-Host "`n[3/8] Adding security directives..." -ForegroundColor Yellow
$securityBlock = @"

# === SECURITY HARDENING (Applied $timestamp) ===
# Disable HTTP TRACE method (prevents XST attacks)
TraceEnable Off

# Hide server version info
ServerTokens Prod
ServerSignature Off

# Security headers
<IfModule mod_headers.c>
    # Prevent clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    # Prevent MIME-type sniffing
    Header always set X-Content-Type-Options "nosniff"
    # XSS protection
    Header always set X-XSS-Protection "1; mode=block"
    # Referrer policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    # Remove PHP version header
    Header unset X-Powered-By
    Header always unset X-Powered-By
    # Content Security Policy (permissive for app compatibility)
    Header always set Content-Security-Policy "default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data:; font-src 'self' https://fonts.gstatic.com"
</IfModule>

# Secure session cookies
<IfModule mod_php.c>
    php_value session.cookie_httponly 1
    php_value session.cookie_secure 1
    php_value session.cookie_samesite "Lax"
    php_value expose_php Off
</IfModule>
# === END SECURITY HARDENING ===
"@

# Append to httpd.conf
Add-Content $apacheConf $securityBlock
Write-Host "  Added TraceEnable Off, ServerTokens Prod, security headers, cookie hardening"

# --- 3. RESTRICT SERVER-STATUS AND SERVER-INFO ---
Write-Host "`n[4/8] Restricting server-status and server-info to localhost..." -ForegroundColor Yellow
$infoContent = Get-Content $infoConf -Raw

# Replace the server-status block to restrict to localhost only
$infoContent = $infoContent -replace '(?s)<Location /server-status>.*?</Location>', @"
<Location /server-status>
    SetHandler server-status
    Require local
</Location>
"@

# Replace the server-info block to restrict to localhost only
$infoContent = $infoContent -replace '(?s)<Location /server-info>.*?</Location>', @"
<Location /server-info>
    SetHandler server-info
    Require local
</Location>
"@

Set-Content $infoConf $infoContent -NoNewline
Write-Host "  server-status and server-info now require local access only"

# --- 4. RESTRICT PHPMYADMIN ---
Write-Host "`n[5/8] Restricting phpMyAdmin to localhost..." -ForegroundColor Yellow
$pmaHtaccess = "$htdocs\phpmyadmin\.htaccess"
$pmaBlock = @"
# Restrict phpMyAdmin to localhost only
Require local
"@
Set-Content $pmaHtaccess $pmaBlock
Write-Host "  Created .htaccess in phpMyAdmin directory (Require local)"

# Also add an alias restriction in httpd.conf
$pmaRestriction = @"

# Restrict phpMyAdmin access
<Directory "C:/xampp/htdocs/phpmyadmin">
    Require local
</Directory>
"@
Add-Content $apacheConf $pmaRestriction
Write-Host "  Added Directory restriction for phpMyAdmin in httpd.conf"

# --- 5. FIX XSS IN EXPERIENCE.PHP ---
Write-Host "`n[6/8] Fixing XSS in experience.php..." -ForegroundColor Yellow
$expFile = "$htdocs\pages\experience.php"
if (Test-Path $expFile) {
    $expContent = Get-Content $expFile -Raw
    # The id parameter needs to be cast to integer since it's a database ID
    # Replace $_GET['id'] with (int)$_GET['id'] or intval($_GET['id'])
    $expContent = $expContent -replace "\`\$_GET\['id'\]", "intval(`$_GET['id'])"
    $expContent = $expContent -replace '\$_GET\["id"\]', 'intval($_GET["id"])'
    Set-Content $expFile $expContent -NoNewline
    Write-Host "  Wrapped id parameter with intval() to prevent XSS and injection"
} else {
    Write-Host "  WARNING: experience.php not found at expected path"
}

# --- 6. REMOVE .DS_STORE AND DEFAULT FILES ---
Write-Host "`n[7/8] Removing .DS_Store and default files..." -ForegroundColor Yellow
$filesToRemove = @(
    "$htdocs\.DS_Store",
    "$htdocs\icons\README"
)
foreach ($f in $filesToRemove) {
    if (Test-Path $f) {
        Remove-Item $f -Force
        Write-Host "  Removed: $f"
    } else {
        Write-Host "  Not found (already clean): $f"
    }
}

# --- 7. RESTART APACHE AND VERIFY ---
Write-Host "`n[8/8] Restarting Apache..." -ForegroundColor Yellow
$apacheSvc = Get-Service -Name "Apache*" -ErrorAction SilentlyContinue
if ($apacheSvc) {
    Restart-Service $apacheSvc.Name -Force
    Start-Sleep -Seconds 3
    $status = (Get-Service $apacheSvc.Name).Status
    Write-Host "  Apache service status: $status"
} else {
    # Try XAMPP method
    & "C:\xampp\apache\bin\httpd.exe" -k restart 2>&1
    Start-Sleep -Seconds 3
    $proc = Get-Process httpd -ErrorAction SilentlyContinue
    if ($proc) {
        Write-Host "  Apache process running (PID: $($proc[0].Id))"
    } else {
        Write-Host "  WARNING: Apache may not have restarted. Check error log."
        Write-Host "  Checking syntax: "
        & "C:\xampp\apache\bin\httpd.exe" -t 2>&1
    }
}

# Quick verification
Write-Host "`n=== Verification ===" -ForegroundColor Cyan
try {
    $response = Invoke-WebRequest -Uri "http://localhost/" -UseBasicParsing -TimeoutSec 5
    Write-Host "  HTTP Status: $($response.StatusCode)"
    Write-Host "  Server header: $($response.Headers['Server'])"
    $xpb = $response.Headers['X-Powered-By']
    Write-Host "  X-Powered-By: $(if ($xpb) { $xpb } else { 'HIDDEN (good)' })"
    $xfo = $response.Headers['X-Frame-Options']
    Write-Host "  X-Frame-Options: $(if ($xfo) { $xfo } else { 'NOT SET (check mod_headers)' })"
} catch {
    Write-Host "  WARNING: Could not reach localhost - $($_.Exception.Message)"
}

Write-Host "`n=== Hardening Complete ===" -ForegroundColor Green
Write-Host "Backup files saved with timestamp: $timestamp"
Write-Host "To rollback: copy .bak.$timestamp files back to originals and restart Apache"
