<?php

namespace Database\Seeders;

use App\Models\SeederStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegisterSeedersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seeders = [
            [
                'seeder_class' => 'ReferenceDataSeeder',
                'display_name' => 'Reference Data',
                'description' => 'Platform reference data for grooming profiles (hair types, skin types, allergies, etc.)',
                'category' => 'platform',
                'is_required' => true,
                'priority' => 100,
            ],
            [
                'seeder_class' => 'UserSeeder',
                'display_name' => 'Users',
                'description' => 'Default users for the platform',
                'category' => 'users',
                'is_required' => false,
                'priority' => 50,
            ],
            [
                'seeder_class' => 'SalonSeeder',
                'display_name' => 'Salons',
                'description' => 'Sample salons for testing',
                'category' => 'business',
                'is_required' => false,
                'priority' => 40,
            ],
            [
                'seeder_class' => 'ServiceSeeder',
                'display_name' => 'Services',
                'description' => 'Sample services for salons',
                'category' => 'business',
                'is_required' => false,
                'priority' => 30,
            ],
        ];

        foreach ($seeders as $seeder) {
            SeederStatus::firstOrCreate(
                ['seeder_class' => $seeder['seeder_class']],
                [
                    'id' => Str::uuid(),
                    'display_name' => $seeder['display_name'],
                    'description' => $seeder['description'],
                    'category' => $seeder['category'],
                    'is_required' => $seeder['is_required'],
                    'priority' => $seeder['priority'],
                ]
            );
        }

        $this->command->info('Seeders registered successfully.');
    }
}
