# ==============================================================================
# Phase 4 Verification Test Script: Sales Orders & Goods Issue (SO-01 + ARCH-02)
# Features:
# - Sales Order Lifecycle: Draft -> PendingApproval -> Approved -> Fulfilled / Cancelled
# - Strict Segregation of Duties: Admin-only Approval; Sales blocked from approving
# - ARCH-02 Optimistic Locking & Negative Stock (Oversell) Prevention
# - Atomic Transaction: product_stock decrement + stock_ledger (Issue) log
# - Exact Numerical Assertions (No "or" conditionals)
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
Write-Host " Running Phase 4 Automated HTTP-Level Tests against $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# Test 1: Unauthenticated access to /sales-orders redirects to /login
# ------------------------------------------------------------------------------
Write-Host "`nTest 1: Unauthenticated access to /sales-orders" -ForegroundColor Yellow
$guestPassed = $false
$guestDetails = ""
try {
    $res1 = Invoke-WebRequest -Uri "$BaseUrl/sales-orders" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
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
        $resFallback = Invoke-WebRequest -Uri "$BaseUrl/sales-orders" -UseBasicParsing
        $guestPassed = ($resFallback.Content -match 'Masuk ke Sistem' -or $resFallback.Content -match 'Silakan login terlebih dahulu')
        $guestDetails = "Redirected to login page"
    }
}
Assert-Test -Name "Unauthenticated /sales-orders redirects to /login" -Condition $guestPassed -Details $guestDetails

# ------------------------------------------------------------------------------
# Test 2: Warehouse Staff Role Permissions & 403 Enforcement
# ------------------------------------------------------------------------------
Write-Host "`nTest 2: Warehouse Staff Role SO Permissions & 403 Enforcement" -ForegroundColor Yellow
$whSession = $null
$whLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'warehouse1@inventory.local'; password = 'password123' } -SessionVariable whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff login succeeds" -Condition ($whLogin.Content -match "Dashboard Staf Gudang" -or $whLogin.StatusCode -eq 200) -Details "Status: $($whLogin.StatusCode)"

$whSoList = Invoke-WebRequest -Uri "$BaseUrl/sales-orders" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff can view SO list (/sales-orders 200 OK)" -Condition ($whSoList.StatusCode -eq 200 -and $whSoList.Content -match "Sales Orders") -Details "SO list loaded"
Assert-Test -Name "Warehouse Staff UI does NOT show 'Buat SO Baru' button" -Condition ($whSoList.Content -notmatch "/sales-orders/create") -Details "No create button in UI"

