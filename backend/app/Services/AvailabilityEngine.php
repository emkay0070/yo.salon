<?php

namespace App\Services;


use App\Models\Specialist;
use App\Models\Service;
use App\Models\Booking;
use App\Models\Provider;
use App\Domain\Availability\DTOs\AvailabilityResult;
use App\Domain\Availability\DTOs\ResolvedSchedule;
use App\Domain\Availability\DTOs\ProviderLocationContext;
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

    // -------------------------------------------------------------------------
    // PRIMARY API — Provider + Location
    // -------------------------------------------------------------------------

    /**
     * Get available time slots for a Provider+Location context on a specific date.
     *
     * This is the destination-architecture primary entry point.
     * Provider is the business/tenant. Location is the optional physical context.
     * Use locationId = null for independent/mobile providers.
     *
     * @param string      $providerId  Provider UUID
     * @param string|null $locationId  Location UUID, or null for independent
     * @param string      $date        Date in Y-m-d format
     * @param string|null $serviceId   Optional service filter
     * @param string|null $specialistId Optional specialist filter
     * @param int         $slotGranularity Slot size in minutes
     * @return AvailabilityResult
     */
    public function getAvailableSlots(
        string $providerId,
        ?string $locationId,
        string $date,
        ?string $serviceId = null,
        ?string $specialistId = null,
        int $slotGranularity = self::SLOT_GRANULARITY_MINUTES
    ): AvailabilityResult {
        $ctx = new ProviderLocationContext($providerId, $locationId);
        $cacheKey = $this->getContextCacheKey($ctx, $date, $serviceId, $specialistId);

        $cachedData = Cache::remember($cacheKey, 300, function () use (
            $ctx, $date, $serviceId, $specialistId, $slotGranularity
        ) {
            $result = $this->computeAvailableSlots($ctx, $date, $serviceId, $specialistId, $slotGranularity);
            return $result->toLegacyPayload();
        });

        return $this->reconstructFromCache($cachedData, $locationId, $date);
    }



    /**
     * Reconstruct AvailabilityResult from cached array data
     */
    private function reconstructFromCache(array $data, ?string $locationId, string $date): AvailabilityResult
    {
        $status = AvailabilityDomainStatus::from($data['domain_status'] ?? AvailabilityDomainStatus::AVAILABLE->value);
        $allStatuses = array_map(
            fn ($s) => AvailabilityDomainStatus::from($s),
            $data['domain_statuses'] ?? [$status->value]
        );

        $schedule = isset($data['schedule']) ? new ResolvedSchedule(
            openTime: $data['schedule']['open_time'] ?? null,
            closeTime: $data['schedule']['close_time'] ?? null,
            breakStart: $data['schedule']['break_start'] ?? null,
            breakEnd: $data['schedule']['break_end'] ?? null,
            isWorkingDay: $data['schedule']['is_working_day'] ?? false,
            source: $data['schedule']['source'] ?? 'unknown',
        ) : null;

        return new AvailabilityResult(
            locationId: $data['location_id'] ?? $locationId,
            date: $data['date'] ?? $date,
            status: $status,
            allStatuses: $allStatuses,
            slots: collect($data['slots'] ?? []),
            schedule: $schedule,
            locationName: $data['location_name'] ?? null,
            timezone: $data['timezone'] ?? null,
            operatingHours: $data['operating_hours'] ?? null,
            isClosed: $data['is_closed'] ?? false,
            firstAvailableSlot: $data['first_available_slot'] ?? null,
            totalAvailableSlots: $data['total_available_slots'] ?? 0,
        );
    }

    /**
     * Core availability computation logic.
     * Operates on ProviderLocationContext — no Salon dependency.
     *
     * @return AvailabilityResult Computed availability with domain status
     */
    private function computeAvailableSlots(
        ProviderLocationContext $ctx,
        string $date,
        ?string $serviceId,
        ?string $specialistId,
        int $slotGranularity,
    ): AvailabilityResult {
        $provider = Provider::findOrFail($ctx->providerId);
        $location = $ctx->locationId ? \App\Models\Location::find($ctx->locationId) : null;
        $service  = $serviceId   ? Service::findOrFail($serviceId)   : null;
        $specialist = $specialistId ? Specialist::findOrFail($specialistId) : null;

        // For independent_specialist providers without an explicit specialistId,
        // auto-resolve the primary assignment to use AssignmentSchedule
        \Log::info('AvailabilityEngine: Provider type check', [
            'provider_id' => $ctx->providerId,
            'provider_type' => $provider->type,
            'specialistId_input' => $specialistId,
            'TYPE_SPECIALIST_const' => Provider::TYPE_SPECIALIST,
        ]);

        if ($provider->type === Provider::TYPE_SPECIALIST && !$specialistId) {
            \Log::info('AvailabilityEngine: Entering independent_specialist branch', [
                'provider_id' => $ctx->providerId,
            ]);

            $primaryAssignment = \App\Models\SpecialistAssignment::where('provider_id', $ctx->providerId)
                ->where('is_primary', true)
                ->whereIn('status', ['ACTIVE', 'active'])
                ->first();

            \Log::info('AvailabilityEngine: Primary assignment lookup', [
                'provider_id' => $ctx->providerId,
                'found' => $primaryAssignment ? true : false,
                'assignment_id' => $primaryAssignment?->id,
                'specialist_id' => $primaryAssignment?->specialist_id,
            ]);

            if ($primaryAssignment) {
                $specialistId = $primaryAssignment->specialist_id;
                $specialist = Specialist::find($specialistId);
                \Log::info('AvailabilityEngine: Resolved specialistId', ['specialistId' => $specialistId]);
            }
        }

        $this->validateBookingWindow($date, $service);

        $preflight = $this->preflightByContext($ctx, $specialistId, $date);

        $locationSchedule = $this->getSchedule($ctx, $date, $specialistId);

        $ctxKey = $ctx->cacheKey();

        if (in_array(AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED, $preflight, true)) {
            return $this->buildEmptyResult(
                locationId: $ctxKey,
                date: $date,
                status: AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED,
                allStatuses: $preflight,
                isClosed: false,
            );
        }

        if (!$locationSchedule) {
            return $this->buildEmptyResult(
                locationId: $ctxKey,
                date: $date,
                status: AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED,
                allStatuses: [...$preflight, AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED],
                isClosed: false,
            );
        }

        if ($locationSchedule->isClosed) {
            return $this->buildEmptyResult(
                locationId: $ctxKey,
                date: $date,
                status: AvailabilityDomainStatus::BRANCH_CLOSED,
                allStatuses: [...$preflight, AvailabilityDomainStatus::BRANCH_CLOSED],
                isClosed: true,
            );
        }

        $assignmentResolved = null;
        if ($specialistId) {
            // Assignment is looked up by provider + location (destination boundary)
            // For independent specialists with locationId=null, match the primary assignment regardless of location
            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('provider_id', $ctx->providerId)
                ->when($ctx->locationId, fn($q) => $q->where('location_id', $ctx->locationId))
                ->whereIn('status', ['ACTIVE', 'active'])
                ->first();

            if (!$assignment) {
                return $this->buildEmptyResult(
                    locationId: $ctxKey,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED],
                    isClosed: false,
                );
            }

            $dayName = Carbon::parse($date)->format('l');

            $assignmentScheduleModel = \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)
                ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                ->where('day_of_week', $dayName)
                ->first();

            $resolver = new \App\Domain\Availability\Resolvers\ScheduleResolver();

            $providerScheduleModel = \App\Models\ProviderSchedule::where('provider_id', $ctx->providerId)
                ->where('day_of_week', Carbon::parse($date)->dayOfWeek)
                ->first();

            $locationScheduleModel = null;
            if ($ctx->locationId) {
                $locationScheduleModel = \App\Models\LocationSchedule::where('location_id', $ctx->locationId)
                    ->where('day_of_week', $dayName)
                    ->first();
            }

            $assignmentResolved = $resolver->resolve(
                providerSchedule: $providerScheduleModel,
                locationSchedule: $locationScheduleModel,
                locationMode: $locationScheduleModel ? 'CUSTOM' : 'INHERIT',
                assignmentSchedule: $assignmentScheduleModel,
                assignmentMode: $assignment->schedule_mode ?? 'INHERIT',
            );

            if (!$assignmentResolved) {
                return $this->buildEmptyResult(
                    locationId: $ctxKey,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_NOT_CONFIGURED],
                    isClosed: false,
                );
            }

            if ($assignmentResolved->isClosed) {
                return $this->buildEmptyResult(
                    locationId: $ctxKey,
                    date: $date,
                    status: AvailabilityDomainStatus::ASSIGNMENT_CLOSED,
                    allStatuses: [...$preflight, AvailabilityDomainStatus::ASSIGNMENT_CLOSED],
                    isClosed: true,
                );
            }

            $locationSchedule = $assignmentResolved;
        }

        $existingBookings = $this->getExistingBookingsByContext($ctx, $date, $specialistId);

        // Get live session extensions as an operational extension layer
        $liveSessionExtensions = $this->getLiveSessionExtensionsByContext($ctx, $date, $specialistId);

        // Apply live session extensions to existing booking blocks
        foreach ($existingBookings as &$booking) {
            if (isset($liveSessionExtensions[$booking['id']])) {
                $liveEnd = $liveSessionExtensions[$booking['id']];
                $bookingEnd = $booking['end_time'];
                if ($liveEnd > $bookingEnd) {
                    $booking['end_time'] = $liveEnd;
                }
            }
        }
        unset($booking);

        $availableSpecialists = []; // Disabled legacy specialist_schedules query

        $timezone = $ctx->timezone ?? $location?->timezone ?? $provider->timezone ?? 'Africa/Kampala';

        $slots = $this->generateTimeSlots(
            schedule: $locationSchedule,
            existingBookings: $existingBookings,
            service: $service,
            availableSpecialists: $availableSpecialists,
            date: $date,
            timezone: $timezone,
            slotGranularity: $slotGranularity,
            location: $location
        );

        return $this->finalizeResult(
            [
                'location_id' => $ctxKey,
                'date' => $date,
                'timezone' => $timezone,
                'operating_hours' => [
                    'open' => $locationSchedule->openTime,
                    'close' => $locationSchedule->closeTime,
                ],
                'is_closed' => false,
            ],
            $locationSchedule,
            $slots,
            $preflight,
        );
    }

    public function getBatchAvailability(
        string $providerId,
        ?string $locationId,
        string $startDate,
        string $endDate,
        ?string $serviceId = null,
        ?string $specialistId = null
    ): array {
        $dates = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $availability = $this->getAvailableSlots($providerId, $locationId, $dateStr, $serviceId, $specialistId);

            $dates[] = [
                'date'            => $dateStr,
                'is_closed'       => $availability->isClosed,
                'available_slots' => $availability->totalAvailableSlots,
                'first_slot'      => $availability->firstAvailableSlot['start'] ?? null,
                'status'          => $availability->status->value,
            ];
        }

        return [
            'provider_id' => $providerId,
            'location_id' => $locationId,
            'dates'       => $dates,
        ];
    }



    public function getFirstAvailableSlot(
        string $providerId,
        ?string $locationId,
        ?string $serviceId = null,
        ?string $specialistId = null,
        int $daysAhead = 30
    ): ?array {
        $startDate = Carbon::now()->format('Y-m-d');
        $endDate   = Carbon::now()->addDays($daysAhead)->format('Y-m-d');

        $batch = $this->getBatchAvailability($providerId, $locationId, $startDate, $endDate, $serviceId, $specialistId);

        foreach ($batch['dates'] as $date) {
            if (!$date['is_closed'] && $date['available_slots'] > 0) {
                $availability = $this->getAvailableSlots($providerId, $locationId, $date['date'], $serviceId, $specialistId);

                $first = $availability->firstAvailableSlot;
                if (!$first) {
                    continue;
                }

                return [
                    'date'       => $date['date'],
                    'start_time' => $first['start'],
                    'end_time'   => $first['end'],
                    'specialist' => $first['available_specialists'][0] ?? null,
                ];
            }
        }

        return null;
    }

    public function lockSlot(
        string $locationId,
        string $date,
        string $startTime,
        string $serviceId,
        ?string $specialistId = null
    ): array {
        $lockId = (string) Str::uuid();
        $expiresAt = Carbon::now()->addMinutes(self::SLOT_LOCK_MINUTES);

        // Resolve provider_id from location_id
        $location = \App\Models\Location::findOrFail($locationId);
        $providerId = $location->provider_id;

        $booking = Booking::create([
            'id' => $lockId,
            'location_id' => $locationId,
            'provider_id' => $providerId,
            'specialist_id' => $specialistId,
            'service_id' => $serviceId,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $this->calculateEndTime($startTime, $serviceId),
            'status' => 'pending',
            'slot_locked_until' => $expiresAt,
        ]);

        $ctx = new ProviderLocationContext($providerId, $locationId);
        $this->clearAvailabilityCache($ctx, $date, $serviceId, $specialistId);

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
            if ($booking->location_id) {
                $location = \App\Models\Location::find($booking->location_id);
                if ($location) {
                    $ctx = new ProviderLocationContext($location->provider_id, $location->id);
                    $this->clearAvailabilityCache($ctx, $booking->date, $booking->service_id, $booking->specialist_id);
                }
            }

            $booking->delete();
        }
    }

    /**
     * Resolve operating schedule for a Provider+Location context on a given date.
     *
     * - Independent (locationId = null)  → ProviderSchedule only
     * - Branch     (locationId set)      → LocationSchedule CUSTOM, else ProviderSchedule
     * - Specialist specified             → AssignmentSchedule CUSTOM, else fall through
     */
    private function getSchedule(ProviderLocationContext $ctx, string $date, ?string $specialistId = null): ?ResolvedSchedule
    {
        $dayName      = Carbon::parse($date)->format('l');
        $dayOfWeekInt = Carbon::parse($date)->dayOfWeek;

        $providerSchedule = \App\Models\ProviderSchedule::where('provider_id', $ctx->providerId)
            ->where('day_of_week', $dayOfWeekInt)
            ->first();

        $locationSchedule = null;
        if ($ctx->locationId) {
            $locationSchedule = \App\Models\LocationSchedule::where('location_id', $ctx->locationId)
                ->where('day_of_week', $dayName)
                ->first();
        }

        $assignmentSchedule = null;
        $assignmentMode = 'INHERIT';
        if ($specialistId) {
            \Log::info('AvailabilityEngine: getSchedule - resolving assignment', [
                'specialistId' => $specialistId,
                'provider_id' => $ctx->providerId,
                'locationId' => $ctx->locationId,
            ]);

            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('provider_id', $ctx->providerId)
                ->when($ctx->locationId, fn($q) => $q->where('location_id', $ctx->locationId))
                ->whereIn('status', ['ACTIVE', 'active'])
                ->first();

            \Log::info('AvailabilityEngine: getSchedule - assignment lookup result', [
                'found' => $assignment ? true : false,
                'assignment_id' => $assignment?->id,
                'schedule_mode' => $assignment?->schedule_mode,
            ]);

            if ($assignment) {
                $assignmentSchedule = \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)
                    ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                    ->where('day_of_week', $dayName)
                    ->first();

                \Log::info('AvailabilityEngine: getSchedule - AssignmentSchedule lookup', [
                    'assignment_id' => $assignment->id,
                    'day_of_week' => $dayName,
                    'found' => $assignmentSchedule ? true : false,
                ]);

                $assignmentMode = $assignment->schedule_mode ?? 'INHERIT';
            }
        }

        // For independent providers: always inherit from ProviderSchedule
        // For branches: LocationSchedule is CUSTOM if present, else INHERIT from Provider
        $branchMode = $locationSchedule ? 'CUSTOM' : 'INHERIT';

        $resolver = new \App\Domain\Availability\Resolvers\ScheduleResolver();

        return $resolver->resolve(
            providerSchedule: $providerSchedule,
            locationSchedule: $locationSchedule,
            locationMode: $branchMode,
            assignmentSchedule: $assignmentSchedule,
            assignmentMode: $assignmentMode,
        );
    }

    private function getExistingBookingsByContext(
        ProviderLocationContext $ctx,
        string $date,
        ?string $specialistId = null
    ): array {
        \Log::info('getExistingBookingsByContext', [
            'provider_id' => $ctx->providerId,
            'location_id' => $ctx->locationId,
            'date' => $date,
            'specialist_id' => $specialistId,
        ]);

        $location = $ctx->locationId ? \App\Models\Location::find($ctx->locationId) : null;

        // Query BookingService to get start_time and end_time for availability checking
        // BookingService has a relationship to Booking which contains provider_id, location_id, etc.
        try {
            $bookingServices = \App\Models\BookingService::with(['booking', 'service'])
                ->whereHas('booking', function ($q) use ($ctx, $date) {
                    $q->where('provider_id', $ctx->providerId)
                      ->where('date', $date)
                      ->whereIn('status', ['confirmed', 'pending', 'reserved', 'pending_payment'])
                      ->where(function ($query) {
                          // Lifecycle-aware check for reserved status
                          $query->where('status', '!=', 'reserved')
                                ->orWhereNull('slot_locked_until')
                                ->orWhere('slot_locked_until', '>', Carbon::now());
                      });

                    if ($ctx->locationId) {
                        $q->where('location_id', $ctx->locationId);
                    }
                })
                ->when($specialistId, function ($q) use ($specialistId) {
                    $q->whereHas('booking', function ($q) use ($specialistId) {
                        $q->where('specialist_id', $specialistId);
                    });
                })
                ->get();

            $result = $bookingServices->map(function ($bs) use ($location) {
                $startTime = $bs->start_time;
                $endTime = $bs->end_time;

                // If end_time is not stored, calculate it with the booking's own service buffers
                if (!$endTime && $bs->service) {
                    $bufferBefore = $bs->service->buffer_before ?? 0;
                    $bufferAfter = $bs->service->buffer_after ?? 0;

                    // Apply location-specific buffer overrides if available
                    if ($location && $bs->service) {
                        $catalogResolver = new LocationServiceCatalogResolver();
                        $resolvedService = $catalogResolver->queryAllServices($location)
                            ->where('services.id', $bs->service->id)
                            ->first();
                        $bufferBefore = $resolvedService?->pivot_buffer_before_override ?? $bufferBefore;
                        $bufferAfter = $resolvedService?->pivot_buffer_after_override ?? $bufferAfter;
                    }

                    $totalDuration = ($bs->service->duration ?? 60) + $bufferBefore + $bufferAfter;
                    $endTime = $startTime->copy()->addMinutes($totalDuration);
                }

                return [
                    'id' => $bs->booking->id,
                    'start_time' => $startTime ? $startTime->format('Y-m-d H:i:s') : null,
                    'end_time' => $endTime ? $endTime->format('Y-m-d H:i:s') : null,
                ];
            })->toArray();

            // Inject ad-hoc SpecialistBreaks as blocked slots if a specialist is resolved
            if ($specialistId) {
                $breaks = \App\Models\SpecialistBreak::where('specialist_id', $specialistId)
                    ->whereDate('started_at', $date)
                    ->where(function ($q) {
                        $q->whereNull('actual_end_at') // Still active
                          ->orWhereRaw('actual_end_at > started_at'); // Already finished, but occupied time
                    })
                    ->get();
                    
                foreach ($breaks as $break) {
                    $endAt = $break->actual_end_at ?? $break->expected_end_at;
                    if ($endAt && $endAt->gt($break->started_at)) {
                        $result[] = [
                            'id' => 'break_' . $break->id,
                            'start_time' => $break->started_at->format('Y-m-d H:i:s'),
                            'end_time' => $endAt->format('Y-m-d H:i:s'),
                        ];
                    }
                }
            }

            \Log::info('getExistingBookingsByContext result', ['count' => count($result)]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('getExistingBookingsByContext error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Get live session extensions for in-progress sessions.
     * This is an operational extension layer over existing Booking reservations.
     * It only extends occupancy when live execution exceeds the scheduled reservation.
     *
     * Returns a map of booking_id -> extended_end_time for bookings that need extension.
     *
     * @param string $salonId
     * @param string $date
     * @param string|null $specialistId
     * @return array
     */
    private function getLiveSessionExtensionsByContext(
        ProviderLocationContext $ctx,
        string $date,
        ?string $specialistId = null
    ): array {
        $query = \App\Models\BookingService::whereHas('booking', function ($q) use ($ctx, $date, $specialistId) {
            $q->where('provider_id', $ctx->providerId)
              ->where('date', $date);

            if ($ctx->locationId) {
                $q->where('location_id', $ctx->locationId);
            }

            if ($specialistId) {
                $q->where('specialist_id', $specialistId);
            }
        })->where('session_status', 'in_progress')
          ->whereNotNull('started_at')
          ->where(function ($q) {
              $q->whereNull('actual_end_at')
                  ->orWhere('actual_end_at', '>=', Carbon::now());
          });

        $sessions = $query->with(['booking', 'service'])->get();

        $extensions = [];
        foreach ($sessions as $session) {
            $location = $ctx->locationId ? \App\Models\Location::find($ctx->locationId) : null;

            if (!$location) {
                $effectiveDuration = $session->service->duration;
            } else {
                $catalogResolver = new \App\Services\LocationServiceCatalogResolver();
                $resolvedService = $catalogResolver->queryAllServices($location)
                    ->where('services.id', $session->service_id)
                    ->first();

                $effectiveDuration = $resolvedService
                    ? ($resolvedService->pivot_duration_override ?? $resolvedService->duration)
                    : $session->service->duration;
            }

            $expectedEnd = $session->started_at->addMinutes($effectiveDuration);
            $actualEnd   = $session->actual_end_at ?? Carbon::now();
            $liveEnd     = $actualEnd->gt($expectedEnd) ? $actualEnd : $expectedEnd;

            $extensions[$session->booking_id] = $liveEnd->format('H:i:s');
        }

        return $extensions;
    }

    private function getAvailableSpecialistsByContext(
        ProviderLocationContext $ctx,
        string $serviceId,
        string $date
    ): array {
        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        try {
            // Specialists resolved via provider_id + optional location_id
            $query = DB::table('specialists')
                ->join('specialist_service', 'specialists.id', '=', 'specialist_service.specialist_id')
                ->join('specialist_schedules', 'specialists.id', '=', 'specialist_schedules.specialist_id')
                ->join('specialist_assignments', 'specialists.id', '=', 'specialist_assignments.specialist_id')
                ->where('specialist_assignments.provider_id', $ctx->providerId)
                ->where('specialist_assignments.status', 'ACTIVE')
                ->where('specialists.active', true)
                ->where('specialist_service.service_id', $serviceId)
                ->where('specialist_schedules.day_of_week', $dayOfWeek)
                ->where('specialist_schedules.is_available', true)
                ->where('specialist_schedules.effective_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('specialist_schedules.expires_date')
                      ->orWhere('specialist_schedules.expires_date', '>=', $date);
                });

            if ($ctx->locationId) {
                $query->where('specialist_assignments.location_id', $ctx->locationId);
            }

            return $query->select(
                'specialists.id',
                'specialists.name',
                'specialist_service.skill_level',
                'specialist_service.is_primary',
                'specialist_service.price_override'
            )->get()->toArray();

        } catch (\Exception $e) {
            \Log::warning('getAvailableSpecialistsByContext fallback', ['error' => $e->getMessage()]);

            return DB::table('specialists')
                ->join('specialist_service', 'specialists.id', '=', 'specialist_service.specialist_id')
                ->join('specialist_assignments', 'specialists.id', '=', 'specialist_assignments.specialist_id')
                ->where('specialist_assignments.provider_id', $ctx->providerId)
                ->where('specialist_assignments.status', 'ACTIVE')
                ->where('specialists.active', true)
                ->where('specialist_service.service_id', $serviceId)
                ->select(
                    'specialists.id',
                    'specialists.name',
                    'specialist_service.skill_level',
                    'specialist_service.is_primary',
                    'specialist_service.price_override'
                )->get()->toArray();
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
        int $slotGranularity,
        ?\App\Models\Location $location = null
    ): Collection {
        \Log::info('generateTimeSlots start', [
            'date' => $date,
            'timezone' => $timezone,
            'openTime' => $schedule->openTime,
            'closeTime' => $schedule->closeTime,
            'existingBookingsCount' => count($existingBookings),
            'serviceDuration' => $service?->duration,
        ]);

        /** @var Collection<int, array> $slots */
        $slots = collect();

        $openTime  = Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->openTime}", $timezone);
        $closeTime = Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->closeTime}", $timezone);

        \Log::info('generateTimeSlots times', [
            'openTime' => $openTime->toDateTimeString(),
            'closeTime' => $closeTime->toDateTimeString(),
            'now' => Carbon::now($timezone)->toDateTimeString(),
        ]);

        $serviceDuration = $service ? $service->duration : 60;

        // Resolve location-specific buffer overrides to match BookingConflictService behavior
        $bufferBefore = 0;
        $bufferAfter = 0;
        if ($service && $location) {
            $catalogResolver = new LocationServiceCatalogResolver();
            $resolvedService = $catalogResolver->queryAllServices($location)
                ->where('services.id', $service->id)
                ->first();
            $bufferBefore = $resolvedService?->pivot_buffer_before_override ?? $service->buffer_before ?? 0;
            $bufferAfter = $resolvedService?->pivot_buffer_after_override ?? $service->buffer_after ?? 0;
        } elseif ($service) {
            $bufferBefore = $service->buffer_before ?? 0;
            $bufferAfter = $service->buffer_after ?? 0;
        }

        $totalDuration = $serviceDuration + $bufferBefore + $bufferAfter;

        $breakStart = $schedule->breakStart
            ? Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->breakStart}", $timezone)
            : null;
        $breakEnd = $schedule->breakEnd
            ? Carbon::createFromFormat('Y-m-d H:i:s', "$date {$schedule->breakEnd}", $timezone)
            : null;

        $currentTime = $openTime->copy();
        $slotsGenerated = 0;
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

            if ($service && $service->min_booking_notice > 0) {
                $minBookingTime = Carbon::now($timezone)->addHours($service->min_booking_notice);
                if ($slotStart->lt($minBookingTime)) {
                    \Log::info('Slot filtered by min_booking_notice', [
                        'slotStart' => $slotStart->toDateTimeString(),
                        'minBookingTime' => $minBookingTime->toDateTimeString(),
                        'min_booking_notice_hours' => $service->min_booking_notice,
                    ]);
                    $isAvailable = false;
                }
            }

            $slot = [
                'start' => $slotStart->format('H:i'),
                'end' => $slotEnd->format('H:i'),
                'duration' => $totalDuration,
                'available' => $isAvailable,
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
            $slotsGenerated++;
        }

        \Log::info('generateTimeSlots complete', ['slotsGenerated' => $slotsGenerated]);

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

    private function getContextCacheKey(
        ProviderLocationContext $ctx,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): string {
        $parts = ['availability', $ctx->cacheKey(), $date];

        // Always include serviceId and specialistId parts for consistent key structure
        // Use 'null' when not present to match invalidation logic
        $parts[] = $serviceId ?? 'null';
        $parts[] = $specialistId ?? 'null';

        return implode(':', $parts);
    }

    /** @deprecated use getContextCacheKey */
    private function getCacheKey(string $locationId, string $date, ?string $serviceId, ?string $specialistId): string
    {
        // Resolve provider_id from location_id for legacy compatibility
        $location = \App\Models\Location::find($locationId);
        $providerId = $location?->provider_id ?? $locationId;
        
        return $this->getContextCacheKey(
            new ProviderLocationContext($providerId, $locationId),
            $date, $serviceId, $specialistId
        );
    }

    /**
     * Clear availability cache for a session (start/complete/cancel).
     *
     * @param string|null $locationId  Location ID (preferred) or salon_id (legacy)
     * @param string $date
     * @param string|null $serviceId
     * @param string|null $specialistId
     */
    public function clearAvailabilityCacheForSession(
        ?string $locationId,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): void {
        if (!$locationId) {
            return;
        }

        // Try to resolve as location first
        $location = \App\Models\Location::find($locationId);
        if ($location) {
            $ctx = new ProviderLocationContext($location->provider_id, $location->id);
            $this->clearAvailabilityCache($ctx, $date, $serviceId, $specialistId);
            return;
        }

        // No valid location found — nothing to clear
    }

    private function clearAvailabilityCache(
        ProviderLocationContext $ctx,
        string $date,
        ?string $serviceId,
        ?string $specialistId
    ): void {
        $keys = [
            $this->getContextCacheKey($ctx, $date, $serviceId, $specialistId),
            $this->getContextCacheKey($ctx, $date, $serviceId, null),
            $this->getContextCacheKey($ctx, $date, null, $specialistId),
            $this->getContextCacheKey($ctx, $date, null, null),
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
        string $locationId,
        string $date,
        AvailabilityDomainStatus $status,
        array $allStatuses = [],
        bool $isClosed = false,
    ): AvailabilityResult {
        $unique = array_values(array_unique(array_merge([$status], $allStatuses), SORT_REGULAR));
        $winner = AvailabilityDomainStatus::pickWinner($unique, $status);

        return new AvailabilityResult(
            locationId: $locationId,
            date: $date,
            status: $winner,
            allStatuses: $unique,
            slots: collect(),
            schedule: null,
            locationName: null,
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
            locationId: $base['location_id'],
            date: $base['date'],
            status: $winner,
            allStatuses: $all,
            slots: $slots,
            schedule: $schedule,
            locationName: $base['location_name'] ?? null,
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
    /**
     * Pre-flight checks operating on ProviderLocationContext — no Salon dependency.
     */
    private function preflightByContext(ProviderLocationContext $ctx, ?string $specialistId, string $date): array
    {
        $codes   = [];
        $dayName = Carbon::parse($date)->format('l');

        $provider = Provider::find($ctx->providerId);

        \Log::info('preflightByContext: start', [
            'provider_id' => $ctx->providerId,
            'provider_type' => $provider->type,
            'day_of_week' => $dayName,
            'specialistId' => $specialistId,
        ]);

        // ── Provider-level gate: inactive or suspended provider cannot be booked ──
        if (!$provider->active || in_array($provider->status, ['inactive', 'suspended'], true)) {
            \Log::info('preflightByContext: provider disabled', [
                'active' => $provider->active,
                'status' => $provider->status,
            ]);
            $codes[] = AvailabilityDomainStatus::PROVIDER_DISABLED;
            return $codes; // short-circuit — no further checks needed
        }

        // For independent_specialist providers, check AssignmentSchedule instead of ProviderSchedule
        if ($provider->type === Provider::TYPE_SPECIALIST) {
            \Log::info('preflightByContext: independent_specialist branch');
            $primaryAssignment = \App\Models\SpecialistAssignment::where('provider_id', $ctx->providerId)
                ->where('is_primary', true)
                ->whereIn('status', ['ACTIVE', 'active'])
                ->first();

            \Log::info('preflightByContext: primary assignment lookup', [
                'found' => $primaryAssignment ? true : false,
                'assignment_id' => $primaryAssignment?->id,
                'schedule_mode' => $primaryAssignment?->schedule_mode,
            ]);

            if ($primaryAssignment) {
                $assMode = $primaryAssignment->schedule_mode ?? 'INHERIT';
                if ($assMode === 'CUSTOM') {
                    $hasAssSched = \App\Models\AssignmentSchedule::where('assignment_id', $primaryAssignment->id)
                        ->where('day_of_week', $dayName)
                        ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
                        ->exists();

                    \Log::info('preflightByContext: AssignmentSchedule check', [
                        'has_schedule' => $hasAssSched,
                    ]);

                    if (!$hasAssSched) {
                        $codes[] = AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED;
                    }
                    // CUSTOM mode: skip ProviderSchedule check entirely
                    \Log::info('preflightByContext: CUSTOM mode, skipping ProviderSchedule check');
                    \Log::info('preflightByContext: result', ['codes' => $codes]);
                    return $codes;
                }
                // INHERIT mode: fall through to ProviderSchedule check below
                \Log::info('preflightByContext: INHERIT mode, will check ProviderSchedule');
            } else {
                // No primary assignment - not configured
                \Log::info('preflightByContext: no primary assignment');
                $codes[] = AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED;
                \Log::info('preflightByContext: result', ['codes' => $codes]);
                return $codes;
            }
        }

        // For normal providers (or independent specialists in INHERIT mode), check ProviderSchedule
        $hasProviderSchedule = \App\Models\ProviderSchedule::where('provider_id', $ctx->providerId)
            ->where(function ($q) { $q->whereNull('status')->orWhere('status', 'ACTIVE'); })
            ->count() >= 1;

        \Log::info('preflightByContext: ProviderSchedule check', [
            'has_provider_schedule' => $hasProviderSchedule,
        ]);

        if (!$hasProviderSchedule) {
            $codes[] = AvailabilityDomainStatus::PROVIDER_NOT_CONFIGURED;
        }

        \Log::info('preflightByContext: result', ['codes' => $codes]);

        // If a location is specified, check LocationSchedule when CUSTOM mode
        if ($ctx->locationId) {
            $locationHasCustom = \App\Models\LocationSchedule::where('location_id', $ctx->locationId)->exists();
            if (!$locationHasCustom && !$hasProviderSchedule) {
                $codes[] = AvailabilityDomainStatus::BRANCH_NOT_CONFIGURED;
            }
        }

        // Specialist assignment check (via provider+location boundary)
        if ($specialistId) {
            $assignment = \App\Models\SpecialistAssignment::where('specialist_id', $specialistId)
                ->where('provider_id', $ctx->providerId)
                ->when($ctx->locationId, fn($q) => $q->where('location_id', $ctx->locationId))
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

        // Subscription check is provider-level
        $subscriptionOk = \App\Models\Subscription::where('provider_id', $ctx->providerId)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where('status', '!=', 'cancelled')
            ->exists();
        if (!$subscriptionOk) {
            $codes[] = AvailabilityDomainStatus::SUBSCRIPTION_NOT_CONFIGURED;
        }

        return array_values(array_unique($codes, SORT_REGULAR));
    }
}
