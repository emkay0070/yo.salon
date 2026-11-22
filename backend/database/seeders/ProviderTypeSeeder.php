<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProviderTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $types = [
            [
                'id' => Str::uuid()->toString(),
                'key' => 'salon',
                'name' => 'Salon',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'independent_specialist',
                'name' => 'Independent Specialist',
                'is_active' => true,
                'is_independent' => true,
                'is_owner_managed' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'spa',
                'name' => 'Spa',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'beauty_school',
                'name' => 'Beauty School',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => Str::uuid()->toString(),
                'key' => 'mobile_team',
                'name' => 'Mobile Team',
                'is_active' => true,
                'is_independent' => false,
                'is_owner_managed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($types as $type) {
            DB::table('provider_types')->updateOrInsert(
                ['key' => $type['key']],
                $type
            );
        }

        $this->command->info('Provider types seeded successfully.');
    }
}
