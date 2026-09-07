<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use App\Models\Salon;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmCutsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create em-cuts salon if it doesn't exist
        $salon = Salon::where('slug', 'em-cuts')->first();

        if (!$salon) {
            $salon = Salon::create([
                'name' => 'Em Cuts',
                'slug' => 'em-cuts',
                'description' => 'Premium barbershop experience',
                'address' => '123 Kampala Road',
                'city' => 'Kampala',
                'booking_deposit_enabled' => true,
                'deposit_type' => 'percentage',
                'deposit_value' => 30,
                'deposit_required_for' => 'all',
                'deposit_min_service_amount' => 10000,
            ]);
            $this->command->info('Created salon: Em Cuts');
        }

        // Create owner user for Em Cuts
        $user = User::where('email', 'emcuts@yosalon.com')->first();
        if (!$user) {
            $user = User::create([
                'name'     => 'Em Cuts Owner',
                'email'    => 'emcuts@yosalon.com',
                'password' => 'password123', // plain text — User model casts to 'hashed' automatically
                'status'   => 'active',
            ]);
            $this->command->info('Created user: emcuts@yosalon.com / password123');
        }

        // Attach user to salon as owner
        if (!$user->salons()->where('salon_id', $salon->id)->exists()) {
            $user->salons()->attach($salon->id, ['role' => 'owner']);
            $this->command->info('Attached user to Em Cuts as owner');
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

        // Ensure the salon has a Provider
        $provider = \App\Models\Provider::where('slug', 'em-cuts')->first();
        if (!$provider) {
            $provider = \App\Models\Provider::create([
                'type' => 'salon',
                'status' => 'active',
                'display_name' => 'Em Cuts',
                'slug' => 'em-cuts',
                'active' => true,
            ]);
            $salon->update(['provider_id' => $provider->id]);
            $serviceList = [];
            foreach ($services as $data) {
                $service = Service::create([
                    'provider_id' => $provider->id,
                    'name'        => $data['name'],
                    'description' => $data['description'] ?? null,
                    'price'       => $data['price'],
                    'duration'    => $data['duration'],
                    'category'    => $data['category'],
                    'active'      => true,
                ]);
                $serviceList[] = $service;
                
                $this->command->info('Added service: ' . $service->name);
            }
        } else {
            foreach ($services as $serviceData) {
                $service = Service::where('provider_id', $provider->id)
                    ->where('name', $serviceData['name'])
                    ->first();

                if (!$service) {
                    $service = Service::create([
                        ...$serviceData,
                        'provider_id' => $provider->id,
                    ]);
                    $this->command->info("Added service: {$serviceData['name']}");
                } else {
                    $this->command->info("Service already exists: {$serviceData['name']}");
                }
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
                $role = $staffData['role'];

                Staff::create([
                    ...$staffData,
                    'salon_id' => $salon->id,
                ]);

                // Also create a specialist since the architecture migrated
                // Note: 'role' is NOT a column on specialists — it lives on the pivot
                $specialist = \App\Models\Specialist::create([
                    'name'        => $staffData['name'],
                    'active'      => $staffData['active'],
                    'provider_id' => $provider->id,
                ]);
                $specialist->salons()->attach($salon->id, ['role' => $role]);

                $this->command->info("Added staff/specialist: {$staffData['name']}");
            } else {
                $this->command->info("Staff already exists: {$staffData['name']}");
            }
        }

        // Add payment method (MTN Mobile Money)
        $paymentMethod = PaymentMethod::where('salon_id', $salon->id)->first();
        if (!$paymentMethod) {
            PaymentMethod::create([
                'salon_id' => $salon->id,
                'provider' => 'mtn',
                'type' => 'mobile_money',
                'display_name' => 'MTN Mobile Money',
                'account_identifier' => '256700123456',
                'currency' => 'UGX',
                'is_active' => true,
                'is_primary' => true,
                'environment' => 'sandbox',
                'merchant_id' => 'demo-merchant-id',
            ]);
            $this->command->info('Added payment method: MTN Mobile Money');
        } else {
            // Update existing payment method to be primary
            $paymentMethod->update(['is_primary' => true]);
            $this->command->info('Updated payment method to primary');
        }

        $this->command->info('Em-cuts demo data seeded successfully!');
    }
}
