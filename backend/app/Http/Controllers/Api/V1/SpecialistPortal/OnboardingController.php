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
            'specialties' => 'required|array',
            'years_experience' => 'required|integer|min:0',
        ]);

        $request->user()->specialist->update($validated);

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
        $specialist->update($validated);

        if (in_array($validated['work_preference'], ['independent', 'both'])) {
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

        return response()->json($result);
    }
}
