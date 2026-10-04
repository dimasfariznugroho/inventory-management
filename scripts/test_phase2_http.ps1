# ==============================================================================
# Phase 2 Verification Test Script: Master Data (PRD-01, WH-01, Partners)
# ==============================================================================
param (
    [string]$BaseUrl = "http://localhost:8080"
)

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
Write-Host " Running Phase 2 Automated HTTP-Level Tests against $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# Test 1: Unauthenticated access to /products redirects to /login (302)
# ------------------------------------------------------------------------------
Write-Host "`nTest 1: Unauthenticated access to /products" -ForegroundColor Yellow
$guestPassed = $false
$guestDetails = ""
try {
    $res1 = Invoke-WebRequest -Uri "$BaseUrl/products" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    if ($res1.StatusCode -eq 302 -and $res1.Headers['Location'] -match '/login') {
        $guestPassed = $true
        $guestDetails = "Status 302, Location: $($res1.Headers['Location'])"
    }
} catch {
    $resp = $_.Exception.Response
    if ($resp -and [int]$resp.StatusCode -eq 302) {
        $loc = $resp.Headers['Location']
        $guestPassed = ($loc -match '/login')
        $guestDetails = "Caught 302, Location: $loc"
    } else {
        # Fallback by following redirect to login page
        $resFallback = Invoke-WebRequest -Uri "$BaseUrl/products" -UseBasicParsing
        $guestPassed = ($resFallback.Content -match 'Masuk ke Sistem' -or $resFallback.Content -match 'Silakan login terlebih dahulu')
        $guestDetails = "Redirected to login page"
    }
}
Assert-Test -Name "Unauthenticated /products is protected and redirects to /login" -Condition $guestPassed -Details $guestDetails

# ------------------------------------------------------------------------------
# Test 2: Login as Sales & Access Catalog Read-Only
# ------------------------------------------------------------------------------
Write-Host "`nTest 2: Sales Role Catalog Access & Permissions" -ForegroundColor Yellow
$salesSession = $null
$salesLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'sales1@inventory.local'; password = 'password123' } -SessionVariable salesSession -UseBasicParsing
Assert-Test -Name "Sales login succeeds" -Condition ($salesLogin.Content -match "Dashboard Sales" -or $salesLogin.StatusCode -eq 200) -Details "Status: $($salesLogin.StatusCode)"

$salesCatalog = Invoke-WebRequest -Uri "$BaseUrl/products" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Sales can view catalog (/products 200 OK)" -Condition ($salesCatalog.StatusCode -eq 200 -and $salesCatalog.Content -match "Katalog Produk") -Details "Catalog loaded"
Assert-Test -Name "Sales UI does NOT show 'Tambah Produk Baru' button" -Condition ($salesCatalog.Content -notmatch "/admin/products/create") -Details "No create button in UI"

