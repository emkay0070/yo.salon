<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use App\Models\Provider;
use App\Models\Salon;
use App\Models\SpecialistAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndependentWorkspaceProvisioner
{
    /**
     * Ensures that an independent specialist has a corresponding Provider,
     * Salon (workspace), and Primary Assignment.
     * This operation is idempotent and transactional.
     */
    public function ensureIndependentWorkspace(Specialist $specialist): void
    {
        DB::transaction(function () use ($specialist) {
            // 1. Check if the specialist already has an independent workspace assignment.
            // We look for a primary assignment where the provider type is 'specialist'.
            $existingAssignment = $specialist->assignments()
                ->where('is_primary', true)
                ->whereHas('provider', function ($query) {
                    $query->where('type', Provider::TYPE_SPECIALIST);
                })
                ->first();

            if ($existingAssignment) {
                // If it already exists, ensure schedule_mode is CUSTOM and return early.
                if ($existingAssignment->schedule_mode !== 'CUSTOM') {
                    $existingAssignment->update(['schedule_mode' => 'CUSTOM']);
                }
                return;
            }

            // 2. Ensure Provider exists or create it
            // We'll tie it to the specialist's email as a unique identifier for self-onboarded ones
            $provider = Provider::firstOrCreate(
                ['email' => $specialist->email, 'type' => Provider::TYPE_SPECIALIST],
                [
                    'status' => 'active',
                    'display_name' => $specialist->name,
                    'slug' => $this->generateUniqueProviderSlug($specialist->name),
                    'active' => true,
                ]
            );

            // 3. Ensure Salon (Workspace) exists or create it
            $salon = Salon::firstOrCreate(
                ['provider_id' => $provider->id],
                [
                    'name' => $specialist->name . "'s Workspace",
                    'slug' => $this->generateUniqueSalonSlug($specialist->name . ' Workspace'),
                    'status' => 'active',
                    'email' => $specialist->email,
                    'active' => true,
                ]
            );

            // 4. Ensure Primary Assignment exists or create it
            SpecialistAssignment::firstOrCreate(
                [
                    'specialist_id' => $specialist->id,
                    'provider_id' => $provider->id,
                    'salon_id' => $salon->id,
                ],
                [
                    'is_primary' => true,
                    'status' => 'active',
                    'schedule_mode' => 'CUSTOM',
                    'starts_at' => now(),
                ]
            );

            // 5. Update the specialist's provider_id to link back
            if ($specialist->provider_id !== $provider->id) {
                $specialist->update(['provider_id' => $provider->id]);
            }
        });
    }

    protected function generateUniqueProviderSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = 'provider-' . time();
        }

        $originalSlug = $slug;
        $counter = 1;

        while (Provider::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    protected function generateUniqueSalonSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = 'workspace-' . time();
        }

        $originalSlug = $slug;
        $counter = 1;

        while (Salon::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }
}
