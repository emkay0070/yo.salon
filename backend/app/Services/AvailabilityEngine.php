<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\Specialist;
use App\Models\Service;
use App\Models\Booking;
use App\Models\SalonSchedule;
use App\Domain\Availability\DTOs\AvailabilityResult;
use App\Domain\Availability\DTOs\ResolvedSchedule;
use App\Domain\Availability\Statuses\AvailabilityDomainStatus;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AvailabilityEngine
{
    /** Slot granularity in minutes for time slot generation */
    private const SLOT_GRANULARITY_MINUTES = 15;
    
    /** Slot lock duration in minutes for concurrent booking protection */
    private const SLOT_LOCK_MINUTES = 10;

    /**
     * Get available time slots for a salon on a specific date.
     * Returns a typed AvailabilityResult with domain status and slots.
     *
     * @param string $salonId Salon UUID
     * @param string $date Date in Y-m-d format
     * @param string|null $serviceId Optional service filter
     * @param string|null $specialistId Optional specialist filter
     * @param int $slotGranularity Slot size in minutes
     * @return AvailabilityResult Typed availability result
     */
    public function getAvailableSlots(
        string $salonId,
        string $date,
        ?string $serviceId = null,
        ?string $specialistId = null,
        int $slotGranularity = self::SLOT_GRANULARITY_MINUTES
    ): AvailabilityResult {
        $cacheKey = $this->getCacheKey($salonId, $date, $serviceId, $specialistId);

        /** @var AvailabilityResult $cached */
        $cached = Cache::remember($cacheKey, 300, function () use (
            $salonId,
            $date,
            $serviceId,
            $specialistId,
            $slotGranularity
        ) {
            return $this->computeAvailableSlots(
                $salonId,
                $date,
                $serviceId,
                $specialistId,
                $slotGranularity,
            );
        });

        return $cached;
    }

    /**
     * Core availability computation logic.
     * Checks configuration, resolves schedules, generates slots, determines status.
     *
     * @return AvailabilityResult Computed availability with domain status
     */
    private function computeAvailableSlots(
        string $salonId,
        string $date,
        ?string $serviceId,
        ?string $specialistId,
        int $slotGranularity,
    ): AvailabilityResult {
        $salon = Salon::findOrFail($salonId);
        $service = $serviceId ? Service::findOrFail($serviceId) : null;
        $specialist = $specialistId ? Specialist::findOrFail($specialistId) : null;

        $this->validateBookingWindow($date, $service);

        $preflight = $this->preflightConfigurationStatuses($salon, $specialistId, $date);

        $salonSchedule = $this->getSalonSchedule($salonId, $date);

        if (in_array(AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED, $preflight, true)) {
            return $this->buildEmptyResult(
                salonId: $salonId,
                date: $date,
                status: AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED,
                allStatuses: $preflight,
                isClosed: false,
            );
        }

        if (!$salonSchedule) {
            return $this->buildEmptyResult(
                salonId: $salonId,
                date: $date,
                status: AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED,
                allStatuses: [...$preflight, AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED],
                isClosed: false,
            );
        }

        if ($salonSchedule->isClosed) {
            return $this->buildEmptyResult(
                salonId: $salonId,
                date: $date,
                status: AvailabilityDomainStatus::BRANCH_CLOSED,
                allStatuses: [...$preflight, AvailabilityDomainStatus::BRANCH_CLOSED],
                isClosed: true,
            );
        }

        $assignmentResolved = null;
        if ($specialistId) {
            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('salon_id', $salonId)
                ->first();

            if (!$assignment) {
                return $this->buildEmptyResult(
                    salonId: $salonId,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED],
                    isClosed: false,
                );
            }

            $assignmentScheduleModel = \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)
                ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                ->where('day_of_week', Carbon::parse($date)->format('l'))
                ->first();

            $resolver = new \App\Domain\Availability\Resolvers\ScheduleResolver();

            $providerScheduleModel = \App\Models\ProviderSchedule::where('provider_id', $salon->provider_id)
                ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
                ->first();

            $salonScheduleModel = \App\Models\SalonSchedule::where('salon_id', $salonId)
                ->where('day_of_week', Carbon::parse($date)->format('l'))
                ->first();

            $assignmentResolved = $resolver->resolve(
                providerSchedule: $providerScheduleModel,
                salonSchedule: $salonScheduleModel,
                salonMode: $salon->schedule_mode ?? 'INHERIT',
                assignmentSchedule: $assignmentScheduleModel,
                assignmentMode: $assignment->schedule_mode ?? 'INHERIT',
            );

            if (!$assignmentResolved) {
                return $this->buildEmptyResult(
                    salonId: $salonId,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED],
                    isClosed: false,
                );
            }

            if ($assignmentResolved->isClosed) {
                return $this->buildEmptyResult(
                    salonId: $salonId,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_OFF,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_OFF],
                    isClosed: true,
                );
            }

            $salonSchedule = $assignmentResolved;
        }

        $existingBookings = $this->getExistingBookings($salonId, $date, $specialistId);

        $availableSpecialists = $serviceId
            ? $this->getAvailableSpecialists($salonId, $serviceId, $date)
            : [];

        $slots = $this->generateTimeSlots(
            schedule: $salonSchedule,
            existingBookings: $existingBookings,
            service: $service,
            availableSpecialists: $availableSpecialists,
            date: $date,
            timezone: $salon->timezone ?? 'Africa/Kampala',
            slotGranularity: $slotGranularity,
        );

        return $this->finalizeResult(
            base: [
                'salon_id' => $salonId,
                'salon_name' => $salon->name,
                'date' => $date,
                'timezone' => $salon->timezone ?? 'Africa/Kampala',
                'operating_hours' => [
                    'open' => $salonSchedule->openTime,
                    'close' => $salonSchedule->closeTime,
                ],
                'is_closed' => false,
            ],
            schedule: $salonSchedule,
            slots: $slots,
            preflightStatuses: $preflight,
        );
    }

    public function getBatchAvailability(
        string $salonId,
        string $startDate,
        string $endDate,
        ?string $serviceId = null,
        ?string $specialistId = null
    ): array {
        $dates = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $availability = $this->getAvailableSlots($salonId, $dateStr, $serviceId, $specialistId);

            $dates[] = [
                'date' => $dateStr,
                'is_closed' => $availability->isClosed,
                'available_slots' => $availability->totalAvailableSlots,
                'first_slot' => $availability->firstAvailableSlot['start'] ?? null,
                'status' => $availability->status->value,
            ];
        }

        return [
            'salon_id' => $salonId,
            'dates' => $dates,
        ];
    }

    public function getFirstAvailableSlot(
        string $salonId,
        ?string $serviceId = null,
        ?string $specialistId = null,
        int $daysAhead = 30
    ): ?array {
        $startDate = Carbon::now()->format('Y-m-d');
        $endDate = Carbon::now()->addDays($daysAhead)->format('Y-m-d');

        $batch = $this->getBatchAvailability($salonId, $startDate, $endDate, $serviceId, $specialistId);

        foreach ($batch['dates'] as $date) {
            if (!$date['is_closed'] && $date['available_slots'] > 0) {
                $availability = $this->getAvailableSlots(
                    $salonId,
                    $date['date'],
                    $serviceId,
                    $specialistId
                );

                $first = $availability->firstAvailableSlot;
                if (!$first) {
                    continue;
                }

                return [
                    'date' => $date['date'],
                    'start_time' => $first['start'],
                    'end_time' => $first['end'],
                    'specialist' => $first['available_specialists'][0] ?? null,
                ];
            }
        }

        return null;
    }

    public function lockSlot(
        string $salonId,
        string $date,
        string $startTime,
        string $serviceId,
        ?string $specialistId = null
    ): array {
        $lockId = (string) Str::uuid();
        $expiresAt = Carbon::now()->addMinutes(self::SLOT_LOCK_MINUTES);

        $booking = Booking::create([
            'id' => $lockId,
            'salon_id' => $salonId,
            'specialist_id' => $specialistId,
            'service_id' => $serviceId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $this->calculateEndTime($startTime, $serviceId),
            'status' => 'pending',
            'slot_locked_until' => $expiresAt,
        ]);

        $this->clearAvailabilityCache($salonId, $date, $serviceId, $specialistId);

        return [
            'lock_id' => $lockId,
            'expires_at' => $expiresAt->toISOString(),
            'slot' => [
                'start' => $startTime,
                'end' => $booking->end_time,
            ],
        ];
    }

    public function releaseSlot(string $lockId): void
    {
        $booking = Booking::where('id', $lockId)
            ->where('status', 'pending')
            ->where('slot_locked_until', '>', Carbon::now())
            ->first();

        if ($booking) {
            $this->clearAvailabilityCache(
                $booking->salon_id,
                $booking->date,
                $booking->service_id,
                $booking->specialist_id
            );

            $booking->delete();
        }
    }

    private function getSalonSchedule(string $salonId, string $date): ?ResolvedSchedule
    {
        $salon = Salon::findOrFail($salonId);
        $dayName = Carbon::parse($date)->format('l');
        $dayOfWeekInt = Carbon::parse($date)->dayOfWeek;

        $providerSchedule = \App\Models\ProviderSchedule::where('provider_id', $salon->provider_id)
            ->where('day_of_week', $dayOfWeekInt)
            ->first();

        $salonSchedule = SalonSchedule::where('salon_id', $salonId)
            ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
            ->where('day_of_week', $dayName)
            ->first();

        $resolver = new \App\Domain\Availability\Resolvers\ScheduleResolver();

        return $resolver->resolve(
            providerSchedule: $providerSchedule,
            salonSchedule: $salonSchedule,
            salonMode: $salon->schedule_mode ?? 'INHERIT',
            assignmentSchedule: null,
            assignmentMode: 'INHERIT',
        );
    }

    private function getExistingBookings(
        string $salonId,
        string $date,
        ?string $specialistId = null
    ): array {
        $query = Booking::where('salon_id', $salonId)
            ->where('date', $date)
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query) {
                $query->whereNull('slot_locked_until')
                    ->orWhere('slot_locked_until', '>', Carbon::now());
            });

        if ($specialistId) {
            $query->where('specialist_id', $specialistId);
        }

        return $query->get()->toArray();
    }

    private function getAvailableSpecialists(string $salonId, string $serviceId, string $date): array
    {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        try {
            return DB::table('specialists')
                ->join('specialist_service', 'specialists.id', '=', 'specialist_service.specialist_id')
                ->join('specialist_schedules', 'specialists.id', '=', 'specialist_schedules.specialist_id')
                ->join('salon_specialist', 'specialists.id', '=', 'salon_specialist.specialist_id')
                ->where('salon_specialist.salon_id', $salonId)
                ->where('specialists.active', true)
                ->where('salon_specialist.active', true)
                ->where('specialist_service.service_id', $serviceId)
                ->where('specialist_schedules.day_of_week', $dayOfWeek)
                ->where('specialist_schedules.is_available', true)
                ->where('specialist_schedules.effective_date', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('specialist_schedules.expires_date')
                        ->orWhere('specialist_schedules.expires_date', '>=', $date);
                })
                ->select(
                    'specialists.id',
                    'specialists.name',
                    'specialist_service.skill_level',
                    'specialist_service.is_primary',
                    'specialist_service.price_override'
                )
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            \Log::warning('getAvailableSpecialists schedule join failed, using fallback', [
                'error' => $e->getMessage(),
            ]);

            return DB::table('specialists')
                ->join('specialist_service', 'specialists.id', '=', 'specialist_service.specialist_id')
                ->join('salon_specialist', 'specialists.id', '=', 'salon_specialist.specialist_id')
                ->where('salon_specialist.salon_id', $salonId)
                ->where('specialists.active', true)
                ->where('salon_specialist.active', true)
                ->where('specialist_service.service_id', $serviceId)
                ->select(
                    'specialists.id',
                    'specialists.name',
                    'specialist_service.skill_level',
                    'specialist_service.is_primary',
                    'specialist_service.price_override'
                )
                ->get()
                ->toArray();
        }
    }

    /**
     * Generate time slots from a resolved schedule.
     *
     * NOTE: Specialist-level schedule/hours/breaks are already incorporated
     * into the $schedule DTO upstream ("swap up" pattern), so we use the
     * schedule's open/close/break fields directly.
     */
    private function generateTimeSlots(
        ResolvedSchedule $schedule,
        array $existingBookings,
        ?Service $service,
        array $availableSpecialists,
        string $date,
        string $timezone,
        int $slotGranularity
    ): Collection {
        /** @var Collection<int, array> $slots */
        $slots = collect();

        $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->openTime}", $timezone);
        $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->closeTime}", $timezone);

        $serviceDuration = $service ? $service->duration : 60;
        $bufferBefore    = $service ? ($service->buffer_before ?? 0) : 0;
        $bufferAfter     = $service ? ($service->buffer_after ?? 0) : 0;
        $totalDuration   = $serviceDuration + $bufferBefore + $bufferAfter;

        $breakStart = $schedule->breakStart
            ? Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->breakStart}", $timezone)
            : null;
        $breakEnd = $schedule->breakEnd
            ? Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->breakEnd}", $timezone)
            : null;

        $currentTime = $openTime->copy();
        while ($currentTime->addMinutes($slotGranularity)->lte($closeTime)) {
            $slotStart = $currentTime->copy()->subMinutes($slotGranularity);
            $slotEnd   = $slotStart->copy()->addMinutes($totalDuration);

            if ($slotEnd->gt($closeTime)) {
                continue;
            }

            if ($breakStart && $breakEnd
                && $slotStart->lt($breakEnd)
                && $slotEnd->gt($breakStart)) {
                continue;
            }

            $isAvailable = true;
            foreach ($existingBookings as $booking) {
                $bookingStart = Carbon::parse($booking['start_time'], $timezone);
                $bookingEnd   = Carbon::parse($booking['end_time'], $timezone);

                if ($slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
                    $isAvailable = false;
                    break;
                }
            }

            if (!$isAvailable) {
                continue;
            }

            if ($service && $service->min_booking_notice > 0) {
                $minBookingTime = Carbon::now($timezone)->addHours($service->min_booking_notice);
                if ($slotStart->lt($minBookingTime)) {
                    continue;
                }
            }

            $slot = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'duration' => $totalDuration,
            ];

            if (!empty($availableSpecialists)) {
                $slot['available_specialists'] = array_map(function ($sp) use ($service) {
                    return [
                        'id' => $sp['id'],
                        'name' => $sp['name'],
                        'skill_level' => $sp['skill_level'],
                        'price' => $sp['price_override'] ?? $service->price,
                    ];
                }, $availableSpecialists);

                $slot['base_price'] = $service ? $service->price : null;
            }

            $slots->push($slot);
        }

        return $slots;
    }

    private function calculateEndTime(string $startTime, string $serviceId): string
    {
        $service = Service::findOrFail($serviceId);
        $start = Carbon::parse($startTime);
        $end = $start->addMinutes($service->duration + $service->buffer_before + $service->buffer_after);

        return $end->format('H:i:s');
    }

    private function validateBookingWindow(string $date, ?Service $service): void
    {
        $requestedDate = Carbon::parse($date);
        $now = Carbon::now();

        if ($requestedDate->lt($now->startOfDay())) {
            throw new \InvalidArgumentException('Date must be today or in the future');
        }

        if ($service) {
            $maxDate = $now->addDays($service->max_booking_days_ahead);
            if ($requestedDate->gt($maxDate)) {
                throw new \InvalidArgumentException(
                    "Bookings can only be made {$service->max_booking_days_ahead} days in advance"
                );
            }
        }
    }

    private function getCacheKey(
        string $salonId,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): string {
        $parts = ['availability', $salonId, $date];

        if ($serviceId) {
            $parts[] = $serviceId;
        }

        if ($specialistId) {
            $parts[] = $specialistId;
        }

        return implode(':', $parts);
    }

    private function clearAvailabilityCache(
        string $salonId,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): void {
        $keys = [
            $this->getCacheKey($salonId, $date, $serviceId, $specialistId),
            $this->getCacheKey($salonId, $date, $serviceId, null),
            $this->getCacheKey($salonId, $date, null, $specialistId),
            $this->getCacheKey($salonId, $date, null, null),
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Build an AvailabilityResult for unavailable/no-slots cases.
     * Uses pickWinner() to select the highest-priority status from all contributing factors.
     *
     * @param array<int, AvailabilityDomainStatus> $allStatuses All contributing statuses
     * @return AvailabilityResult Empty result with appropriate status
     */
    private function buildEmptyResult(
        string $salonId,
        string $date,
        AvailabilityDomainStatus $status,
        array $allStatuses = [],
        bool $isClosed = false,
    ): AvailabilityResult {
        $unique = array_values(array_unique(array_merge([$status], $allStatuses), SORT_REGULAR));
        $winner = AvailabilityDomainStatus::pickWinner($unique, $status);

        return new AvailabilityResult(
            salonId: $salonId,
            date: $date,
            status: $winner,
            allStatuses: $unique,
            slots: collect(),
            schedule: null,
            salonName: null,
            timezone: null,
            operatingHours: null,
            isClosed: $isClosed,
            firstAvailableSlot: null,
            totalAvailableSlots: 0,
        );
    }

    /**
     * Build an AvailabilityResult for successful slot generation.
     * Determines final status based on slot count and preflight checks.
     *
     * @param array<string, mixed> $base Base context data
     * @param ResolvedSchedule $schedule Resolved schedule with hours
     * @param Collection<int, array> $slots Generated time slots
     * @param array<int, AvailabilityDomainStatus> $preflightStatuses Configuration checks
     * @return AvailabilityResult Finalized result with status
     */
    private function finalizeResult(
        array $base,
        ResolvedSchedule $schedule,
        Collection $slots,
        array $preflightStatuses,
    ): AvailabilityResult {
        $slotCount = $slots->count();

        if ($slotCount > 0) {
            $primary = AvailabilityDomainStatus::AVAILABLE;
        } else {
            $primary = AvailabilityDomainStatus::NO_WINDOWS;
        }

        $all = array_values(array_unique(array_merge($preflightStatuses, [$primary]), SORT_REGULAR));
        $winner = AvailabilityDomainStatus::pickWinner($all, $primary);

        return new AvailabilityResult(
            salonId: $base['salon_id'],
            date: $base['date'],
            status: $winner,
            allStatuses: $all,
            slots: $slots,
            schedule: $schedule,
            salonName: $base['salon_name'] ?? null,
            timezone: $base['timezone'] ?? null,
            operatingHours: $base['operating_hours'] ?? null,
            isClosed: (bool) ($base['is_closed'] ?? false),
            firstAvailableSlot: $slotCount > 0 ? $slots->first() : null,
            totalAvailableSlots: $slotCount,
        );
    }

    /**
     * Pre-flight configuration checks for provider, branch, assignment, subscription.
     * Returns array of any missing configuration statuses.
     *
     * @return array<int, AvailabilityDomainStatus> Configuration issues found
     */
    private function preflightConfigurationStatuses(Salon $salon, ?string $specialistId, string $date): array
    {
        $codes = [];
        $dayName    = Carbon::parse($date)->format('l');
        $dayOfWeek  = Carbon::parse($date)->dayOfWeek;

        $hasAnyProviderSchedule = \App\Models\ProviderSchedule::where('provider_id', $salon->provider_id)
            ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
            ->count() >= 1;

        if (!$hasAnyProviderSchedule) {
            $codes[] = AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED;
        }

        $branchMode = $salon->schedule_mode ?? 'INHERIT';
        if ($branchMode === 'CUSTOM') {
            $hasBranchSched = SalonSchedule::where('salon_id', $salon->id)
                ->where('day_of_week', $dayName)
                ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                ->exists();
            if (!$hasBranchSched && $hasAnyProviderSchedule) {
                $codes[] = AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED;
            }
        }

        if ($specialistId) {
            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('salon_id', $salon->id)
                ->first();

            if ($assignment) {
                $assMode = $assignment->schedule_mode ?? 'INHERIT';
                if ($assMode === 'CUSTOM') {
                    $hasAssSched = \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)
                        ->where('day_of_week', $dayName)
                        ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                        ->exists();
                    if (!$hasAssSched) {
                        $codes[] = AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED;
                    }
                }
            }
        }

        $subscriptionOk = \App\Models\Subscription::where('provider_id', $salon->provider_id)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where('status', '!=', 'cancelled')
            ->exists();
        if (!$subscriptionOk) {
            $codes[] = AvailabilityDomainStatus::SUBSCRIPTION_NOT_CONFIGURED;
        }

        if (isset($salon->accepting_online_bookings) && $salon->accepting_online_bookings === false) {
            $codes[] = AvailabilityDomainStatus::MAINTENANCE_NOT_CONFIGURED;
        }

        return array_values(array_unique($codes, SORT_REGULAR));
    }
}
