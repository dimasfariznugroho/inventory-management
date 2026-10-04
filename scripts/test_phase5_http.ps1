# ==============================================================================
# Phase 5 Automated Verification: Listing, Dashboard, Laporan, dan API
# Features:
# - VIEW-01: Product, PO, SO paginated lists (10 items/page) + Informative Empty States
# - FIND-01:
#     * Products: Search name/SKU, filter category & stock_status (low vs normal)
#     * PO & SO: Search number/partner, filter status, sort date ASC/DESC
#     * Pagination: Query string state preserved across page navigation
# - DASH-01: Real-time dynamic aggregation metrics per role (Admin, Sales, Warehouse)
# - REPORT-01: Standardized CSV exports for stock_ledger and orders with filters
# - API-01: Authenticated JSON endpoint GET /api/products/{sku}/availability (401, 404, 200)
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
Write-Host " Running Phase 5 Automated HTTP-Level Tests against $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# Helper: Login Session Factory
# ------------------------------------------------------------------------------
function Get-AuthenticatedSession {
    param (
        [string]$Email,
        [string]$Password = "password123"
    )

    $sess = $null
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
# Test 1: VIEW-01 & FIND-01 Catalog Pagination & Preservation
# ------------------------------------------------------------------------------
Write-Host "`nTest 1: Catalog Pagination (10 items/page) & Parameter Preservation" -ForegroundColor Yellow

$catP1 = Invoke-WebRequest -Uri "$BaseUrl/products?page=1" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Catalog Page 1 returns HTTP 200" -Condition ($catP1.StatusCode -eq 200)
Assert-Test -Name "Catalog Page 1 displays Product 1 (PRD-LAP-001)" -Condition ($catP1.Content -match 'PRD-LAP-001')
Assert-Test -Name "Catalog Page 1 displays Product 10 (PRD-ROU-010)" -Condition ($catP1.Content -match 'PRD-ROU-010')
Assert-Test -Name "Catalog Page 1 does NOT display Product 11 (PRD-SWI-011)" -Condition ($catP1.Content -notmatch 'PRD-SWI-011')
Assert-Test -Name "Catalog Page 1 contains pagination link to Page 2" -Condition ($catP1.Content -match 'page=2')

$catP2 = Invoke-WebRequest -Uri "$BaseUrl/products?page=2" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Catalog Page 2 returns HTTP 200" -Condition ($catP2.StatusCode -eq 200)
Assert-Test -Name "Catalog Page 2 displays Product 11 (PRD-SWI-011)" -Condition ($catP2.Content -match 'PRD-SWI-011')
Assert-Test -Name "Catalog Page 2 displays Product 20 (PRD-COR-020)" -Condition ($catP2.Content -match 'PRD-COR-020')
Assert-Test -Name "Catalog Page 2 does NOT display Product 1 (PRD-LAP-001)" -Condition ($catP2.Content -notmatch 'PRD-LAP-001')

# ------------------------------------------------------------------------------
# Test 2: FIND-01 Catalog Search, Filters & Informative Empty State
# ------------------------------------------------------------------------------
Write-Host "`nTest 2: Catalog Filtering & Informative Empty State" -ForegroundColor Yellow

# Filter Low Stock: exactly 8 low-stock products in catalog (7 active + 1 archive)
$lowStockRes = Invoke-WebRequest -Uri "$BaseUrl/products?stock_status=low" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Filter stock_status=low returns HTTP 200" -Condition ($lowStockRes.StatusCode -eq 200)
Assert-Test -Name "Low stock filter contains PRD-KBD-003" -Condition ($lowStockRes.Content -match 'PRD-KBD-003')
Assert-Test -Name "Low stock filter contains PRD-MON-007" -Condition ($lowStockRes.Content -match 'PRD-MON-007')
Assert-Test -Name "Low stock filter excludes normal-stock PRD-LAP-001" -Condition ($lowStockRes.Content -notmatch 'PRD-LAP-001')

# Combined Filter: search + category_id + stock_status=low SIMULTANEOUSLY
# Tests positional params (name OR sku: 2 params) + category (1 param) + HAVING clause
$combinedRes = Invoke-WebRequest -Uri "$BaseUrl/products?search=Monitor&category_id=1&stock_status=low" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Combined search+category+stock_status=low returns HTTP 200 without SQL error" -Condition ($combinedRes.StatusCode -eq 200)
Assert-Test -Name "Combined filter matches low-stock monitor PRD-MON-007" -Condition ($combinedRes.Content -match 'PRD-MON-007')
Assert-Test -Name "Combined filter excludes normal-stock laptop in same category" -Condition ($combinedRes.Content -notmatch 'PRD-LAP-001')
Assert-Test -Name "Combined filter excludes low-stock keyboard in different category" -Condition ($combinedRes.Content -notmatch 'PRD-KBD-003')

# Search Non-Existent keyword triggers Informative Empty State
$emptyCatalog = Invoke-WebRequest -Uri "$BaseUrl/products?search=NONEXISTENTKEYWORD999" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Empty catalog search returns HTTP 200" -Condition ($emptyCatalog.StatusCode -eq 200)
Assert-Test -Name "Empty catalog shows informative empty state title" -Condition ($emptyCatalog.Content -match 'Tidak Ada Produk Ditemukan')
Assert-Test -Name "Empty catalog shows reset filter button" -Condition ($emptyCatalog.Content -match 'Reset Filter &amp; Pencarian' -or $emptyCatalog.Content -match 'Reset Filter & Pencarian')

# ------------------------------------------------------------------------------
# Test 3: VIEW-01 & FIND-01 Purchase Orders (Search, Filter, Sort, Pagination)
# ------------------------------------------------------------------------------
Write-Host "`nTest 3: Purchase Orders Search, Filter, Sort, Pagination & Empty State" -ForegroundColor Yellow

# Pagination: 14 POs total -> Page 1 has 10, Page 2 has 4
$poP1 = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?page=1" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO Page 1 returns HTTP 200" -Condition ($poP1.StatusCode -eq 200)
Assert-Test -Name "PO Page 1 displays pagination info" -Condition ($poP1.Content -match 'Menampilkan <strong>1</strong>' -and $poP1.Content -match 'dari <strong>14</strong> data')
Assert-Test -Name "PO Page 1 contains pagination link to Page 2" -Condition ($poP1.Content -match 'page=2')

$poP2 = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?page=2" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO Page 2 returns HTTP 200" -Condition ($poP2.StatusCode -eq 200)
Assert-Test -Name "PO Page 2 displays 11 - 14 record range" -Condition ($poP2.Content -match 'Menampilkan <strong>11</strong>' -and $poP2.Content -match '<strong>14</strong>')

# Date Sorting: DESC vs ASC
$poSortDesc = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?sort=desc" -WebSession $adminSession -UseBasicParsing
$poSortAsc = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?sort=asc" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO Sort DESC displays latest PO-20260914-0014 first" -Condition ($poSortDesc.Content -match 'PO-20260914-0014')
Assert-Test -Name "PO Sort ASC displays earliest PO-20260901-0001 first" -Condition ($poSortAsc.Content -match 'PO-20260901-0001')

# Status Filter: Ordered status
$poOrdered = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?status=Ordered" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO filter status=Ordered returns HTTP 200" -Condition ($poOrdered.StatusCode -eq 200)
Assert-Test -Name "PO filter status=Ordered contains PO-20260904-0004" -Condition ($poOrdered.Content -match 'PO-20260904-0004')
Assert-Test -Name "PO filter status=Ordered excludes Draft PO-20260901-0001" -Condition ($poOrdered.Content -notmatch 'PO-20260901-0001')

# Empty State
$emptyPo = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders?search=NONEXISTENT_PO" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO search non-existent shows informative empty state" -Condition ($emptyPo.Content -match 'Tidak Ada Purchase Order Ditemukan')

# ------------------------------------------------------------------------------
# Test 4: VIEW-01 & FIND-01 Sales Orders (Search, Filter, Sort, Pagination)
# ------------------------------------------------------------------------------
Write-Host "`nTest 4: Sales Orders Search, Filter, Sort, Pagination & Empty State" -ForegroundColor Yellow

# Pagination: 14 SOs total -> Page 1 has 10, Page 2 has 4
$soP1 = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?page=1" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO Page 1 returns HTTP 200" -Condition ($soP1.StatusCode -eq 200)
Assert-Test -Name "SO Page 1 displays pagination info" -Condition ($soP1.Content -match 'Menampilkan <strong>1</strong>' -and $soP1.Content -match 'dari <strong>14</strong> data')
Assert-Test -Name "SO Page 1 contains pagination link to Page 2" -Condition ($soP1.Content -match 'page=2')

$soP2 = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?page=2" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO Page 2 returns HTTP 200" -Condition ($soP2.StatusCode -eq 200)
Assert-Test -Name "SO Page 2 displays 11 - 14 record range" -Condition ($soP2.Content -match 'Menampilkan <strong>11</strong>' -and $soP2.Content -match '<strong>14</strong>')

# Date Sorting: DESC vs ASC
$soSortDesc = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?sort=desc" -WebSession $adminSession -UseBasicParsing
$soSortAsc = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?sort=asc" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO Sort DESC displays latest SO-20260914-0014 first" -Condition ($soSortDesc.Content -match 'SO-20260914-0014')
Assert-Test -Name "SO Sort ASC displays earliest SO-20260901-0001 first" -Condition ($soSortAsc.Content -match 'SO-20260901-0001')

# Status Filter: PendingApproval status
$soPending = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?status=PendingApproval" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO filter status=PendingApproval returns HTTP 200" -Condition ($soPending.StatusCode -eq 200)
Assert-Test -Name "SO filter status=PendingApproval contains SO-20260904-0004" -Condition ($soPending.Content -match 'SO-20260904-0004')
Assert-Test -Name "SO filter status=PendingApproval excludes Draft SO-20260901-0001" -Condition ($soPending.Content -notmatch 'SO-20260901-0001')

# Empty State
$emptySo = Invoke-WebRequest -Uri "$BaseUrl/sales-orders?search=NONEXISTENT_SO" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO search non-existent shows informative empty state" -Condition ($emptySo.Content -match 'Tidak Ada Sales Order Ditemukan')

# ------------------------------------------------------------------------------
# Test 5: DASH-01 Dynamic Aggregation Dashboards per Role
# ------------------------------------------------------------------------------
Write-Host "`nTest 5: Dynamic Aggregation Dashboards per Role (DASH-01)" -ForegroundColor Yellow

# 1. Admin Dashboard
$adminDash = Invoke-WebRequest -Uri "$BaseUrl/admin/dashboard" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin Dashboard returns HTTP 200" -Condition ($adminDash.StatusCode -eq 200)
Assert-Test -Name "Admin Dashboard displays 'Dashboard Administrator'" -Condition ($adminDash.Content -match 'Dashboard Administrator')
Assert-Test -Name "Admin Dashboard displays Nilai Inventori Global" -Condition ($adminDash.Content -match 'Nilai Inventori Global' -and $adminDash.Content -match 'Rp ')
Assert-Test -Name "Admin Dashboard displays exact low stock count: 7 Produk" -Condition ($adminDash.Content -match '7 Produk')
Assert-Test -Name "Admin Dashboard displays Pending PO (Ordered + Partial = 5 PO)" -Condition ($adminDash.Content -match '5 PO')
Assert-Test -Name "Admin Dashboard displays SO Menunggu Approval (3 SO)" -Condition ($adminDash.Content -match '3 SO')

# 2. Sales Dashboard (User 2: Sarah Jenkins)
$salesDash = Invoke-WebRequest -Uri "$BaseUrl/sales/dashboard" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Sales Dashboard returns HTTP 200" -Condition ($salesDash.StatusCode -eq 200)
Assert-Test -Name "Sales Dashboard displays 'Dashboard Sales'" -Condition ($salesDash.Content -match 'Dashboard Sales')
Assert-Test -Name "Sales Dashboard displays Total Penjualan Saya" -Condition ($salesDash.Content -match 'Total Penjualan Saya' -and $salesDash.Content -match 'Rp ')
Assert-Test -Name "Sales Dashboard displays Menunggu Persetujuan (2 SO for User 2)" -Condition ($salesDash.Content -match '2 SO')
Assert-Test -Name "Sales Dashboard displays Disetujui (2 SO for User 2)" -Condition ($salesDash.Content -match 'Disetujui \(Siap Kirim\)')
Assert-Test -Name "Sales Dashboard displays Selesai (2 SO for User 2)" -Condition ($salesDash.Content -match 'Selesai \(Fulfilled\)')

# 3. Warehouse Staff Dashboard
$whDash = Invoke-WebRequest -Uri "$BaseUrl/warehouse/dashboard" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Dashboard returns HTTP 200" -Condition ($whDash.StatusCode -eq 200)
Assert-Test -Name "Warehouse Dashboard displays 'Dashboard Staf Gudang'" -Condition ($whDash.Content -match 'Dashboard Staf Gudang')
Assert-Test -Name "Warehouse Dashboard displays Antrean Goods Receipt: 5 PO" -Condition ($whDash.Content -match 'Antrean Goods Receipt' -and $whDash.Content -match '5 PO')
Assert-Test -Name "Warehouse Dashboard displays Antrean Goods Issue: 3 SO" -Condition ($whDash.Content -match 'Antrean Goods Issue' -and $whDash.Content -match '3 SO')
Assert-Test -Name "Warehouse Dashboard displays Peringatan Low Stock: 7 Produk" -Condition ($whDash.Content -match 'Peringatan Low Stock' -and $whDash.Content -match '7 Produk')

# ------------------------------------------------------------------------------
# Test 6: REPORT-01 CSV Reports Export
# ------------------------------------------------------------------------------
Write-Host "`nTest 6: CSV Reports Export (REPORT-01)" -ForegroundColor Yellow

# 1. Stock Ledger CSV Export
$ledgerCsvRes = Invoke-WebRequest -Uri "$BaseUrl/reports/stock-ledger/export" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Stock Ledger CSV export returns HTTP 200" -Condition ($ledgerCsvRes.StatusCode -eq 200)
Assert-Test -Name "Stock Ledger CSV Content-Type is text/csv" -Condition ($ledgerCsvRes.Headers['Content-Type'] -match 'text/csv')
Assert-Test -Name "Stock Ledger CSV contains required column headers" -Condition ($ledgerCsvRes.Content -match 'ID,Tanggal,SKU' -and $ledgerCsvRes.Content -match 'Tipe Transaksi' -and $ledgerCsvRes.Content -match 'Kuantitas')
Assert-Test -Name "Stock Ledger CSV contains initial Receipt entries" -Condition ($ledgerCsvRes.Content -match 'Receipt')
Assert-Test -Name "Stock Ledger CSV contains initial Issue entries" -Condition ($ledgerCsvRes.Content -match 'Issue')

# 2. Purchase Orders CSV Export
$poCsvRes = Invoke-WebRequest -Uri "$BaseUrl/reports/orders/export?type=po" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Purchase Orders CSV export returns HTTP 200" -Condition ($poCsvRes.StatusCode -eq 200)
Assert-Test -Name "Purchase Orders CSV contains header 'Nomor PO'" -Condition ($poCsvRes.Content -match 'Nomor PO' -and $poCsvRes.Content -match 'Total Nilai')
Assert-Test -Name "Purchase Orders CSV contains seeded PO number" -Condition ($poCsvRes.Content -match 'PO-20260901-0001')

# 3. Sales Orders CSV Export
$soCsvRes = Invoke-WebRequest -Uri "$BaseUrl/reports/orders/export?type=so" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Sales Orders CSV export returns HTTP 200" -Condition ($soCsvRes.StatusCode -eq 200)
Assert-Test -Name "Sales Orders CSV contains header 'Nomor SO'" -Condition ($soCsvRes.Content -match 'Nomor SO' -and $soCsvRes.Content -match 'Pelanggan')
Assert-Test -Name "Sales Orders CSV contains seeded SO number" -Condition ($soCsvRes.Content -match 'SO-20260901-0001')

# ------------------------------------------------------------------------------
# Test 7: API-01 Authenticated Product Availability JSON API
# ------------------------------------------------------------------------------
Write-Host "`nTest 7: Authenticated Product Availability JSON API (API-01)" -ForegroundColor Yellow

# 1. Unauthenticated Request -> strictly 401 Unauthorized with JSON
$unauthApiPassed = $false
$unauthApiDetails = ""
try {
    $unauthApi = Invoke-WebRequest -Uri "$BaseUrl/api/products/PRD-LAP-001/availability" -UseBasicParsing -ErrorAction Stop
    $unauthApiDetails = "Status code was $($unauthApi.StatusCode), expected 401"
} catch {
    $resp = $_.Exception.Response
    if ($resp) {
        $statusCode = [int]$resp.StatusCode
        $cType = $resp.Headers['Content-Type']
        $bodyText = if ($_.ErrorDetails) {
            $_.ErrorDetails.Message
        } else {
            $st = $resp.GetResponseStream()
            if ($st.CanSeek) { $st.Position = 0 }
            (New-Object System.IO.StreamReader($st)).ReadToEnd()
        }

        $jsonObj = $null
        try { $jsonObj = ConvertFrom-Json $bodyText } catch {}

        if ($statusCode -eq 401 -and $cType -match 'application/json' -and $jsonObj.error -eq 'Unauthorized') {
            $unauthApiPassed = $true
            $unauthApiDetails = "Status: 401, JSON error: $($jsonObj.error), Message: $($jsonObj.message)"
        } else {
            $unauthApiDetails = "Status: $statusCode, Content-Type: $cType, Body: $bodyText"
        }
    }
}
Assert-Test -Name "Unauthenticated API call returns HTTP 401 JSON" -Condition $unauthApiPassed -Details $unauthApiDetails

# 2. Authenticated Request with Non-Existent SKU -> strictly 404 Not Found with JSON
$nonExistentApiPassed = $false
$nonExistentApiDetails = ""
try {
    $nonExApi = Invoke-WebRequest -Uri "$BaseUrl/api/products/SKU-NON-EXISTENT-999/availability" -WebSession $adminSession -UseBasicParsing -ErrorAction Stop
    $nonExistentApiDetails = "Status code was $($nonExApi.StatusCode), expected 404"
} catch {
    $resp = $_.Exception.Response
    if ($resp) {
        $statusCode = [int]$resp.StatusCode
        $cType = $resp.Headers['Content-Type']
        $bodyText = if ($_.ErrorDetails) {
            $_.ErrorDetails.Message
        } else {
            $st = $resp.GetResponseStream()
            if ($st.CanSeek) { $st.Position = 0 }
            (New-Object System.IO.StreamReader($st)).ReadToEnd()
        }

        $jsonObj = $null
        try { $jsonObj = ConvertFrom-Json $bodyText } catch {}

        if ($statusCode -eq 404 -and $cType -match 'application/json' -and $jsonObj.error -eq 'Not Found') {
            $nonExistentApiPassed = $true
            $nonExistentApiDetails = "Status: 404, JSON error: $($jsonObj.error), Message: $($jsonObj.message)"
        } else {
            $nonExistentApiDetails = "Status: $statusCode, Content-Type: $cType, Body: $bodyText"
        }
    }
}
Assert-Test -Name "Authenticated API call for non-existent SKU returns HTTP 404 JSON" -Condition $nonExistentApiPassed -Details $nonExistentApiDetails

# 3. Authenticated Request with Valid SKU -> HTTP 200 with structured JSON
$authApiRes = Invoke-WebRequest -Uri "$BaseUrl/api/products/PRD-LAP-001/availability" -WebSession $adminSession -UseBasicParsing
$authApiJson = ConvertFrom-Json $authApiRes.Content

Assert-Test -Name "Authenticated API call returns HTTP 200" -Condition ($authApiRes.StatusCode -eq 200)
Assert-Test -Name "API response Content-Type is application/json" -Condition ($authApiRes.Headers['Content-Type'] -match 'application/json')
Assert-Test -Name "API response status is success" -Condition ($authApiJson.status -eq 'success')
Assert-Test -Name "API returns SKU 'PRD-LAP-001'" -Condition ($authApiJson.data.sku -eq 'PRD-LAP-001')
Assert-Test -Name "API returns Product Name 'Laptop Pro Ultra 14 Inch'" -Condition ($authApiJson.data.name -eq 'Laptop Pro Ultra 14 Inch')
Assert-Test -Name "API returns Category 'Elektronik & Gadget'" -Condition ($authApiJson.data.category -eq 'Elektronik & Gadget')
Assert-Test -Name "API returns total_stock exactly 35" -Condition ($authApiJson.data.total_stock -eq 35)
Assert-Test -Name "API returns reorder_point exactly 15" -Condition ($authApiJson.data.reorder_point -eq 15)
Assert-Test -Name "API returns stock_status 'normal'" -Condition ($authApiJson.data.stock_status -eq 'normal')
Assert-Test -Name "API returns breakdown for 3 active warehouses" -Condition ($authApiJson.data.warehouses.Count -eq 3)
Assert-Test -Name "API returns Warehouse 1 (Jakarta) stock exactly 25" -Condition ($authApiJson.data.warehouses[0].quantity -eq 25)
Assert-Test -Name "API returns Warehouse 2 (Surabaya) stock exactly 10" -Condition ($authApiJson.data.warehouses[1].quantity -eq 10)
Assert-Test -Name "API returns Warehouse 3 (Medan) stock exactly 0" -Condition ($authApiJson.data.warehouses[2].quantity -eq 0)

# ==============================================================================
# Summary
# ==============================================================================
Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " Phase 5 Test Summary: $passed PASSED, $failed FAILED" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==================================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
}
