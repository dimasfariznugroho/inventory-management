# ==============================================================================
# HTTP-Level Automated Verification Script for Phase 1
# Tests: Authentication, Sessions, Guards, and Role Authorization
# ==============================================================================

$baseUrl = "http://localhost:8080"
$allPassed = $true

function Write-TestResult($name, $passed, $details) {
    if ($passed) {
        Write-Host "[PASS] $name - $details" -ForegroundColor Green
    } else {
        Write-Host "[FAIL] $name - $details" -ForegroundColor Red
        $global:allPassed = $false
    }
}

Write-Host "`n=== STARTING HTTP-LEVEL TESTS FOR PHASE 1 ($baseUrl) ===`n"

# ------------------------------------------------------------------------------
# Test 1: GET /admin/dashboard WITHOUT session -> Redirect to /login
# ------------------------------------------------------------------------------
try {
    $res1 = Invoke-WebRequest -Uri "$baseUrl/admin/dashboard" -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    $passed1 = ($res1.StatusCode -eq 302 -and $res1.Headers['Location'] -match '/login')
    Write-TestResult "1. Access Protected Page Without Session" $passed1 "Status: $($res1.StatusCode), Redirect: $($res1.Headers['Location'])"
} catch {
    $response = $_.Exception.Response
    if ($response -and $response.StatusCode -eq 302) {
        $loc = $response.Headers['Location']
        $passed1 = ($loc -match '/login')
        Write-TestResult "1. Access Protected Page Without Session" $passed1 "Status: 302, Redirect Location: $loc"
    } else {
        # If redirected through to login page
        $res1Fallback = Invoke-WebRequest -Uri "$baseUrl/admin/dashboard" -UseBasicParsing
        $passed1 = ($res1Fallback.Content -match 'Masuk ke Sistem' -or $res1Fallback.Content -match 'Silakan login terlebih dahulu')
        Write-TestResult "1. Access Protected Page Without Session" $passed1 "Redirected to Login Form (Found: 'Masuk ke Sistem')"
    }
}

# ------------------------------------------------------------------------------
# Test 2: POST login as admin@inventory.local -> Redirect to /admin/dashboard & Cookie formed
# ------------------------------------------------------------------------------
$adminBody = @{
    email    = 'admin@inventory.local'
    password = 'password123'
}

$uriObj = New-Object System.Uri($baseUrl)
try {
    $res2 = Invoke-WebRequest -Uri "$baseUrl/login" -Method Post -Body $adminBody -SessionVariable adminSession -UseBasicParsing -MaximumRedirection 0 -ErrorAction Stop
    $loc2 = $res2.Headers['Location']
    $cookies = $adminSession.Cookies.GetCookies($uriObj)
    $hasSessionCookie = ($cookies['PHPSESSID'] -ne $null)
    $passed2 = ($res2.StatusCode -eq 302 -and $loc2 -match '/admin/dashboard' -and $hasSessionCookie)
    Write-TestResult "2. Admin Login (admin@inventory.local)" $passed2 "Redirect: $loc2, Session Cookie created: $($cookies['PHPSESSID'].Value.Substring(0,8))..."
} catch {
    $response2 = $_.Exception.Response
    if ($response2 -and [int]$response2.StatusCode -eq 302) {
        $loc2 = $response2.Headers['Location']
        $cookies = $adminSession.Cookies.GetCookies($uriObj)
        $hasSessionCookie = ($cookies['PHPSESSID'] -ne $null)
        $passed2 = ($loc2 -match '/admin/dashboard' -and $hasSessionCookie)
        Write-TestResult "2. Admin Login (admin@inventory.local)" $passed2 "Redirect: $loc2, Session Cookie created: $($cookies['PHPSESSID'].Value.Substring(0,8))..."
    } else {
        # Test by following redirect to verify authenticated dashboard
        $res2Follow = Invoke-WebRequest -Uri "$baseUrl/login" -Method Post -Body $adminBody -SessionVariable adminSession -UseBasicParsing
        $cookies = $adminSession.Cookies.GetCookies($uriObj)
        $hasSessionCookie = ($cookies['PHPSESSID'] -ne $null)
        $passed2 = ($res2Follow.Content -match 'Dashboard Administrator' -and $hasSessionCookie)
        Write-TestResult "2. Admin Login (admin@inventory.local)" $passed2 "Landed on Dashboard Administrator, Session Cookie: $($cookies['PHPSESSID'].Value.Substring(0,8))..."
    }
}

