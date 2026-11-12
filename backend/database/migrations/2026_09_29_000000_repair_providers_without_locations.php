<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Repair Providers that were created without Locations due to onboarding bug.
     * 
     * Bug: OnboardingService only created Location if address was non-null,
     * but coordinates (lat/lng) are sufficient for a valid Location.
     * 
     * This migration finds Providers without Locations and creates them using
     * the same logic as OnboardingService, pulling data from onboarding sessions.
     */
    public function up(): void
    {
        // Find Providers without any Locations
        $providersWithoutLocations = DB::table('providers')
            ->whereNotIn('id', function ($query) {
                $query->select('provider_id')->from('locations');
            })
            ->get();

        foreach ($providersWithoutLocations as $provider) {
            // Try to get onboarding session data for the provider's owner
            $userProvider = DB::table('provider_users')
                ->where('provider_id', $provider->id)
                ->where('role', 'owner')
                ->first();

            if (!$userProvider) {
                continue;
            }

            $onboardingSession = DB::table('onboarding_sessions')
                ->where('user_id', $userProvider->user_id)
                ->first();

            if (!$onboardingSession) {
                continue;
            }

            $draftData = json_decode($onboardingSession->draft_data, true);
            $businessData = $draftData['business'] ?? $draftData['salon'] ?? [];
            $walletData = $draftData['wallet'] ?? [];

            // Only create Location if we have address OR coordinates (same logic as OnboardingService)
            if (empty($businessData['address']) && (empty($businessData['lat']) || empty($businessData['lng']))) {
                continue;
            }

            // Generate location slug
            $locationSlug = Str::slug(($businessData['name'] ?? $provider->display_name) . '-' . ($businessData['city'] ?? 'location'));
            if (DB::table('locations')->where('provider_id', $provider->id)->where('slug', $locationSlug)->count() > 0) {
                $locationSlug .= '-' . time();
            }

            // Create Location using same structure as OnboardingService
            $locationId = (string) Str::uuid();
            DB::table('locations')->insert([
                'id' => $locationId,
                'provider_id' => $provider->id,
                'name' => $businessData['name'] ?? $provider->display_name,
                'slug' => $locationSlug,
                'address' => $businessData['address'] ?? null,
                'city' => $businessData['city'] ?? null,
                'country' => $businessData['country'] ?? null,
                'lat' => $businessData['lat'] ?? null,
                'lng' => $businessData['lng'] ?? null,
                'phone' => $businessData['phone'] ?? $provider->phone,
                'email' => $businessData['email'] ?? $provider->email,
                'is_primary' => true,
                'is_active' => true,
                'opening_hours' => isset($walletData['openingHours']) ? json_encode($walletData['openingHours']) : null,
                'timezone' => $businessData['timezone'] ?? $provider->timezone ?? 'Africa/Kampala',
                'created_at' => $provider->created_at,
                'updated_at' => now(),
            ]);

            // Update SpecialistAssignments to reference the new location if they exist without location_id
            DB::table('specialist_assignments')
                ->where('provider_id', $provider->id)
                ->whereNull('location_id')
                ->update(['location_id' => $locationId]);

            // Update Staff records to reference the new location if they exist without location_id
            DB::table('staff')
                ->where('provider_id', $provider->id)
                ->whereNull('location_id')
                ->update(['location_id' => $locationId]);
        }
    }

    /**
     * Reverse the migration - remove Locations created by this repair.
     */
    public function down(): void
    {
        // This is a data repair migration; reversing it would require
        // knowing which Locations were created by this migration.
        // For safety, we don't auto-reverse data repairs.
    }
};
