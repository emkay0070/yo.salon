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
                'name' => 'Em Cuts Owner',
                'email' => 'emcuts@yosalon.com',
                'password' => Hash::make('password123'),
                'status' => 'active',
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
