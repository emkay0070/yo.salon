<?php

namespace App\Http\Controllers\Api\V1\SpecialistPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private \App\Services\SpecialistOnboardingService $onboardingService
    ) {}

    public function status(Request $request)
    {
        $account = $request->user();
        return response()->json($this->onboardingService->getStatus($account));
    }

    public function updateIdentity(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'handle' => 'required|string|max:255|unique:specialists,handle,' . $request->user()->specialist_id,
            'headline' => 'nullable|string|max:100',
        ]);

        // Only update headline if explicitly provided to avoid overwriting existing values
        $updateData = $request->only(['name', 'handle']);
        if ($request->has('headline')) {
            $updateData['headline'] = $validated['headline'];
        }

        $request->user()->specialist->update($updateData);

        return response()->json(['success' => true]);
    }

    public function updateCraft(Request $request)
    {
        $validated = $request->validate([
            'bio' => 'required|string|max:1000',
            'years_experience' => 'required|integer|min:0',
            'profession_ids' => 'nullable|array',
            'profession_ids.*' => 'uuid|exists:professions,id',
            'expertise_ids' => 'nullable|array',
            'expertise_ids.*' => 'uuid|exists:craft_taxonomy,id',
            // Keep specialties for fallback/legacy support if needed
            'specialties' => 'nullable|array',
        ]);

        $specialist = $request->user()->specialist;
        
        $specialist->update([
            'bio' => $validated['bio'],
            'years_experience' => $validated['years_experience'],
            'specialties' => $validated['specialties'] ?? [],
        ]);

        if (isset($validated['profession_ids'])) {
            // Treat the first profession as primary if we must, or just simple sync for now.
            $syncData = [];
            foreach ($validated['profession_ids'] as $index => $profId) {
                $syncData[$profId] = ['is_primary' => $index === 0];
            }
            $specialist->professions()->sync($syncData);
        }

        if (isset($validated['expertise_ids'])) {
            $specialist->expertise()->delete();
            
            $expertises = [];
            foreach ($validated['expertise_ids'] as $expertiseId) {
                $expertises[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'specialist_id' => $specialist->id,
                    'craft_taxonomy_id' => $expertiseId,
                    'name' => \App\Models\CraftTaxonomy::find($expertiseId)?->name ?? 'Expertise',
                    'skill_level' => 'intermediate',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (count($expertises) > 0) {
                \App\Models\SpecialistExpertise::insert($expertises);
            }
        }

        return response()->json(['success' => true]);
    }

    public function submitVerification(Request $request)
    {
        $validated = $request->validate([
            // In a real app, this would validate uploaded documents/IDs
            'verification_data' => 'nullable|array',
        ]);

        $specialist = $request->user()->specialist;
        
        // Move to UNDER_REVIEW
        $specialist->update([
            'verification_status' => \App\Domain\Specialist\VerificationStatus::UNDER_REVIEW
        ]);

        return response()->json(['success' => true]);
    }

    public function updateWorkPreferences(Request $request)
    {
        $validated = $request->validate([
            'work_preference' => 'required|string|in:salon,independent,both',
        ]);

        $specialist = $request->user()->specialist;

        \Log::info('Updating work preference', [
            'specialist_id' => $specialist->id,
            'work_preference' => $validated['work_preference'],
            'specialist_email' => $specialist->email,
        ]);

        $specialist->update($validated);

        if (in_array($validated['work_preference'], ['independent', 'both'])) {
            \Log::info('Provisioning independent workspace', ['specialist_id' => $specialist->id]);
            $this->onboardingService->getWorkspaceProvisioner()->ensureIndependentWorkspace($specialist);
        }

        return response()->json(['success' => true]);
    }

    public function updateAvailability(Request $request)
    {
        $validated = $request->validate([
            'availability' => 'required|array',
        ]);

        $specialist = $request->user()->specialist;
        
        // Delegate to domain service
        $this->onboardingService->setupAvailability($specialist, $validated['availability']);

        return response()->json(['success' => true]);
    }

    public function complete(Request $request)
    {
        $result = $this->onboardingService->complete($request->user());

        if (!$result['success']) {
            return response()->json($result, 422);
        }

        $specialist = $request->user()->specialist;

        // Check if specialist already has an assignment (from invitation)
        $assignment = $specialist->assignments()->where('is_primary', true)->first();

        if (!$assignment) {
            // No assignment = independent specialist, provision their workspace
            $this->onboardingService->getWorkspaceProvisioner()->ensureIndependentWorkspace($specialist);
            $assignment = $specialist->assignments()->where('is_primary', true)->first();
        }

        // Return workspace info for redirect
        $workspace = $assignment ? [
            'provider_slug' => $assignment->provider->slug,
            'provider_id' => $assignment->provider_id,
        ] : null;

        return response()->json([
            'success' => true,
            'workspace' => $workspace,
        ]);
    }
}
