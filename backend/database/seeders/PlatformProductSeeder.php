<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformProductSeeder extends Seeder
{
    /**
     * Seed the initial product catalog — the things Yo.Salon sells to salon owners.
     * Each product references a resource_code from billing_resources.
     */
    public function run(): void
    {
        $products = [
            // ─── SMS Packs ───────────────────────────────────────────
            [
                'code'          => 'SMS_500',
                'name'          => '500 SMS Pack',
                'description'   => 'Send 500 SMS appointment reminders to your customers.',
                'resource_code' => 'SMS',
                'type'          => 'prepaid',
                'billing_model' => 'one_time',
                'units'         => 500,
                'is_active'     => true,
                'sort_order'    => 10,
            ],
            [
                'code'          => 'SMS_1000',
                'name'          => '1,000 SMS Pack',
                'description'   => 'Send 1,000 SMS appointment reminders — best value.',
                'resource_code' => 'SMS',
                'type'          => 'prepaid',
                'billing_model' => 'one_time',
                'units'         => 1000,
                'is_active'     => true,
                'sort_order'    => 11,
            ],

            // ─── AI Packs ────────────────────────────────────────────
            [
                'code'          => 'AI_500',
                'name'          => '500 AI Requests',
                'description'   => '500 Yo.Salon Copilot AI requests for captions, scheduling, insights.',
                'resource_code' => 'AI_REQUEST',
                'type'          => 'prepaid',
                'billing_model' => 'one_time',
                'units'         => 500,
                'is_active'     => true,
                'sort_order'    => 20,
            ],
            [
                'code'          => 'AI_1000',
                'name'          => '1,000 AI Requests',
                'description'   => '1,000 Yo.Salon Copilot AI requests — best value.',
                'resource_code' => 'AI_REQUEST',
                'type'          => 'prepaid',
                'billing_model' => 'one_time',
                'units'         => 1000,
                'is_active'     => true,
                'sort_order'    => 21,
            ],

            // ─── Marketing Packs ─────────────────────────────────────
            [
                'code'          => 'MARKETING_PACK_5',
                'name'          => '5 Marketing Campaigns',
                'description'   => 'Run 5 outbound campaigns to your full customer list.',
                'resource_code' => 'MARKETING_CAMPAIGN',
                'type'          => 'prepaid',
                'billing_model' => 'one_time',
                'units'         => 5,
                'is_active'     => true,
                'sort_order'    => 30,
            ],

            // ─── Marketplace ─────────────────────────────────────────
            [
                'code'          => 'MARKETPLACE_BOOST_MONTHLY',
                'name'          => 'Marketplace Featured Listing',
                'description'   => 'Appear at the top of Yo.Salon Discover search results for 1 month.',
                'resource_code' => 'MARKETPLACE_BOOST',
                'type'          => 'add_on',
                'billing_model' => 'recurring',
                'units'         => 1,
                'is_active'     => true,
                'sort_order'    => 40,
            ],

            // ─── Extra Seats ─────────────────────────────────────────
            [
                'code'          => 'EXTRA_STAFF_SEAT',
                'name'          => 'Extra Staff Seat',
                'description'   => 'Add one additional staff member beyond your plan limit.',
                'resource_code' => 'STAFF_SEAT',
                'type'          => 'add_on',
                'billing_model' => 'recurring',
                'units'         => 1,
                'is_active'     => true,
                'sort_order'    => 50,
            ],

            // ─── Extra Branch ─────────────────────────────────────────
            [
                'code'          => 'EXTRA_BRANCH',
                'name'          => 'Extra Branch',
                'description'   => 'Add one additional salon branch beyond your plan limit.',
                'resource_code' => 'BRANCH',
                'type'          => 'add_on',
                'billing_model' => 'recurring',
                'units'         => 1,
                'is_active'     => true,
                'sort_order'    => 60,
            ],
        ];

        foreach ($products as $product) {
            $existing = DB::table('platform_products')
                ->where('code', $product['code'])
                ->first();

            if (!$existing) {
                DB::table('platform_products')->insert(array_merge($product, [
                    'id'         => Str::uuid()->toString(),
                    'metadata'   => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            } else {
                DB::table('platform_products')
                    ->where('code', $product['code'])
                    ->update(array_merge($product, ['updated_at' => now()]));
            }
        }
    }
}