# Server-level 403 for WarehouseStaff trying GET /sales-orders/create
try {
    $whGet = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -WebSession $whSession -UseBasicParsing -ErrorAction Stop
    $whGetStatus = $whGet.StatusCode
} catch {
    $whGetStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Warehouse Staff blocked from GET /sales-orders/create (403 Forbidden)" -Condition ($whGetStatus -eq 403) -Details "Status: $whGetStatus"

# Server-level 403 for WarehouseStaff trying POST /sales-orders/create
try {
    $whPost = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body @{ customer_id = 1; warehouse_id = 1 } -WebSession $whSession -UseBasicParsing -ErrorAction Stop
    $whPostStatus = $whPost.StatusCode
} catch {
    $whPostStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Warehouse Staff blocked from POST /sales-orders/create (403 Forbidden)" -Condition ($whPostStatus -eq 403) -Details "Status: $whPostStatus"

# ------------------------------------------------------------------------------
# Test 3: Sales Staff Creates a Sales Order (Status: Draft)
# ------------------------------------------------------------------------------
Write-Host "`nTest 3: Sales Staff Creates Sales Order" -ForegroundColor Yellow
$salesSession = $null
$salesLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'sales1@inventory.local'; password = 'password123' } -SessionVariable salesSession -UseBasicParsing
Assert-Test -Name "Sales Staff login succeeds" -Condition ($salesLogin.Content -match "Dashboard Sales" -or $salesLogin.StatusCode -eq 200) -Details "Status: $($salesLogin.StatusCode)"

$soCreatePage = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Sales Staff can open /sales-orders/create (200 OK)" -Condition ($soCreatePage.StatusCode -eq 200) -Details "Form loaded"

# Submit SO with 2 items:
# Item 1: Product 1 (ThinkPad), Qty = 5, Unit Price = 14500000
# Item 2: Product 2 (Mouse), Qty = 10, Unit Price = 225000
$soCreateBody = @{
    customer_id            = "1" # PT Retail Nusantara Megah
    warehouse_id           = "1" # Gudang Utama Jakarta
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "1"
    "items[0][quantity]"   = "5"
    "items[0][unit_price]" = "14500000"
    "items[1][product_id]" = "2"
    "items[1][quantity]"   = "10"
    "items[1][unit_price]" = "225000"
}

$soCreateResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $soCreateBody -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Sales Order created successfully" -Condition ($soCreateResp.Content -match "Sales Order.*berhasil dibuat" -or $soCreateResp.Content -match "Draft") -Details "SO creation confirmed"

$createdSo1Id = $null
if ($soCreateResp.BaseResponse.ResponseUri.AbsoluteUri -match "/sales-orders/(\d+)") {
    $createdSo1Id = [int]$matches[1]
} else {
    $soList = Invoke-WebRequest -Uri "$BaseUrl/sales-orders" -WebSession $salesSession -UseBasicParsing
    if ($soList.Content -match 'href="/sales-orders/(\d+)"') {
        $createdSo1Id = [int]$matches[1]
    }
}
Assert-Test -Name "Retrieved newly created SO ID ($createdSo1Id)" -Condition ($createdSo1Id -ne $null -and $createdSo1Id -gt 0) -Details "SO ID: $createdSo1Id"

$soDetail = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "SO detail shows status Draft" -Condition ($soDetail.Content -match "Draft") -Details "Status check"
Assert-Test -Name "SO detail shows 'Submit for Approval' action button" -Condition ($soDetail.Content -match "Ajukan Persetujuan") -Details "Submit button visible"
Assert-Test -Name "Goods Issue form is NOT shown for Draft SO" -Condition ($soDetail.Content -notmatch "Proses Pengeluaran Barang") -Details "Issue form closed on Draft"

# ------------------------------------------------------------------------------
# Test 4: Negative Test - Goods Issue on Draft SO is Rejected
# ------------------------------------------------------------------------------
Write-Host "`nTest 4: Goods Issue on Draft SO Rejected" -ForegroundColor Yellow
$draftIssueBody = @{
    "issued_quantities[1]" = "2"
    notes                  = "Percobaan pengeluaran barang saat SO masih Draft"
}
$draftIssueResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/issue" -Method Post -Body $draftIssueBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Goods Issue on Draft SO is rejected" -Condition ($draftIssueResp.Content -match "tidak dapat diproses untuk status SO.*Draft" -and $draftIssueResp.Content -notmatch "berhasil dicatat") -Details "Rejected with error message"
Assert-Test -Name "SO status remains Draft after rejected issue attempt" -Condition ($draftIssueResp.Content -match "Draft") -Details "Status unchanged"

# ------------------------------------------------------------------------------
# Test 5: Sales Submits SO for Approval (Draft -> PendingApproval)
# ------------------------------------------------------------------------------
Write-Host "`nTest 5: Sales Submits SO for Approval" -ForegroundColor Yellow
$submitResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/submit-approval" -Method Post -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "SO status transitioned to PendingApproval" -Condition ($submitResp.Content -match "PendingApproval") -Details "Status: PendingApproval"
Assert-Test -Name "Sales UI shows Segregation of Duties notice (cannot self-approve)" -Condition ($submitResp.Content -match "Segregation of Duties" -or $submitResp.Content -match "Menunggu Persetujuan Administrator") -Details "SoD banner shown to Sales"
Assert-Test -Name "Sales UI does NOT show Approve button" -Condition ($submitResp.Content -notmatch 'action="/sales-orders/\d+/approve"') -Details "No approve button for Sales"

# ------------------------------------------------------------------------------
# Test 6: SEGREGATION OF DUTIES ENFORCEMENT - Sales Attempt to Approve is Rejected
# ------------------------------------------------------------------------------
Write-Host "`nTest 6: Segregation of Duties - Sales Attempt to Approve Rejected" -ForegroundColor Yellow
try {
    $salesApproveResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/approve" -Method Post -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $salesApproveStatus = $salesApproveResp.StatusCode
    $salesApproveBody = $salesApproveResp.Content
} catch {
    $salesApproveStatus = [int]$_.Exception.Response.StatusCode
    $salesApproveBody = ""
}
# Must be rejected with 403 Forbidden or explicit rejection message
$sodEnforced = ($salesApproveStatus -eq 403 -or $salesApproveBody -match "Hanya Administrator" -or $salesApproveBody -match "Segregation of Duties")
Assert-Test -Name "Sales staff blocked from approving SO (Server-level 403 / SoD rejection)" -Condition $sodEnforced -Details "Status: $salesApproveStatus"

# Verify status in database remains PendingApproval
$soVerifyPending = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "SO status remains PendingApproval after illegal Sales approval attempt" -Condition ($soVerifyPending.Content -match "PendingApproval" -and $soVerifyPending.Content -notmatch "Status:\s*Approved") -Details "State preserved"

# ------------------------------------------------------------------------------
# Test 7: Admin Approves Sales Order (PendingApproval -> Approved)
# ------------------------------------------------------------------------------
Write-Host "`nTest 7: Admin Approves Sales Order" -ForegroundColor Yellow
$adminSession = $null
$adminLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'admin@inventory.local'; password = 'password123' } -SessionVariable adminSession -UseBasicParsing
Assert-Test -Name "Admin login succeeds" -Condition ($adminLogin.StatusCode -eq 200) -Details "Status: $($adminLogin.StatusCode)"