# ------------------------------------------------------------------------------
# Test 3: POST login with WRONG password -> Generic error message
# ------------------------------------------------------------------------------
$wrongBody = @{
    email    = 'admin@inventory.local'
    password = 'wrongpassword123'
}

$res3 = Invoke-WebRequest -Uri "$baseUrl/login" -Method Post -Body $wrongBody -UseBasicParsing
$passed3 = ($res3.Content -match 'Email atau password yang Anda masukkan salah')
Write-TestResult "3. Login With Wrong Password" $passed3 "Contains generic message: 'Email atau password yang Anda masukkan salah'"

# ------------------------------------------------------------------------------
# Test 4: POST login with INACTIVE user -> Rejection message
# ------------------------------------------------------------------------------
$inactiveBody = @{
    email    = 'inactive@inventory.local'
    password = 'password123'
}

$res4 = Invoke-WebRequest -Uri "$baseUrl/login" -Method Post -Body $inactiveBody -UseBasicParsing
$passed4 = ($res4.Content -match 'berstatus nonaktif')
Write-TestResult "4. Login With Inactive Account" $passed4 "Rejected with message containing: 'berstatus nonaktif'"

# ------------------------------------------------------------------------------
# Test 5: GET /admin/users using ADMIN session -> Status 200 & User Table
# ------------------------------------------------------------------------------
$res5 = Invoke-WebRequest -Uri "$baseUrl/admin/users" -WebSession $adminSession -UseBasicParsing
$passed5 = ($res5.StatusCode -eq 200 -and $res5.Content -match 'Manajemen Pengguna \(USR-01\)' -and $res5.Content -match 'sales1@inventory.local')
Write-TestResult "5. Admin Session Accessing /admin/users" $passed5 "Status: $($res5.StatusCode), Found 'Manajemen Pengguna (USR-01)' & user list"

# ------------------------------------------------------------------------------
# Test 6: Login as sales1@inventory.local, then GET /admin/users -> 403 Forbidden!
# ------------------------------------------------------------------------------
$salesBody = @{
    email    = 'sales1@inventory.local'
    password = 'password123'
}

try {
    # Login as sales
    $salesLogin = Invoke-WebRequest -Uri "$baseUrl/login" -Method Post -Body $salesBody -SessionVariable salesSession -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue
} catch {
    # 302 expected
}

try {
    $res6 = Invoke-WebRequest -Uri "$baseUrl/admin/users" -WebSession $salesSession -UseBasicParsing -ErrorAction Stop
    Write-TestResult "6. Sales Session Accessing /admin/users" $false "Expected 403, but received status $($res6.StatusCode)"
} catch {
    $response6 = $_.Exception.Response
    if ($response6 -and [int]$response6.StatusCode -eq 403) {
        Write-TestResult "6. Sales Session Accessing /admin/users" $true "Server returned HTTP 403 Forbidden (Strictly enforced at server level)"
    } else {
        Write-TestResult "6. Sales Session Accessing /admin/users" $false "Received status: $($response6.StatusCode)"
    }
}

Write-Host "`n=== SUMMARY OF PHASE 1 VERIFICATION ==="
if ($allPassed) {
    Write-Host "ALL 6 HTTP TESTS PASSED SUCCESSFULLY!`n" -ForegroundColor Green
    exit 0
} else {
    Write-Host "SOME TESTS FAILED!`n" -ForegroundColor Red
    exit 1
}
