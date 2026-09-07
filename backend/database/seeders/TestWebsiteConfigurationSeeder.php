<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\WebsiteConfiguration;
use Illuminate\Database\Seeder;

class TestWebsiteConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::where('slug', 'em-cuts')->first();
        
        if (!$salon) {
            $this->command->error('Salon with slug "em-cuts" not found');
            return;
        }

        // Delete existing config for this salon
        WebsiteConfiguration::where('configurable_type', 'App\\Models\\Salon')
            ->where('configurable_id', $salon->id)
            ->delete();

        // Create new website configuration
        $config = WebsiteConfiguration::create([
            'configurable_type' => 'App\\Models\\Salon',
            'configurable_id' => $salon->id,
            'theme' => 'luxury_noir',
            'enabled_sections' => json_encode(['hero', 'services', 'team', 'contact']),
            'section_order' => json_encode(['hero', 'services', 'team', 'contact']),
            'custom_colors' => json_encode([
                'primary' => '#FFD700',
                'secondary' => '#FF6B6B',
                'accent' => '#4ECDC4',
            ]),
            'custom_fonts' => json_encode([
                'font_heading' => 'Inter',
                'font_body' => 'Inter',
            ]),
            'is_active' => true,
        ]);

        $this->command->info("Created website configuration for {$salon->name} (ID: {$config->id})");
    }
}
