<?php

namespace App\Services;

use App\Models\User;
use App\Models\Location;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\Provider;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class OnboardingService
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Complete onboarding by creating all provider + location records in a transaction.
     */
    public function complete(User $user, array $onboardingData): Provider
    {
        return DB::transaction(function () use ($user, $onboardingData) {
            $this->validateOnboardingData($onboardingData);

            \Log::info('Onboarding data:', $onboardingData);

            $businessData = $onboardingData['business'] ?? $onboardingData['salon'] ?? [];
            $walletData = $onboardingData['wallet'] ?? [];

            // Get business category from selected category slug
            $categorySlug = $businessData['category'] ?? null;
            $businessCategoryId = $this->getCategoryIdFromSlug($categorySlug);

            if (!$businessCategoryId) {
                throw new Exception('Invalid business category: ' . ($categorySlug ?? 'none'));
            }

            // Upload logo
            $logoMediaId = null;
            if (!empty($businessData['logo']) && str_starts_with($businessData['logo'], 'data:image')) {
                $logoMedia = $this->mediaService->uploadFromBase64($businessData['logo'], [
                    'directory'   => 'providers/logos',
                    'provider_id' => null,
                    'alt_text'    => ($businessData['name'] ?? 'Provider') . ' logo',
                ]);
                $logoMediaId = $logoMedia->id;
            }

            // Determine provider type from category (preserve existing type enum for compatibility)
            $providerType = $this->mapCategoryToProviderType($categorySlug);

            // Create Provider (business identity / tenant)
            $provider = Provider::create([
                'type'                => $providerType,
                'status'              => 'active',
                'display_name'        => $businessData['name'] ?? 'New Business',
                'slug'                => $this->generateUniqueSlug($businessData['name'] ?? 'New Business'),
                'description'         => $businessData['description'] ?? null,
                'logo'                => $logoMediaId,
                'phone'               => $businessData['phone'] ?? null,
                'email'               => $businessData['email'] ?? null,
                'active'              => true,
                'business_category_id' => $businessCategoryId,
            ]);

            // Link logo media to provider
            if ($logoMediaId) {
                $logoMedia->update(['provider_id' => $provider->id]);
            }

            // Create primary Location - always create if we have address OR coordinates
            $location = null;
            if (!empty($businessData['address']) || (!empty($businessData['lat']) && !empty($businessData['lng']))) {
                $locationSlug = Str::slug(($businessData['name'] ?? 'location') . '-' . ($businessData['city'] ?? 'location'));
                if (DB::table('locations')->where('provider_id', $provider->id)->where('slug', $locationSlug)->count() > 0) {
                    $locationSlug .= '-' . time();
                }

                $locationId = DB::table('locations')->insertGetId([
                    'id'            => (string) Str::uuid(),
                    'provider_id'   => $provider->id,
                    'name'          => $businessData['name'] ?? 'Primary Location',
                    'slug'          => $locationSlug,
                    'address'       => $businessData['address'] ?? null,
                    'city'          => $businessData['city'] ?? null,
                    'country'       => $businessData['country'] ?? null,
                    'lat'           => $businessData['lat'] ?? null,
                    'lng'           => $businessData['lng'] ?? null,
                    'phone'         => $businessData['phone'] ?? null,
                    'email'         => $businessData['email'] ?? null,
                    'is_primary'    => true,
                    'is_active'     => true,
                    'opening_hours' => !empty($walletData['openingHours']) ? json_encode($walletData['openingHours']) : null,
                    'timezone'      => $businessData['timezone'] ?? 'Africa/Kampala',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ], 'id');

                $location = Location::find($locationId);

                // Create canonical LocationSchedule records from opening_hours
                $this->createLocationSchedules($location, $walletData['openingHours'] ?? []);

                // Create canonical ProviderSchedule records for provider-level scheduling
                $this->createProviderSchedules($provider, $walletData['openingHours'] ?? []);
            }

            // Create Provider booking config
            $bookingConfig = \App\Models\ProviderBookingConfig::firstOrCreate(
                ['provider_id' => $provider->id],
                [
                    'availability_policy'  => null,
                    'assignment_policy'    => null,
                    'payment_policy'       => null,
                    'cancellation_policy'  => null,
                    'approval_policy'      => null,
                    'booking_policy'       => null,
                ]
            );

            if (isset($walletData['appointmentDuration']) && !empty($walletData['appointmentDuration'])) {
                $currentBookingPolicy = $bookingConfig->booking_policy ?? [];
                $currentBookingPolicy['appointment_duration'] = (int) $walletData['appointmentDuration'];
                $bookingConfig->booking_policy = $currentBookingPolicy;
                $bookingConfig->save();
            }

            // Link user to provider as owner with provider-wide authority
            $user->providers()->attach($provider->id, [
                'id' => (string) Str::uuid(),
                'role' => 'owner',
                'authority_scope' => 'provider_wide'
            ]);
            $user->update(['status' => 'active']);

            // Create specialist records
            $teamData = $onboardingData['team'] ?? [];
            if (!empty($teamData) && is_array($teamData)) {
                foreach ($teamData as $teamMember) {
                    if (!is_array($teamMember)) continue;

                    $photoMediaId = null;
                    if (!empty($teamMember['photo']) && str_starts_with($teamMember['photo'], 'data:image')) {
                        $photoMedia = $this->mediaService->uploadFromBase64($teamMember['photo'], [
                            'directory'   => 'specialists/photos',
                            'provider_id' => $provider->id,
                            'alt_text'    => $teamMember['name'] ?? 'Specialist photo',
                        ]);
                        $photoMediaId = $photoMedia->id;
                    }

                    $specialist = Specialist::create([
                        'name'          => $teamMember['name'] ?? 'Team Member',
                        'phone'         => $teamMember['phone'] ?? null,
                        'email'         => $teamMember['email'] ?? null,
                        'specialties'   => $teamMember['specializations'] ?? [],
                        'photo_media_id'=> $photoMediaId,
                        'active'        => true,
                    ]);

                    SpecialistAssignment::create([
                        'specialist_id'   => $specialist->id,
                        'provider_id'     => $provider->id,
                        'location_id'     => $location?->id,
                        'role'            => strtoupper($teamMember['role'] ?? 'SPECIALIST'),
                        'employment_type' => 'EMPLOYEE',
                        'commission_rate' => $teamMember['commission_rate'] ?? 0,
                        'is_primary'      => true,
                        'status'          => 'ACTIVE',
                        'starts_at'       => now(),
                    ]);
                }
            }

            // Create services
            $servicesData = $onboardingData['services'] ?? [];
            if (!empty($servicesData) && is_array($servicesData)) {
                foreach ($servicesData as $service) {
                    if (!is_array($service)) continue;
                    if (empty($service['enabled']) || empty($service['price'])) continue;

                    Service::create([
                        'provider_id' => $provider->id,
                        'name'        => $service['name'] ?? 'Service',
                        'description' => $service['description'] ?? null,
                        'duration'    => (int)($service['duration'] ?? 30),
                        'price'       => (int)($service['price'] ?? 0),
                        'category'    => $service['category'] ?? null,
                        'active'      => $service['enabled'] ?? true,
                    ]);
                }
            }

            // Create payment methods
            $paymentMethods = $walletData['paymentMethods'] ?? [];
            if (!empty($paymentMethods) && is_array($paymentMethods)) {
                foreach ($paymentMethods as $methodId) {
                    $displayName = match ($methodId) {
                        'mtn'    => 'MTN Mobile Money',
                        'airtel' => 'Airtel Money',
                        'card'   => 'Card',
                        default  => 'Cash',
                    };
                    PaymentMethod::create([
                        'provider_id'  => $provider->id,
                        'provider'     => $methodId,
                        'type'         => $methodId,
                        'display_name' => $displayName,
                        'is_primary'   => false,
                    ]);
                }
            }

            // Create trial subscription
            $membershipData = $onboardingData['membership'] ?? [];
            if (!empty($membershipData)) {
                Subscription::create([
                    'provider_id'  => $provider->id,
                    'plan_id'      => $membershipData['plan_id'] ?? null,
                    'status'       => 'trial',
                    'trial_ends_at'=> now()->addDays(14),
                    'starts_at'    => now(),
                ]);
            }

            // Mark onboarding complete
            $user->onboardingSession()->update([
                'completed'    => true,
                'current_step' => 'completed',
            ]);

            return $provider->load(['specialists', 'subscription']);
        });
    }

    /**
     * Validate onboarding data structure
     */
    protected function validateOnboardingData(array $data): void
    {
        $businessData = $data['business'] ?? $data['salon'] ?? [];
        if (empty($businessData['name'])) {
            throw new Exception('Business name is required');
        }
    }

    /**
     * Generate unique slug for provider
     */
    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        if (empty($slug)) {
            $slug = 'provider-' . time();
        }

        $originalSlug = $slug;
        $counter      = 1;

        while (Provider::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    /**
     * Get category ID from slug
     */
    private function getCategoryIdFromSlug(?string $slug): ?string
    {
        if (!$slug) return null;
        $category = DB::table('business_categories')->where('slug', $slug)->first();
        return $category ? $category->id : null;
    }

    /**
     * Map category slug to Provider type enum for compatibility
     * BusinessCategory is the authoritative classification; this reads
     * the provider_type from the business_categories table.
     */
    private function mapCategoryToProviderType(?string $categorySlug): string
    {
        // Default to salon if no category or unknown
        if (!$categorySlug) return Provider::TYPE_SALON;

        // Read provider_type from business_categories table
        $category = DB::table('business_categories')->where('slug', $categorySlug)->first();

        if ($category && $category->provider_type) {
            return $category->provider_type;
        }

        // Fallback to hardcoded mapping for backward compatibility
        return match($categorySlug) {
            'spa' => Provider::TYPE_SPA,
            'beauty-school' => Provider::TYPE_BEAUTY_SCHOOL,
            'mobile-team' => Provider::TYPE_MOBILE_TEAM,
            'independent-specialist' => Provider::TYPE_SPECIALIST,
            default => Provider::TYPE_SALON,
        };
    }

    /**
     * Create canonical LocationSchedule records from opening_hours JSON
     * Maps frontend day IDs (monday, tuesday, etc.) to LocationSchedule day_of_week format
     */
    private function createLocationSchedules(Location $location, array $openingHours): void
    {
        $dayMap = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $workingDays = array_keys($openingHours);

        foreach ($dayMap as $frontendDay => $scheduleDay) {
            if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
                $hours = $openingHours[$frontendDay];
                \App\Models\LocationSchedule::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'day_of_week' => $scheduleDay,
                    ],
                    [
                        'open_time' => $hours['open'] . ':00',
                        'close_time' => $hours['close'] . ':00',
                        'is_closed' => false,
                    ]
                );
            } else {
                // Mark non-working days as closed
                \App\Models\LocationSchedule::updateOrCreate(
                    [
                        'location_id' => $location->id,
                        'day_of_week' => $scheduleDay,
                    ],
                    [
                        'open_time' => null,
                        'close_time' => null,
                        'is_closed' => true,
                    ]
                );
            }
        }
    }

    /**
     * Create canonical ProviderSchedule records from opening_hours JSON
     * Maps frontend day IDs (monday, tuesday, etc.) to ProviderSchedule day_of_week integer format (0-6)
     */
    private function createProviderSchedules(Provider $provider, array $openingHours): void
    {
        $dayMap = [
            'monday' => 1,    // Carbon::MONDAY = 1
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 0,    // Carbon::SUNDAY = 0
        ];

        $workingDays = array_keys($openingHours);

        foreach ($dayMap as $frontendDay => $dayOfWeekInt) {
            if (in_array($frontendDay, $workingDays) && isset($openingHours[$frontendDay])) {
                $hours = $openingHours[$frontendDay];
                \App\Models\ProviderSchedule::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'day_of_week' => $dayOfWeekInt,
                    ],
                    [
                        'open_time' => $hours['open'] . ':00',
                        'close_time' => $hours['close'] . ':00',
                        'is_closed' => false,
                    ]
                );
            } else {
                // Mark non-working days as closed
                \App\Models\ProviderSchedule::updateOrCreate(
                    [
                        'provider_id' => $provider->id,
                        'day_of_week' => $dayOfWeekInt,
                    ],
                    [
                        'open_time' => null,
                        'close_time' => null,
                        'is_closed' => true,
                    ]
                );
            }
        }
    }
}
