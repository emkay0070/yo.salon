<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Staff;
use App\Models\Booking;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AnalyticsDemoSeeder extends Seeder
{
    /**
     * Seed the database with rich analytics demo data.
     * Designed to trigger the Intelligence Engine's rules:
     * - Churn Predictor (customers who haven't booked in 45-90 days)
     * - Slow Day Detector (Tuesdays underutilized)
     * - Staff Utilization (multiple staff members with varying loads)
     * - Revenue Ledger (gross vs net with fee breakdowns)
     */
    public function run(): void
    {
        $salon = Salon::where('slug', 'yo-salon')->first();

        if (!$salon) {
            $this->command->error('No salon found with slug "yo-salon". Run DatabaseSeeder first.');
            return;
        }

        $this->command->info('🎨 Starting Analytics Demo Seeder for: ' . $salon->name);

        // ── 1. Services ──────────────────────────────────────────────────────
        $serviceData = [
            ['name' => 'Executive Hair Cut', 'price' => 120000, 'duration' => 45, 'category' => 'Hair'],
            ['name' => 'Full Color Treatment', 'price' => 350000, 'duration' => 180, 'category' => 'Hair'],
            ['name' => 'Luxury Manicure', 'price' => 80000, 'duration' => 60, 'category' => 'Nails'],
            ['name' => 'Signature Pedicure', 'price' => 90000, 'duration' => 75, 'category' => 'Nails'],
            ['name' => 'Facial Treatment', 'price' => 200000, 'duration' => 90, 'category' => 'Skincare'],
            ['name' => 'Braiding', 'price' => 150000, 'duration' => 120, 'category' => 'Hair'],
        ];
        $services = [];
        foreach ($serviceData as $data) {
            $services[] = Service::firstOrCreate(
                ['salon_id' => $salon->id, 'name' => $data['name']],
                array_merge($data, ['salon_id' => $salon->id, 'active' => true])
            );
        }
        $this->command->info('✅ Services seeded: ' . count($services));

        // ── 2. Staff ─────────────────────────────────────────────────────────
        $staffData = [
            ['name' => 'Naluyima Grace',  'email' => 'grace@yo-salon.ug',   'specializations' => ['Haircut', 'Braiding', 'Styling']],
            ['name' => 'Nakato Priscilla', 'email' => 'priscilla@yo-salon.ug', 'specializations' => ['Nails', 'Pedicure']],
            ['name' => 'Kato Robert',     'email' => 'robert@yo-salon.ug',  'specializations' => ['Facials', 'Skincare']],
        ];
        $staff = [];
        foreach ($staffData as $data) {
            $staff[] = Staff::firstOrCreate(
                ['salon_id' => $salon->id, 'email' => $data['email']],
                [
                    'salon_id' => $salon->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => '+256 7' . rand(00000000, 99999999),
                    'specializations' => json_encode($data['specializations']),
                    'active' => true,
                ]
            );
        }
        $this->command->info('✅ Staff seeded: ' . count($staff));

        // ── 3. Payment Method ─────────────────────────────────────────────────
        $cashMethod = PaymentMethod::firstOrCreate(
            ['salon_id' => $salon->id, 'provider' => 'cash'],
            ['salon_id' => $salon->id, 'display_name' => 'Cash', 'type' => 'cash', 'provider' => 'cash', 'is_primary' => true, 'is_active' => true]
        );
        $mtnMethod = PaymentMethod::firstOrCreate(
            ['salon_id' => $salon->id, 'provider' => 'mtn_momo'],
            ['salon_id' => $salon->id, 'display_name' => 'MTN Mobile Money', 'type' => 'mobile_money', 'provider' => 'mtn_momo', 'is_primary' => false, 'is_active' => true]
        );

        // ── 4. Customers (50 realistic Ugandan customers) ───────────────────
        $customerNames = [
            ['first_name' => 'Auma', 'last_name' => 'Faith'],
            ['first_name' => 'Nakate', 'last_name' => 'Sarah'],
            ['first_name' => 'Nantongo', 'last_name' => 'Agnes'],
            ['first_name' => 'Namirembe', 'last_name' => 'Brenda'],
            ['first_name' => 'Kisakye', 'last_name' => 'Joan'],
            ['first_name' => 'Nalwanga', 'last_name' => 'Rita'],
            ['first_name' => 'Namukasa', 'last_name' => 'Josephine'],
            ['first_name' => 'Namutebi', 'last_name' => 'Diana'],
            ['first_name' => 'Nalubega', 'last_name' => 'Carol'],
            ['first_name' => 'Nakabugo', 'last_name' => 'Esther'],
            ['first_name' => 'Nakalema', 'last_name' => 'Gloria'],
            ['first_name' => 'Namugeri', 'last_name' => 'Hellen'],
            ['first_name' => 'Nakabembe', 'last_name' => 'Irene'],
            ['first_name' => 'Najjemba', 'last_name' => 'Jane'],
            ['first_name' => 'Nakyejwe', 'last_name' => 'Karen'],
            ['first_name' => 'Nalubwama', 'last_name' => 'Laura'],
            ['first_name' => 'Namubiru', 'last_name' => 'Mary'],
            ['first_name' => 'Nakafu', 'last_name' => 'Olivia'],
            ['first_name' => 'Nakintu', 'last_name' => 'Patricia'],
            ['first_name' => 'Namala', 'last_name' => 'Queen'],
        ];

        $createdCustomers = [];
        foreach ($customerNames as $i => $nameData) {
            $fullName = $nameData['first_name'] . ' ' . $nameData['last_name'];
            $customer = Customer::firstOrCreate(
                ['email' => strtolower($nameData['first_name']) . '@example.ug'],
                [
                    'name' => $fullName,
                    'phone' => '+256 77' . str_pad($i, 7, '0', STR_PAD_LEFT),
                    'email' => strtolower($nameData['first_name']) . '@example.ug',
                ]
            );
            $customer->salons()->syncWithoutDetaching([
                $salon->id => [
                    'id' => (string) Str::uuid(),
                    'visits' => rand(2, 15),
                    'joined_at' => now()->subDays(rand(30, 365)),
                ]
            ]);
            $createdCustomers[] = $customer;
        }
        $this->command->info('✅ Customers seeded: ' . count($createdCustomers));

        // ── 5. Bookings & Transactions (90 days of history) ──────────────────
        $this->command->info('⏳ Generating 90 days of booking and transaction history...');

        $bookingTimes = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
        $bookingCount = 0;
        $txCount = 0;

        // Generate bookings for the last 90 days
        // Intentionally sparse on Tuesdays to trigger the "Slow Day" rule
        for ($daysAgo = 90; $daysAgo >= 0; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo);
            $dayOfWeek = $date->dayOfWeek; // 0=Sun, 2=Tue

            // Tuesday = 2 in Carbon, make it low (0-1 bookings only)
            $maxBookingsToday = ($dayOfWeek === 2) ? rand(0, 1) : rand(2, 5);

            for ($b = 0; $b < $maxBookingsToday; $b++) {
                $customer = $createdCustomers[array_rand($createdCustomers)];
                $service  = $services[array_rand($services)];
                $staffMember = $staff[array_rand($staff)];

                // Last 5 customers get last bookings 50-80 days ago (triggers churn rule)
                if (in_array($customer->id, array_map(fn($c) => $c->id, array_slice($createdCustomers, -5)))) {
                    if ($daysAgo < 50) continue; // Only create old bookings for churn customers
                }

                $booking = Booking::create([
                    'salon_id'   => $salon->id,
                    'customer_id' => $customer->id,
                    'staff_id'   => $staffMember->id,
                    'service_id' => $service->id,
                    'date'       => $date->toDateString(),
                    'time'       => $bookingTimes[array_rand($bookingTimes)],
                    'status'     => $daysAgo > 0 ? 'completed' : 'confirmed',
                ]);
                $bookingCount++;

                // Create matching transaction with fee calculation
                if ($booking->status === 'completed') {
                    $grossAmount = $service->price;
                    $useDigital  = rand(0, 2) > 0; // 2/3 chance of digital payment
                    $paymentMethod = $useDigital ? $mtnMethod : $cashMethod;

                    // Fee calculation mirrors FeeEngine logic
                    if ($paymentMethod->provider === 'cash') {
                        $gatewayFee = 0;
                        $platformFee = 0;
                    } else {
                        // MTN Mobile Money: 2% gateway + 2500 flat
                        $gatewayFee = round($grossAmount * 0.02);
                        $platformFee = 2500;
                    }

                    $netAmount = $grossAmount - $gatewayFee - $platformFee;

                    Transaction::create([
                        'salon_id'          => $salon->id,
                        'booking_id'        => $booking->id,
                        'customer_id'       => $customer->id,
                        'payment_method_id' => $paymentMethod->id,
                        'type'              => 'payment',
                        'status'            => 'completed',
                        'gross_amount'      => $grossAmount,
                        'gateway_fee'       => $gatewayFee,
                        'platform_fee'      => $platformFee,
                        'tax_amount'        => 0,
                        'net_amount'        => $netAmount,
                        'currency'          => 'UGX',
                        'internal_reference' => 'TXN-DEMO-' . strtoupper(Str::random(8)),
                        'notes'             => 'Demo transaction',
                        'paid_at'           => $date,
                        'created_at'        => $date,
                        'updated_at'        => $date,
                    ]);
                    $txCount++;
                }
            }
        }

        $this->command->info("✅ Bookings created: {$bookingCount}");
        $this->command->info("✅ Transactions created: {$txCount}");
        $this->command->info('');
        $this->command->info('🎉 Analytics Demo Data seeded successfully!');
        $this->command->info('   - Slow Day rule triggered: Tuesdays have very low volume.');
        $this->command->info('   - Churn Risk rule triggered: 5 customers have old bookings (50+ days ago).');
        $this->command->info('   - Ledger populated with Gross/Fee/Net breakdown.');
    }
}
