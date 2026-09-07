<?php

namespace App\Services;

use App\Models\User;
use App\Models\Salon;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\Provider;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class OnboardingService
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Complete onboarding by creating all salon-related records in a transaction
     */
    public function complete(User $user, array $onboardingData): Salon
    {
        return DB::transaction(function () use ($user, $onboardingData) {
            // Validate required data
            $this->validateOnboardingData($onboardingData);

            // Log the incoming data for debugging
            \Log::info('Onboarding data:', $onboardingData);

            // Create salon with safe array access
            $salonData = $onboardingData['salon'] ?? [];
            $walletData = $onboardingData['wallet'] ?? [];
            
            // Create a provider first (required for salon)
            $logoMediaId = null;
            if (!empty($salonData['logo']) && str_starts_with($salonData['logo'], 'data:image')) {
                $logoMedia = $this->mediaService->uploadFromBase64($salonData['logo'], [
                    'directory' => 'salons/logos',
                    'provider_id' => null, // Will set after provider is created
                    'salon_id' => null,
                    'alt_text' => $salonData['name'] . ' logo',
                ]);
                $logoMediaId = $logoMedia->id;
            }

            $provider = Provider::create([
                'type' => 'salon',
                'status' => 'active',
                'display_name' => $salonData['name'] ?? 'New Salon',
                'slug' => $this->generateUniqueSlug($salonData['name'] ?? 'New Salon'),
                'description' => $salonData['description'] ?? null,
                'logo' => $logoMediaId,
                'phone' => $salonData['phone'] ?? null,
                'email' => $salonData['email'] ?? null,
                'location' => [
                    'address' => $salonData['address'] ?? null,
                    'city' => $salonData['city'] ?? null,
                    'lat' => $salonData['lat'] ?? null,
                    'lng' => $salonData['lng'] ?? null,
                ],
                'active' => true,
            ]);

            // Update media with provider_id if logo was uploaded
            if ($logoMediaId) {
                $logoMedia->update(['provider_id' => $provider->id]);
            }
            
            $salon = Salon::create([
                'provider_id' => $provider->id,
                'name' => $salonData['name'] ?? 'New Salon',
                'slug' => $this->generateUniqueSlug($salonData['name'] ?? 'New Salon'),
                'description' => $salonData['description'] ?? null,
                'logo' => $salonData['logo'] ?? null,
                'whatsapp' => $salonData['whatsapp'] ?? null,
                'phone' => $salonData['phone'] ?? null,
                'email' => $salonData['email'] ?? null,
                'address' => $salonData['address'] ?? null,
                'lat' => $salonData['lat'] ?? null,
                'lng' => $salonData['lng'] ?? null,
                'city' => $salonData['city'] ?? null,
                'category' => $salonData['category'] ?? null,
                'vibe' => $salonData['vibe'] ?? null,
                'business_type' => $salonData['businessType'] ?? null,
                'team_size' => $salonData['teamSize'] ?? null,
                'branches' => $salonData['branches'] ?? 'one',
                'timezone' => $salonData['timezone'] ?? 'Africa/Kampala',
                'currency' => $salonData['currency'] ?? 'UGX',
                'opening_hours' => $walletData['openingHours'] ?? [],
            ]);

            // Link user to salon as owner
            $user->salons()->attach($salon->id, ['role' => 'owner']);

            $user->update([
                'status' => 'active',
            ]);

            // Create specialist records with assignments
            $teamData = $onboardingData['team'] ?? [];
            if (!empty($teamData) && is_array($teamData)) {
                foreach ($teamData as $teamMember) {
                    if (!is_array($teamMember)) continue;
                    
                    // Handle specialist photo upload
                    $photoMediaId = null;
                    if (!empty($teamMember['photo']) && str_starts_with($teamMember['photo'], 'data:image')) {
                        $photoMedia = $this->mediaService->uploadFromBase64($teamMember['photo'], [
                            'directory' => 'specialists/photos',
                            'provider_id' => $provider->id,
                            'salon_id' => null, // Specialist photos are provider-level
                            'alt_text' => $teamMember['name'] ?? 'Specialist photo',
                        ]);
                        $photoMediaId = $photoMedia->id;
                    }
                    
                    // Create Specialist (global identity)
                    $specialist = Specialist::create([
                        'name' => $teamMember['name'] ?? 'Team Member',
                        'phone' => $teamMember['phone'] ?? null,
                        'email' => $teamMember['email'] ?? null,
                        'specialties' => $teamMember['specializations'] ?? [],
                        'photo_media_id' => $photoMediaId,
                        'active' => true,
                    ]);
                    
                    // Create SpecialistAssignment (salon relationship)
                    SpecialistAssignment::create([
                        'specialist_id' => $specialist->id,
                        'provider_id' => $provider->id,
                        'salon_id' => $salon->id,
                        'role' => strtoupper($teamMember['role'] ?? 'SPECIALIST'),
                        'employment_type' => 'EMPLOYEE', // Default for onboarding
                        'commission_rate' => $teamMember['commission_rate'] ?? 0,
                        'is_primary' => true, // Primary assignment for this salon
                        'status' => 'ACTIVE',
                        'starts_at' => now(),
                    ]);
                }
            }

            // Create services
            $servicesData = $onboardingData['services'] ?? [];
            if (!empty($servicesData) && is_array($servicesData)) {
                foreach ($servicesData as $service) {
                    if (!is_array($service)) continue;
                    
                    Service::create([
                        'provider_id' => $provider->id,
                        'name' => $service['name'] ?? 'Service',
                        'description' => $service['description'] ?? null,
                        'duration' => $service['duration'] ?? 30,
                        'price' => $service['price'] ?? 0,
                        'category' => $service['category'] ?? null,
                        'active' => $service['enabled'] ?? true,
                    ]);
                }
            }

            // Create payment methods
            $paymentMethods = $walletData['paymentMethods'] ?? [];
            if (!empty($paymentMethods) && is_array($paymentMethods)) {
                foreach ($paymentMethods as $methodId) {
                    // $methodId is e.g. 'cash', 'mtn', 'airtel', 'card'
                    $displayName = match ($methodId) {
                        'mtn' => 'MTN Mobile Money',
                        'airtel' => 'Airtel Money',
                        'card' => 'Card',
                        default => 'Cash',
                    };
                    PaymentMethod::create([
                        'salon_id' => $salon->id,
                        'provider' => $methodId,
                        'type' => $methodId,
                        'display_name' => $displayName,
                        'is_primary' => false,
                    ]);
                }
            }

            // Create trial subscription
            $membershipData = $onboardingData['membership'] ?? [];
            if (!empty($membershipData)) {
                Subscription::create([
                    'provider_id' => $provider->id,
                    'plan_id' => $membershipData['plan_id'] ?? null,
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays(14),
                    'starts_at' => now(),
                ]);
            }

            // Mark onboarding session as completed
            $user->onboardingSession()->update([
                'completed' => true,
                'current_step' => 'completed',
            ]);

            return $salon->load(['specialists', 'paymentMethods', 'subscription', 'provider']);
        });
    }

    /**
     * Validate onboarding data structure
     */
    protected function validateOnboardingData(array $data): void
    {
        if (empty($data['salon']['name'])) {
            throw new Exception('Salon name is required');
        }
    }

    /**
     * Generate unique slug for salon
     */
    protected function generateUniqueSlug(string $name): string
    {
        // Convert to lowercase
        $slug = strtolower($name);
        
        // Remove special characters except spaces and hyphens
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        
        // Replace multiple spaces with single hyphen
        $slug = preg_replace('/[\s]+/', '-', $slug);
        
        // Replace multiple hyphens with single hyphen
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Handle empty slug
        if (empty($slug)) {
            $slug = 'salon-' . time();
        }
        
        $originalSlug = $slug;
        $counter = 1;

        while (Salon::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }
}
