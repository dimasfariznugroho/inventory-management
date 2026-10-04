# ==============================================================================
# Phase 3 Verification Test Script: Purchase Order & Goods Receipt (PO-01)
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
Write-Host " Running Phase 3 Automated HTTP-Level Tests against $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

# ------------------------------------------------------------------------------
# Test 1: Unauthenticated access to /purchase-orders redirects to /login
# ------------------------------------------------------------------------------
Write-Host "`nTest 1: Unauthenticated access to /purchase-orders" -ForegroundColor Yellow
$guestPassed = $false
$guestDetails = ""
try {
    $res1 = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
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
        $resFallback = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders" -UseBasicParsing
        $guestPassed = ($resFallback.Content -match 'Masuk ke Sistem' -or $resFallback.Content -match 'Silakan login terlebih dahulu')
        $guestDetails = "Redirected to login page"
    }
}
Assert-Test -Name "Unauthenticated /purchase-orders redirects to /login" -Condition $guestPassed -Details $guestDetails

# ------------------------------------------------------------------------------
# Test 2: Sales Role Permissions (Read-only catalog & PO, Blocked from Create/Receipt)
# ------------------------------------------------------------------------------
Write-Host "`nTest 2: Sales Role PO Permissions & 403 Enforcement" -ForegroundColor Yellow
$salesSession = $null
$salesLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'sales1@inventory.local'; password = 'password123' } -SessionVariable salesSession -UseBasicParsing
Assert-Test -Name "Sales login succeeds" -Condition ($salesLogin.Content -match "Dashboard Sales" -or $salesLogin.StatusCode -eq 200) -Details "Status: $($salesLogin.StatusCode)"

$salesPoList = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders" -WebSession $salesSession -UseBasicParsing
Assert-Test -Name "Sales can view PO list (/purchase-orders 200 OK)" -Condition ($salesPoList.StatusCode -eq 200 -and $salesPoList.Content -match "Purchase Orders") -Details "PO list loaded"
Assert-Test -Name "Sales UI does NOT show 'Buat PO Baru' button" -Condition ($salesPoList.Content -notmatch "/purchase-orders/create") -Details "No create button in UI"

# Server-level 403 for Sales trying GET /purchase-orders/create
try {
    $sfGet = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $sfGetStatus = $sfGet.StatusCode
} catch {
    $sfGetStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Sales blocked from GET /purchase-orders/create (403 Forbidden)" -Condition ($sfGetStatus -eq 403) -Details "Status: $sfGetStatus"

# Server-level 403 for Sales trying POST /purchase-orders/create
try {
    $sfPost = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -Method Post -Body @{ supplier_id = 1; warehouse_id = 1 } -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    $sfPostStatus = $sfPost.StatusCode
} catch {
    $sfPostStatus = [int]$_.Exception.Response.StatusCode
}
Assert-Test -Name "Sales blocked from POST /purchase-orders/create (403 Forbidden)" -Condition ($sfPostStatus -eq 403) -Details "Status: $sfPostStatus"

# ------------------------------------------------------------------------------
# Test 3: Warehouse Staff Creates a New Purchase Order (Draft)
# ------------------------------------------------------------------------------
Write-Host "`nTest 3: Warehouse Staff Creates Purchase Order" -ForegroundColor Yellow
$whSession = $null
$whLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'warehouse1@inventory.local'; password = 'password123' } -SessionVariable whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff login succeeds" -Condition ($whLogin.Content -match "Dashboard Staf Gudang" -or $whLogin.StatusCode -eq 200) -Details "Status: $($whLogin.StatusCode)"

$whCreatePage = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff can open /purchase-orders/create (200 OK)" -Condition ($whCreatePage.StatusCode -eq 200) -Details "Form loaded"

# Submit new PO with 2 items:
# Item 1: Product 1 (ThinkPad), Qty = 10, Unit Price = 12000000
# Item 2: Product 2 (Mouse), Qty = 20, Unit Price = 150000
$poCreateBody = @{
    supplier_id             = "1"
    warehouse_id            = "1" # Gudang Utama Jakarta
    order_date              = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]"  = "1"
    "items[0][quantity]"    = "10"
    "items[0][unit_price]"  = "12000000"
    "items[1][product_id]"  = "2"
    "items[1][quantity]"    = "20"
    "items[1][unit_price]"  = "150000"
}

$poCreateResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -Method Post -Body $poCreateBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "PO created successfully" -Condition ($poCreateResp.Content -match "Purchase Order.*berhasil dibuat" -or $poCreateResp.Content -match "Draft") -Details "PO creation confirmed"

# Extract the newly created PO ID from URL or response
$createdPoId = $null
if ($poCreateResp.Content -match "Purchase Order:\s*(PO-[^<]+)") {
    $poNumber = $matches[1].Trim()
}
if ($poCreateResp.BaseResponse.ResponseUri.AbsoluteUri -match "/purchase-orders/(\d+)") {
    $createdPoId = [int]$matches[1]
} else {
    # Find latest PO ID from list
    $poList = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders" -WebSession $whSession -UseBasicParsing
    if ($poList.Content -match 'href="/purchase-orders/(\d+)"') {
        $createdPoId = [int]$matches[1]
    }
}
Assert-Test -Name "Retrieved newly created PO ID ($createdPoId)" -Condition ($createdPoId -ne $null -and $createdPoId -gt 0) -Details "PO ID: $createdPoId"

# Verify PO Detail shows status Draft and items
$poDetail = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "PO detail shows status Draft" -Condition ($poDetail.Content -match "Draft") -Details "Status check"
Assert-Test -Name "PO detail shows 'Mark as Ordered' button" -Condition ($poDetail.Content -match "Kirim Pesanan \(Mark as Ordered\)") -Details "Order action available"

# ------------------------------------------------------------------------------
# Test 4: Negative Test - Goods Receipt on Draft PO Rejected (Must Not Proceed)
# ------------------------------------------------------------------------------
Write-Host "`nTest 4: Goods Receipt on Draft PO Rejected" -ForegroundColor Yellow
$draftReceiptBody = @{
    "received_quantities[1]" = "2"
    notes                    = "Percobaan penerimaan saat PO masih Draft"
}
$draftReceiptResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId/receipt" -Method Post -Body $draftReceiptBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Goods receipt on Draft PO is rejected" -Condition ($draftReceiptResp.Content -match "tidak dapat diproses untuk status PO.*Draft" -and $draftReceiptResp.Content -notmatch "berhasil dicatat") -Details "Rejected with error message"
Assert-Test -Name "PO status remains Draft after rejected receipt attempt" -Condition ($draftReceiptResp.Content -match "Draft" -and $draftReceiptResp.Content -notmatch "PartiallyReceived") -Details "Status remains Draft"

# ------------------------------------------------------------------------------
# Test 5: Status Transition Draft -> Ordered
# ------------------------------------------------------------------------------
Write-Host "`nTest 5: Status Transition Draft -> Ordered" -ForegroundColor Yellow
$orderResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId/order" -Method Post -WebSession $whSession -UseBasicParsing
Assert-Test -Name "PO marked as Ordered" -Condition ($orderResp.Content -match "Ordered") -Details "Status changed to Ordered"
Assert-Test -Name "Goods Receipt form is now available on PO detail" -Condition ($orderResp.Content -match "Proses Penerimaan Barang \(Goods Receipt\)") -Details "Receipt form visible"

# Extract item IDs from detail HTML
$itemIds = @()
$matchesCol = [regex]::Matches($orderResp.Content, 'name="received_quantities\[(\d+)\]"')
foreach ($m in $matchesCol) {
    $itemIds += [int]$m.Groups[1].Value
}
Assert-Test -Name "Found 2 item inputs in receipt form" -Condition ($itemIds.Count -ge 2) -Details "Item IDs: $($itemIds -join ', ')"

$itemId1 = $itemIds[0]
$itemId2 = $itemIds[1]

