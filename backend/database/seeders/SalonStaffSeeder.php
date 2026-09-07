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
        // Get all salons
        $salons = Salon::all();

        foreach ($salons as $salon) {
            // Create 3-5 staff members for each salon
            $staffCount = rand(3, 5);
            $specializations = ['Haircut', 'Hair Color', 'Styling', 'Manicure', 'Pedicure', 'Facial', 'Massage'];
            
            for ($i = 1; $i <= $staffCount; $i++) {
                $staffSpecializations = array_slice($specializations, 0, rand(2, 4));
                
                Staff::create([
                    'salon_id' => $salon->id,
                    'name' => $this->generateStaffName(),
                    'phone' => '+1 ' . rand(200, 999) . '-' . rand(100, 999) . '-' . rand(1000, 9999),
                    'email' => strtolower(str_replace(' ', '.', $this->generateStaffName())) . '@' . strtolower(str_replace(' ', '', $salon->name)) . '.com',
                    'specializations' => json_encode($staffSpecializations),
                    'availability' => json_encode([
                        'monday' => ['09:00', '18:00'],
                        'tuesday' => ['09:00', '18:00'],
                        'wednesday' => ['09:00', '18:00'],
                        'thursday' => ['09:00', '18:00'],
                        'friday' => ['09:00', '18:00'],
                        'saturday' => ['10:00', '17:00'],
                    ]),
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
