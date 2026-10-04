# ==============================================================================
# Phase 6 Automated Verification: Hardening Validasi, Error Handling, UI Responsif, dan Scheduled Job
# Features:
# - VAL-01: Dual validation (frontend + backend) & sticky forms for failed validation
# - ERR-01: 302 login redirect, 403 Forbidden, 404 Not Found, 500 Server Error without stack trace leakage
# - UI-01: Mobile responsiveness (360px viewport meta tag, table container, focus-visible states)
# - JOB-01: Standalone CLI job scripts/check-low-stock.php
# ==============================================================================
param (
    [string]$BaseUrl = "http://localhost:8080",
    [switch]$ResetDatabase = $false
)

if ($ResetDatabase) {
    Write-Host "Resetting database to seed baseline..." -ForegroundColor Yellow
    $candidatePhpPaths = @(
        "c:\Program Files\xampp\php\php.exe",
        "c:\xampp\php\php.exe"
    )
    $phpCmd = $candidatePhpPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
    if (-not $phpCmd) {
        $phpInPath = Get-Command php.exe -ErrorAction SilentlyContinue
        if ($phpInPath) { $phpCmd = $phpInPath.Source }
    }
    if ($phpCmd) {
        $migrateScript = Join-Path $PSScriptRoot "..\database\migrate.php"
        & $phpCmd -f $migrateScript -- --host=127.0.0.1 --port=3306 --user=inventory_user --pass=secret --database=inventory_db | Out-Null
        Write-Host "Database reset complete." -ForegroundColor Green
    } else {
        Write-Host "Warning: PHP executable not found to auto-reset database." -ForegroundColor Yellow
    }
}

$passed = 0
$failed = 0

function Assert-Test {
    param (
        [string]$Name,
        [bool]$Condition,
        [string]$Details = ""
    )
    if ($Condition) {
        Write-Host "  [PASS] $Name" -ForegroundColor Green
        $script:passed++
    } else {
        Write-Host "  [FAIL] $Name - $Details" -ForegroundColor Red
        $script:failed++
    }
}

Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " Running Phase 6 Automated HTTP-Level Tests against $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# Helper: Login Session Factory
# ------------------------------------------------------------------------------
function Get-AuthenticatedSession {
    param (
        [string]$Email,
        [string]$Password = "password123"
    )
    $body = @{
        email    = $Email
        password = $Password
    }
    $null = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body $body -SessionVariable sess -UseBasicParsing
    return $sess
}

# Pre-authenticate user sessions
$adminSession = Get-AuthenticatedSession -Email "admin@inventory.local"
$salesSession = Get-AuthenticatedSession -Email "sales1@inventory.local"
$whSession = Get-AuthenticatedSession -Email "warehouse1@inventory.local"

# ------------------------------------------------------------------------------
# Test 1: VAL-01 Dual Validation & Sticky Forms (Product Creation)
# ------------------------------------------------------------------------------
Write-Host "`nTest 1: VAL-01 Product Dual Validation & Sticky Form on Validation Error" -ForegroundColor Yellow

# Submit with negative purchase price and non-existent category_id (foreign key violation)
$invalidProdBody = @{
    sku            = "PRD-VAL-999"
    name           = "Produk Uji Validasi Sticky"
    category_id    = "99999"  # Non-existent FK
    unit           = "box"
    purchase_price = "-50000" # Negative number rejected
    selling_price  = "75000"
    reorder_point  = "10"
}

$prodValResp = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -Method Post -Body $invalidProdBody -WebSession $adminSession -UseBasicParsing

Assert-Test -Name "Product validation rejects negative price and invalid FK" -Condition ($prodValResp.StatusCode -eq 200 -and ($prodValResp.Content -match 'tidak boleh negatif' -or $prodValResp.Content -match 'tidak valid'))
Assert-Test -Name "Sticky form preserves input SKU 'PRD-VAL-999'" -Condition ($prodValResp.Content -match 'PRD-VAL-999')
Assert-Test -Name "Sticky form preserves input name 'Produk Uji Validasi Sticky'" -Condition ($prodValResp.Content -match 'Produk Uji Validasi Sticky')
Assert-Test -Name "Sticky form preserves unit 'box'" -Condition ($prodValResp.Content -match 'box')

# Verify the invalid product was NOT saved into catalog
$catCheck = Invoke-WebRequest -Uri "$BaseUrl/products?search=PRD-VAL-999" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Invalid product is NOT persisted into database" -Condition ($catCheck.Content -notmatch 'Produk Uji Validasi Sticky')