# ------------------------------------------------------------------------------
# Test 6: Negative Test - Goods Receipt Exceeding Remaining Quantity Rejected
# ------------------------------------------------------------------------------
Write-Host "`nTest 6: Goods Receipt Exceeding Remaining Quantity Rejected" -ForegroundColor Yellow
$exceedReceiptBody = @{
    "received_quantities[$itemId1]" = "15" # Ordered 10, attempting to receive 15
    "received_quantities[$itemId2]" = "0"
    notes                           = "Percobaan penerimaan melebihi sisa pesanan"
}
$exceedReceiptResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId/receipt" -Method Post -Body $exceedReceiptBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Over-receipt quantity is rejected with clear error message" -Condition ($exceedReceiptResp.Content -match "melebihi sisa pesanan" -and $exceedReceiptResp.Content -notmatch "berhasil dicatat") -Details "Rejected with error message"
Assert-Test -Name "PO status remains Ordered without partial state change" -Condition ($exceedReceiptResp.Content -match "Ordered" -and $exceedReceiptResp.Content -notmatch "PartiallyReceived") -Details "Status remains Ordered"

# ------------------------------------------------------------------------------
# Test 7: Partial Goods Receipt (Atomic Transaction: Stock increment & Ledger log)
# ------------------------------------------------------------------------------
Write-Host "`nTest 7: Partial Goods Receipt (Receive 4 of 10 ThinkPad, 0 of 20 Mouse)" -ForegroundColor Yellow

# Baseline stock confirmation before receipt execution
$prodBefore = Invoke-WebRequest -Uri "$BaseUrl/products/1" -WebSession $whSession -UseBasicParsing
$baselineTotal = 0
$baselineWh1 = 0
if ($prodBefore.Content -match 'Total Stok Fisik[\s\S]*?<div[^>]*>\s*(\d+)\s*<span') {
    $baselineTotal = [int]$matches[1]
}
if ($prodBefore.Content -match 'Gudang Utama Jakarta[\s\S]*?<td[^>]*>\s*(\d+)\s*unit') {
    $baselineWh1 = [int]$matches[1]
}
Assert-Test -Name "Product 1 baseline stock confirmed (Total: 35, Jakarta: 25)" -Condition ($baselineTotal -eq 35 -and $baselineWh1 -eq 25) -Details "Total: $baselineTotal, Jakarta: $baselineWh1"

# Record Partial Receipt: 4 units for item 1, 0 units for item 2
$partialReceiptBody = @{
    "received_quantities[$itemId1]" = "4"
    "received_quantities[$itemId2]" = "0"
    notes                           = "Penerimaan Parsial Batch 1"
}

$receiptResp1 = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId/receipt" -Method Post -Body $partialReceiptBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Partial Goods Receipt processed successfully" -Condition ($receiptResp1.Content -match "Penerimaan 4 unit barang berhasil dicatat") -Details "Receipt response confirmed"
Assert-Test -Name "PO status updated to PartiallyReceived" -Condition ($receiptResp1.Content -match "PartiallyReceived") -Details "Status: PartiallyReceived"
Assert-Test -Name "Item 1 remaining quantity is now 6" -Condition ($receiptResp1.Content -match "6 unit" -or $receiptResp1.Content -match "6 pcs") -Details "Remaining: 6"

# Verify Stock Ledger History table on PO detail page
Assert-Test -Name "PO detail lists stock ledger receipt entry (+4)" -Condition ($receiptResp1.Content -match "\+4" -and $receiptResp1.Content -match "Penerimaan Parsial Batch 1") -Details "Ledger entry on PO page"

# Verify global product detail shows EXACT increased stock for ThinkPad:
# Total Stock: 35 + 4 = 39 (exact)
# Gudang Utama Jakarta: 25 + 4 = 29 (exact)
$prod1Detail = Invoke-WebRequest -Uri "$BaseUrl/products/1" -WebSession $whSession -UseBasicParsing
$actualTotal = 0
$actualWh1 = 0
if ($prod1Detail.Content -match 'Total Stok Fisik[\s\S]*?<div[^>]*>\s*(\d+)\s*<span') {
    $actualTotal = [int]$matches[1]
}
if ($prod1Detail.Content -match 'Gudang Utama Jakarta[\s\S]*?<td[^>]*>\s*(\d+)\s*unit') {
    $actualWh1 = [int]$matches[1]
}

$expectedTotal = $baselineTotal + 4
$expectedWh1 = $baselineWh1 + 4

Assert-Test -Name "Product 1 total stock is exactly $expectedTotal (baseline $baselineTotal + 4)" -Condition ($actualTotal -eq $expectedTotal -and $actualTotal -eq 39) -Details "Actual total: $actualTotal, expected: $expectedTotal"
Assert-Test -Name "Product 1 Gudang Jakarta stock is exactly $expectedWh1 (baseline $baselineWh1 + 4)" -Condition ($actualWh1 -eq $expectedWh1 -and $actualWh1 -eq 29) -Details "Actual Jakarta: $actualWh1, expected: $expectedWh1"

