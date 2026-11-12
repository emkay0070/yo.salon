<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PortalAccount;
use App\Models\Booking;
use App\Services\CustomerResolver;
use App\Services\CapabilityService;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortalAuthService
{
    private CustomerPortalService $customerPortalService;
    private PortalRecommendationService $recommendationService;
    private CustomerResolver $customerResolver;
    private CapabilityService $capabilityService;
    private LoyaltyService $loyaltyService;

    public function __construct(
        CustomerPortalService $customerPortalService,
        PortalRecommendationService $recommendationService,
        CustomerResolver $customerResolver,
        CapabilityService $capabilityService,
        LoyaltyService $loyaltyService
    ) {
        $this->customerPortalService = $customerPortalService;
        $this->recommendationService = $recommendationService;
        $this->customerResolver = $customerResolver;
        $this->capabilityService = $capabilityService;
        $this->loyaltyService = $loyaltyService;
    }

    /**
     * Register a new portal account for an existing customer
     * 
     * Principle: Authentication never creates customers. Business events create or resolve customers.
     * This method only creates a PortalAccount and links it to an existing Customer.
     * 
     * @param array $data ['email', 'password', 'phone', 'salon_id']
     * @return array
     * @throws ValidationException if customer doesn't exist or already has portal account
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $locationId = $data['location_id'] ?? null;
            $providerId = $data['provider_id'] ?? null;
            $email = $data['email'];
            $password = $data['password'];
            $phone = $data['phone'];

            // Resolve provider_id from location_id if needed
            if ($locationId && !$providerId) {
                $location = \App\Models\Location::query()->whereKey($locationId)->firstOrFail();
                $providerId = $location->provider_id;
            }

            if (!$providerId) {
                throw ValidationException::withMessages([
                    'provider_id' => ['Invalid provider or missing provider context.'],
                ]);
            }

            // Resolve existing customer by phone (primary identifier)
            $customer = $this->customerResolver->findByPhone($phone);

            // If not found by phone, try email
            if (!$customer && isset($data['email'])) {
                $customer = $this->customerResolver->findByEmail($data['email']);
            }

            // Customer must exist - authentication cannot create customers
            if (!$customer) {
                throw ValidationException::withMessages([
                    'phone' => ['No customer found with this phone or email. Please complete a booking or visit the salon first.'],
                ]);
            }

            // Check if customer already has a portal account
            if ($customer->hasPortalAccount()) {
                throw ValidationException::withMessages([
                    'email' => ['This customer already has a portal account. Please login instead.'],
                ]);
            }

            // Check if customer has relationship with this provider
            $hasProviderRelationship = $customer->providers()->where('provider_id', $providerId)->exists();

            if (!$hasProviderRelationship) {
                // Create new provider relationship for existing customer
                $customer->providers()->attach($providerId, [
                    'id' => (string) Str::uuid(),
                    'visits' => 0,
                    'joined_at' => now(),
                ]);
            }

            // Create and link portal account
            $portalAccount = PortalAccount::create([
                'customer_id' => $customer->id,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            return [
                'portal_account' => $portalAccount,
                'customer' => $customer,
                'location' => $locationId ? \App\Models\Location::find($locationId) : null,
                'is_new_customer' => false,
                'is_new_provider_relationship' => !$hasProviderRelationship,
            ];
        });
    }

    /**
     * Login to portal account
     * Returns token and context. Location context defaults to first location if not provided.
     */
    public function login(array $credentials): array
    {
        $portalAccount = PortalAccount::where('email', $credentials['email'])->first();

        if (!$portalAccount || !Hash::check($credentials['password'], $portalAccount->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Generate token using Sanctum
        $token = $portalAccount->createToken('portal-token')->plainTextToken;

        $customer = $portalAccount->customer;
        $providers = $customer->providers;
        
        $providerId = null;
        if ($providers->isNotEmpty()) {
            $providerId = $providers->first()->id;
        }

        $context = [];
        if ($providerId) {
            $context = $this->getContext($portalAccount, $providerId);
        } else {
            // No salon relationship yet — return minimal context so the customer
            // isn't shown as "Guest". They can complete setup from the portal.
            $context = [
                'portal_account' => [
                    'id'                  => $portalAccount->id,
                    'email'               => $portalAccount->email,
                    'email_verified_at'   => $portalAccount->email_verified_at,
                    'phone_verified_at'   => $portalAccount->phone_verified_at,
                ],
                'customer' => [
                    'id'     => $customer->id,
                    'name'   => $customer->name,
                    'phone'  => $customer->phone,
                    'email'  => $customer->email,
                    'visits' => 0,
                ],
                'active_salon'    => null,
                'salons'          => [],
                'capabilities'    => [],
                'wallet_summary'  => ['balance' => 0, 'packages' => 0, 'gift_cards' => 0],
                'loyalty_summary' => ['balance' => 0, 'tier' => 'bronze', 'tier_progress' => 0, 'points_to_next' => 100, 'next_tier' => 'silver'],
                'notification_count' => 0,
            ];
        }

        return [
            'token'   => $token,
            'context' => $context,
        ];
    }


    /**
     * Logout from portal account
     */
    public function logout(PortalAccount $portalAccount): void
    {
        $portalAccount->currentAccessToken()->delete();
    }

    /**
     * Get operating context for portal
     */
    public function getContext(PortalAccount $portalAccount, ?string $providerId): array
    {
        // Load customer without relationships to avoid circular references
        $customer = \App\Models\Customer::select('id', 'name', 'phone', 'email')
            ->where('id', $portalAccount->customer_id)
            ->first();

        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        // Get provider relationship with pivot data
        $providerRelationship = null;
        if ($providerId) {
            $providerRelationship = \DB::table('customer_providers')
                ->where('customer_id', $customer->id)
                ->where('provider_id', $providerId)
                ->first();
        }

        $visits = $providerRelationship->visits ?? 0;

        // Get active provider data
        $activeProvider = null;
        if ($providerId) {
            $activeProvider = \App\Models\Provider::select('id', 'display_name as name', 'slug', 'logo', 'phone', 'email')
                ->where('id', $providerId)
                ->first();
        }

        // All providers for the switcher
        $allProviders = \DB::table('customer_providers')
            ->join('providers', 'customer_providers.provider_id', '=', 'providers.id')
            ->where('customer_providers.customer_id', $customer->id)
            ->select('providers.id', 'providers.display_name as name', 'providers.slug', 'providers.logo', 'customer_providers.visits')
            ->get()
            ->map(function ($p) use ($providerId) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'logo' => $p->logo,
                    'is_active' => $p->id === $providerId,
                    'visits' => $p->visits ?? 0,
                ];
            })
            ->values();

        // Simplified capabilities - all enabled for now
        $capabilities = [
            'wallet' => true,
            'loyalty' => true,
            'gift_cards' => true,
            'packages' => true,
            'membership' => true,
            'offers' => true,
            'reviews' => true,
            'support' => true,
            'waitlist' => true,
            'referrals' => true,
            'my_stylist' => true,
            'rebook' => true,
            'service_categories' => true,
            'staff_profiles' => true,
        ];

        return [
            'portal_account' => [
                'id' => $portalAccount->id,
                'email' => $portalAccount->email,
                'email_verified_at' => $portalAccount->email_verified_at,
                'phone_verified_at' => $portalAccount->phone_verified_at,
            ],
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'visits' => $visits,
            ],
            'active_provider' => $activeProvider ? [
                'id' => $activeProvider->id,
                'name' => $activeProvider->name,
                'slug' => $activeProvider->slug,
                'logo' => $activeProvider->logo,
                'phone' => $activeProvider->phone,
                'email' => $activeProvider->email,
                'address' => null, // Providers don't have direct addresses, locations do
                'opening_hours' => null,
            ] : null,
            'providers' => $allProviders,
            'capabilities' => $capabilities,
            'wallet_summary' => [
                'balance' => 0,
                'packages' => 0,
                'gift_cards' => 0,
            ],
            'loyalty_summary' => [
                'balance' => 0,
                'tier' => 'bronze',
                'tier_progress' => 0,
                'points_to_next' => 100,
                'next_tier' => 'silver',
            ],
            'notification_count' => 0,
        ];
    }

    /**
     * Get dynamic home data for portal
     */
    public function getHomeData(PortalAccount $portalAccount, ?string $locationId): array
    {
        try {
            $customer = $portalAccount->customer;

            if (!$customer) {
                throw new \Exception('Customer not found for portal account');
            }

            // Get upcoming booking (globally if no locationId)
            $upcomingQuery = Booking::where('customer_id', $customer->id)
                ->where('date', '>=', now()->toDateString())
                ->where('status', '!=', 'cancelled')
                ->with(['staff', 'service', 'location']);
                
            if ($locationId) {
                $location = \App\Models\Location::find($locationId);
                if ($location) {
                    $upcomingQuery->where('location_id', $location->id);
                }
            }
            
            $upcomingBooking = $upcomingQuery->orderBy('date')->orderBy('time')->first();

            $providerId = isset($location) && $location ? $location->provider_id : null;

            // Get recommended services for this provider (empty if no provider context)
            $recommendedServices = $providerId ? $this->recommendationService->getRecommendedServices($customer, $providerId, 4) : [];

            // Get recent visits (empty if no provider context)
            $recentVisits = $providerId ? $this->customerPortalService->getRecentVisits($customer, $locationId, 5) : [];

            // Get last booking
            $lastBooking = $providerId ? $this->customerPortalService->getLastBooking($customer, $locationId) : null;

            // Get favorite stylist
            $favoriteStylist = $providerId ? $this->customerPortalService->getFavoriteStylist($customer, $locationId) : null;

            // Get wallet summary (placeholder, will be refactored to global later)
            $walletSummary = [
                'balance' => 0,
                'packages' => 0,
                'gift_cards' => 0,
            ];

            // Get loyalty summary (placeholder)
            $loyaltySummary = [
                'tier' => 'bronze',
                'points' => 0,
                'tier_progress' => 0,
                'points_to_next' => 100,
                'next_tier' => 'silver',
            ];

            return [
                'next_appointment' => $upcomingBooking ? [
                    'id' => $upcomingBooking->id,
                    'date' => $upcomingBooking->date,
                    'time' => $upcomingBooking->time,
                    'status' => $upcomingBooking->status,
                    'staff' => $upcomingBooking->staff ? [
                        'id' => $upcomingBooking->staff->id,
                        'name' => $upcomingBooking->staff->name,
                    ] : null,
                    'service' => $upcomingBooking->service ? [
                        'id' => $upcomingBooking->service->id,
                        'name' => $upcomingBooking->service->name,
                        'price' => $upcomingBooking->service->price,
                        'duration' => $upcomingBooking->service->duration,
                    ] : null,
                ] : null,
                'recommended_services' => $recommendedServices ?? [],
                'recent_visits' => $recentVisits ?? [],
                'last_booking' => $lastBooking,
                'favorite_stylist' => $favoriteStylist,
                'wallet_summary' => $walletSummary,
                'loyalty_summary' => $loyaltySummary,
                'offers' => [], // Integration later
                'announcements' => [], // Integration later
                'quick_actions' => [
                    ['id' => 'book', 'label' => 'Book Again', 'icon' => 'Repeat', 'action' => 'rebook'],
                    ['id' => 'gift', 'label' => 'Buy Gift Card', 'icon' => 'Gift', 'action' => 'gift_card'],
                ],
            ];
        } catch (\Exception $e) {
            \Log::error('getHomeData error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'portal_account_id' => $portalAccount->id,
                'location_id' => $locationId,
            ]);

            // Return safe default data instead of throwing
            return [
                'next_appointment' => null,
                'recommended_services' => [],
                'recent_visits' => [],
                'last_booking' => null,
                'favorite_stylist' => null,
                'wallet_summary' => [
                    'balance' => 0,
                    'packages' => 0,
                    'gift_cards' => 0,
                ],
                'loyalty_summary' => [
                    'tier' => 'bronze',
                    'points' => 0,
                    'tier_progress' => 0,
                    'points_to_next' => 100,
                    'next_tier' => 'silver',
                ],
                'offers' => [],
                'announcements' => [],
                'quick_actions' => [
                    ['id' => 'book', 'label' => 'Book Again', 'icon' => 'Repeat', 'action' => 'rebook'],
                    ['id' => 'gift', 'label' => 'Buy Gift Card', 'icon' => 'Gift', 'action' => 'gift_card'],
                ],
            ];
        }
    }

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(string $email): void
    {
        // TODO: Implement password reset logic
        // This will involve creating a password reset token and sending email
    }

    /**
     * Reset password
     */
    public function resetPassword(array $data): void
    {
        // TODO: Implement password reset logic
        // This will validate token and update password
    }

    /**
     * Verify email
     */
    public function verifyEmail(PortalAccount $portalAccount): void
    {
        $portalAccount->update([
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Verify phone
     */
    public function verifyPhone(PortalAccount $portalAccount): void
    {
        $portalAccount->update([
            'phone_verified_at' => now(),
        ]);
    }
}
