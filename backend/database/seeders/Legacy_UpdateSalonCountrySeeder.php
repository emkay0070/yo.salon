<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdateSalonCountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if salons table doesn't exist (migrated to Provider/Location architecture)
        if (!\Schema::hasTable('salons')) {
            $this->command->info('Skipping UpdateSalonCountrySeeder - salons table does not exist');
            return;
        }

        \App\Models\Salon::first()->update(['country' => 'Uganda']);
    }
}
