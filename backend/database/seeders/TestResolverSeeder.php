<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Services\WebsiteConfigurationResolver;
use Illuminate\Database\Seeder;

class TestResolverSeeder extends Seeder
{
    public function run(): void
    {
        $salon = Salon::where('slug', 'em-cuts')->first();
        
        if (!$salon) {
            $this->command->error('Salon not found');
            return;
        }

        $resolver = new WebsiteConfigurationResolver();
        $config = $resolver->resolve($salon);
        
        $this->command->info('Resolved configuration:');
        $this->command->info(json_encode($config, JSON_PRETTY_PRINT));
    }
}
