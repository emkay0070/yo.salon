<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Free Specialist Plan
        $freePlanId = (string) \Illuminate\Support\Str::uuid();
        DB::table('plans')->insert([
            'id' => $freePlanId,
            'provider_type' => 'specialist',
            'name' => 'Free',
            'slug' => 'specialist-free',
            'description' => 'Core professional identity tools',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'features' => json_encode([
                'Workspace',
                'Calendar',
                'Appointments',
                'Profile',
                'Craft',
                'Clients',
                'Settings',
                'Verification',
            ]),
            'staff_limit' => 1,
            'branches_limit' => 1,
            'storage_limit_gb' => 1,
            'support_level' => 'community',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Pro Specialist Plan
        $proPlanId = (string) \Illuminate\Support\Str::uuid();
        DB::table('plans')->insert([
            'id' => $proPlanId,
            'provider_type' => 'specialist',
            'name' => 'Pro',
            'slug' => 'specialist-pro',
            'description' => 'Advanced business intelligence',
            'monthly_price' => 0, // Price to be determined
            'yearly_price' => 0, // Price to be determined
            'features' => json_encode([
                'All Free features',
                'Career',
                'Intelligence',
                'Finance',
                'Journey',
            ]),
            'staff_limit' => 1,
            'branches_limit' => 1,
            'storage_limit_gb' => 10,
            'support_level' => 'priority',
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get billing resource codes
        $freeCapabilities = [
            'SPECIALIST_WORKSPACE',
            'SPECIALIST_CALENDAR',
            'SPECIALIST_APPOINTMENTS',
            'SPECIALIST_PROFILE',
            'SPECIALIST_CRAFT',
            'SPECIALIST_CLIENTS',
            'SPECIALIST_SETTINGS',
            'SPECIALIST_VERIFICATION',
        ];

        $proCapabilities = [
            'SPECIALIST_CAREER',
            'SPECIALIST_INTELLIGENCE',
            'SPECIALIST_FINANCE',
            'SPECIALIST_JOURNEY',
        ];

        // Add Free plan entitlements
        foreach ($freeCapabilities as $code) {
            DB::table('plan_entitlements')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'plan_id' => $freePlanId,
                'resource_code' => $code,
                'limit' => 1, // Enabled
                'reset_period' => 'never', // Features don't reset
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Add Pro plan entitlements (includes Free + Pro features)
        foreach (array_merge($freeCapabilities, $proCapabilities) as $code) {
            DB::table('plan_entitlements')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'plan_id' => $proPlanId,
                'resource_code' => $code,
                'limit' => 1, // Enabled
                'reset_period' => 'never', // Features don't reset
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->where('provider_type', 'specialist')->delete();
    }
};
