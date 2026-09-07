<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\SalonSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SalonSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $salons = Salon::all();

        $defaultSchedule = [
            'Monday'    => ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
            'Tuesday'   => ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
            'Wednesday' => ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
            'Thursday'  => ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
            'Friday'    => ['open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false],
            'Saturday'  => ['open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false],
            'Sunday'    => ['open_time' => null,        'close_time' => null,        'is_closed' => true],
        ];

        foreach ($salons as $salon) {
            // Seed relational salon_schedules (replaces legacy JSON opening_hours column)
            foreach ($defaultSchedule as $day => $hours) {
                SalonSchedule::firstOrCreate(
                    ['salon_id' => $salon->id, 'day_of_week' => $day],
                    array_merge(['id' => (string) Str::uuid()], $hours)
                );
            }

            // Update salon with default deposit/booking settings
            $salon->update([
                'booking_deposit_enabled'    => false,
                'deposit_type'               => 'percentage',
                'deposit_value'              => 20,
                'deposit_required_for'       => 'all',
                'deposit_min_service_amount' => 0,
            ]);

            $this->command->info("Updated settings for salon: {$salon->name}");
        }

        $this->command->info('Salon settings seeded successfully!');
    }
}
