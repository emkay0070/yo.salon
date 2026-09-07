<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Provider;
use App\Models\Specialist;
use App\Models\Service;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Seed default provider schedules (9 AM - 6 PM, Mon-Sat)
        $providers = Provider::all();
        foreach ($providers as $provider) {
            // Create schedules for Monday-Saturday (0-5)
            for ($day = 1; $day <= 6; $day++) {
                DB::table('provider_schedules')->insert([
                    'id' => (string) Str::uuid(),
                    'provider_id' => $provider->id,
                    'day_of_week' => $day,
                    'open_time' => '09:00:00',
                    'close_time' => '18:00:00',
                    'is_closed' => false,
                    'effective_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Sunday is closed
            DB::table('provider_schedules')->insert([
                'id' => (string) Str::uuid(),
                'provider_id' => $provider->id,
                'day_of_week' => 0, // Sunday
                'open_time' => '09:00:00',
                'close_time' => '18:00:00',
                'is_closed' => true,
                'effective_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed default specialist schedules (9 AM - 6 PM, Mon-Sat)
        // Replaced by assignment_schedules
        /*
        $specialists = Specialist::all();
        foreach ($specialists as $specialist) {
            // Create schedules for Monday-Saturday (0-5)
            for ($day = 1; $day <= 6; $day++) {
                DB::table('specialist_schedules')->insert([
                    'id' => (string) Str::uuid(),
                    'specialist_id' => $specialist->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'break_start_time' => null,
                    'break_end_time' => null,
                    'is_available' => true,
                    'effective_date' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Sunday is not available
            DB::table('specialist_schedules')->insert([
                'id' => (string) Str::uuid(),
                'specialist_id' => $specialist->id,
                'day_of_week' => 0, // Sunday
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'break_start_time' => null,
                'break_end_time' => null,
                'is_available' => false,
                'effective_date' => now()->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        */

        // Set default service availability columns
        Service::query()->update([
            'buffer_before' => 0,
            'buffer_after' => 0,
            'requires_specialist' => true,
            'max_concurrent' => 1,
            'min_booking_notice' => 2, // 2 hours
            'max_booking_days_ahead' => 30,
            'cancellation_cutoff_hours' => 24,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove seeded schedules
        DB::table('provider_schedules')->where('effective_date', now()->toDateString())->delete();
        // DB::table('specialist_schedules')->where('effective_date', now()->toDateString())->delete();

        // Reset service columns to null
        Service::query()->update([
            'buffer_before' => 0,
            'buffer_after' => 0,
            'requires_specialist' => true,
            'max_concurrent' => 1,
            'min_booking_notice' => 2,
            'max_booking_days_ahead' => 30,
            'cancellation_cutoff_hours' => 24,
        ]);
    }
};
