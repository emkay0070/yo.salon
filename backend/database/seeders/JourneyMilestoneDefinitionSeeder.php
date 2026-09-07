<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class JourneyMilestoneDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $milestones = [
            [
                'code' => 'FIRST_CLIENT',
                'title' => 'First Client',
                'description' => 'Complete your first booking',
                'category' => 'CLIENT',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'booking_completed', 'count' => 1],
            ],
            [
                'code' => 'FIRST_REVIEW',
                'title' => 'First Review',
                'description' => 'Receive your first client review',
                'category' => 'REPUTATION',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'review_received', 'count' => 1],
            ],
            [
                'code' => 'FIRST_5_STAR',
                'title' => 'First 5-Star Review',
                'description' => 'Receive your first 5-star rating',
                'category' => 'REPUTATION',
                'tier' => 'SILVER',
                'trigger_criteria' => ['event' => 'review_5_star', 'count' => 1],
            ],
            [
                'code' => '100_CLIENTS',
                'title' => '100 Clients',
                'description' => 'Serve 100 unique clients',
                'category' => 'CLIENT',
                'tier' => 'GOLD',
                'trigger_criteria' => ['event' => 'clients_count', 'count' => 100],
            ],
            [
                'code' => '100_SERVICES',
                'title' => '100 Services',
                'description' => 'Complete 100 service bookings',
                'category' => 'SERVICE',
                'tier' => 'SILVER',
                'trigger_criteria' => ['event' => 'booking_completed', 'count' => 100],
            ],
            [
                'code' => 'NEW_EXPERTISE',
                'title' => 'New Expertise',
                'description' => 'Add a new expertise to your craft',
                'category' => 'CRAFT',
                'tier' => 'BRONZE',
                'trigger_criteria' => ['event' => 'expertise_added'],
            ],
        ];

        foreach ($milestones as $milestone) {
            \App\Models\JourneyMilestoneDefinition::updateOrCreate(
                ['code' => $milestone['code']],
                $milestone
            );
        }
    }
}
