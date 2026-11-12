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
            // Specialist Plans
            [
                'provider_type' => 'specialist',
                'name' => 'Free',
                'slug' => 'specialist-free',
                'description' => 'Core professional identity and workspace tools for specialists',
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
                'branches_limit' => 0,
                'storage_limit_gb' => 1,
                'support_level' => 'community',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'provider_type' => 'specialist',
                'name' => 'Pro',
                'slug' => 'specialist-pro',
                'description' => 'Advanced business intelligence and growth tools for specialists',
                'monthly_price' => 0, // Coming soon
                'yearly_price' => 0, // Coming soon
                'features' => json_encode([
                    'Everything in Free',
                    'Career Tracking',
                    'Business Intelligence',
                    'Finance Reports',
                    'Journey Goals',
                ]),
                'staff_limit' => 1,
                'branches_limit' => 0,
                'storage_limit_gb' => 10,
                'support_level' => 'email',
                'is_active' => true,
                'sort_order' => 2,
            ],
            // Salon Plans
            [
                'provider_type' => 'salon',
                'name' => 'Free Trial',
                'slug' => 'free-trial',
                'description' => '14 days to experience Yo.Salon',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'features' => json_encode([
                    'Smart Analytics',
                    'Online Booking',
                    'Customer Profiles',
                    'Workspace',
                    'Calendar',
                    'Appointments',
                    'Profile',
                    'Craft',
                    'Clients',
                    'Settings',
                ]),
                'staff_limit' => 5,
                'branches_limit' => 1,
                'storage_limit_gb' => 5,
                'support_level' => 'email',
                'is_active' => true,
                'sort_order' => 1,
            ],
            // Spa Plans (same as salon for now)
            [
                'provider_type' => 'spa',
                'name' => 'Free Trial',
                'slug' => 'spa-free-trial',
                'description' => '14 days to experience Yo.Salon',
                'monthly_price' => 0,
                'yearly_price' => 0,
                'features' => json_encode([
                    'Smart Analytics',
                    'Online Booking',
                    'Customer Profiles',
                    'Workspace',
                    'Calendar',
                    'Appointments',
                    'Profile',
                    'Craft',
                    'Clients',
                    'Settings',
                ]),
                'staff_limit' => 5,
                'branches_limit' => 1,
                'storage_limit_gb' => 5,
                'support_level' => 'email',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'provider_type' => 'salon',
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Everything you need to get started',
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
                    'Basic booking management',
                ]),
                'staff_limit' => 1,
                'branches_limit' => 1,
                'storage_limit_gb' => 1,
                'support_level' => 'community',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'provider_type' => 'salon',
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For growing spaces',
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
                'sort_order' => 3,
            ],
            [
                'provider_type' => 'salon',
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'For established spaces',
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
                'sort_order' => 4,
            ],
            [
                'provider_type' => 'salon',
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'For spaces ready to scale',
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
                'sort_order' => 5,
            ],
            [
                'provider_type' => 'salon',
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For larger operations and growing networks',
                'monthly_price' => 750000,
                'yearly_price' => 7500000,
                'features' => json_encode([
                    'Everything in Premium',
                    'Unlimited Staff',
                    'Unlimited Branches',
                    'White-label Solution',
                    'Custom Integrations',
                    '24/7 Phone Support',
                    'Dedicated Account Manager',
                    'Training Programs',
                    'SLA Guarantee',
                ]),
                'staff_limit' => null, // Unlimited
                'branches_limit' => null, // Unlimited
                'storage_limit_gb' => 500,
                'support_level' => '24/7',
                'is_active' => true,
                'sort_order' => 6,
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
