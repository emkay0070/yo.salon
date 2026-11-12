<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessCategoryServiceTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get category and template IDs
        $categories = DB::table('business_categories')->pluck('id', 'slug');
        $templates = DB::table('service_templates')->pluck('id', 'slug');

        // Define category → template mappings
        $mappings = [
            // Hair Salon
            'hair_salon' => ['haircut', 'hair_wash', 'hair_coloring', 'blow_dry', 'hair_treatment', 'kids_haircut'],
            
            // Barbershop
            'barbershop' => ['haircut', 'beard_trim', 'beard_shape', 'hot_towel_shave', 'hair_beard', 'kids_haircut'],
            
            // Beauty Salon
            'beauty_salon' => ['haircut', 'hair_coloring', 'blow_dry', 'bridal_makeup', 'party_makeup', 'everyday_makeup'],
            
            // Nail Studio
            'nail_studio' => ['manicure', 'pedicure', 'gel_polish', 'acrylic', 'nail_art', 'nail_extensions'],
            
            // Spa
            'spa' => ['massage', 'facial', 'body_treatment', 'aromatherapy', 'steam_sauna', 'couples_treatment'],
            
            // Wellness Center
            'wellness_center' => ['massage', 'facial', 'yoga_session', 'meditation_session', 'consultation'],
            
            // Mobile Beauty
            'mobile_beauty' => ['haircut', 'manicure', 'pedicure', 'massage', 'facial'],
        ];

        $sortOrder = 1;

        foreach ($mappings as $categorySlug => $templateSlugs) {
            if (!isset($categories[$categorySlug])) {
                $this->command->warn("Category {$categorySlug} not found, skipping.");
                continue;
            }

            $categoryId = $categories[$categorySlug];

            foreach ($templateSlugs as $templateSlug) {
                if (!isset($templates[$templateSlug])) {
                    $this->command->warn("Template {$templateSlug} not found, skipping.");
                    continue;
                }

                $templateId = $templates[$templateSlug];

                DB::table('business_category_service_templates')->updateOrInsert(
                    [
                        'business_category_id' => $categoryId,
                        'service_template_id' => $templateId,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'sort_order' => $sortOrder++,
                        'is_recommended' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('Business category service template relationships seeded successfully.');
    }
}
