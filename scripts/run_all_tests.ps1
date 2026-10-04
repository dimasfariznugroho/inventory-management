# ==============================================================================
# Complete Regression Test Runner (Phases 1 - 5)
# ==============================================================================
param (
    [string]$BaseUrl = "http://localhost:8080"
)

$candidatePhpPaths = @(
    "c:\Program Files\xampp\php\php.exe",
    "c:\xampp\php\php.exe"
)
$phpCmd = $candidatePhpPaths | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $phpCmd) {
    $phpInPath = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpInPath) { $phpCmd = $phpInPath.Source }
}

function Reset-Database {
    if ($phpCmd) {
        $migrateScript = Join-Path $PSScriptRoot "..\database\migrate.php"
        & $phpCmd -f $migrateScript -- --host=127.0.0.1 --port=3306 --user=inventory_user --pass=secret --database=inventory_db | Out-Null
    }
}

Write-Host "==================================================================" -ForegroundColor Cyan
Write-Host " STARTING FULL REGRESSION SUITE (PHASES 1 - 6)" -ForegroundColor Cyan
Write-Host " Target: $BaseUrl" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan

$results = @{}

# Phase 1
Write-Host "`n>>> Running Phase 1..." -ForegroundColor Magenta
Reset-Database
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase1_http.ps1") -BaseUrl $BaseUrl
$results["Phase 1 (Auth & RBAC)"] = ($LASTEXITCODE -eq 0)

# Phase 2
Write-Host "`n>>> Running Phase 2..." -ForegroundColor Magenta
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase2_http.ps1") -BaseUrl $BaseUrl
$results["Phase 2 (Catalog & Master Data)"] = ($LASTEXITCODE -eq 0)

# Phase 3
Write-Host "`n>>> Running Phase 3..." -ForegroundColor Magenta
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase3_http.ps1") -BaseUrl $BaseUrl
$results["Phase 3 (Purchase Orders & Receipt)"] = ($LASTEXITCODE -eq 0)

# Phase 4
Write-Host "`n>>> Running Phase 4..." -ForegroundColor Magenta
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase4_http.ps1") -BaseUrl $BaseUrl
$results["Phase 4 (Sales Orders & Goods Issue)"] = ($LASTEXITCODE -eq 0)

# Phase 5
Write-Host "`n>>> Running Phase 5..." -ForegroundColor Magenta
Reset-Database
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase5_http.ps1") -BaseUrl $BaseUrl
$results["Phase 5 (Listing, Dashboards, Reports, API)"] = ($LASTEXITCODE -eq 0)

# Phase 6
Write-Host "`n>>> Running Phase 6..." -ForegroundColor Magenta
& powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot "test_phase6_http.ps1") -BaseUrl $BaseUrl
$results["Phase 6 (Validation, Error Handling, UI 360px, CLI Job)"] = ($LASTEXITCODE -eq 0)

Write-Host "`n==================================================================" -ForegroundColor Cyan
Write-Host " FULL REGRESSION SUITE SUMMARY (PHASES 1 - 6)" -ForegroundColor Cyan
Write-Host "==================================================================" -ForegroundColor Cyan
$allPass = $true
foreach ($k in $results.Keys) {
    if ($results[$k]) {
        Write-Host "  [PASS] $k" -ForegroundColor Green
    } else {
        Write-Host "  [FAIL] $k" -ForegroundColor Red
        $allPass = $false
    }
}
Write-Host "==================================================================" -ForegroundColor Cyan
if ($allPass) {
    Write-Host "ALL 6 PHASES PASSED WITH 100% SUCCESS!" -ForegroundColor Green
    exit 0
} else {
    Write-Host "SOME TESTS FAILED!" -ForegroundColor Red
    exit 1
}