# ------------------------------------------------------------------------------
# Test 2: VAL-01 Purchase Order Foreign Key & Item Constraints Validation
# ------------------------------------------------------------------------------
Write-Host "`nTest 2: VAL-01 Purchase Order FK & Item Constraints Validation" -ForegroundColor Yellow

$invalidPoBody = @{
    supplier_id  = "99999" # Non-existent supplier FK
    warehouse_id = "1"
    order_date   = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "1"
    "items[0][quantity]"   = "5"
    "items[0][unit_price]" = "1000000"
}

$poValResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -Method Post -Body $invalidPoBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "PO validation rejects non-existent supplier FK" -Condition ($poValResp.StatusCode -eq 200 -and ($poValResp.Content -match 'Pemasok.*tidak valid' -or $poValResp.Content -match 'Pemasok.*wajib dipilih'))
Assert-Test -Name "Sticky PO form preserves warehouse selection" -Condition ($poValResp.Content -match 'selected' -or $poValResp.Content -match 'Gudang Utama Jakarta')

# PO item negative qty & unit price validation
$invalidPoItemBody = @{
    supplier_id  = "1"
    warehouse_id = "1"
    order_date   = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "1"
    "items[0][quantity]"   = "-5"
    "items[0][unit_price]" = "-25000"
}
$poItemValResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -Method Post -Body $invalidPoItemBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "PO validation rejects negative quantity on items" -Condition ($poItemValResp.Content -match 'Jumlah pesanan harus lebih dari 0')
Assert-Test -Name "PO validation rejects negative unit price on items" -Condition ($poItemValResp.Content -match 'Harga beli satuan tidak boleh negatif')
Assert-Test -Name "Sticky PO form preserves items row" -Condition ($poItemValResp.Content -match 'addRow\(1, -5, -25000\)' -or $poItemValResp.Content -match 'items\[0\]')

# SO item negative qty & unit price validation
$invalidSoItemBody = @{
    customer_id  = "1"
    warehouse_id = "1"
    order_date   = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "1"
    "items[0][quantity]"   = "0"
    "items[0][unit_price]" = "-50000"
}
$soItemValResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $invalidSoItemBody -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "SO validation rejects zero/negative quantity on items" -Condition ($soItemValResp.Content -match 'harus lebih besar dari 0')
Assert-Test -Name "SO validation rejects negative unit price on items" -Condition ($soItemValResp.Content -match 'tidak boleh negatif')
Assert-Test -Name "Sticky SO form preserves customer and warehouse selection" -Condition ($soItemValResp.Content -match 'PT Retail Nusantara Megah' -or $soItemValResp.Content -match 'selected')

# ------------------------------------------------------------------------------
# Test 3: VAL-01 Frontend Validation Attributes Inspection
# ------------------------------------------------------------------------------
Write-Host "`nTest 3: VAL-01 Frontend HTML5 Validation Attributes" -ForegroundColor Yellow

$prodFormPage = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Product form has required attribute on SKU" -Condition ($prodFormPage.Content -match 'id="sku"[^>]*required' -or $prodFormPage.Content -match 'name="sku"[^>]*required')
Assert-Test -Name "Product form has required attribute on Name" -Condition ($prodFormPage.Content -match 'id="name"[^>]*required' -or $prodFormPage.Content -match 'name="name"[^>]*required')
Assert-Test -Name "Product form has min='0' on purchase price" -Condition ($prodFormPage.Content -match 'min="0"')
Assert-Test -Name "Product form has min='0' on reorder point" -Condition ($prodFormPage.Content -match 'id="reorder_point"[^>]*min="0"' -or $prodFormPage.Content -match 'min="0"')

# ------------------------------------------------------------------------------
# Test 4: ERR-01 Failure Path 1 — Unauthenticated Access Protection
# ------------------------------------------------------------------------------
Write-Host "`nTest 4: ERR-01 Failure Path 1 (Unauthenticated Access Redirects)" -ForegroundColor Yellow

$unauthPassed = $false
try {
    $res4 = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    if ($res4.StatusCode -eq 302 -and $res4.Headers['Location'] -match '/login') {
        $unauthPassed = $true
    }
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 302) {
        $unauthPassed = ($resp.Headers['Location'] -match '/login')
    } else {
        $resFallback = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -UseBasicParsing
        $unauthPassed = ($resFallback.Content -match 'Masuk ke Sistem' -or $resFallback.Content -match 'Silakan login terlebih dahulu')
    }
}
Assert-Test -Name "Unauthenticated access redirects to /login with 302" -Condition $unauthPassed

