<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use App\Models\Salon;
use Illuminate\Database\Seeder;

class EmCutsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::where('slug', 'em-cuts')->first();

        if (!$salon) {
            $this->command->error('Salon with slug "em-cuts" not found.');
            return;
        }

        // Add services
        $services = [
            [
                'name' => 'Haircut',
                'category' => 'Hair',
                'price' => 25000,
                'duration' => 30,
                'active' => true,
            ],
            [
                'name' => 'Beard Trim',
                'category' => 'Grooming',
                'price' => 15000,
                'duration' => 15,
                'active' => true,
            ],
            [
                'name' => 'Haircut + Beard',
                'category' => 'Package',
                'price' => 35000,
                'duration' => 45,
                'active' => true,
            ],
            [
                'name' => 'Hair Styling',
                'category' => 'Hair',
                'price' => 30000,
                'duration' => 45,
                'active' => true,
            ],
            [
                'name' => 'Shave',
                'category' => 'Grooming',
                'price' => 20000,
                'duration' => 20,
                'active' => true,
            ],
        ];

        foreach ($services as $serviceData) {
            $service = Service::where('salon_id', $salon->id)
                ->where('name', $serviceData['name'])
                ->first();

            if (!$service) {
                Service::create([
                    ...$serviceData,
                    'salon_id' => $salon->id,
                ]);
                $this->command->info("Added service: {$serviceData['name']}");
            } else {
                $this->command->info("Service already exists: {$serviceData['name']}");
            }
        }

        // Add staff
        $staffMembers = [
            [
                'name' => 'Emma',
                'role' => 'Senior Barber',
                'active' => true,
            ],
            [
                'name' => 'John',
                'role' => 'Barber',
                'active' => true,
            ],
            [
                'name' => 'Sarah',
                'role' => 'Stylist',
                'active' => true,
            ],
        ];

        foreach ($staffMembers as $staffData) {
            $staff = Staff::where('salon_id', $salon->id)
                ->where('name', $staffData['name'])
                ->first();

            if (!$staff) {
                Staff::create([
                    ...$staffData,
                    'salon_id' => $salon->id,
                ]);
                $this->command->info("Added staff: {$staffData['name']}");
            } else {
                $this->command->info("Staff already exists: {$staffData['name']}");
            }
        }

        $this->command->info('Em-cuts demo data seeded successfully!');
    }
}
