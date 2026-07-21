<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
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
            ],
        ];

        foreach ($plans as $planData) {
            Plan::firstOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
            $this->command->info('Plan created: ' . $planData['name']);
        }
    }
}
