<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    /**
     * Get current onboarding session data
     */
    public function show(Request $request): JsonResponse
    {
        $session = $request->user()->onboardingSession;
        
        if (!$session) {
            return response()->json([
                'message' => 'No onboarding session found',
            ], 404);
        }

        return response()->json([
            'current_step' => $session->current_step,
            'draft_data' => $session->draft_data,
            'completed' => $session->completed,
        ]);
    }

    /**
     * Save draft data for a specific onboarding step
     */
    public function updateDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'step' => 'required|string|in:salon,business,team,services,wallet,membership',
            'data' => 'present|array',
        ]);

        $user = $request->user();
        $session = $user->onboardingSession;

        if (!$session) {
            $session = \App\Models\OnboardingSession::create([
                'user_id' => $user->id,
                'current_step' => 'welcome',
                'draft_data' => [],
            ]);
        }

        if ($session->completed) {
            return response()->json([
                'message' => 'Onboarding already completed',
            ], 400);
        }

        // Update user status if starting onboarding
        if ($user->status === 'email_verified') {
            $user->update(['status' => 'onboarding_started']);
        }

        $session->updateDraft($validated['step'], $validated['data']);

        return response()->json([
            'message' => 'Draft saved successfully',
            'current_step' => $session->current_step,
            'draft_data' => $session->draft_data,
        ]);
    }

    /**
     * Complete onboarding and create salon
     */
    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $user->onboardingSession;

        if (!$session) {
            return response()->json([
                'message' => 'No onboarding session found',
            ], 404);
        }

        if ($session->completed) {
            return response()->json([
                'message' => 'Onboarding already completed',
            ], 400);
        }

        try {
            $salon = $this->onboardingService->complete($user, $session->draft_data);

            return response()->json([
                'message' => 'Onboarding completed successfully',
                'salon' => $salon,
                'user' => $user->fresh()->load('salons'),
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Onboarding complete error: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to complete onboarding',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
