<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PricesSeeder extends Seeder
{
    /**
     * Seed Uganda (UGX) pricing for all active platform products.
     * Prices are in UGX — whole numbers, no decimals needed.
     *
     * To add a new market: add a new price_book_code block below.
     */
    public function run(): void
    {
        $ugandaBook = DB::table('price_books')
            ->where('code', 'uganda_default')
            ->first();

        if (!$ugandaBook) {
            $this->command->warn('PriceBookSeeder must run before PricesSeeder. Skipping.');
            return;
        }

        // Map: product_code => price in UGX
        $ugandaPrices = [
            'SMS_500'                   => 30_000,
            'SMS_1000'                  => 55_000,   // slight bulk discount vs 2x SMS_500
            'AI_500'                    => 25_000,
            'AI_1000'                   => 45_000,   // slight bulk discount
            'MARKETING_PACK_5'          => 25_000,
            'MARKETPLACE_BOOST_MONTHLY' => 50_000,
            'EXTRA_STAFF_SEAT'          => 15_000,
            'EXTRA_BRANCH'              => 40_000,
        ];

        foreach ($ugandaPrices as $productCode => $amount) {
            $product = DB::table('platform_products')
                ->where('code', $productCode)
                ->first();

            if (!$product) {
                $this->command->warn("Product '{$productCode}' not found. Run PlatformProductSeeder first.");
                continue;
            }

            $existing = DB::table('prices')
                ->where('product_id', $product->id)
                ->where('price_book_id', $ugandaBook->id)
                ->first();

            $priceData = [
                'product_id'    => $product->id,
                'price_book_id' => $ugandaBook->id,
                'amount'        => $amount,
                'currency'      => 'UGX',
                'is_active'     => true,
                'updated_at'    => now(),
            ];

            if (!$existing) {
                DB::table('prices')->insert(array_merge($priceData, [
                    'id'         => Str::uuid()->toString(),
                    'created_at' => now(),
                ]));
            } else {
                DB::table('prices')
                    ->where('id', $existing->id)
                    ->update($priceData);
            }
        }
    }
}
