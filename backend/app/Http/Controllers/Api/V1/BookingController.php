<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Location;
use App\Models\Service;
use App\Models\SpecialistAssignment;
use App\Domain\Catalog\Queries\BranchCatalogQuery;
use App\Domain\Catalog\Engines\BookingEngine;
use App\Services\BookingService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    private BookingService $bookingService;
    private NotificationService $notificationService;

    public function __construct(BookingService $bookingService, NotificationService $notificationService)
    {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        \Log::info('BookingController::index called', ['params' => $request->all()]);
        
        $locationId = $request->attributes->get('location_id');
        $locationScope = $request->attributes->get('location_scope', 'all');
        $providerId = $request->attributes->get('provider_id');
        
        $query = Booking::with([
            'provider',
            'location',
            'customer',
            'specialist',
            'service',
            'staff',
            'resolution',
        ]);

        // Respect location_scope: 'specific' filters by location_id, 'all' is Provider-wide
        if ($locationScope === 'specific' && $locationId) {
            $query->where('location_id', $locationId);
        } elseif ($providerId) {
            // Provider-wide scope (location_scope === 'all' or location_id is null)
            $query->where('provider_id', $providerId);
        } else {
            return response()->json(['message' => 'Provider context not found'], 400);
        }

        // Filter by staff_id for Employee "my bookings" view
        if ($request->has('staff_id')) {
            $query->where('staff_id', $request->query('staff_id'));
        }

        if ($request->has('date')) {
            $date = $request->query('date');
            if ($date === 'today') {
                $query->whereDate('date', now()->toDateString());
            } else {
                $query->whereDate('date', $date);
            }
        }

        // Search by customer name, phone, or booking reference
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('phone', 'like', "%{$search}%");
                })->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $statuses = explode(',', $request->query('status'));
            $query->whereIn('status', $statuses);
        }

        // Filter by operational_state
        if ($request->has('operational_state')) {
            $states = explode(',', $request->query('operational_state'));
            $query->whereIn('operational_state', $states);
        }

        // Filter for upcoming appointments (scheduled datetime >= now in business timezone)
        if ($request->query('upcoming') === 'true') {
            $timezoneResolver = app(\App\Services\TimezoneResolverService::class);
            $timezone = $timezoneResolver->resolveBusinessTimezoneById($providerId, $locationId);
            $now = \Carbon\Carbon::now($timezone);

            $query->where(function ($q) use ($now, $timezone) {
                $q->where('date', '>', $now->toDateString())
                  ->orWhere(function ($subQ) use ($now, $timezone) {
                      $subQ->where('date', '=', $now->toDateString())
                           ->where('time', '>=', $now->format('H:i'));
                  });
            });
        }

        $bookings = $query->orderBy('date', 'desc')->orderBy('time', 'desc')->get();
        \Log::info("Found {$bookings->count()} bookings");
        
        return response()->json(BookingResource::collection($bookings));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id'      => 'nullable|uuid|exists:locations,id',
            'provider_id'      => 'required|uuid|exists:providers,id',
            'customer_id'      => 'nullable|uuid|exists:customers,id',
            // For external bookings: one of customer_id, customer_name+phone is required.
            // For internal/walk-in bookings: both are optional — a guest record is created.
            'customer_name'    => 'nullable|string|max:255',
            'customer_phone'   => 'nullable|string|max:20',
            'customer_email'   => 'nullable|email|max:255',
            'specialist_id'    => 'nullable|uuid|exists:specialists,id',
            'service_id'       => 'required|array|min:1',
            'service_id.*'     => 'exists:services,id',
            'date'             => 'required|date',
            'time'             => 'nullable|string',
            'status'           => 'sometimes|in:pending,confirmed,cancelled,completed',
            'notes'            => 'nullable|string',
            'idempotency_key'  => 'nullable|string',
            'internal_booking' => 'sometimes|boolean', // Staff-created walk-in; bypasses slot-time rules only
        ]);

        // For external bookings (non-internal), require customer identity
        if (empty($validated['internal_booking']) && empty($validated['customer_id'])) {
            if (empty($validated['customer_name']) || empty($validated['customer_phone'])) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors'  => [
                        'customer_name'  => ['Customer name is required for external bookings.'],
                        'customer_phone' => ['Customer phone is required for external bookings.'],
                    ],
                ], 422);
            }
        }

        // ── Provider context invariant ────────────────────────────────────────────
        // The authenticated user's provider_id must match the payload provider_id.
        // This prevents a staff member of provider A from creating a booking under provider B.
        $ctxProviderId = $request->attributes->get('provider_id');
        if ($ctxProviderId && $ctxProviderId !== $validated['provider_id']) {
            return response()->json(['message' => 'Unauthorized: provider_id does not match your account.'], 403);
        }

        // ── Location context resolution ───────────────────────────────────────────
        // Priority chain for internal/walk-in bookings:
        //   1. Explicit location_id in request body (already validated above)
        //   2. Middleware-resolved location from X-Location-Id header
        //   3. Provider's primary location (auto-resolve from DB)
        // External/portal bookings without a location will be rejected below.
        if (empty($validated['location_id'])) {
            // Check if middleware already resolved a location
            $ctxLocationId = $request->attributes->get('location_id');

            if ($ctxLocationId) {
                $validated['location_id'] = $ctxLocationId;
            } elseif (!empty($validated['internal_booking'])) {
                // For staff walk-ins: auto-resolve from provider's primary location
                $provider = \App\Models\Provider::find($validated['provider_id']);
                $primaryLocation = $provider?->primaryLocation;

                if (!$primaryLocation) {
                    // Fall back to any active location owned by this provider
                    $primaryLocation = $provider?->locations()->first();
                }

                if ($primaryLocation) {
                    $validated['location_id'] = $primaryLocation->id;
                }
                // If still no location (e.g. independent specialist without a fixed location),
                // allow the booking to proceed location-less — the orchestrator handles this case.
            }
            // External bookings without a resolvable location are permitted for
            // independent_specialist providers (no fixed location). The orchestrator
            // enforces all subsequent constraints.
        }

        // ── Location ownership guard ──────────────────────────────────────────────
        // Validate that the resolved location actually belongs to this provider.
        if (!empty($validated['location_id'])) {
            $locationBelongs = \App\Models\Location::where('id', $validated['location_id'])
                ->where('provider_id', $validated['provider_id'])
                ->exists();

            if (!$locationBelongs) {
                return response()->json(['message' => 'Location does not belong to this provider.'], 403);
            }
        }

        // ── Specialist ownership guard ────────────────────────────────────────────
        // Validate that the specialist is assigned to this provider.
        if (!empty($validated['specialist_id'])) {
            $specialistAssigned = \App\Models\SpecialistAssignment::where('specialist_id', $validated['specialist_id'])
                ->where('provider_id', $validated['provider_id'])
                ->whereIn('status', ['ACTIVE', 'active'])
                ->exists();

            if (!$specialistAssigned) {
                return response()->json(['message' => 'Specialist is not assigned to this provider.'], 403);
            }
        }

        // ── Time resolution ───────────────────────────────────────────────────────
        if (empty($validated['time']) && !empty($validated['date'])) {
            $validated['time'] = \Illuminate\Support\Carbon::parse($validated['date'])->format('H:i');
        }

        try {
            $result = app(\App\Services\BookingOrchestrator::class)->reserveAndBook($validated);
            $booking = $result['booking'];
        } catch (\Exception $e) {
            \Log::error('BookingController::store failed', ['message' => $e->getMessage(), 'payload' => $validated]);
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // ── Booking confirmation notifications ────────────────────────────────────
        // Only send when a real customer and location exist; walk-ins with placeholder
        // phones (0000000000) are silently skipped.
        if ($booking->customer && $booking->location) {
            $isPlaceholderPhone = $booking->customer->phone === '0000000000';

            if ($booking->customer->phone && !$isPlaceholderPhone) {
                $this->notificationService->sendBookingConfirmationSms(
                    $booking->customer->phone,
                    $booking->customer->name,
                    $booking->location->name,
                    $booking->date,
                    $booking->time
                );
            }

            if ($booking->customer->email) {
                $this->notificationService->sendBookingConfirmationEmail(
                    $booking->customer->email,
                    $booking->customer->name,
                    $booking->location->name,
                    $booking->date,
                    $booking->time
                );
            }
        }

        return response()->json(new BookingResource($booking->load(['location', 'customer', 'specialist', 'service', 'staff'])), 201);
    }


    public function show(Booking $booking): JsonResponse
    {
        return response()->json(new BookingResource($booking->load(['location', 'customer', 'specialist', 'service', 'staff', 'resolution'])));
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'location_id'  => 'sometimes|uuid|exists:locations,id',
            'provider_id'  => 'sometimes|uuid|exists:providers,id',
            'customer_id'  => 'nullable|uuid|exists:customers,id',
            'specialist_id'=> 'nullable|uuid|exists:specialists,id',
            'staff_id'     => 'nullable|uuid|exists:staff,id',
            'service_id'   => 'sometimes|uuid|exists:services,id',
            'date'         => 'sometimes|date',
            'time'         => 'nullable|string',
            'status'       => 'sometimes|in:pending,confirmed,in_progress,cancelled,completed,no_show,arrived,waiting',
            'notes'        => 'nullable|string',
        ]);

        // If time is not provided but date is, extract it from date
        if (empty($validated['time']) && !empty($validated['date'])) {
            $validated['time'] = \Illuminate\Support\Carbon::parse($validated['date'])->format('H:i');
        }

        // Check if scheduling fields are changing (date, time, specialist_id, location_id)
        $schedulingFieldsChanging = isset($validated['date']) || isset($validated['time']) ||
                                     isset($validated['specialist_id']) || isset($validated['location_id']);

        // If scheduling fields are changing, acquire lock and check conflicts
        if ($schedulingFieldsChanging) {
            $conflictService = app(\App\Services\BookingConflictService::class);
            $newDate = $validated['date'] ?? $booking->date;
            $newTime = $validated['time'] ?? $booking->time;
            $newSpecialistId = $validated['specialist_id'] ?? $booking->specialist_id;
            $newLocationId = $validated['location_id'] ?? $booking->location_id;
            $providerId = $validated['provider_id'] ?? $booking->provider_id;

            return \Illuminate\Support\Facades\DB::transaction(function () use (
                $request,
                $booking,
                $validated,
                $conflictService,
                $newDate,
                $newTime,
                $newSpecialistId,
                $newLocationId,
                $providerId
            ) {
                // Acquire lock for the NEW interval
                if ($newSpecialistId) {
                    $conflictService->acquireSpecialistLock($newSpecialistId, $providerId);
                } else {
                    $conflictService->acquireProviderLock($providerId);
                }

                // Calculate occupied interval for conflict detection
                $service = \App\Models\Service::find($booking->service_id);
                $newServiceStart = \Carbon\Carbon::parse("{$newDate} {$newTime}");
                $newServiceEnd = $newServiceStart->copy()->addMinutes($service->duration);
                $newOccupiedInterval = $conflictService->calculateOccupiedInterval(
                    $newServiceStart,
                    $newServiceEnd,
                    $service
                );

                // Check for conflicts (handles both new and legacy bookings)
                if ($newSpecialistId && $newLocationId) {
                    $hasConflict = $conflictService->hasIntervalConflict(
                        $newSpecialistId,
                        $newLocationId,
                        $newOccupiedInterval['start'],
                        $newOccupiedInterval['end'],
                        $booking->id // Exclude current booking from conflict check
                    );
                } elseif (!$newSpecialistId) {
                    $hasConflict = $conflictService->hasProviderLevelConflict(
                        $providerId,
                        $newLocationId,
                        $newOccupiedInterval['start'],
                        $newOccupiedInterval['end'],
                        $booking->id
                    );
                } else {
                    $hasConflict = false;
                }

                if ($hasConflict) {
                    throw new \Illuminate\Validation\ValidationException(
                        'The requested schedule is not available due to conflicts.',
                        422
                    );
                }

                // ── Status transition must go through the state machine ─────────────────
                // All other field updates (notes, specialist, date, etc.) are applied directly.
                // This ensures the state machine is the single source of truth for booking lifecycle.
                if (isset($validated['status']) && $validated['status'] !== $booking->status) {
                    try {
                        // If booking is in needs_resolution state, use resolveBooking instead
                        if ($booking->operational_state === 'needs_resolution') {
                            // Map status to resolution outcome
                            $outcomeMap = [
                                'no_show' => 'NO_SHOW',
                                'cancelled' => 'CANCELLED',
                                'completed' => 'COMPLETED_LATE',
                                'arrived' => 'CUSTOMER_ARRIVED',
                            ];

                            if (isset($outcomeMap[$validated['status']])) {
                                $actor = $request->user();
                                $this->bookingService->resolveBooking(
                                    $booking,
                                    $outcomeMap[$validated['status']],
                                    $this->mapStatusToReasonCode($validated['status']),
                                    'staff',
                                    $actor?->id,
                                    ['notes' => $validated['notes'] ?? null]
                                );
                                $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff', 'resolution']);
                            } else {
                                // For statuses not mapped to outcomes (e.g., in_progress), use regular transition
                                $this->bookingService->transitionStatus($booking, $validated['status']);
                                $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff']);
                            }
                        } else {
                            // Regular status transition for non-needs_resolution bookings
                            $this->bookingService->transitionStatus($booking, $validated['status']);
                            $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff']);
                        }
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        return response()->json([
                            'message' => $e->getMessage(),
                            'errors'  => $e->errors(),
                        ], 422);
                    }
                    // Remove status from the payload so we don't double-apply it below
                    unset($validated['status']);
                }

                // Apply remaining non-status field changes
                $nonStatusFields = array_filter($validated, fn($key) => $key !== 'status', ARRAY_FILTER_USE_KEY);
                if (!empty($nonStatusFields)) {
                    $booking->update($nonStatusFields);
                }

                return response()->json(new BookingResource($booking->load(['location', 'customer', 'specialist', 'service', 'staff', 'resolution'])));
            });
        }

        // ── Status transition must go through the state machine ─────────────────
        // All other field updates (notes, specialist, date, etc.) are applied directly.
        // This ensures the state machine is the single source of truth for booking lifecycle.
        if (isset($validated['status']) && $validated['status'] !== $booking->status) {
            try {
                // If booking is in needs_resolution state, use resolveBooking instead
                if ($booking->operational_state === 'needs_resolution') {
                    // Map status to resolution outcome
                    $outcomeMap = [
                        'no_show' => 'NO_SHOW',
                        'cancelled' => 'CANCELLED',
                        'completed' => 'COMPLETED_LATE',
                        'arrived' => 'CUSTOMER_ARRIVED',
                    ];

                    if (isset($outcomeMap[$validated['status']])) {
                        $actor = $request->user();
                        $this->bookingService->resolveBooking(
                            $booking,
                            $outcomeMap[$validated['status']],
                            $this->mapStatusToReasonCode($validated['status']),
                            'staff',
                            $actor?->id,
                            ['notes' => $validated['notes'] ?? null]
                        );
                        $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff', 'resolution']);
                    } else {
                        // For statuses not mapped to outcomes (e.g., in_progress), use regular transition
                        $this->bookingService->transitionStatus($booking, $validated['status']);
                        $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff']);
                    }
                } else {
                    // Regular status transition for non-needs_resolution bookings
                    $this->bookingService->transitionStatus($booking, $validated['status']);
                    $booking = $booking->fresh(['location', 'customer', 'specialist', 'service', 'staff']);
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
            // Remove status from the payload so we don't double-apply it below
            unset($validated['status']);
        }

        // Apply remaining non-status field changes
        $nonStatusFields = array_filter($validated, fn($key) => $key !== 'status', ARRAY_FILTER_USE_KEY);
        if (!empty($nonStatusFields)) {
            $booking->update($nonStatusFields);
        }

        return response()->json(new BookingResource($booking->load(['location', 'customer', 'specialist', 'service', 'staff', 'resolution'])));
    }

    /**
     * POST /bookings/{booking}/resolve
     *
     * Resolve a booking with a formal resolution record.
     * This is the preferred method for resolving bookings in needs_resolution state.
     */
    public function resolve(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'outcome'      => 'required|in:NO_SHOW,CANCELLED,RESCHEDULED,COMPLETED_LATE,CUSTOMER_ARRIVED',
            'reason_code'  => 'required|in:CUSTOMER_DID_NOT_ARRIVE,CUSTOMER_CANCELLED,CUSTOMER_REQUESTED_RESCHEDULE,CUSTOMER_COULD_NOT_BE_REACHED,CUSTOMER_ARRIVED_LATE,PROVIDER_UNAVAILABLE,SPECIALIST_UNAVAILABLE,LOCATION_CLOSED,TECHNICAL_ISSUE,OTHER',
            'notes'        => 'nullable|string',
            'follow_up_required' => 'sometimes|boolean',
            'follow_up_at' => 'nullable|date',
            'follow_up_notes' => 'nullable|string',
            'new_booking_data' => 'required_if:outcome,RESCHEDULED|array',
            'new_booking_data.date' => 'required_if:outcome,RESCHEDULED|date',
            'new_booking_data.time' => 'required_if:outcome,RESCHEDULED|string',
            'new_booking_data.customer_name' => 'required_if:outcome,RESCHEDULED|string',
        ]);

        try {
            $actor = $request->user();
            $options = [
                'notes' => $validated['notes'] ?? null,
                'follow_up_required' => $validated['follow_up_required'] ?? false,
                'follow_up_at' => $validated['follow_up_at'] ?? null,
                'follow_up_notes' => $validated['follow_up_notes'] ?? null,
            ];

            if ($validated['outcome'] === 'RESCHEDULED') {
                $options['new_booking_data'] = $validated['new_booking_data'];
            }

            $resolved = $this->bookingService->resolveBooking(
                $booking,
                $validated['outcome'],
                $validated['reason_code'],
                'user',
                $actor?->id,
                $options
            );

            return response()->json([
                'booking' => new BookingResource($resolved->load(['location', 'customer', 'specialist', 'service', 'staff', 'resolution'])),
                'message' => 'Booking resolved successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * POST /bookings/{booking}/follow-up
     *
     * Initiate follow-up for an existing resolution.
     */
    public function followUp(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'follow_up_at' => 'nullable|date',
            'follow_up_notes' => 'nullable|string',
        ]);

        if (!$booking->resolution) {
            return response()->json([
                'message' => 'This booking does not have a resolution.',
            ], 422);
        }

        try {
            $actor = $request->user();
            $updated = $this->bookingService->initiateFollowUp(
                $booking->resolution,
                'staff',
                $actor?->id,
                [
                    'follow_up_at' => $validated['follow_up_at'] ?? null,
                    'follow_up_notes' => $validated['follow_up_notes'] ?? null,
                ]
            );

            return response()->json([
                'resolution' => $updated,
                'message' => 'Follow-up initiated successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }
    }

    /**
     * Map booking status to appropriate reason code for resolution.
     */
    private function mapStatusToReasonCode(string $status): string
    {
        return match ($status) {
            'no_show' => 'CUSTOMER_DID_NOT_ARRIVE',
            'cancelled' => 'CUSTOMER_CANCELLED',
            'completed' => 'OTHER',
            'arrived' => 'CUSTOMER_ARRIVED_LATE',
            default => 'OTHER',
        };
    }

    /**
     * GET /bookings/needs-resolution
     *
     * Returns bookings that have reached needs_resolution state and are still unresolved.
     * This endpoint is historical - it does NOT filter by date=today.
     * A booking from yesterday or several days ago will appear if it remains unresolved.
     */
    public function needsResolution(Request $request): JsonResponse
    {
        $locationId = $request->attributes->get('location_id');
        $locationScope = $request->attributes->get('location_scope', 'all');
        $providerId = $request->attributes->get('provider_id');

        $query = Booking::with([
            'provider',
            'location',
            'customer',
            'specialist',
            'service',
            'staff',
            'resolution',
        ]);

        // Respect location_scope: 'specific' filters by location_id, 'all' is Provider-wide
        if ($locationScope === 'specific' && $locationId) {
            $query->where('location_id', $locationId);
        } elseif ($providerId) {
            // Provider-wide scope (location_scope === 'all' or location_id is null)
            $query->where('provider_id', $providerId);
        } else {
            return response()->json(['message' => 'Provider context not found'], 400);
        }

        // Core query: only bookings in needs_resolution state
        $query->where('operational_state', 'needs_resolution');

        // No date filter - this is historical by design
        // Bookings from yesterday or older will appear if still unresolved

        $bookings = $query->orderBy('date', 'desc')->orderBy('time', 'desc')->get();

        return response()->json([
            'count' => $bookings->count(),
            'bookings' => BookingResource::collection($bookings),
        ]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();
        return response()->json(null, 204);
    }

    public function byProvider(string $provider): JsonResponse
    {
        // Get provider_id from location (legacy salon request)
        $locationModel = \App\Models\Location::find($provider);
        $providerId = $locationModel ? $locationModel->provider_id : null;

        if (!$providerId) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        $bookings = Booking::where('provider_id', $providerId)
            ->with(['customer', 'specialist', 'service'])
            ->get();
        return response()->json($bookings);
    }

    public function byCustomer(string $customer): JsonResponse
    {
        $bookings = Booking::where('customer_id', $customer)
            ->with(['location', 'specialist', 'service', 'services'])
            ->orderBy('date', 'desc')
            ->orderBy('time', 'desc')
            ->get();
        return response()->json($bookings);
    }

    /**
     * Create booking with portal account (Journey 4)
     * This is the main entry point for website booking with account creation
     */
    public function storeWithAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'nullable|uuid|exists:locations,id', // Optional for independent specialists
            'provider_id' => 'required|uuid|exists:providers,id',
            'customer_name' => 'required|string',
            'customer_phone' => ['required', 'string', 'regex:/^[0-9+\s\-\(\)]{7,20}$/'],
            'customer_email' => 'nullable|email',
            'service_id' => 'required|array|min:1', // Accept array of service IDs
            'service_id.*' => 'uuid|exists:services,id', // Each service_id must be a valid UUID
            'specialist_id' => 'nullable|uuid|exists:specialists,id',
            'date' => 'required|date',
            'time' => 'nullable|string',
            'create_account' => 'boolean',
            'account_email' => 'required_if:create_account,true|nullable|email',
            'account_password' => 'required_if:create_account,true|nullable|string|min:8',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'idempotency_key' => 'nullable|string',
        ], [
            'customer_phone.regex' => 'Please enter a valid phone number.',
        ]);

        try {
            $result = app(\App\Services\BookingOrchestrator::class)->reserveAndBook($validated);

            return response()->json([
                'booking' => $result['booking'],
                'customer' => $result['customer'],
                'portal_account' => $result['portal_account'],
                'is_new_customer' => $result['is_new_customer'],
                'payment' => $result['payment'] ?? null,
                'message' => $result['portal_account'] 
                    ? 'Booking created. Please complete payment to confirm.' 
                    : 'Booking created. Please complete payment to confirm.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
