<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanEntitlementSeeder extends Seeder
{
    /**
     * Seed plan entitlements from existing Plan data.
     * Migrates hardcoded plan columns (staff_limit, branches_limit, storage_limit_gb)
     * to the flexible PlanEntitlement system.
     */
    public function run(): void
    {
        // Get all plans
        $plans = DB::table('plans')->get(['id', 'slug', 'staff_limit', 'branches_limit', 'storage_limit_gb']);

        $entitlements = [];

        foreach ($plans as $plan) {
            // Quota entitlements from existing plan columns
            $entitlements[] = [
                'id' => Str::uuid()->toString(),
                'plan_id' => $plan->id,
                'resource_code' => 'STAFF_SEAT',
                'limit' => $plan->staff_limit,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $entitlements[] = [
                'id' => Str::uuid()->toString(),
                'plan_id' => $plan->id,
                'resource_code' => 'BRANCH',
                'limit' => $plan->branches_limit,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $entitlements[] = [
                'id' => Str::uuid()->toString(),
                'plan_id' => $plan->id,
                'resource_code' => 'STORAGE_GB',
                'limit' => $plan->storage_limit_gb,
                'reset_period' => 'never',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Feature entitlements based on plan tier
            $features = $this->getFeaturesForPlan($plan->slug);
            foreach ($features as $featureCode) {
                $entitlements[] = [
                    'id' => Str::uuid()->toString(),
                    'plan_id' => $plan->id,
                    'resource_code' => $featureCode,
                    'limit' => 1, // Features are on/off (1 = enabled)
                    'reset_period' => 'never',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Credit entitlements (SMS, AI) based on plan tier
            $credits = $this->getCreditsForPlan($plan->slug);
            foreach ($credits as $creditCode => $amount) {
                $entitlements[] = [
                    'id' => Str::uuid()->toString(),
                    'plan_id' => $plan->id,
                    'resource_code' => $creditCode,
                    'limit' => $amount,
                    'reset_period' => 'monthly',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Clear existing entitlements and insert new ones
        DB::table('plan_entitlements')->delete();
        DB::table('plan_entitlements')->insert($entitlements);
    }

    /**
     * Get feature entitlements for each plan tier.
     */
    private function getFeaturesForPlan(string $planSlug): array
    {
        $baseFeatures = [
            'CALENDAR_WEEK',
            'CALENDAR_MONTH',
            'CUSTOMER_PORTAL',
            'ANALYTICS_ADVANCED',
            'INTELLIGENCE_AI',
            'BRANDING_CUSTOM',
            'SMS_NOTIFICATIONS',
        ];

        // Specialist feature codes
        $specialistFreeFeatures = [
            'SPECIALIST_WORKSPACE',
            'SPECIALIST_CALENDAR',
            'SPECIALIST_APPOINTMENTS',
            'SPECIALIST_PROFILE',
            'SPECIALIST_CRAFT',
            'SPECIALIST_CLIENTS',
            'SPECIALIST_SETTINGS',
            'SPECIALIST_VERIFICATION',
        ];

        $specialistProFeatures = [
            'SPECIALIST_WORKSPACE',
            'SPECIALIST_CALENDAR',
            'SPECIALIST_APPOINTMENTS',
            'SPECIALIST_PROFILE',
            'SPECIALIST_CRAFT',
            'SPECIALIST_CLIENTS',
            'SPECIALIST_SETTINGS',
            'SPECIALIST_VERIFICATION',
            'SPECIALIST_CAREER',
            'SPECIALIST_INTELLIGENCE',
            'SPECIALIST_FINANCE',
            'SPECIALIST_JOURNEY',
        ];

        return match($planSlug) {
            'specialist-free' => $specialistFreeFeatures,
            'specialist-pro' => $specialistProFeatures,
            'free-trial' => [
                'CALENDAR_WEEK',
                'CALENDAR_MONTH',
                'CUSTOMER_PORTAL',
                'ANALYTICS_ADVANCED',
                'INTELLIGENCE_AI',
                'BRANDING_CUSTOM',
                'SMS_NOTIFICATIONS',
            ],
            'starter' => [
                // Starter has basic features only
            ],
            'professional' => [
                'CALENDAR_WEEK',
                'CALENDAR_MONTH',
                'CUSTOMER_PORTAL',
                'ANALYTICS_ADVANCED',
                'SMS_NOTIFICATIONS',
            ],
            'premium' => [
                'CALENDAR_WEEK',
                'CALENDAR_MONTH',
                'CUSTOMER_PORTAL',
                'ANALYTICS_ADVANCED',
                'INTELLIGENCE_AI',
                'BRANDING_CUSTOM',
                'SMS_NOTIFICATIONS',
            ],
            'enterprise' => $baseFeatures,
            default => [],
        };
    }

    /**
     * Get credit entitlements (included credits) for each plan tier.
     */
    private function getCreditsForPlan(string $planSlug): array
    {
        return match($planSlug) {
            'free-trial' => [
                'SMS' => 500,
                'AI_REQUEST' => 100,
            ],
            'starter' => [
                'SMS' => 0, // Purchased separately
                'AI_REQUEST' => 0, // Purchased separately
            ],
            'professional' => [
                'SMS' => 500,
                'AI_REQUEST' => 100,
            ],
            'premium' => [
                'SMS' => 2000,
                'AI_REQUEST' => 1000,
            ],
            'enterprise' => [
                'SMS' => -1, // Unlimited
                'AI_REQUEST' => -1, // Unlimited
            ],
            default => [
                'SMS' => 0,
                'AI_REQUEST' => 0,
            ],
        };
    }
}