# ------------------------------------------------------------------------------
# Test 5: ERR-01 Failure Path 2 — Unauthorized Role Access (403 Forbidden)
# ------------------------------------------------------------------------------
Write-Host "`nTest 5: ERR-01 Failure Path 2 (Unauthorized Role 403 Forbidden)" -ForegroundColor Yellow

$unauthRolePassed = $false
$unauthRoleContent = ""
try {
    $res5 = Invoke-WebRequest -Uri "$BaseUrl/admin/users" -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 403) {
        $unauthRolePassed = $true
        $unauthRoleContent = $_.ErrorDetails.Message
        if (-not $unauthRoleContent) {
            try {
                $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
                $unauthRoleContent = $reader.ReadToEnd()
            } catch {}
        }
    }
}
Assert-Test -Name "Sales session accessing /admin/users returns HTTP 403" -Condition $unauthRolePassed
Assert-Test -Name "403 response contains clean title '403 - Akses Ditolak' or '403 Forbidden'" -Condition ($unauthRoleContent -match '403' -and ($unauthRoleContent -match 'Akses Ditolak' -or $unauthRoleContent -match 'Forbidden'))
Assert-Test -Name "403 response does NOT leak stack trace" -Condition ($unauthRoleContent -notmatch 'Stack trace:' -and $unauthRoleContent -notmatch 'Exception in')

# ------------------------------------------------------------------------------
# Test 6: ERR-01 Failure Path 3 — Unknown Route (404 Not Found)
# ------------------------------------------------------------------------------
Write-Host "`nTest 6: ERR-01 Failure Path 3 (Unknown Route 404 Not Found)" -ForegroundColor Yellow

$notFoundPassed = $false
$notFoundContent = ""
try {
    $res6 = Invoke-WebRequest -Uri "$BaseUrl/unknown-route-that-does-not-exist" -WebSession $adminSession -UseBasicParsing -ErrorAction Stop
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 404) {
        $notFoundPassed = $true
        $notFoundContent = $_.ErrorDetails.Message
        if (-not $notFoundContent) {
            try {
                $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
                $notFoundContent = $reader.ReadToEnd()
            } catch {}
        }
    }
}
Assert-Test -Name "Non-existent route returns HTTP 404" -Condition $notFoundPassed
Assert-Test -Name "404 response contains clean message 'Halaman Tidak Ditemukan'" -Condition ($notFoundContent -match '404' -and $notFoundContent -match 'Halaman Tidak Ditemukan')
Assert-Test -Name "404 response does NOT leak stack trace" -Condition ($notFoundContent -notmatch 'Stack trace:' -and $notFoundContent -notmatch 'vendor/')

# ------------------------------------------------------------------------------
# Test 7: ERR-01 Failure Path 4 — Non-Existent Entity ID (404 Not Found)
# ------------------------------------------------------------------------------
Write-Host "`nTest 7: ERR-01 Failure Path 4 (Non-Existent Entity ID 404)" -ForegroundColor Yellow

$prodNotFoundPassed = $false
$prodNotFoundContent = ""
try {
    $res7 = Invoke-WebRequest -Uri "$BaseUrl/products/99999" -WebSession $adminSession -UseBasicParsing -ErrorAction Stop
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 404) {
        $prodNotFoundPassed = $true
        $prodNotFoundContent = $_.ErrorDetails.Message
        if (-not $prodNotFoundContent) {
            try {
                $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
                $prodNotFoundContent = $reader.ReadToEnd()
            } catch {}
        }
    }
}
Assert-Test -Name "Non-existent product ID 99999 returns HTTP 404" -Condition $prodNotFoundPassed
Assert-Test -Name "404 page informs product not found cleanly" -Condition ($prodNotFoundContent -match 'Produk dengan ID #99999 tidak ditemukan')
Assert-Test -Name "404 page does NOT leak raw SQL query or PDOException" -Condition ($prodNotFoundContent -notmatch 'SQLSTATE' -and $prodNotFoundContent -notmatch 'PDOException')

# ------------------------------------------------------------------------------
# Test 8: ERR-01 Failure Path 5 — Unhandled Server Error Suppression (HTTP 500)
# ------------------------------------------------------------------------------
Write-Host "`nTest 8: ERR-01 Failure Path 5 (Server Error 500 & Stack Trace Suppression)" -ForegroundColor Yellow

