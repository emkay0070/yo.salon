<?php

namespace Database\Seeders;

use App\Models\ReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'hair_type' => [
                ['key' => 'straight', 'label' => 'Straight', 'sort_order' => 1],
                ['key' => 'wavy', 'label' => 'Wavy', 'sort_order' => 2],
                ['key' => 'curly', 'label' => 'Curly', 'sort_order' => 3],
                ['key' => 'coily', 'label' => 'Coily', 'sort_order' => 4],
                ['key' => 'kinky', 'label' => 'Kinky', 'sort_order' => 5],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 6],
            ],
            'hair_style' => [
                ['key' => 'fade', 'label' => 'Fade', 'sort_order' => 1],
                ['key' => 'crew_cut', 'label' => 'Crew Cut', 'sort_order' => 2],
                ['key' => 'pompadour', 'label' => 'Pompadour', 'sort_order' => 3],
                ['key' => 'undercut', 'label' => 'Undercut', 'sort_order' => 4],
                ['key' => 'quiff', 'label' => 'Quiff', 'sort_order' => 5],
                ['key' => 'side_part', 'label' => 'Side Part', 'sort_order' => 6],
                ['key' => 'buzz_cut', 'label' => 'Buzz Cut', 'sort_order' => 7],
                ['key' => 'long', 'label' => 'Long', 'sort_order' => 8],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 9],
            ],
            'hair_concern' => [
                ['key' => 'dry', 'label' => 'Dry', 'sort_order' => 1],
                ['key' => 'oily', 'label' => 'Oily', 'sort_order' => 2],
                ['key' => 'dandruff', 'label' => 'Dandruff', 'sort_order' => 3],
                ['key' => 'thinning', 'label' => 'Thinning', 'sort_order' => 4],
                ['key' => 'frizzy', 'label' => 'Frizzy', 'sort_order' => 5],
                ['key' => 'color_treated', 'label' => 'Color-treated', 'sort_order' => 6],
                ['key' => 'damaged', 'label' => 'Damaged', 'sort_order' => 7],
                ['key' => 'split_ends', 'label' => 'Split Ends', 'sort_order' => 8],
                ['key' => 'heat_damage', 'label' => 'Heat Damage', 'sort_order' => 9],
                ['key' => 'chemical_damage', 'label' => 'Chemical Damage', 'sort_order' => 10],
                ['key' => 'itchy_scalp', 'label' => 'Itchy Scalp', 'sort_order' => 11],
                ['key' => 'psoriasis', 'label' => 'Psoriasis', 'sort_order' => 12],
                ['key' => 'alopecia', 'label' => 'Alopecia', 'sort_order' => 13],
                ['key' => 'none', 'label' => 'None', 'sort_order' => 14],
            ],
            'beard_style' => [
                ['key' => 'clean_shaven', 'label' => 'Clean Shaven', 'sort_order' => 1],
                ['key' => 'stubble', 'label' => 'Stubble', 'sort_order' => 2],
                ['key' => 'goatee', 'label' => 'Goatee', 'sort_order' => 3],
                ['key' => 'full_beard', 'label' => 'Full Beard', 'sort_order' => 4],
                ['key' => 'van_dyke', 'label' => 'Van Dyke', 'sort_order' => 5],
                ['key' => 'circle_beard', 'label' => 'Circle Beard', 'sort_order' => 6],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 7],
            ],
            'beard_product' => [
                ['key' => 'beard_oil', 'label' => 'Beard Oil', 'sort_order' => 1],
                ['key' => 'beard_balm', 'label' => 'Beard Balm', 'sort_order' => 2],
                ['key' => 'beard_wax', 'label' => 'Beard Wax', 'sort_order' => 3],
                ['key' => 'beard_butter', 'label' => 'Beard Butter', 'sort_order' => 4],
                ['key' => 'none', 'label' => 'None', 'sort_order' => 5],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 6],
            ],
            'skin_type' => [
                ['key' => 'oily', 'label' => 'Oily', 'sort_order' => 1],
                ['key' => 'dry', 'label' => 'Dry', 'sort_order' => 2],
                ['key' => 'combination', 'label' => 'Combination', 'sort_order' => 3],
                ['key' => 'normal', 'label' => 'Normal', 'sort_order' => 4],
                ['key' => 'sensitive', 'label' => 'Sensitive', 'sort_order' => 5],
                ['key' => 'acne_prone', 'label' => 'Acne-prone', 'sort_order' => 6],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 7],
            ],
            'allergy' => [
                ['key' => 'nuts', 'label' => 'Nuts', 'sort_order' => 1],
                ['key' => 'fragrance', 'label' => 'Fragrance', 'sort_order' => 2],
                ['key' => 'latex', 'label' => 'Latex', 'sort_order' => 3],
                ['key' => 'sulfates', 'label' => 'Sulfates', 'sort_order' => 4],
                ['key' => 'parabens', 'label' => 'Parabens', 'sort_order' => 5],
                ['key' => 'alcohol', 'label' => 'Alcohol', 'sort_order' => 6],
                ['key' => 'dyes', 'label' => 'Dyes', 'sort_order' => 7],
                ['key' => 'essential_oils', 'label' => 'Essential Oils', 'sort_order' => 8],
                ['key' => 'none', 'label' => 'None', 'sort_order' => 9],
                ['key' => 'other', 'label' => 'Other', 'sort_order' => 10],
            ],
        ];

        foreach ($categories as $category => $items) {
            foreach ($items as $item) {
                ReferenceData::firstOrCreate(
                    [
                        'category' => $category,
                        'key' => $item['key'],
                    ],
                    [
                        'id' => Str::uuid(),
                        'label' => $item['label'],
                        'value' => $item['label'],
                        'sort_order' => $item['sort_order'],
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }
    }
}
