<?php

namespace App\Domain\Specialist\Services;

use App\Models\Specialist;
use App\Models\Provider;
use App\Models\ProviderType;

use App\Models\SpecialistAssignment;
use App\Services\LocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndependentWorkspaceProvisioner
{
    private LocationService $locationService;

    public function __construct(?LocationService $locationService = null)
    {
        $this->locationService = $locationService ?? app(LocationService::class);
    }

    /**
     * Ensures that an independent specialist has a corresponding Provider,
     * Location (workspace), and Primary Assignment.
     * This operation is idempotent and transactional.
     */
    public function ensureIndependentWorkspace(Specialist $specialist): void
    {
        DB::transaction(function () use ($specialist) {
            // 1. Check if the specialist already has an independent workspace assignment.
            // We look for a primary assignment where the Provider Type key is
            // independent_specialist, resolved through the canonical FK.
            $existingAssignment = $specialist->assignments()
                ->where('is_primary', true)
                ->whereHas('provider.providerType', function ($query) {
                    $query->where('key', Provider::TYPE_SPECIALIST);
                })
                ->first();

            if ($existingAssignment) {
                // If it already exists, ensure schedule_mode is CUSTOM and return early.
                if ($existingAssignment->schedule_mode !== 'CUSTOM') {
                    $existingAssignment->update(['schedule_mode' => 'CUSTOM']);
                }
                return;
            }

            // 2. Ensure lightweight personal Organization exists first for independent specialist
            $specialistAccount = \App\Models\SpecialistAccount::where('specialist_id', $specialist->id)->first()
                ?? \App\Models\SpecialistAccount::where('email', $specialist->email)->first();

            $principal = $specialistAccount;
            if (!$principal) {
                $linkedUser = \App\Models\User::where('email', $specialist->email)->first();
                if ($linkedUser) {
                    $principal = $linkedUser;
                } else {
                    // Create SpecialistAccount for independent specialist if none exists
                    $principal = \App\Models\SpecialistAccount::create([
                        'id'            => (string) \Illuminate\Support\Str::uuid(),
                        'specialist_id' => $specialist->id,
                        'email'         => $specialist->email,
                        'password'      => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
                        'is_active'     => true,
                    ]);
                }
            }

            $org = app(\App\Services\OrganizationService::class)->createPersonalForSpecialistPrincipal($principal, [
                'name'  => $specialist->name,
                'email' => $specialist->email,
                'phone' => $specialist->phone,
            ]);

            // 3. Ensure Provider exists or create it.
            // We'll tie it to the specialist's email as a unique identifier for
            // self-onboarded ones. The FK is the canonical classification; the
            // legacy type column is derived by the Provider model.
            $providerTypeRecord = ProviderType::active()
                ->byKey(Provider::TYPE_SPECIALIST)
                ->first();

            if (!$providerTypeRecord) {
                throw new \RuntimeException(
                    'The independent_specialist Provider Type is not active or not registered.'
                );
            }

            $provider = Provider::firstOrCreate(
                [
                    'email' => $specialist->email,
                    'provider_type_id' => $providerTypeRecord->id,
                ],
                [
                    'organization_id'   => $org->id,
                    'status'            => 'active',
                    'display_name'      => $specialist->name,
                    'slug'              => $this->generateUniqueProviderSlug($specialist->name),
                    'active'            => true,
                ]
            );

            if (!$provider->organization_id) {
                $provider->update(['organization_id' => $org->id]);
            }

            // 3. Ensure Location (Workspace) exists or create it. The first
            // Location for the Provider becomes primary; later ones do not.
            $location = $this->locationService->firstOrCreateForProvider(
                $provider,
                [
                    'name' => $specialist->name . "'s Workspace",
                    'slug' => $this->generateUniqueLocationSlug($specialist->name . ' Workspace'),
                    'email' => $specialist->email,
                    'is_active' => true,
                ]
            );

            // 4. Ensure Primary Assignment exists or create it
            SpecialistAssignment::firstOrCreate(
                [
                    'specialist_id' => $specialist->id,
                    'provider_id' => $provider->id,
                    'location_id' => $location->id,
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

    protected function generateUniqueLocationSlug(string $name): string
    {
        $slug = Str::slug($name);
        if (empty($slug)) {
            $slug = 'workspace-' . time();
        }

        $originalSlug = $slug;
        $counter = 1;

        while (\App\Models\Location::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }
}
