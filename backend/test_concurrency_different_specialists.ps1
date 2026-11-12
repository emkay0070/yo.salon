# Windows Concurrency Test - Different Specialists
# Launches two PHP processes to book the same time slot for different specialists simultaneously

$scriptPath = Join-Path $PSScriptRoot "concurrent_booking.php"

Write-Host "Starting concurrent booking test (different specialists)..."
Write-Host "Script Path: $scriptPath"

# Use a future time that's unlikely to have existing bookings
$testDate = (Get-Date).AddDays(1).ToString("yyyy-MM-dd")
# Use a fixed time during business hours (11:00 AM)
$testTime = "11:00"

Write-Host "Test Date: $testDate"
Write-Host "Test Time: $testTime"

# Get two different specialist IDs
$specialist1Id = php query_specialist_id.php
$specialist2Id = php query_specialist2_id.php

Write-Host "Specialist 1 ID: $specialist1Id"
Write-Host "Specialist 2 ID: $specialist2Id"

# Ensure both specialists are assigned to the same provider/location
$providerId = php query_provider_id.php
$locationId = php query_location_id.php $providerId

Write-Host "Provider ID: $providerId"
Write-Host "Location ID: $locationId"

# Create assignment for specialist 2 if it doesn't exist
php create_assignment.php $specialist2Id $providerId $locationId

# Clean up any existing booking for this time slot before the test
php cleanup_bookings.php $testTime

# Clean up barrier file if it exists
Remove-Item "concurrent_barrier.txt" -ErrorAction SilentlyContinue

# Start two processes in parallel with different specialists using Start-Process
Start-Process -FilePath "php" -ArgumentList "`"$scriptPath`"", "1", $specialist1Id, $testDate, $testTime -NoNewWindow -WorkingDirectory $PSScriptRoot -RedirectStandardOutput "output1.txt" -RedirectStandardError "error1.txt"
Start-Process -FilePath "php" -ArgumentList "`"$scriptPath`"", "2", $specialist2Id, $testDate, $testTime -NoNewWindow -WorkingDirectory $PSScriptRoot -RedirectStandardOutput "output2.txt" -RedirectStandardError "error2.txt"

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

# Strict validation: both should succeed for different specialists
if ($successCount -ne 2) {
    Write-Host "TEST FAILED: Expected both bookings to succeed for different specialists, only $successCount succeeded"
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Query PostgreSQL to verify database state
$dbCount1 = php query_booking_count.php $specialist1Id $testDate $testTime
$dbCount2 = php query_booking_count.php $specialist2Id $testDate $testTime

Write-Host "Database booking count for specialist 1: $dbCount1"
Write-Host "Database booking count for specialist 2: $dbCount2"

# Strict validation: exactly one booking should exist for each specialist
if ($dbCount1 -ne 1 -or $dbCount2 -ne 1) {
    Write-Host "TEST FAILED: Expected 1 booking per specialist in database, found specialist1: $dbCount1, specialist2: $dbCount2"
    Remove-Item "output1.txt", "output2.txt", "error1.txt", "error2.txt", "concurrent_barrier.txt" -ErrorAction SilentlyContinue
    exit 1
}

# Cleanup
Remove-Item "output1.txt" -ErrorAction SilentlyContinue
Remove-Item "output2.txt" -ErrorAction SilentlyContinue
Remove-Item "error1.txt" -ErrorAction SilentlyContinue
Remove-Item "error2.txt" -ErrorAction SilentlyContinue
Remove-Item "concurrent_barrier.txt" -ErrorAction SilentlyContinue

Write-Host "TEST PASSED: Both bookings succeeded for different specialists"
exit 0
