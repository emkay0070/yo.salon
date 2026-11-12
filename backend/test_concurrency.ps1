# Windows Concurrency Test - Same Specialist
# Launches two PHP processes to book the same slot simultaneously

$scriptPath = Join-Path $PSScriptRoot "concurrent_booking.php"

Write-Host "Starting concurrent booking test (same specialist)..."
Write-Host "Script Path: $scriptPath"

# Use a future time that's unlikely to have existing bookings
$testDate = (Get-Date).AddDays(1).ToString("yyyy-MM-dd")
# Use a fixed time during business hours (10:00 AM)
$testTime = "10:00"

Write-Host "Test Date: $testDate"
Write-Host "Test Time: $testTime"

# Clean up any existing booking for this time slot before the test
php cleanup_bookings.php $testTime

# Clean up barrier file if it exists
Remove-Item "concurrent_barrier.txt" -ErrorAction SilentlyContinue

# Start two processes in parallel with the same time slot using Start-Process
Start-Process -FilePath "php" -ArgumentList "`"$scriptPath`"", "1", "skip", $testDate, $testTime -NoNewWindow -WorkingDirectory $PSScriptRoot -RedirectStandardOutput "output1.txt" -RedirectStandardError "error1.txt"
Start-Process -FilePath "php" -ArgumentList "`"$scriptPath`"", "2", "skip", $testDate, $testTime -NoNewWindow -WorkingDirectory $PSScriptRoot -RedirectStandardOutput "output2.txt" -RedirectStandardError "error2.txt"

# Wait for processes to complete
Start-Sleep -Seconds 5

# Read results
$result1 = Get-Content "output1.txt" -Raw -ErrorAction SilentlyContinue
$result2 = Get-Content "output2.txt" -Raw -ErrorAction SilentlyContinue
$error1 = Get-Content "error1.txt" -Raw -ErrorAction SilentlyContinue
$error2 = Get-Content "error2.txt" -Raw -ErrorAction SilentlyContinue

Write-Host "Process 1 Result: $result1"
Write-Host "Process 2 Result: $result2"

# Strict validation: check for crashes or missing output
if ([string]::IsNullOrWhiteSpace($result1)) {
    Write-Host "TEST FAILED: Process 1 produced no output"
    if (-not [string]::IsNullOrWhiteSpace($error1)) {
        Write-Host "Process 1 Error: $error1"
    }
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

if ([string]::IsNullOrWhiteSpace($result2)) {
    Write-Host "TEST FAILED: Process 2 produced no output"
    if (-not [string]::IsNullOrWhiteSpace($error2)) {
        Write-Host "Process 2 Error: $error2"
    }
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Count successes
$successCount = 0
$bookingId1 = ""
$bookingId2 = ""

if ($result1 -match "^SUCCESS:(.+)") {
    $successCount++
    $bookingId1 = $matches[1].Trim()
}
if ($result2 -match "^SUCCESS:(.+)") {
    $successCount++
    $bookingId2 = $matches[1].Trim()
}

Write-Host "Success Count: $successCount"

# Strict validation: both should not fail
if ($successCount -eq 0) {
    Write-Host "TEST FAILED: Both processes failed (at least one should succeed)"
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Strict validation: both should not succeed
if ($successCount -eq 2) {
    Write-Host "TEST FAILED: Both bookings succeeded (should not happen - race condition not prevented)"
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Query PostgreSQL to verify database state
$specialistId = php query_specialist_id.php
$dbCount = php query_booking_count.php $specialistId $testDate $testTime

Write-Host "Database booking count for slot: $dbCount"

# Strict validation: exactly one booking should exist in database
if ($dbCount -ne 1) {
    Write-Host "TEST FAILED: Expected 1 booking in database, found $dbCount"
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Cleanup
Remove-Item "output1.txt" -ErrorAction SilentlyContinue
Remove-Item "output2.txt" -ErrorAction SilentlyContinue
Remove-Item "error1.txt" -ErrorAction SilentlyContinue
Remove-Item "error2.txt" -ErrorAction SilentlyContinue
Remove-Item "concurrent_barrier.txt" -ErrorAction SilentlyContinue

Write-Host "TEST PASSED: Exactly one booking succeeded and persisted in database"
exit 0
