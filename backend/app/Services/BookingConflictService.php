<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingConflictService
{
    /**
     * Acquire PostgreSQL transaction-scoped advisory lock for a specialist
     *
     * @param string $specialistId
     * @param string $providerId
     * @return void
     */
    public function acquireSpecialistLock(string $specialistId, string $providerId): void
    {
        $lockKey = $this->deriveSpecialistLockKey($specialistId, $providerId);
        DB::statement("SELECT pg_advisory_xact_lock(?)", [$lockKey]);
    }

    /**
     * Acquire PostgreSQL transaction-scoped advisory lock for provider-level booking
     *
     * @param string $providerId
     * @return void
     */
    public function acquireProviderLock(string $providerId): void
    {
        $lockKey = $this->deriveProviderLockKey($providerId);
        DB::statement("SELECT pg_advisory_xact_lock(?)", [$lockKey]);
    }

    /**
     * Acquire PostgreSQL transaction-scoped advisory lock for a resource
     *
     * @param string $resourceId
     * @return void
     */
    public function acquireResourceLock(string $resourceId): void
    {
        $lockKey = $this->deriveResourceLockKey($resourceId);
        DB::statement("SELECT pg_advisory_xact_lock(?)", [$lockKey]);
    }

    /**
     * Acquire multiple resource locks in deterministic order
     *
     * @param array $resourceIds
     * @return void
     */
    public function acquireResourceLocks(array $resourceIds): void
    {
        // Sort by resource_id for deterministic ordering
        sort($resourceIds);
        
        foreach ($resourceIds as $resourceId) {
            $this->acquireResourceLock($resourceId);
        }
    }

    /**
     * Derive lock key for specialist using PostgreSQL hashtextextended
     *
     * @param string $specialistId
     * @param string $providerId
     * @return int
     */
    private function deriveSpecialistLockKey(string $specialistId, string $providerId): int
    {
        // Use complete namespaced identity: specialist:<specialist_uuid>:<provider_uuid>
        $identity = "specialist:{$specialistId}:{$providerId}";

        // Use PostgreSQL hashtextextended for stable 64-bit hash
        // Fixed seed of 0 for consistent hashing
        $result = DB::selectOne("SELECT hashtextextended(?, 0) as hash", [$identity]);
        return (int) $result->hash;
    }

    /**
     * Derive lock key for provider-level booking
     *
     * @param string $providerId
     * @return int
     */
    private function deriveProviderLockKey(string $providerId): int
    {
        // Use namespaced identity: provider:<UUID>
        $identity = "provider:{$providerId}";

        // Use PostgreSQL hashtextextended for stable 64-bit hash
        // Fixed seed of 0 for consistent hashing
        $result = DB::selectOne("SELECT hashtextextended(?, 0) as hash", [$identity]);
        return (int) $result->hash;
    }

    /**
     * Derive lock key for resource
     *
     * @param string $resourceId
     * @return int
     */
    private function deriveResourceLockKey(string $resourceId): int
    {
        // Use namespaced identity: resource:<UUID>
        $identity = "resource:{$resourceId}";

        // Use PostgreSQL hashtextextended for stable 64-bit hash
        // Fixed seed of 0 for consistent hashing
        $result = DB::selectOne("SELECT hashtextextended(?, 0) as hash", [$identity]);
        return (int) $result->hash;
    }

    /**
     * Calculate occupied interval including buffers
     *
     * @param Carbon $serviceStart
     * @param Carbon $serviceEnd
     * @param Service $service
     * @param object|null $locationServiceOverride
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    public function calculateOccupiedInterval(
        Carbon $serviceStart,
        Carbon $serviceEnd,
        Service $service,
        ?object $locationServiceOverride = null
    ): array {
        $bufferBefore = $this->getBufferBefore($service, $locationServiceOverride);
        $bufferAfter = $this->getBufferAfter($service, $locationServiceOverride);
        
        $occupiedStart = $serviceStart->copy()->subMinutes($bufferBefore);
        $occupiedEnd = $serviceEnd->copy()->addMinutes($bufferAfter);
        
        return [
            'start' => $occupiedStart,
            'end' => $occupiedEnd,
        ];
    }

    /**
     * Get buffer before value with location override
     *
     * @param Service $service
     * @param object|null $locationServiceOverride
     * @return int
     */
    private function getBufferBefore(Service $service, ?object $locationServiceOverride = null): int
    {
        return $locationServiceOverride?->buffer_before_override
            ?? $service->buffer_before
            ?? 0;
    }

    /**
     * Get buffer after value with location override
     *
     * @param Service $service
     * @param object|null $locationServiceOverride
     * @return int
     */
    private function getBufferAfter(Service $service, ?object $locationServiceOverride = null): int
    {
        return $locationServiceOverride?->buffer_after_override
            ?? $service->buffer_after
            ?? 0;
    }

    /**
     * Determine if a booking currently reserves time (lifecycle-aware)
     *
     * @param Booking $booking
     * @return bool
     */
    public function bookingReservesTime(Booking $booking): bool
    {
        // Check status
        if (!in_array($booking->status, ['confirmed', 'pending', 'reserved', 'pending_payment'])) {
            return false;
        }
        
        // For reserved status, check if hold is still valid
        if ($booking->status === 'reserved') {
            if ($booking->slot_locked_until && $booking->slot_locked_until < now()) {
                return false; // Expired hold, does not reserve time
            }
        }
        
        return true;
    }

    /**
     * Detect interval conflicts including legacy bookings
     *
     * @param string $specialistId
     * @param string $locationId
     * @param Carbon $occupiedStart
     * @param Carbon $occupiedEnd
     * @param string|null $excludeBookingId
     * @return bool
     */
    public function hasIntervalConflict(
        string $specialistId,
        string $locationId,
        Carbon $occupiedStart,
        Carbon $occupiedEnd,
        ?string $excludeBookingId = null
    ): bool {
        // Get all bookings for this specialist/location that reserve time
        $query = Booking::where('specialist_id', $specialistId)
            ->where('location_id', $locationId)
            ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
            ->where(function ($q) {
                // Lifecycle-aware check for reserved status
                $q->where('status', '!=', 'reserved')
                  ->orWhereNull('slot_locked_until')
                  ->orWhere('slot_locked_until', '>', now());
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $bookings = $query->get();

        foreach ($bookings as $booking) {
            // Calculate occupied interval for this booking
            $bookingOccupied = $this->calculateBookingOccupiedInterval($booking);

            // Check for overlap
            if ($this->intervalsOverlap($occupiedStart, $occupiedEnd, $bookingOccupied['start'], $bookingOccupied['end'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate occupied interval for a booking (handles both new and legacy)
     *
     * @param Booking $booking
     * @return array ['start' => Carbon, 'end' => Carbon]
     */
    private function calculateBookingOccupiedInterval(Booking $booking): array
    {
        $service = $booking->service;

        if ($booking->bookingServices()->exists()) {
            // New booking with BookingService records
            $bookingService = $booking->bookingServices->first();
            $serviceStart = $bookingService->start_time;
            $serviceEnd = $bookingService->end_time;
        } else {
            // Legacy booking without BookingService records
            // Extract date portion only to avoid double time specification
            $dateOnly = Carbon::parse($booking->date)->toDateString();
            $serviceStart = Carbon::parse("{$dateOnly} {$booking->time}");
            $serviceEnd = $serviceStart->copy()->addMinutes($service->duration);
        }

        return $this->calculateOccupiedInterval($serviceStart, $serviceEnd, $service);
    }

    /**
     * Check if two time intervals overlap
     *
     * @param Carbon $start1
     * @param Carbon $end1
     * @param Carbon $start2
     * @param Carbon $end2
     * @return bool
     */
    private function intervalsOverlap(Carbon $start1, Carbon $end1, Carbon $start2, Carbon $end2): bool
    {
        return $start1->lt($end2) && $end1->gt($start2);
    }

    /**
     * Detect interval conflicts for provider-level bookings (no specialist)
     *
     * @param string $providerId
     * @param string|null $locationId
     * @param Carbon $occupiedStart
     * @param Carbon $occupiedEnd
     * @param string|null $excludeBookingId
     * @return bool
     */
    public function hasProviderLevelConflict(
        string $providerId,
        ?string $locationId,
        Carbon $occupiedStart,
        Carbon $occupiedEnd,
        ?string $excludeBookingId = null
    ): bool {
        $query = Booking::where('provider_id', $providerId)
            ->whereNull('specialist_id')
            ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
            ->where(function ($q) {
                $q->where('status', '!=', 'reserved')
                  ->orWhereNull('slot_locked_until')
                  ->orWhere('slot_locked_until', '>', now());
            });
        
        if ($locationId) {
            $query->where('location_id', $locationId);
        }
        
        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }
        
        // Check conflicts with BookingService records
        $hasBookingServiceConflict = $query->whereHas('bookingServices', function ($q) use ($occupiedStart, $occupiedEnd) {
            $q->where(function ($inner) use ($occupiedStart, $occupiedEnd) {
                $inner->where('start_time', '<', $occupiedEnd)
                      ->where('end_time', '>', $occupiedStart);
            });
        })->exists();
        
        if ($hasBookingServiceConflict) {
            return true;
        }
        
        // Check conflicts with legacy bookings
        $hasLegacyConflict = $query->whereDoesntHave('bookingServices')
            ->whereDate('date', $occupiedStart->toDateString())
            ->where('time', $occupiedStart->format('H:i'))
            ->exists();
        
        return $hasLegacyConflict;
    }
}
