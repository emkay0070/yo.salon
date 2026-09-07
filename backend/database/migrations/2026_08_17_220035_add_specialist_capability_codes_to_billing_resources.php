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
        // Insert specialist capability codes
        DB::table('billing_resources')->insert([
            // Free capabilities (always available)
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_WORKSPACE',
                'name' => 'Specialist Workspace',
                'description' => 'Basic specialist dashboard and workspace',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_CALENDAR',
                'name' => 'Specialist Calendar',
                'description' => 'Availability and schedule management',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_APPOINTMENTS',
                'name' => 'Specialist Appointments',
                'description' => 'Booking management',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_PROFILE',
                'name' => 'Specialist Profile',
                'description' => 'Professional profile management',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_CRAFT',
                'name' => 'Specialist Craft',
                'description' => 'Skills and expertise management',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_CLIENTS',
                'name' => 'Specialist Clients',
                'description' => 'Customer relationship management',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_SETTINGS',
                'name' => 'Specialist Settings',
                'description' => 'Account and preference settings',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_VERIFICATION',
                'name' => 'Specialist Verification',
                'description' => 'Professional verification',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Pro capabilities (require subscription)
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_CAREER',
                'name' => 'Specialist Career',
                'description' => 'Career progression and tracking',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_INTELLIGENCE',
                'name' => 'Specialist Intelligence',
                'description' => 'AI-powered professional insights',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_FINANCE',
                'name' => 'Specialist Finance',
                'description' => 'Earnings and financial intelligence',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'SPECIALIST_JOURNEY',
                'name' => 'Specialist Journey',
                'description' => 'Growth tracking, goals, and milestones',
                'unit_label' => null,
                'is_metered' => false,
                'is_active' => true,
                'type' => 'feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('billing_resources')->whereIn('code', [
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
        ])->delete();
    }
};
