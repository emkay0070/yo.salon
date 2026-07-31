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
            $salonId = $data['salon_id'];
            $email = $data['email'];
            $password = $data['password'];
            $phone = $data['phone'];

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

            // Check if customer has relationship with this salon
            $hasSalonRelationship = $customer->salons()->where('salon_id', $salonId)->exists();

            if (!$hasSalonRelationship) {
                // Create new salon relationship for existing customer
                $customer->salons()->attach($salonId, [
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

            $salon = $customer->salons()->where('salon_id', $salonId)->first();

            return [
                'portal_account' => $portalAccount,
                'customer' => $customer,
                'salon' => $salon,
                'is_new_customer' => false,
                'is_new_salon_relationship' => !$hasSalonRelationship,
            ];
        });
    }

    /**
     * Login to portal account
     * Returns token and context. Salon context defaults to first salon if not provided.
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
        $salons = $customer->salons;
        
        $salonId = null;
        if ($salons->isNotEmpty()) {
            $salonId = $salons->first()->id;
        }

        $context = [];
        if ($salonId) {
            $context = $this->getContext($portalAccount, $salonId);
        }

        return [
            'token' => $token,
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
    public function getContext(PortalAccount $portalAccount, string $salonId): array
    {
        // Load customer without relationships to avoid circular references
        $customer = \App\Models\Customer::select('id', 'name', 'phone', 'email')
            ->where('id', $portalAccount->customer_id)
            ->first();

        if (!$customer) {
            throw new \Exception('Customer not found');
        }

        // Get salon relationship with pivot data
        $salonRelationship = \DB::table('customer_salon')
            ->where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->first();

        if (!$salonRelationship) {
            throw new \Exception('Customer does not have a relationship with this salon');
        }

        $visits = $salonRelationship->visits ?? 0;

        // Get active salon data without loading relationships
        $activeSalon = \App\Models\Salon::select('id', 'name', 'slug', 'logo', 'phone', 'email', 'address', 'opening_hours')
            ->where('id', $salonId)
            ->first();

        // All salons for the salon switcher - load from DB directly
        $allSalons = \DB::table('customer_salon')
            ->join('salons', 'customer_salon.salon_id', '=', 'salons.id')
            ->where('customer_salon.customer_id', $customer->id)
            ->select('salons.id', 'salons.name', 'salons.slug', 'salons.logo', 'customer_salon.visits')
            ->get()
            ->map(function ($s) use ($salonId) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'logo' => $s->logo,
                    'is_active' => $s->id === $salonId,
                    'visits' => $s->visits ?? 0,
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
            'active_salon' => [
                'id' => $activeSalon->id,
                'name' => $activeSalon->name,
                'slug' => $activeSalon->slug,
                'logo' => $activeSalon->logo,
                'phone' => $activeSalon->phone,
                'email' => $activeSalon->email,
                'address' => $activeSalon->address,
                'opening_hours' => $activeSalon->opening_hours,
            ],
            'salons' => $allSalons,
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
    public function getHomeData(PortalAccount $portalAccount, string $salonId): array
    {
        $customer = $portalAccount->customer;

        // Get upcoming booking for this salon
        $upcomingBooking = Booking::where('customer_id', $customer->id)
            ->where('salon_id', $salonId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->with(['staff', 'service'])
            ->orderBy('date')
            ->orderBy('time')
            ->first();

        // Get recommended services for this salon
        $recommendedServices = $this->recommendationService->getRecommendedServices($customer, $salonId, 4);

        // Get recent visits
        $recentVisits = $this->customerPortalService->getRecentVisits($customer, $salonId, 5);
        
        // Get last booking
        $lastBooking = $this->customerPortalService->getLastBooking($customer, $salonId);

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
            'recommended_services' => $recommendedServices,
            'recent_visits' => $recentVisits,
            'last_booking' => $lastBooking,
            'offers' => [], // Integration later
            'announcements' => [], // Integration later
            'quick_actions' => [
                ['id' => 'book', 'label' => 'Book Again', 'icon' => 'Repeat', 'action' => 'rebook'],
                ['id' => 'gift', 'label' => 'Buy Gift Card', 'icon' => 'Gift', 'action' => 'gift_card'],
            ],
        ];
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