# ------------------------------------------------------------------------------
# Test 8: Complete Goods Receipt (Receive remaining 6 of ThinkPad, 20 of Mouse)
# ------------------------------------------------------------------------------
Write-Host "`nTest 8: Complete Goods Receipt (PO transitions to Received)" -ForegroundColor Yellow
$fullReceiptBody = @{
    "received_quantities[$itemId1]" = "6"
    "received_quantities[$itemId2]" = "20"
    notes                           = "Penerimaan Batch 2 - Pelunasan Lengkap"
}

$receiptResp2 = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$createdPoId/receipt" -Method Post -Body $fullReceiptBody -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Complete Goods Receipt processed successfully" -Condition ($receiptResp2.Content -match "Penerimaan 26 unit barang berhasil dicatat") -Details "Full receipt processed"
Assert-Test -Name "PO status updated to Received (Completed)" -Condition ($receiptResp2.Content -match "Received") -Details "Status: Received"
Assert-Test -Name "Goods Receipt input form is no longer shown (PO complete)" -Condition ($receiptResp2.Content -notmatch 'name="received_quantities\[') -Details "Receipt form closed"

# ------------------------------------------------------------------------------
# Test 9: Admin Creates & Cancels a Separate PO
# ------------------------------------------------------------------------------
Write-Host "`nTest 9: Admin Creates & Cancels a Draft PO" -ForegroundColor Yellow
$adminSession = $null
$adminLogin = Invoke-WebRequest -Uri "$BaseUrl/login" -Method Post -Body @{ email = 'admin@inventory.local'; password = 'password123' } -SessionVariable adminSession -UseBasicParsing

$po2Body = @{
    supplier_id            = "2"
    warehouse_id           = "2" # Surabaya
    order_date             = (Get-Date).ToString("yyyy-MM-dd")
    "items[0][product_id]" = "4"
    "items[0][quantity]"   = "50"
    "items[0][unit_price]" = "42000"
}
$po2Resp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/create" -Method Post -Body $po2Body -WebSession $adminSession -UseBasicParsing

$po2Id = $null
if ($po2Resp.BaseResponse.ResponseUri.AbsoluteUri -match "/purchase-orders/(\d+)") {
    $po2Id = [int]$matches[1]
}
Assert-Test -Name "Second PO created for cancel test ($po2Id)" -Condition ($po2Id -gt 0) -Details "PO ID: $po2Id"

$cancelResp = Invoke-WebRequest -Uri "$BaseUrl/purchase-orders/$po2Id/cancel" -Method Post -WebSession $adminSession -UseBasicParsing
Assert-Test -Name "PO cancelled successfully" -Condition ($cancelResp.Content -match "Cancelled" -and $cancelResp.Content -match "berhasil dibatalkan") -Details "Status: Cancelled"

# ------------------------------------------------------------------------------
# Test 10: Central Stock Ledger Audit Trail View (/stock-ledger)
# ------------------------------------------------------------------------------
Write-Host "`nTest 10: Central Stock Ledger View (/stock-ledger)" -ForegroundColor Yellow
$ledgerResp = Invoke-WebRequest -Uri "$BaseUrl/stock-ledger" -WebSession $whSession -UseBasicParsing
Assert-Test -Name "Warehouse Staff can access /stock-ledger (200 OK)" -Condition ($ledgerResp.StatusCode -eq 200 -and $ledgerResp.Content -match "Stock Ledger") -Details "Stock Ledger accessible"
Assert-Test -Name "Stock Ledger displays Receipt entries for PO #$createdPoId" -Condition ($ledgerResp.Content -match "PO #$createdPoId" -and $ledgerResp.Content -match "Receipt") -Details "Audit trail contains PO receipts"


# ------------------------------------------------------------------------------
# Summary
# ------------------------------------------------------------------------------
Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " Phase 3 Test Summary: $passed PASSED, $failed FAILED" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Red" })
Write-Host "==================================================================" -ForegroundColor Cyan

if ($failed -gt 0) {
    exit 1
} else {
    exit 0
}
