<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providerId = $request->attributes->get('provider_id');
        $middlewareLocationId = $request->attributes->get('location_id');
        $middlewareLocationScope = $request->attributes->get('location_scope', 'all');
        $locationFilter = $request->query('location_filter'); // 'unassigned' for NULL location bookings
        $explicitLocationId = $request->query('location_id'); // Explicit location_id query param

        if (!$providerId) {
            return response()->json(['message' => 'No provider context associated with this request'], 403);
        }

        // Canonical: customer_providers relationship (provider-level ownership)
        $query = Customer::query()
            ->whereHas('providers', function ($q) use ($providerId) {
                $q->where('providers.id', $providerId);
            });

        // Location-based filtering based on booking history
        // Priority: explicit query params > middleware context
        if ($locationFilter === 'unassigned') {
            // Filter to customers with bookings where location_id IS NULL
            // Explicit filter takes precedence over middleware location context
            $query->whereHas('bookings', function ($q) use ($providerId) {
                $q->where('provider_id', $providerId)
                  ->whereNull('location_id')
                  ->where('status', '!=', 'cancelled');
            });
        } elseif ($explicitLocationId) {
            // Explicit location_id query param takes precedence
            // Validate the location belongs to the provider
            $location = \App\Models\Location::where('id', $explicitLocationId)
                ->where('provider_id', $providerId)
                ->first();

            if (!$location) {
                return response()->json(['message' => 'Location not found or does not belong to this Provider'], 403);
            }

            // Validate user has access to this location
            $authorityService = app(\App\Services\ProviderUserAuthorityService::class);
            if (!$authorityService->validateLocationScope($providerId, $explicitLocationId)) {
                return response()->json(['message' => 'You do not have access to this location'], 403);
            }

            $query->whereHas('bookings', function ($q) use ($providerId, $explicitLocationId) {
                $q->where('provider_id', $providerId)
                  ->where('location_id', $explicitLocationId)
                  ->where('status', '!=', 'cancelled');
            });
        } elseif ($middlewareLocationScope === 'specific' && $middlewareLocationId) {
            // Use middleware location context only if no explicit query params
            $query->whereHas('bookings', function ($q) use ($providerId, $middlewareLocationId) {
                $q->where('provider_id', $providerId)
                  ->where('location_id', $middlewareLocationId)
                  ->where('status', '!=', 'cancelled');
            });
        }
        // If no location filter specified, return all provider customers

        $customers = $query->with(['bookings' => function ($q) use ($providerId) {
            $q->where('provider_id', $providerId)->where('status', '!=', 'cancelled');
        }])->get();

        // Add location context metadata to each customer
        $customers->transform(function ($customer) use ($providerId, $explicitLocationId, $middlewareLocationId) {
            $lastBooking = $customer->bookings->where('status', 'completed')->sortByDesc('date')->first();
            $customer->setAttribute('lastVisit', $lastBooking ? $lastBooking->date : null);

            // Use explicit location_id if provided, otherwise use middleware location_id
            $currentLocationId = $explicitLocationId ?? $middlewareLocationId;

            // Build location context
            $bookingLocations = $customer->bookings
                ->whereNotNull('location_id')
                ->pluck('location_id')
                ->unique()
                ->values()
                ->toArray();

            $hasNullLocationBookings = $customer->bookings
                ->whereNull('location_id')
                ->isNotEmpty();

            $hasBookingsAtLocation = $currentLocationId && in_array($currentLocationId, $bookingLocations);

            $customer->setAttribute('locationContext', [
                'hasBookingsAtLocation' => $hasBookingsAtLocation ?? false,
                'hasNullLocationBookings' => $hasNullLocationBookings,
                'bookingLocations' => $bookingLocations,
            ]);

            return $customer;
        });

        // Get location metadata for response
        $location = null;
        $responseLocationId = $explicitLocationId ?? $middlewareLocationId;
        if ($responseLocationId) {
            $location = \App\Models\Location::where('id', $responseLocationId)
                ->where('provider_id', $providerId)
                ->first(['id', 'name']);
        }

        // Calculate distinct counts for each category
        $providerWideCount = \App\Models\Customer::query()
            ->whereHas('providers', function ($q) use ($providerId) {
                $q->where('providers.id', $providerId);
            })
            ->count();

        $provider = \App\Models\Provider::find($providerId);
        $locationCounts = [];
        if ($provider) {
            foreach ($provider->locations as $loc) {
                $locationCounts[$loc->id] = \DB::table('bookings')
                    ->where('provider_id', $providerId)
                    ->where('location_id', $loc->id)
                    ->where('status', '!=', 'cancelled')
                    ->count(\DB::raw('DISTINCT customer_id'));
            }
        }

        $unassignedCount = \DB::table('bookings')
            ->where('provider_id', $providerId)
            ->whereNull('location_id')
            ->where('status', '!=', 'cancelled')
            ->count(\DB::raw('DISTINCT customer_id'));

        return response()->json([
            'customers' => $customers,
            'metadata' => [
                'total' => $customers->count(),
                'locationScope' => $middlewareLocationScope,
                'locationId' => $responseLocationId,
                'locationName' => $location?->name,
                'locationFilter' => $locationFilter,
                'counts' => [
                    'providerWide' => $providerWideCount,
                    'byLocation' => $locationCounts,
                    'unassigned' => $unassignedCount,
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'sometimes|required_without:auth|uuid|exists:locations,id',
            'provider_id' => 'sometimes|required_without:auth|uuid|exists:providers,id',
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
            'visits' => 'sometimes|integer',
        ]);

        // Resolve active Provider context. Server-side context is primary and authoritative.
        $providerId = $request->attributes->get('provider_id');

        if (!$providerId && auth()->check()) {
            $providerId = $request->user()?->currentProvider()?->id;
        }

        // For unauthenticated/guest flows, derive from validated parameters if context was not injected
        if (!$providerId && !auth()->check()) {
            $locationId = $validated['location_id'] ?? null;
            $providerId = $validated['provider_id'] ?? null;

            if ($locationId && !$providerId) {
                $location = \App\Models\Location::find($locationId);
                $providerId = $location?->provider_id;
            }
        }

        if (!$providerId) {
            return response()->json(['message' => 'No provider context associated with this request'], 403);
        }

        // FIND-CRIT-04 / R-1: Check if customer already exists within the active Provider context.
        // A customer record belonging exclusively to another Provider must NEVER be disclosed
        // or returned here. Phone equality alone is not authorization.
        $lookupPhones = \App\Services\PhoneNormalizer::lookupValues($validated['phone']);
        $existingCustomer = Customer::whereIn('phone', $lookupPhones)
            ->whereHas('providers', function ($query) use ($providerId) {
                $query->where('providers.id', $providerId);
            })
            ->first();

        if (!$existingCustomer && !empty($validated['email'])) {
            $existingCustomer = Customer::where('email', $validated['email'])
                ->whereHas('providers', function ($query) use ($providerId) {
                    $query->where('providers.id', $providerId);
                })
                ->first();
        }

        if ($existingCustomer) {
            return response()->json([
                'message' => 'Customer already exists',
                'customer' => $existingCustomer,
            ], 200);
        }

        // Create new customer and associate with active Provider
        $customer = DB::transaction(function () use ($validated, $providerId) {
            $customer = Customer::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
            ]);

            $customer->providers()->attach($providerId, [
                'id' => (string) Str::uuid(),
                'visits' => $validated['visits'] ?? 0,
                'joined_at' => now(),
            ]);

            return $customer;
        });

        return response()->json($customer, 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'    => 'required|string',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
        ]);

        // Resolve provider_id from location_id if provided
        $providerId = $validated['provider_id'];
        if (!$providerId && $validated['location_id']) {
            $location   = \App\Models\Location::find($validated['location_id']);
            $providerId = $location?->provider_id;
        }

        if (!$providerId) {
            return response()->json(['found' => false]);
        }

        $lookupPhones = \App\Services\PhoneNormalizer::lookupValues($validated['phone']);
        $customer = Customer::whereIn('phone', $lookupPhones)
            ->whereHas('providers', function ($query) use ($providerId) {
                $query->where('providers.id', $providerId);
            })
            ->first();

        // Security: Return only found status, no customer details before verification
        if ($customer) {
            return response()->json(['found' => true]);
        }

        return response()->json(['found' => false]);
    }

    /**
     * Find booking-safe customer details for existing customers
     * This is for the "Find my details" booking convenience feature
     * Returns only the information needed to prefill the booking form
     */
    public function findDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'    => 'required|string',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'provider_id' => 'nullable|uuid|exists:providers,id',
        ]);

        // Resolve provider_id from location_id if provided
        $providerId = $validated['provider_id'];
        if (!$providerId && $validated['location_id']) {
            $location   = \App\Models\Location::find($validated['location_id']);
            $providerId = $location?->provider_id;
        }

        if (!$providerId) {
            return response()->json(['found' => false]);
        }

        $lookupPhones = \App\Services\PhoneNormalizer::lookupValues($validated['phone']);
        $customer = Customer::whereIn('phone', $lookupPhones)
            ->whereHas('providers', function ($query) use ($providerId) {
                $query->where('providers.id', $providerId);
            })
            ->first();

        if ($customer) {
            return response()->json([
                'found' => true,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ]
            ]);
        }

        return response()->json(['found' => false]);
    }

    public function show(Customer $customer): JsonResponse
    {
        // FIND-CRIT-03: Verify the customer is associated with the active Provider.
        // Authorization uses server-side provider context only (never client-supplied IDs).
        $this->authorize('view', $customer);

        $providerId = request()->attributes->get('provider_id');
        $customer->load(['bookings' => function ($q) use ($providerId) {
            if ($providerId) {
                $q->where('provider_id', $providerId);
            }
        }]);
        $lastBooking = $customer->bookings->where('status', 'completed')->sortByDesc('date')->first();
        $customer->setAttribute('lastVisit', $lastBooking ? $lastBooking->date : null);
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        // FIND-CRIT-03: Verify the customer is associated with the active Provider
        // before returning any data or accepting any mutation.
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'phone' => 'sometimes|string',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
            'visits' => 'sometimes|integer',
        ]);

        $customer->update($validated);
        return response()->json($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        // FIND-CRIT-03: Verify the customer is associated with the active Provider.
        $this->authorize('detach', $customer);

        // FIND-CRIT-03: Domain safety — a Provider must never permanently delete a
        // globally shared customer identity that may be associated with other Providers.
        //
        // Safe behavior: remove only this Provider's association (customer_providers pivot).
        // The customer identity record is preserved for other Providers.
        //
        // Global customer deletion is not supported through this endpoint.
        // If a future domain decision requires global deletion, it must be a separate,
        // explicitly authorized administrative operation with cascade-impact review.
        $providerId = request()->attributes->get('provider_id');

        $customer->providers()->detach($providerId);

        return response()->json(['message' => 'Customer removed from your Provider successfully'], 200);
    }
}
