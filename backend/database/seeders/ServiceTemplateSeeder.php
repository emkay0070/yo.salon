<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            // Hair Services
            ['slug' => 'haircut', 'name' => 'Haircut', 'sort_order' => 1],
            ['slug' => 'hair_wash', 'name' => 'Hair Wash', 'sort_order' => 2],
            ['slug' => 'hair_coloring', 'name' => 'Hair Coloring', 'sort_order' => 3],
            ['slug' => 'blow_dry', 'name' => 'Blow Dry', 'sort_order' => 4],
            ['slug' => 'hair_treatment', 'name' => 'Hair Treatment', 'sort_order' => 5],
            ['slug' => 'kids_haircut', 'name' => 'Kids Haircut', 'sort_order' => 6],
            
            // Beard Services
            ['slug' => 'beard_trim', 'name' => 'Beard Trim', 'sort_order' => 7],
            ['slug' => 'beard_shape', 'name' => 'Beard Shape', 'sort_order' => 8],
            ['slug' => 'beard_color', 'name' => 'Beard Color', 'sort_order' => 9],
            ['slug' => 'hot_towel_shave', 'name' => 'Hot Towel Shave', 'sort_order' => 10],
            ['slug' => 'hair_beard', 'name' => 'Hair & Beard', 'sort_order' => 11],
            
            // Nail Services
            ['slug' => 'manicure', 'name' => 'Manicure', 'sort_order' => 12],
            ['slug' => 'pedicure', 'name' => 'Pedicure', 'sort_order' => 13],
            ['slug' => 'gel_polish', 'name' => 'Gel Polish', 'sort_order' => 14],
            ['slug' => 'acrylic', 'name' => 'Acrylic', 'sort_order' => 15],
            ['slug' => 'nail_art', 'name' => 'Nail Art', 'sort_order' => 16],
            ['slug' => 'nail_extensions', 'name' => 'Nail Extensions', 'sort_order' => 17],
            
            // Spa Services
            ['slug' => 'massage', 'name' => 'Massage', 'sort_order' => 18],
            ['slug' => 'facial', 'name' => 'Facial', 'sort_order' => 19],
            ['slug' => 'body_treatment', 'name' => 'Body Treatment', 'sort_order' => 20],
            ['slug' => 'aromatherapy', 'name' => 'Aromatherapy', 'sort_order' => 21],
            ['slug' => 'steam_sauna', 'name' => 'Steam / Sauna', 'sort_order' => 22],
            ['slug' => 'couples_treatment', 'name' => 'Couples Treatment', 'sort_order' => 23],
            
            // Makeup Services
            ['slug' => 'bridal_makeup', 'name' => 'Bridal Makeup', 'sort_order' => 24],
            ['slug' => 'party_makeup', 'name' => 'Party Makeup', 'sort_order' => 25],
            ['slug' => 'everyday_makeup', 'name' => 'Everyday Makeup', 'sort_order' => 26],
            
            // Wellness Services
            ['slug' => 'yoga_session', 'name' => 'Yoga Session', 'sort_order' => 27],
            ['slug' => 'meditation_session', 'name' => 'Meditation Session', 'sort_order' => 28],
            ['slug' => 'consultation', 'name' => 'Consultation', 'sort_order' => 29],
        ];

        foreach ($templates as $template) {
            DB::table('service_templates')->updateOrInsert(
                ['slug' => $template['slug']],
                array_merge($template, [
                    'id' => (string) Str::uuid(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Service templates seeded successfully.');
    }
}
