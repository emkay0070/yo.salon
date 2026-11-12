<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Profession;
use App\Models\CraftTaxonomy;
use Illuminate\Support\Str;

class ProfessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $professions = [
            [
                'name' => 'Stylist',
                'icon' => 'scissors',
                'crafts' => ['hair', 'hair-cutting', 'hair-styling']
            ],
            [
                'name' => 'Nail Artist',
                'icon' => 'sparkles',
                'crafts' => ['nails', 'manicure', 'pedicure', 'nail-extensions', 'nail-art']
            ],
            [
                'name' => 'Makeup Artist',
                'icon' => 'brush',
                'crafts' => ['makeup', 'bridal-makeup', 'editorial-makeup', 'event-makeup']
            ],
            [
                'name' => 'Barber',
                'icon' => 'scissors',
                'crafts' => ['hair', 'hair-cutting', 'fades', 'buzz-cuts']
            ],
            [
                'name' => 'Therapist',
                'icon' => 'sparkles',
                'crafts' => ['massage', 'swedish-massage', 'deep-tissue', 'hot-stone', 'sports-massage']
            ],
            [
                'name' => 'Esthetician',
                'icon' => 'sparkles',
                'crafts' => ['skin', 'facials', 'skin-treatments']
            ],
            [
                'name' => 'Braider',
                'icon' => 'scissors',
                'crafts' => ['hair', 'hair-styling', 'braiding']
            ],
            [
                'name' => 'Colorist',
                'icon' => 'brush',
                'crafts' => ['hair', 'hair-coloring']
            ]
        ];

        foreach ($professions as $index => $data) {
            $profession = Profession::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                [
                    'name' => $data['name'],
                    'icon' => $data['icon'],
                    'sort_order' => $index + 1,
                    'is_active' => true
                ]
            );

            if (isset($data['crafts']) && count($data['crafts']) > 0) {
                $craftTaxonomyIds = CraftTaxonomy::whereIn('slug', $data['crafts'])->pluck('id');
                $profession->craftTaxonomy()->sync($craftTaxonomyIds);
            }
        }
    }
}
