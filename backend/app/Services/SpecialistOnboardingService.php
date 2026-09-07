<?php

namespace App\Services;

use App\Models\SpecialistAccount;
use App\Models\Specialist;
use Illuminate\Support\Facades\DB;
use App\Domain\Specialist\VerificationStatus;
use App\Domain\Specialist\Services\IndependentWorkspaceProvisioner;

class SpecialistOnboardingService
{
    public function __construct(
        private IndependentWorkspaceProvisioner $workspaceProvisioner
    ) {}
    /**
     * Calculate and return the onboarding status of a specialist.
     */
    public function getStatus(SpecialistAccount $account): array
    {
        $specialist = $account->specialist;
        
        $steps = [
            'identity' => $this->isIdentityComplete($specialist),
            'craft' => $this->isCraftComplete($specialist),
            'professional' => $this->isProfessionalComplete($specialist),
            'verification' => $this->isVerificationSubmitted($specialist),
            'work_preference' => $this->isWorkPreferenceComplete($specialist),
            'availability' => $this->isAvailabilityComplete($specialist),
        ];

        // Determine current step (1-indexed based on the first incomplete step)
        $currentStep = 1;
        foreach (array_values($steps) as $index => $isComplete) {
            if (!$isComplete) {
                $currentStep = $index + 1;
                break;
            }
            if ($index === count($steps) - 1 && $isComplete) {
                $currentStep = 7; // Launch Moment / Complete
            }
        }

        return [
            'completed' => (bool) $account->onboarding_completed_at,
            'current_step' => $currentStep,
            'steps' => $steps
        ];
    }

    /**
     * Complete the onboarding if all requirements are met.
     */
    public function complete(SpecialistAccount $account): array
    {
        $status = $this->getStatus($account);

        // Check if all steps (except maybe verification strictly being VERIFIED) are done
        $allComplete = collect($status['steps'])->every(fn($isComplete) => $isComplete);

        if (!$allComplete) {
            return [
                'success' => false,
                'message' => 'Cannot complete onboarding: not all steps are finished.',
                'status' => $status
            ];
        }

        $account->update(['onboarding_completed_at' => now()]);

        return [
            'success' => true,
            'message' => 'Onboarding completed successfully.',
            'status' => $this->getStatus($account)
        ];
    }

    /**
     * Step 6: Handle availability by delegating to Scheduling Domain.
     * This receives intended availability and sets up the correct schedule.
     */
    public function setupAvailability(Specialist $specialist, array $availabilityData): void
    {
        // Onboarding is an independent specialist setting their OWN availability.
        // Therefore, their assignment schedule_mode must be set to CUSTOM so that
        // the ScheduleResolver uses it instead of inheriting an empty provider/salon schedule.

        $assignment = $specialist->assignments()->where('is_primary', true)->first();

        // If the assignment doesn't exist, we do not fallback to first() or create it here.
        // It should have been created by the IndependentWorkspaceProvisioner.

        if ($assignment) {
            $assignment->update(['schedule_mode' => 'CUSTOM']);

            // Re-create the assignment schedules from the onboarding data
            \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)->delete();

            foreach ($availabilityData as $day) {
                // Frontend sends: { day: 'Monday', active: true, start: '09:00', end: '17:00' }
                // Map to backend format
                $isWorking = !empty($day['active']) && !empty($day['start']) && !empty($day['end']);

                \App\Models\AssignmentSchedule::create([
                    'assignment_id'  => $assignment->id,
                    'day_of_week'    => $day['day'],
                    'is_working_day' => $isWorking,
                    'start_time'     => $isWorking ? $day['start'] : null,
                    'end_time'       => $isWorking ? $day['end'] : null,
                    'break_start'    => null, // Not set in onboarding yet
                    'break_end'      => null, // Not set in onboarding yet
                ]);
            }
        }
    }

    // --- Helper Methods to Check Step Completion --- //

    private function isIdentityComplete(?Specialist $specialist): bool
    {
        if (!$specialist) return false;
        return !empty($specialist->name) && !empty($specialist->handle);
        // headline is optional for onboarding completion
    }

    private function isCraftComplete(?Specialist $specialist): bool
    {
        if (!$specialist) return false;
        return !empty($specialist->bio) && !empty($specialist->specialties);
    }

    private function isProfessionalComplete(?Specialist $specialist): bool
    {
        if (!$specialist) return false;
        return !empty($specialist->years_experience);
    }

    private function isVerificationSubmitted(?Specialist $specialist): bool
    {
        if (!$specialist) return false;
        // As long as it's not UNVERIFIED, it's considered submitted/done for onboarding purposes.
        return $specialist->verification_status !== VerificationStatus::UNVERIFIED;
    }

    private function isWorkPreferenceComplete(?Specialist $specialist): bool
    {
        if (!$specialist) return false;
        return !empty($specialist->work_preference);
    }

    private function isAvailabilityComplete(?Specialist $specialist): bool
    {
        if (!$specialist) return false;

        // Check if the specialist has assignment schedules set up
        $assignment = $specialist->assignments()->where('is_primary', true)->first();
        if (!$assignment) return false;

        return \App\Models\AssignmentSchedule::where('assignment_id', $assignment->id)->exists();
    }

    public function getWorkspaceProvisioner(): IndependentWorkspaceProvisioner
    {
        return $this->workspaceProvisioner;
    }
}
