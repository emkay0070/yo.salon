<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SalonStaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip if salons table doesn't exist (migrated to Provider/Location architecture)
        if (!\Schema::hasTable('salons')) {
            $this->command->info('Skipping SalonStaffSeeder - salons table does not exist');
            return;
        }

        // Get all salons
        $salons = Salon::all();

        foreach ($salons as $salon) {
            // Create 3-5 staff members for each salon
            $staffCount = rand(3, 5);

            for ($i = 1; $i <= $staffCount; $i++) {
                Staff::create([
                    'provider_id' => $salon->provider_id,
                    'location_id' => $salon->location_id,
                    'name' => $this->generateStaffName(),
                    'phone' => '+1 ' . rand(200, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999),
                    'email' => strtolower(str_replace(' ', '.', $this->generateStaffName())) . '@' . strtolower(str_replace(' ', '', $salon->name)) . '.com',
                    'active' => true,
                ]);

                $this->command->info("Created staff member #{$i} for salon: {$salon->name}");
            }
        }

        $this->command->info('Salon staff seeded successfully!');
    }

    /**
     * Generate a random staff name
     */
    private function generateStaffName(): string
    {
        $firstNames = ['Emma', 'Olivia', 'Sophia', 'Ava', 'Isabella', 'Mia', 'Charlotte', 'Amelia', 'Harper', 'Evelyn', 'Liam', 'Noah', 'Oliver', 'Elijah', 'James', 'William'];
        $lastNames = ['Johnson', 'Smith', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez', 'Anderson', 'Taylor', 'Thomas', 'Moore', 'Jackson'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}
