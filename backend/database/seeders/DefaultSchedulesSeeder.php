<?php

namespace Database\Seeders;

use App\Models\Salon;
use App\Models\Provider;
use App\Models\SalonSchedule;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\AssignmentSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DefaultSchedulesSeeder extends Seeder
{
    /**
     * Seed default schedules using the new relational architecture.
     * 
     * Provider  → Salon (salon_schedules)
     * Specialist → Assignment (specialist_assignments)
     * Assignment  → Schedule (assignment_schedules)
     */
    public function run(): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Seed salon schedules for all salons
        foreach (Salon::all() as $salon) {
            foreach ($days as $day) {
                $isWeekend = in_array($day, ['Sunday']);

                SalonSchedule::firstOrCreate(
                    ['salon_id' => $salon->id, 'day_of_week' => $day],
                    [
                        'id'         => (string) Str::uuid(),
                        'open_time'  => $isWeekend ? null : '09:00:00',
                        'close_time' => $isWeekend ? null : '18:00:00',
                        'is_closed'  => $isWeekend,
                    ]
                );
            }
            $this->command->info("Salon schedules seeded for: {$salon->name}");
        }

        // Seed specialist assignments and their schedules
        foreach (Specialist::with('provider')->get() as $specialist) {
            // Find a salon under the same provider to assign to (default: first salon)
            $salon = Salon::where('provider_id', $specialist->provider_id)->first();

            if (!$salon) {
                continue;
            }

            // Create the assignment
            $assignment = SpecialistAssignment::firstOrCreate(
                [
                    'specialist_id' => $specialist->id,
                    'provider_id'   => $specialist->provider_id,
                    'salon_id'      => $salon->id,
                ],
                [
                    'id'              => (string) Str::uuid(),
                    'role'            => 'SPECIALIST',
                    'employment_type' => 'EMPLOYEE',
                    'is_primary'      => true,
                    'status'          => 'ACTIVE',
                    'starts_at'       => now()->toDateString(),
                ]
            );

            // Seed assignment schedules (Mon–Sat by default)
            foreach ($days as $day) {
                $isOff = in_array($day, ['Sunday']);

                AssignmentSchedule::firstOrCreate(
                    ['assignment_id' => $assignment->id, 'day_of_week' => $day],
                    [
                        'id'             => (string) Str::uuid(),
                        'start_time'     => $isOff ? null : '09:00:00',
                        'end_time'       => $isOff ? null : '18:00:00',
                        'break_start'    => $isOff ? null : '13:00:00',
                        'break_end'      => $isOff ? null : '14:00:00',
                        'is_working_day' => !$isOff,
                    ]
                );
            }

            $this->command->info("Assignment + schedules seeded for: {$specialist->name}");
        }
    }
}
