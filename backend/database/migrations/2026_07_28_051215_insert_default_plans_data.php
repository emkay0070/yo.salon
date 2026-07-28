<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plans = [
            [
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'description' => '14-day free trial of Professional plan features',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'features' => json_encode([
                    'Smart Analytics',
                    'Online Booking',
                    'Customer Profiles',
                    '14 Days Access',
                ]),
                'staff_limit' => 5,
                'branches_limit' => 1,
                'storage_limit_gb' => 5,
                'support_level' => 'email',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for small salons just getting started',
                'monthly_price' => 50000,
                'yearly_price' => 500000,
                'features' => json_encode([
                    'Smart Analytics',
                    'Customer Profiles',
                    'Online Booking',
                    'Basic Reports',
                    'Email Support',
                ]),
                'staff_limit' => 2,
                'branches_limit' => 1,
                'storage_limit_gb' => 5,
                'support_level' => 'email',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For growing salons with multiple staff members',
                'monthly_price' => 150000,
                'yearly_price' => 1500000,
                'features' => json_encode([
                    'Smart Analytics',
                    'Unlimited Appointments',
                    'Wallet',
                    'Customer Profiles',
                    'Online Booking',
                    'Reports',
                    'SMS Notifications',
                    'Priority Support',
                ]),
                'staff_limit' => 10,
                'branches_limit' => 2,
                'storage_limit_gb' => 25,
                'support_level' => 'priority',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Advanced features for established salons',
                'monthly_price' => 350000,
                'yearly_price' => 3500000,
                'features' => json_encode([
                    'Everything in Professional',
                    'Advanced Analytics',
                    'Multi-branch Management',
                    'Custom Branding',
                    'API Access',
                    'Dedicated Support',
                    'Staff Performance Tracking',
                    'Marketing Tools',
                ]),
                'staff_limit' => 25,
                'branches_limit' => 5,
                'storage_limit_gb' => 100,
                'support_level' => 'dedicated',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for salon chains and franchises',
                'monthly_price' => 750000,
                'yearly_price' => 7500000,
                'features' => json_encode([
                    'Everything in Premium',
                    'Unlimited Staff',
                    'Unlimited Branches',
                    'White-label Solution',
                    'Custom Integrations',
                    '24/7 Phone Support',
                    'Account Manager',
                    'Training Programs',
                    'SLA Guarantee',
                ]),
                'staff_limit' => -1, // Unlimited
                'branches_limit' => -1, // Unlimited
                'storage_limit_gb' => 500,
                'support_level' => '24/7',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($plans as $plan) {
            $existing = DB::table('plans')->where('slug', $plan['slug'])->first();
            if (!$existing) {
                $plan['id'] = Str::uuid()->toString();
                DB::table('plans')->insert($plan);
            } else {
                DB::table('plans')->where('slug', $plan['slug'])->update($plan);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('plans')->whereIn('slug', ['free-trial', 'starter', 'professional', 'premium', 'enterprise'])->delete();
    }
};
