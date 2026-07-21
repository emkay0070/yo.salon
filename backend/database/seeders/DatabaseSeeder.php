<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\Booking;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
        ]);

        // Create a default salon
        $salon = Salon::firstOrCreate(
            ['slug' => 'yo-salon'],
            [
                'name' => 'Yo Salon',
                'description' => 'Modern salon operating system',
                'address' => '123 Main St',
                'city' => 'New York',
            ]
        );

        $this->command->info('Salon created with ID: ' . $salon->id);

        // Create services
        $services = [
            ['name' => 'Haircut', 'description' => 'Professional haircut', 'price' => 45.00, 'duration' => 30, 'category' => 'Hair'],
            ['name' => 'Hair Color', 'description' => 'Full hair coloring', 'price' => 120.00, 'duration' => 120, 'category' => 'Hair'],
            ['name' => 'Manicure', 'description' => 'Classic manicure', 'price' => 25.00, 'duration' => 45, 'category' => 'Nails'],
            ['name' => 'Pedicure', 'description' => 'Relaxing pedicure', 'price' => 35.00, 'duration' => 60, 'category' => 'Nails'],
        ];

        $createdServices = [];
        foreach ($services as $serviceData) {
            $service = Service::create([
                'salon_id' => $salon->id,
                ...$serviceData,
                'active' => true,
            ]);
            $createdServices[] = $service;
            $this->command->info('Service created: ' . $service->name);
        }

        // Create customers with salon relationships (new architecture)
        $customers = [
            ['name' => 'Emma Johnson', 'phone' => '+1 555-123-4567', 'email' => 'emma@example.com'],
            ['name' => 'Olivia Smith', 'phone' => '+1 555-987-6543', 'email' => 'olivia@example.com'],
            ['name' => 'Sophia Williams', 'phone' => '+1 555-456-7890', 'email' => 'sophia@example.com'],
            ['name' => 'Ava Brown', 'phone' => '+1 555-789-0123', 'email' => 'ava@example.com'],
        ];

        $createdCustomers = [];
        foreach ($customers as $customerData) {
            $customer = Customer::create($customerData);
            // Create salon relationship through pivot table
            $customer->salons()->attach($salon->id, [
                'id' => (string) Str::uuid(),
                'visits' => rand(2, 10),
                'joined_at' => now(),
            ]);
            $createdCustomers[] = $customer;
            $this->command->info('Customer created: ' . $customer->name);
        }

        // Create staff
        $staff = Staff::create([
            'salon_id' => $salon->id,
            'name' => 'Maria Garcia',
            'phone' => '+1 555-321-6547',
            'email' => 'maria@yosalon.com',
            'specializations' => json_encode(['Haircut', 'Hair Color', 'Styling']),
            'availability' => json_encode([
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
                'saturday' => ['10:00', '16:00'],
            ]),
            'active' => true,
        ]);
        $this->command->info('Staff created: ' . $staff->name);

        // Create bookings
        $bookingTimes = ['10:00', '11:30', '13:00', '14:30', '16:00'];
        $bookingDates = [
            now()->toDateString(),
            now()->addDay()->toDateString(),
            now()->addDays(2)->toDateString(),
        ];
        $statuses = ['pending', 'confirmed', 'completed'];

        foreach ($createdCustomers as $index => $customer) {
            Booking::create([
                'salon_id' => $salon->id,
                'customer_id' => $customer->id,
                'staff_id' => $staff->id,
                'service_id' => $createdServices[$index % count($createdServices)]->id,
                'date' => $bookingDates[$index % count($bookingDates)],
                'time' => $bookingTimes[$index % count($bookingTimes)],
                'status' => $statuses[$index % count($statuses)],
                'notes' => $index === 0 ? 'First visit' : null,
            ]);
            $this->command->info('Booking created for: ' . $customer->name);
        }
    }
}
