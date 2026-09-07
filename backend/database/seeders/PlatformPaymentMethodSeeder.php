<?php

namespace Database\Seeders;

use App\Models\PlatformPaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlatformPaymentMethodSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            // Launch Methods (Manual Verification)
            [
                'name' => 'MTN Mobile Money',
                'type' => 'mtn_personal',
                'verification_mode' => 'manual',
                'is_active' => true,
                'description' => 'Pay directly to our MTN Mobile Money number',
                'details' => [
                    'phone_number' => 'YOUR_MTN_NUMBER',
                    'account_name' => 'YOUR_ACCOUNT_NAME',
                    'instructions' => 'Send payment to the MTN Mobile Money number above',
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Airtel Money',
                'type' => 'airtel_personal',
                'verification_mode' => 'manual',
                'is_active' => true,
                'description' => 'Pay directly to our Airtel Money number',
                'details' => [
                    'phone_number' => 'YOUR_AIRTEL_NUMBER',
                    'account_name' => 'YOUR_ACCOUNT_NAME',
                    'instructions' => 'Send payment to the Airtel Money number above',
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'DFCU Bank Transfer',
                'type' => 'dfcu_bank',
                'verification_mode' => 'manual',
                'is_active' => true,
                'description' => 'Transfer to our DFCU bank account',
                'details' => [
                    'bank_name' => 'DFCU Bank',
                    'account_number' => 'YOUR_DFCU_ACCOUNT_NUMBER',
                    'account_name' => 'YOUR_ACCOUNT_NAME',
                    'branch' => 'YOUR_BRANCH',
                    'instructions' => 'Transfer to the DFCU account above',
                ],
                'sort_order' => 3,
            ],
            [
                'name' => 'Cash',
                'type' => 'cash',
                'verification_mode' => 'manual',
                'is_active' => true,
                'description' => 'Pay cash in person at our office',
                'details' => [
                    'location' => 'YOUR_OFFICE_ADDRESS',
                    'contact_person' => 'YOUR_CONTACT_PERSON',
                    'instructions' => 'Visit our office to pay cash',
                ],
                'sort_order' => 4,
            ],
            // Future Methods (Disabled for now)
            [
                'name' => 'MTN Merchant',
                'type' => 'mtn_merchant',
                'verification_mode' => 'automatic',
                'is_active' => false,
                'description' => 'Automatic payment via MTN Merchant account',
                'details' => [],
                'sort_order' => 5,
            ],
            [
                'name' => 'Flutterwave',
                'type' => 'flutterwave',
                'verification_mode' => 'webhook',
                'is_active' => false,
                'description' => 'Card payments via Flutterwave',
                'details' => [],
                'sort_order' => 6,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PlatformPaymentMethod::firstOrCreate(
                ['type' => $method['type']],
                $method
            );
            $this->command->info('Platform payment method created: ' . $method['name']);
        }
    }
}