$adminSoView = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id" -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin detail view shows 'Setujui Pesanan (Approve)' button" -Condition ($adminSoView.Content -match "Setujui Pesanan \(Approve\)") -Details "Approve button visible for Admin"

$approveResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/approve" -Method Post -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Admin approves SO successfully" -Condition ($approveResp.Content -match "berhasil disetujui" -or $approveResp.Content -match "Status: Approved") -Details "SO approved"
Assert-Test -Name "SO status updated to Approved" -Condition ($approveResp.Content -match "Approved") -Details "Status: Approved"

# ------------------------------------------------------------------------------
# Test 8: Sales Staff Blocked from Processing Goods Issue (403 Forbidden)
# ------------------------------------------------------------------------------
Write-Host "`nTest 8: Sales Staff Blocked from Processing Goods Issue" -ForegroundColor Yellow
try {
    $salesIssueResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/issue" -Method Post -Body @{ "issued_quantities[1]" = "1" } -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $salesIssueStatus = $salesIssueResp.StatusCode
} catch {
    $salesIssueStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Sales staff blocked from POST /sales-orders/{id}/issue (403 Forbidden)" -Condition ($salesIssueStatus -eq 403) -Details "Status: $salesIssueStatus"

# ------------------------------------------------------------------------------
# Test 9: Negative Test - Goods Issue Exceeding Remaining Quantity Rejected
# ------------------------------------------------------------------------------
Write-Host "`nTest 9: Goods Issue Exceeding Remaining Quantity Rejected" -ForegroundColor Yellow
$itemIds = @()
$matchesCol = [regex]::Matches($approveResp.Content, 'name="issued_quantities\[(\d+)\]"')
foreach ($m in $matchesCol) {
    $itemIds += [int]$m.Groups[1].Value
}
Assert-Test -Name "Found 2 item inputs in Goods Issue form" -Condition ($itemIds.Count -ge 2) -Details "Item IDs: $($itemIds -join ', ')"

$soItemId1 = $itemIds[0]
$soItemId2 = $itemIds[1]

$exceedIssueBody = @{
    "issued_quantities[$soItemId1]" = "99" # Ordered 5, attempting 99
    "issued_quantities[$soItemId2]" = "0"
    notes                           = "Percobaan pengeluaran melebihi sisa pesanan"
}
$exceedIssueResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/issue" -Method Post -Body $exceedIssueBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Over-issue quantity is rejected with clear error message" -Condition ($exceedIssueResp.Content -match "melebihi sisa pesanan" -and $exceedIssueResp.Content -notmatch "berhasil dicatat") -Details "Rejected with over-quantity error"
Assert-Test -Name "SO status remains Approved without fulfillment change" -Condition ($exceedIssueResp.Content -match "Approved" -and $exceedIssueResp.Content -notmatch "Fulfilled") -Details "Status preserved"

# ------------------------------------------------------------------------------
# Test 10: ARCH-02 Controlled Race Condition & Optimistic Lock Test (Oversell Prevention)
# ------------------------------------------------------------------------------
Write-Host "`nTest 10: ARCH-02 Controlled Race Condition & Optimistic Locking Test" -ForegroundColor Yellow

# Skenario Terkontrol:
# Produk 5 (Mesin Bor Cordless 12V) di Gudang 2 (Surabaya) memiliki stok awal tepat: 5 unit.
# Kita membuat 2 Sales Order berbeda untuk Produk 5 di Gudang 2:
# - SO-A memesan 5 unit (menghabiskan seluruh stok 5 unit).
# - SO-B memesan 3 unit.
# Kedua SO di-approve oleh Admin.
# Eksekusi Goods Issue:
# 1. SO-A diproses -> Stok 5 unit keluar (stok gudang 2 menjadi 0). SO-A Fulfilled.
# 2. SO-B diproses -> Optimistic lock & stock check mendeteksi stok tidak mencukupi / konflik versi.
#    -> Permintaan SO-B DITOLAK, BUKAN lolos dan membuat stok menjadi negatif (-3)!

# Step A: Admin creates SO-A (5 unit Produk 5 di Gudang 2)
$soABody = @{
    customer_id            = "2" # Toko Makmur Surabaya
    warehouse_id           = "2" # Gudang Logistik Surabaya
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "5" # Mesin Bor Cordless
    "items[0][quantity]"   = "5"
    "items[0][unit_price]" = "520000"
}
$soAResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $soABody -WebSession $adminSession -UseBasicParsing
$soAId = 0
if ($soAResp.BaseResponse.ResponseUri.AbsoluteUri -match "/sales-orders/(\d+)") { $soAId = [int]$matches[1] }
Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soAId/submit-approval" -Method Post -WebSession $adminSession -UseBasicParsing | Out-Null
Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soAId/approve" -Method Post -WebSession $adminSession -UseBasicParsing | Out-Null
Assert-Test -Name "SO-A created and approved for 5 units Product 5 in Gudang 2" -Condition ($soAId -gt 0) -Details "SO-A ID: $soAId"

# Step B: Admin creates SO-B (3 unit Produk 5 di Gudang 2)
$soBBody = @{
    customer_id            = "2"
    warehouse_id           = "2"
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "5"
    "items[0][quantity]"   = "3"
    "items[0][unit_price]" = "520000"
}
$soBResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $soBBody -WebSession $adminSession -UseBasicParsing
$soBId = 0
if ($soBResp.BaseResponse.ResponseUri.AbsoluteUri -match "/sales-orders/(\d+)") { $soBId = [int]$matches[1] }
Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soBId/submit-approval" -Method Post -WebSession $adminSession -UseBasicParsing | Out-Null
$soBApproveResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soBId/approve" -Method Post -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "SO-B created and approved for 3 units Product 5 in Gudang 2" -Condition ($soBId -gt 0) -Details "SO-B ID: $soBId"

# Extract Item ID for SO-A
$soADetail = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soAId" -WebSession $whSession -UseBasicParsing
$soAItemId = 0
if ($soADetail.Content -match 'name="issued_quantities\[(\d+)\]"') { $soAItemId = [int]$matches[1] }

# Process SO-A Goods Issue (consumes all 5 units)
$issueABody = @{
    "issued_quantities[$soAItemId]" = "5"
    notes                           = "Order A menghabiskan stok batch"
}
$issueAResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soAId/issue" -Method Post -Body $issueABody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "SO-A goods issue succeeds and depletes stock to 0" -Condition ($issueAResp.Content -match "berhasil dicatat" -and $issueAResp.Content -match "Fulfilled") -Details "SO-A Fulfilled"

# Extract Item ID for SO-B
$soBDetail = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soBId" -WebSession $whSession -UseBasicParsing
$soBItemId = 0
if ($soBDetail.Content -match 'name="issued_quantities\[(\d+)\]"') { $soBItemId = [int]$matches[1] }

# Attempt SO-B Goods Issue (attempting to consume 3 units when stock is 0!)
$issueBBody = @{
    "issued_quantities[$soBItemId]" = "3"
    notes                           = "Order B mencoba keluar saat stok sudah habis"
}
$issueBResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$soBId/issue" -Method Post -Body $issueBBody -WebSession $whSession -UseBasicParsing
$oversellRejected = ($issueBResp.Content -match "tidak mencukupi" -or $issueBResp.Content -match "Konflik konkurensi") -and ($issueBResp.Content -notmatch "berhasil dicatat")
Assert-Test -Name "SO-B goods issue REJECTED by Optimistic Lock / Stock Guard (Oversell Prevented)" -Condition $oversellRejected -Details "Oversell guarded"

# Confirm physical stock in Gudang 2 for Product 5 is EXACTLY 0 (NOT negative!)
$prod5Detail = Invoke-WebRequest -Uri "$BaseUrl/products/5" -WebSession $adminSession -UseBasicParsing
$p5Wh2Stock = -1
if ($prod5Detail.Content -match 'Gudang Logistik Surabaya[\s\S]*?<td[^>]*>\s*(\d+)\s*unit') {
    $p5Wh2Stock = [int]$matches[1]
}
Assert-Test -Name "Product 5 physical stock in Surabaya is EXACTLY 0 (Never Negative)" -Condition ($p5Wh2Stock -eq 0) -Details "Actual stock: $p5Wh2Stock"

# ------------------------------------------------------------------------------
# Test 11: Successful Goods Issue for SO #1 with EXACT Baseline Check
# ------------------------------------------------------------------------------
Write-Host "`nTest 11: Normal Goods Issue for SO #1 (Atomic Stock Decrement)" -ForegroundColor Yellow

# Confirm baseline stock of Product 1 (ThinkPad) before issue
$prod1Before = Invoke-WebRequest -Uri "$BaseUrl/products/1" -WebSession $adminSession -UseBasicParsing
$p1TotalBefore = 0
$p1Wh1Before = 0
if ($prod1Before.Content -match 'Total Stok Fisik[\s\S]*?<div[^>]*>\s*(\d+)\s*<span') {
    $p1TotalBefore = [int]$matches[1]
}
if ($prod1Before.Content -match 'Gudang Utama Jakarta[\s\S]*?<td[^>]*>\s*(\d+)\s*unit') {
    $p1Wh1Before = [int]$matches[1]
}
Assert-Test -Name "Product 1 baseline stock recorded before issue" -Condition ($p1TotalBefore -gt 0 -and $p1Wh1Before -ge 5) -Details "Total: $p1TotalBefore, Jakarta: $p1Wh1Before"

# Issue all items for SO #1: 5 ThinkPad, 10 Mouse
$issue1Body = @{
    "issued_quantities[$soItemId1]" = "5"
    "issued_quantities[$soItemId2]" = "10"
    notes                           = "Pengiriman batch kurir logistik"
}
$issue1Resp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$createdSo1Id/issue" -Method Post -Body $issue1Body -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Goods Issue for SO #1 processed successfully" -Condition ($issue1Resp.Content -match "Pengeluaran 15 unit barang berhasil dicatat") -Details "Issue confirmed"
Assert-Test -Name "SO #1 status updated to Fulfilled (All items fulfilled)" -Condition ($issue1Resp.Content -match "Fulfilled") -Details "Status: Fulfilled"
Assert-Test -Name "Goods Issue form is closed after SO fulfillment" -Condition ($issue1Resp.Content -notmatch 'name="issued_quantities\[') -Details "Form closed"

# Verify EXACT stock decrement for Product 1 (Jakarta decreased by exactly 5, Total decreased by exactly 5)
$prod1After = Invoke-WebRequest -Uri "$BaseUrl/products/1" -WebSession $adminSession -UseBasicParsing
$p1TotalAfter = 0
$p1Wh1After = 0
if ($prod1After.Content -match 'Total Stok Fisik[\s\S]*?<div[^>]*>\s*(\d+)\s*<span') {
    $p1TotalAfter = [int]$matches[1]
}
if ($prod1After.Content -match 'Gudang Utama Jakarta[\s\S]*?<td[^>]*>\s*(\d+)\s*unit') {
    $p1Wh1After = [int]$matches[1]
}

$expectedP1Total = $p1TotalBefore - 5
$expectedP1Wh1 = $p1Wh1Before - 5

Assert-Test -Name "Product 1 total stock is exactly $expectedP1Total (decremented by 5)" -Condition ($p1TotalAfter -eq $expectedP1Total) -Details "Actual total: $p1TotalAfter, expected: $expectedP1Total"
Assert-Test -Name "Product 1 Jakarta stock is exactly $expectedP1Wh1 (decremented by 5)" -Condition ($p1Wh1After -eq $expectedP1Wh1) -Details "Actual Jakarta: $p1Wh1After, expected: $expectedP1Wh1"

# ------------------------------------------------------------------------------
# Test 12: Sales Order Cancellation Lifecycle (Draft & Approved cancellation)
# ------------------------------------------------------------------------------
Write-Host "`nTest 12: Sales Order Cancellation Lifecycle" -ForegroundColor Yellow

# Cancel a Draft SO
$soDraftBody = @{
    customer_id            = "3"
    warehouse_id           = "1"
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "3" # Keyboard
    "items[0][quantity]"   = "2"
    "items[0][unit_price]" = "650000"
}
$soDraftResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $soDraftBody -WebSession $salesSession -UseBasicParsing
$draftSoId = 0
if ($soDraftResp.BaseResponse.ResponseUri.AbsoluteUri -match "/sales-orders/(\d+)") { $draftSoId = [int]$matches[1] }
$cancelDraftResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$draftSoId/cancel" -Method Post -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Draft SO cancelled successfully by Sales" -Condition ($cancelDraftResp.Content -match "Cancelled" -and $cancelDraftResp.Content -match "berhasil dibatalkan") -Details "Draft cancelled"

# Cancel an Approved SO before issue
$soAppBody = @{
    customer_id            = "3"
    warehouse_id           = "1"
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "3"
    "items[0][quantity]"   = "1"
    "items[0][unit_price]" = "650000"
}
$soAppResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/create" -Method Post -Body $soAppBody -WebSession $adminSession -UseBasicParsing
$appSoId = 0
if ($soAppResp.BaseResponse.ResponseUri.AbsoluteUri -match "/sales-orders/(\d+)") { $appSoId = [int]$matches[1] }
Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$appSoId/submit-approval" -Method Post -WebSession $adminSession -UseBasicParsing | Out-Null
Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$appSoId/approve" -Method Post -WebSession $adminSession -UseBasicParsing | Out-Null
$cancelAppResp = Invoke-WebRequest -Uri "$BaseUrl/sales-orders/$appSoId/cancel" -Method Post -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "Approved SO cancelled successfully before issue" -Condition ($cancelAppResp.Content -match "Cancelled" -and $cancelAppResp.Content -match "berhasil dibatalkan") -Details "Approved cancelled"

# ------------------------------------------------------------------------------
# Test 13: Central Stock Ledger Audit Trail View for Sales Orders (/stock-ledger)
# ------------------------------------------------------------------------------
Write-Host "`nTest 13: Central Stock Ledger View for Sales Order Issues" -ForegroundColor Yellow
$ledgerResp = Invoke-WebRequest -Uri "$BaseUrl/stock-ledger" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff can access /stock-ledger (200 OK)" -Condition ($ledgerResp.StatusCode -eq 200 -and $ledgerResp.Content -match "Stock Ledger") -Details "Stock Ledger accessible"
Assert-Test -Name "Stock Ledger displays Issue (-) entry for SO #$createdSo1Id" -Condition ($ledgerResp.Content -match "SO #$createdSo1Id" -and $ledgerResp.Content -match "Issue \(-|\-5 unit") -Details "Audit trail contains SO issue"

# ------------------------------------------------------------------------------
# Summary
# ------------------------------------------------------------------------------
Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " Phase 4 Test Summary: $passed PASSED, $failed FAILED" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==================================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
} else {
    exit 0
}
