<?php

namespace App\Services;

use App\Models\User;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class OnboardingService
{
    /**
     * Complete onboarding by creating all salon-related records in a transaction
     */
    public function complete(User $user, array $onboardingData): Salon
    {
        return DB::transaction(function () use ($user, $onboardingData) {
            // Validate required data
            $this->validateOnboardingData($onboardingData);

            // Create salon
            $salon = Salon::create([
                'name' => $onboardingData['salon']['name'],
                'slug' => $this->generateUniqueSlug($onboardingData['salon']['name']),
                'description' => $onboardingData['salon']['description'] ?? null,
                'logo' => $onboardingData['salon']['logo'] ?? null,
                'whatsapp' => $onboardingData['salon']['whatsapp'] ?? null,
                'phone' => $onboardingData['salon']['phone'] ?? null,
                'email' => $onboardingData['salon']['email'] ?? null,
                'address' => $onboardingData['salon']['address'] ?? null,
                'lat' => $onboardingData['salon']['lat'] ?? null,
                'lng' => $onboardingData['salon']['lng'] ?? null,
                'city' => $onboardingData['salon']['city'] ?? null,
                'opening_hours' => $onboardingData['wallet']['openingHours'] ?? [],
            ]);

            // Link user to salon as owner
            $user->salons()->attach($salon->id, ['role' => 'owner']);

            $user->update([
                'status' => 'active',
            ]);

            // Create staff records
            if (!empty($onboardingData['team'])) {
                foreach ($onboardingData['team'] as $staffMember) {
                    Staff::create([
                        'salon_id' => $salon->id,
                        'name' => $staffMember['name'],
                        'email' => $staffMember['email'] ?? null,
                        'phone' => $staffMember['phone'] ?? null,
                        'role' => $staffMember['role'] ?? 'staff',
                        'specializations' => $staffMember['specializations'] ?? [],
                        'commission_rate' => $staffMember['commission_rate'] ?? 0,
                    ]);
                }
            }

            // Create services
            if (!empty($onboardingData['services'])) {
                foreach ($onboardingData['services'] as $service) {
                    Service::create([
                        'salon_id' => $salon->id,
                        'name' => $service['name'],
                        'description' => $service['description'] ?? null,
                        'duration' => $service['duration'] ?? 30,
                        'price' => $service['price'] ?? 0,
                        'category' => $service['category'] ?? null,
                    ]);
                }
            }

            // Create payment methods
            if (!empty($onboardingData['wallet']['paymentMethods'])) {
                foreach ($onboardingData['wallet']['paymentMethods'] as $methodId) {
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
            if (!empty($onboardingData['membership'])) {
                Subscription::create([
                    'salon_id' => $salon->id,
                    'plan_id' => $onboardingData['membership']['plan_id'] ?? null,
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

            return $salon->load(['staff', 'services', 'paymentMethods', 'subscription']);
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
        $slug = strtolower(str_replace(' ', '-', $name));
        $originalSlug = $slug;
        $counter = 1;

        while (Salon::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }
}