# Server-level 403 test for Sales trying to create product
try {
    $sfResp = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $sfStatus = $sfResp.StatusCode
} catch {
    $sfStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Sales blocked from /admin/products/create (403 Forbidden)" -Condition ($sfStatus -eq 403) -Details "Status: $sfStatus"

# Server-level 403 test for Sales trying to access warehouses
try {
    $swhResp = Invoke-WebRequest -Uri "$BaseUrl/admin/warehouses" -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $swhStatus = $swhResp.StatusCode
} catch {
    $swhStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Sales blocked from /admin/warehouses (403 Forbidden)" -Condition ($swhStatus -eq 403) -Details "Status: $swhStatus"

# ------------------------------------------------------------------------------
# Test 3: Login as Warehouse Staff & Access Catalog Read-Only
# ------------------------------------------------------------------------------
Write-Host "`nTest 3: Warehouse Staff Role Catalog Access & Permissions" -ForegroundColor Yellow
$whSession = $null
$whLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'warehouse1@inventory.local'; password = 'password123' } -SessionVariable whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff login succeeds" -Condition ($whLogin.Content -match "Dashboard Staf Gudang" -or $whLogin.StatusCode -eq 200) -Details "Status: $($whLogin.StatusCode)"

$whCatalog = Invoke-WebRequest -Uri "$BaseUrl/products" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff can view catalog (/products 200 OK)" -Condition ($whCatalog.StatusCode -eq 200) -Details "Catalog loaded"

# Server-level 403 test for WarehouseStaff trying to access categories
try {
    $whfResp = Invoke-WebRequest -Uri "$BaseUrl/admin/categories" -WebSession $whSession -UseBasicParsing -ErrorAction Stop
    $whfStatus = $whfResp.StatusCode
} catch {
    $whfStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Warehouse Staff blocked from /admin/categories (403 Forbidden)" -Condition ($whfStatus -eq 403) -Details "Status: $whfStatus"

# ------------------------------------------------------------------------------
# Test 4: WH-01 Multi-Warehouse Stock Breakdown (/products/1)
# ------------------------------------------------------------------------------
Write-Host "`nTest 4: WH-01 Multi-Warehouse Stock Breakdown (/products/1)" -ForegroundColor Yellow
$productDetail = Invoke-WebRequest -Uri "$BaseUrl/products/1" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Product detail returns 200 OK" -Condition ($productDetail.StatusCode -eq 200) -Details "Status: $($productDetail.StatusCode)"
Assert-Test -Name "Detail contains product SKU PRD-LAP-001" -Condition ($productDetail.Content -match "PRD-LAP-001") -Details "SKU matched"
Assert-Test -Name "Detail shows total stock 35" -Condition ($productDetail.Content -match "35" -and $productDetail.Content -match "Total Stok Fisik") -Details "Total stock 35 displayed"
Assert-Test -Name "Detail shows breakdown: Gudang Utama Jakarta (25)" -Condition ($productDetail.Content -match "Gudang Utama Jakarta" -and $productDetail.Content -match "25") -Details "Jakarta stock 25 matched"
Assert-Test -Name "Detail shows breakdown: Gudang Logistik Surabaya (10)" -Condition ($productDetail.Content -match "Gudang Logistik Surabaya" -and $productDetail.Content -match "10") -Details "Surabaya stock 10 matched"

# ------------------------------------------------------------------------------
# Test 5: Admin Login & Master Data Pages Access
# ------------------------------------------------------------------------------
Write-Host "`nTest 5: Admin Master Data Pages Access" -ForegroundColor Yellow
$adminSession = $null
$adminLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'admin@inventory.local'; password = 'password123' } -SessionVariable adminSession -UseBasicParsing
Assert-Test -Name "Admin login succeeds" -Condition ($adminLogin.Content -match "Dashboard Administrator" -or $adminLogin.StatusCode -eq 200) -Details "Status: $($adminLogin.StatusCode)"

$catResp = Invoke-WebRequest -Uri "$BaseUrl/admin/categories" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin can access /admin/categories (200 OK)" -Condition ($catResp.StatusCode -eq 200 -and $catResp.Content -match "Master Data Kategori") -Details "Categories loaded"

$whResp = Invoke-WebRequest -Uri "$BaseUrl/admin/warehouses" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin can access /admin/warehouses (200 OK)" -Condition ($whResp.StatusCode -eq 200 -and $whResp.Content -match "Master Data Gudang") -Details "Warehouses loaded"

$suppResp = Invoke-WebRequest -Uri "$BaseUrl/admin/suppliers" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin can access /admin/suppliers (200 OK)" -Condition ($suppResp.StatusCode -eq 200 -and $suppResp.Content -match "Master Data Pemasok") -Details "Suppliers loaded"

$custResp = Invoke-WebRequest -Uri "$BaseUrl/admin/customers" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin can access /admin/customers (200 OK)" -Condition ($custResp.StatusCode -eq 200 -and $custResp.Content -match "Master Data Pelanggan") -Details "Customers loaded"

# ------------------------------------------------------------------------------
# Test 6: Category Deletion Protection (Cannot delete category with products)
# ------------------------------------------------------------------------------
Write-Host "`nTest 6: Category Deletion Protection with Products" -ForegroundColor Yellow
# Category 1 is "Elektronik & Gadget" which has seeded products
$delCatResp = Invoke-WebRequest -Uri "$BaseUrl/admin/categories/1/delete" -Method Post -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Category 1 deletion is rejected with friendly message" -Condition ($delCatResp.Content -match "masih digunakan oleh produk") -Details "Protection message matched"

# ------------------------------------------------------------------------------
# Test 7: Product Price Validation (Negative price rejected)
# ------------------------------------------------------------------------------
Write-Host "`nTest 7: Product Price Validation (Negative Price Rejected)" -ForegroundColor Yellow
$prodBadPrice = @{
    sku            = "TEST-SKU-NEG"
    name           = "Produk Test Negatif"
    category_id    = "1"
    unit           = "pcs"
    purchase_price = "-50000"
    selling_price  = "100000"
    reorder_point  = "10"
}
$badPriceResp = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -Method Post -Body $prodBadPrice -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Negative purchase price is rejected by validation" -Condition ($badPriceResp.Content -match "Harga beli tidak boleh negatif") -Details "Validation error displayed"

# ------------------------------------------------------------------------------
# Test 8: Product Image Upload Validation (Non-image file rejected)
# ------------------------------------------------------------------------------
Write-Host "`nTest 8: Product Image Upload Validation" -ForegroundColor Yellow
$boundary = [System.Guid]::NewGuid().ToString()
$LF = "`r`n"
$bodyLines = (
    "--$boundary",
    'Content-Disposition: form-data; name="sku"',
    '',
    'TEST-SKU-BADIMG',
    "--$boundary",
    'Content-Disposition: form-data; name="name"',
    '',
    'Produk Bad Image',
    "--$boundary",
    'Content-Disposition: form-data; name="category_id"',
    '',
    '1',
    "--$boundary",
    'Content-Disposition: form-data; name="unit"',
    '',
    'pcs',
    "--$boundary",
    'Content-Disposition: form-data; name="purchase_price"',
    '',
    '10000',
    "--$boundary",
    'Content-Disposition: form-data; name="selling_price"',
    '',
    '20000',
    "--$boundary",
    'Content-Disposition: form-data; name="reorder_point"',
    '',
    '5',
    "--$boundary",
    'Content-Disposition: form-data; name="image"; filename="malicious.txt"',
    'Content-Type: text/plain',
    '',
    'This is a text file, definitely not an image.',
    "--$boundary--"
) -join $LF

$badImgResp = Invoke-WebRequest -Uri "$BaseUrl/admin/products/create" -Method Post -ContentType "multipart/form-data; boundary=$boundary" -Body $bodyLines -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Non-image upload is rejected with MIME error" -Condition ($badImgResp.Content -match "Tipe file tidak diizinkan" -or $badImgResp.Content -match "Hanya format JPG") -Details "File validation caught"

# ------------------------------------------------------------------------------
# Test 9: Search Catalog with ONLY keyword (no category / status filter) - Fix SQLSTATE[HY093]
# ------------------------------------------------------------------------------
Write-Host "`nTest 9: Search Catalog with Keyword-Only (No Category/Stock Filter)" -ForegroundColor Yellow
$searchOnlyResp = Invoke-WebRequest -Uri "$BaseUrl/products?search=Laptop&category_id=&stock_status=" -WebSession $salesSession -UseBasicParsing
$searchHasNoSqlError = ($searchOnlyResp.Content -notmatch "SQLSTATE" -and $searchOnlyResp.Content -notmatch "Invalid parameter number")
$searchHasResults = ($searchOnlyResp.StatusCode -eq 200 -and $searchOnlyResp.Content -match "PRD-LAP-001")
Assert-Test -Name "Search with only keyword returns 200 OK without SQLSTATE[HY093] error" -Condition ($searchHasNoSqlError -and $searchHasResults) -Details "Status: $($searchOnlyResp.StatusCode), Matched Laptop product"

# Also test search with keyword only (clean query param)
$searchCleanResp = Invoke-WebRequest -Uri "$BaseUrl/products?search=Mouse" -WebSession $salesSession -UseBasicParsing
$searchCleanOk = ($searchCleanResp.StatusCode -eq 200 -and $searchCleanResp.Content -notmatch "SQLSTATE" -and $searchCleanResp.Content -match "PRD-MOU-002")
Assert-Test -Name "Search with clean ?search=Mouse query parameter succeeds" -Condition $searchCleanOk -Details "Status: $($searchCleanResp.StatusCode), Matched Mouse product"

# ------------------------------------------------------------------------------
# Summary
# ------------------------------------------------------------------------------
Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " Test Summary: $passed PASSED, $failed FAILED" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==================================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
} else {
    exit 0
}
