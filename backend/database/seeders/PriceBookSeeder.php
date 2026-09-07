<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PriceBookSeeder extends Seeder
{
    /**
     * Seed the initial price books.
     * Start with Uganda (UGX) as the default.
     * Other regions can be added here as Yo.Salon expands.
     */
    public function run(): void
    {
        $priceBooks = [
            [
                'code'         => 'uganda_default',
                'name'         => 'Uganda — Default Pricing',
                'currency'     => 'UGX',
                'country_code' => 'UG',
                'is_default'   => true,
                'is_active'    => true,
                'valid_from'   => null,
                'valid_until'  => null,
            ],
            // ── Future markets — schema ready, inactive ──────────────
            [
                'code'         => 'kenya_default',
                'name'         => 'Kenya — Default Pricing',
                'currency'     => 'KES',
                'country_code' => 'KE',
                'is_default'   => false,
                'is_active'    => false,
                'valid_from'   => null,
                'valid_until'  => null,
            ],
            [
                'code'         => 'nigeria_default',
                'name'         => 'Nigeria — Default Pricing',
                'currency'     => 'NGN',
                'country_code' => 'NG',
                'is_default'   => false,
                'is_active'    => false,
                'valid_from'   => null,
                'valid_until'  => null,
            ],
            [
                'code'         => 'tanzania_default',
                'name'         => 'Tanzania — Default Pricing',
                'currency'     => 'TZS',
                'country_code' => 'TZ',
                'is_default'   => false,
                'is_active'    => false,
                'valid_from'   => null,
                'valid_until'  => null,
            ],
        ];

        foreach ($priceBooks as $book) {
            $existing = DB::table('price_books')
                ->where('code', $book['code'])
                ->first();

            if (!$existing) {
                DB::table('price_books')->insert(array_merge($book, [
                    'id'         => Str::uuid()->toString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('price_books')
                    ->where('code', $book['code'])
                    ->update(array_merge($book, ['updated_at' => now()]));
            }
        }
    }
}
