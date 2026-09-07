<?php

namespace Database\Seeders;

use App\Models\WebsiteConfiguration;
use Illuminate\Database\Seeder;

class CheckConfigSeeder extends Seeder
{
    public function run(): void
    {
        $config = WebsiteConfiguration::first();
        
        if (!$config) {
            $this->command->error('No website configuration found');
            return;
        }

        $this->command->info('Config ID: ' . $config->id);
        $this->command->info('Configurable Type: ' . $config->configurable_type);
        $this->command->info('Configurable ID: ' . $config->configurable_id);
        $this->command->info('Theme: ' . $config->theme);
        $this->command->info('Custom Colors: ' . $config->custom_colors);
        $this->command->info('Custom Fonts: ' . $config->custom_fonts);
        $this->command->info('Is Active: ' . ($config->is_active ? 'true' : 'false'));
    }
}