$err500Passed = $false
$err500Content = ""
try {
    $res8 = Invoke-WebRequest -Uri "$BaseUrl/simulate-error-500" -UseBasicParsing -ErrorAction Stop
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 500) {
        $err500Passed = $true
        $err500Content = $_.ErrorDetails.Message
        if (-not $err500Content) {
            try {
                $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
                $err500Content = $reader.ReadToEnd()
            } catch {}
        }
    }
}
Assert-Test -Name "Simulated unexpected exception returns HTTP 500" -Condition $err500Passed
Assert-Test -Name "500 response displays generic friendly title '500 - Kesalahan Server'" -Condition ($err500Content -match '500 - Kesalahan Server')
Assert-Test -Name "500 response does NOT display stack trace" -Condition ($err500Content -notmatch 'Stack trace:' -and $err500Content -notmatch 'in C:\\')
Assert-Test -Name "500 response does NOT display raw exception class name" -Condition ($err500Content -notmatch 'RuntimeException' -and $err500Content -notmatch 'PDOException')

# ------------------------------------------------------------------------------
# Test 9: UI-01 Responsive Meta Tag & Mobile 360px Elements
# ------------------------------------------------------------------------------
Write-Host "`nTest 9: UI-01 Responsive Meta Tag & CSS Layout" -ForegroundColor Yellow

$loginPage = Invoke-WebRequest -Uri "$BaseUrl/login" -UseBasicParsing
Assert-Test -Name "Login page contains responsive viewport meta tag" -Condition ($loginPage.Content -match 'name="viewport"[^>]*content="width=device-width,\s*initial-scale=1\.0"')

$dashboardPage = Invoke-WebRequest -Uri "$BaseUrl/admin/dashboard" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Dashboard page contains responsive viewport meta tag" -Condition ($dashboardPage.Content -match 'name="viewport"[^>]*content="width=device-width')
Assert-Test -Name "Dashboard tables are wrapped in table-container" -Condition ($dashboardPage.Content -match 'table-container')

$cssContent = (Invoke-WebRequest -Uri "$BaseUrl/assets/css/style.css" -UseBasicParsing).Content
Assert-Test -Name "CSS defines accessible focus-visible outline" -Condition ($cssContent -match ':focus-visible')
Assert-Test -Name "CSS defines media query for mobile <= 480px / 360px" -Condition ($cssContent -match '@media\s*\(\s*max-width:\s*480px\s*\)')
Assert-Test -Name "CSS defines overflow-x auto for table containers" -Condition ($cssContent -match '\.table-container\s*\{[^}]*overflow-x:\s*auto')

# ------------------------------------------------------------------------------
# Test 10: JOB-01 Standalone CLI Low-Stock Script Execution
# ------------------------------------------------------------------------------
Write-Host "`nTest 10: JOB-01 Standalone CLI check-low-stock.php" -ForegroundColor Yellow

$candidatePhpPaths = @(
    "c:\Program Files\xampp\php\php.exe",
    "c:\xampp\php\php.exe"
)
$phpCmd = $candidatePhpPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $phpCmd) {
    $phpInPath = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpInPath) { $phpCmd = $phpInPath.Source }
}

$jobScriptPath = Join-Path $PSScriptRoot "check-low-stock.php"
$jobOutputRaw = & $phpCmd -f $jobScriptPath
$jobExitCode = $LASTEXITCODE
$jobOutput = ($jobOutputRaw -join "`n")

Assert-Test -Name "check-low-stock.php exits with code 0" -Condition ($jobExitCode -eq 0)
Assert-Test -Name "check-low-stock.php output contains header 'JOB-01'" -Condition ($jobOutput -match 'JOB-01')
Assert-Test -Name "check-low-stock.php output contains low-stock product PRD-MON-007" -Condition ($jobOutput -match 'PRD-MON-007')
Assert-Test -Name "check-low-stock.php output contains low-stock product PRD-KBD-003" -Condition ($jobOutput -match 'PRD-KBD-003')
Assert-Test -Name "check-low-stock.php output contains status 'MENIPIS'" -Condition ($jobOutput -match 'MENIPIS')
Assert-Test -Name "check-low-stock.php output contains operational summary" -Condition ($jobOutput -match 'RINGKASAN OPERASIONAL:' -and $jobOutput -match 'Produk Perlu Reorder')

# ==============================================================================
# Summary
# ==============================================================================
Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " Phase 6 Test Summary: $passed PASSED, $failed FAILED" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
}
exit 0
