<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingResourceSeeder extends Seeder
{
    /**
     * Seed the canonical list of billable resources.
     * These are the atomic units the billing engine meters.
     * Add new resources here — never hardcode resource names elsewhere.
     */
    public function run(): void
    {
        $resources = [
            // Credits - consumable resources
            [
                'code'        => 'SMS',
                'name'        => 'SMS Message',
                'description' => 'A single outbound SMS notification or reminder.',
                'unit_label'  => 'message',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'credit',
            ],
            [
                'code'        => 'AI_REQUEST',
                'name'        => 'AI Request',
                'description' => 'A single inference call to the Yo.Salon Copilot AI engine.',
                'unit_label'  => 'request',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'credit',
            ],
            [
                'code'        => 'WHATSAPP',
                'name'        => 'WhatsApp Message',
                'description' => 'A single outbound WhatsApp message.',
                'unit_label'  => 'message',
                'is_metered'  => true,
                'is_active'   => false, // Not yet live — schema ready
                'type'        => 'credit',
            ],
            [
                'code'        => 'EMAIL',
                'name'        => 'Transactional Email',
                'description' => 'A single outbound transactional email.',
                'unit_label'  => 'email',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'credit',
            ],
            // Quotas - capacity limits
            [
                'code'        => 'STORAGE_GB',
                'name'        => 'Storage',
                'description' => '1 GB of file storage for salon media (photos, logos, gallery).',
                'unit_label'  => 'GB',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'quota',
            ],
            [
                'code'        => 'STAFF_SEAT',
                'name'        => 'Staff Seat',
                'description' => 'One active staff member account.',
                'unit_label'  => 'seat',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'quota',
            ],
            [
                'code'        => 'BRANCH',
                'name'        => 'Branch',
                'description' => 'One additional salon branch location.',
                'unit_label'  => 'branch',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'quota',
            ],
            // Unlimited - not strictly limited
            [
                'code'        => 'BOOKING',
                'name'        => 'Booking',
                'description' => 'One confirmed customer appointment.',
                'unit_label'  => 'booking',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'unlimited',
            ],
            [
                'code'        => 'EXPORT',
                'name'        => 'Data Export',
                'description' => 'One data export (customers, revenue, bookings).',
                'unit_label'  => 'export',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'unlimited',
            ],
            [
                'code'        => 'API_CALL',
                'name'        => 'API Call',
                'description' => 'A single call to the Yo.Salon public API.',
                'unit_label'  => 'call',
                'is_metered'  => true,
                'is_active'   => false, // Not yet live — schema ready
                'type'        => 'unlimited',
            ],
            [
                'code'        => 'MARKETING_CAMPAIGN',
                'name'        => 'Marketing Campaign',
                'description' => 'One outbound marketing campaign sent to a salon\'s customer list.',
                'unit_label'  => 'campaign',
                'is_metered'  => true,
                'is_active'   => true,
                'type'        => 'unlimited',
            ],
            // Feature - on/off capabilities
            [
                'code'        => 'MARKETPLACE_BOOST',
                'name'        => 'Marketplace Featured Listing',
                'description' => 'One month of featured placement in the Yo.Salon Discover marketplace.',
                'unit_label'  => 'month',
                'is_metered'  => false, // Feature flag — on or off
                'is_active'   => true,
                'type'        => 'feature',
            ],
            // Feature resources for plan-based capabilities
            [
                'code'        => 'CALENDAR_WEEK',
                'name'        => 'Week Calendar View',
                'description' => 'Access to week calendar view in booking management.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'CALENDAR_MONTH',
                'name'        => 'Month Calendar View',
                'description' => 'Access to month calendar view in booking management.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'CUSTOMER_PORTAL',
                'name'        => 'Customer Portal',
                'description' => 'Ability to invite customers to self-service portal.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'ANALYTICS_ADVANCED',
                'name'        => 'Advanced Analytics',
                'description' => 'Access to revenue trends, customer retention, and staff performance analytics.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'INTELLIGENCE_AI',
                'name'        => 'AI Intelligence Center',
                'description' => 'Access to AI-powered insights, demand forecasting, and business intelligence.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'BRANDING_CUSTOM',
                'name'        => 'Custom Branding',
                'description' => 'Ability to customize colors, fonts, logos, and white-label appearance.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SMS_NOTIFICATIONS',
                'name'        => 'SMS Notifications',
                'description' => 'Ability to send SMS notifications and reminders.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            // Specialist feature resources
            [
                'code'        => 'SPECIALIST_WORKSPACE',
                'name'        => 'Specialist Workspace',
                'description' => 'Access to professional workspace dashboard for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_CALENDAR',
                'name'        => 'Specialist Calendar',
                'description' => 'Schedule management and calendar for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_APPOINTMENTS',
                'name'        => 'Specialist Appointments',
                'description' => 'Booking management for specialist appointments.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_PROFILE',
                'name'        => 'Specialist Profile',
                'description' => 'Public professional profile for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_CRAFT',
                'name'        => 'Specialist Craft',
                'description' => 'Services and expertise management for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_CLIENTS',
                'name'        => 'Specialist Clients',
                'description' => 'Customer management for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_SETTINGS',
                'name'        => 'Specialist Settings',
                'description' => 'Account settings for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_VERIFICATION',
                'name'        => 'Specialist Verification',
                'description' => 'Professional verification for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_CAREER',
                'name'        => 'Specialist Career',
                'description' => 'Career tracking and milestones for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_INTELLIGENCE',
                'name'        => 'Specialist Intelligence',
                'description' => 'Business insights and analytics for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_FINANCE',
                'name'        => 'Specialist Finance',
                'description' => 'Revenue tracking and reports for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
            [
                'code'        => 'SPECIALIST_JOURNEY',
                'name'        => 'Specialist Journey',
                'description' => 'Growth goals and achievements for specialists.',
                'unit_label'  => null,
                'is_metered'  => false,
                'is_active'   => true,
                'type'        => 'feature',
            ],
        ];

        foreach ($resources as $resource) {
            $existing = DB::table('billing_resources')
                ->where('code', $resource['code'])
                ->first();

            if (!$existing) {
                DB::table('billing_resources')->insert(array_merge($resource, [
                    'id'         => Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('billing_resources')
                    ->where('code', $resource['code'])
                    ->update(array_merge($resource, ['updated_at' => now()]));
            }
        }
    }
}
