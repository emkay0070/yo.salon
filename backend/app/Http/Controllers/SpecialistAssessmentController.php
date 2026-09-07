<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerGroomingProfile;
use App\Models\ProfessionalAssessment;
use App\Models\SpecialistNote;
use App\Models\ReferenceData;
use App\Models\ProfileRecommendation;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SpecialistAssessmentController extends Controller
{
    /**
     * Get customer's grooming profile for specialist view
     */
    public function getCustomerProfile(string $customerId): JsonResponse
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $groomingProfile = $customer->groomingProfile;
        $assessments = ProfessionalAssessment::byCustomer($customerId)
            ->with(['specialist', 'salon'])
            ->recent()
            ->get();

        $notes = SpecialistNote::byCustomer($customerId)
            ->with(['specialist'])
            ->customerVisible()
            ->recent()
            ->get();

        return response()->json([
            'customer' => $customer,
            'grooming_profile' => $groomingProfile,
            'assessments' => $assessments,
            'notes' => $notes,
        ]);
    }

    /**
     * Create a professional assessment
     */
    public function createAssessment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'specialist_id' => 'required|uuid|exists:specialists,id',
            'salon_id' => 'required|uuid|exists:salons,id',
            'booking_id' => 'nullable|uuid|exists:bookings,id',
            
            // Hair Assessment
            'observed_hair_type' => 'nullable|string|max:100',
            'hair_density' => 'nullable|in:Low,Medium,High',
            'scalp_condition' => 'nullable|string|max:100',
            'hairline' => 'nullable|string|max:100',
            'hair_observations' => 'nullable|string',
            
            // Beard Assessment
            'observed_beard_style' => 'nullable|string|max:100',
            'beard_growth_pattern' => 'nullable|in:Patchy,Full,Sparse',
            'beard_observations' => 'nullable|string',
            
            // Skin Assessment
            'observed_skin_type' => 'nullable|string|max:100',
            'skin_observations' => 'nullable|string',
            'skin_conditions' => 'nullable|array',
            
            // Metadata
            'confidence_level' => 'nullable|in:Low,Medium,High',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        // Validate against reference data if available
        if (isset($validated['observed_hair_type'])) {
            $validTypes = ReferenceData::byCategory('hair_type')->active()->pluck('value')->toArray();
            if (!empty($validTypes) && !in_array($validated['observed_hair_type'], $validTypes)) {
                return response()->json(['message' => 'Invalid hair type'], 422);
            }
        }

        if (isset($validated['observed_beard_style'])) {
            $validStyles = ReferenceData::byCategory('beard_style')->active()->pluck('value')->toArray();
            if (!empty($validStyles) && !in_array($validated['observed_beard_style'], $validStyles)) {
                return response()->json(['message' => 'Invalid beard style'], 422);
            }
        }

        if (isset($validated['observed_skin_type'])) {
            $validTypes = ReferenceData::byCategory('skin_type')->active()->pluck('value')->toArray();
            if (!empty($validTypes) && !in_array($validated['observed_skin_type'], $validTypes)) {
                return response()->json(['message' => 'Invalid skin type'], 422);
            }
        }

        $assessment = ProfessionalAssessment::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'customer_id' => $validated['customer_id'],
            'specialist_id' => $validated['specialist_id'],
            'salon_id' => $validated['salon_id'],
            'booking_id' => $validated['booking_id'] ?? null,
            'observed_hair_type' => $validated['observed_hair_type'] ?? null,
            'hair_density' => $validated['hair_density'] ?? null,
            'scalp_condition' => $validated['scalp_condition'] ?? null,
            'hairline' => $validated['hairline'] ?? null,
            'hair_observations' => $validated['hair_observations'] ?? null,
            'observed_beard_style' => $validated['observed_beard_style'] ?? null,
            'beard_growth_pattern' => $validated['beard_growth_pattern'] ?? null,
            'beard_observations' => $validated['beard_observations'] ?? null,
            'observed_skin_type' => $validated['observed_skin_type'] ?? null,
            'skin_observations' => $validated['skin_observations'] ?? null,
            'skin_conditions' => $validated['skin_conditions'] ?? null,
            'confidence_level' => $validated['confidence_level'] ?? 'Medium',
            'notes' => $validated['notes'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        // Auto-generate recommendations from assessment
        $recommendations = $this->generateRecommendationsFromAssessment($assessment);

        return response()->json([
            'message' => 'Assessment created successfully',
            'assessment' => $assessment->load(['specialist', 'salon']),
            'recommendations' => $recommendations,
        ], 201);
    }

    /**
     * Generate profile recommendations from assessment
     */
    private function generateRecommendationsFromAssessment(ProfessionalAssessment $assessment): array
    {
        $customer = $assessment->customer;
        $groomingProfile = $customer->groomingProfile;
        $recommendations = [];

        if (!$groomingProfile) {
            return $recommendations;
        }

        // Compare observed values with current profile values
        $fields = [
            'hair_type' => [
                'observed' => $assessment->observed_hair_type,
                'current' => $groomingProfile->hair_type,
                'label' => 'Hair Type',
            ],
            'beard_style' => [
                'observed' => $assessment->observed_beard_style,
                'current' => $groomingProfile->beard_style,
                'label' => 'Beard Style',
            ],
            'skin_type' => [
                'observed' => $assessment->observed_skin_type,
                'current' => $groomingProfile->skin_type,
                'label' => 'Skin Type',
            ],
        ];

        foreach ($fields as $field => $data) {
            if ($data['observed'] && $data['observed'] !== $data['current']) {
                $recommendation = ProfileRecommendation::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'customer_id' => $customer->id,
                    'assessment_id' => $assessment->id,
                    'specialist_id' => $assessment->specialist_id,
                    'field' => $field,
                    'current_value' => $data['current'],
                    'recommended_value' => $data['observed'],
                    'reason' => "Based on professional assessment by {$assessment->specialist->name}",
                    'confidence_level' => $assessment->confidence_level,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(30), // Expires in 30 days
                ]);

                // Create notification for customer
                Notification::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'customer_id' => $customer->id,
                    'type' => 'profile_recommendation',
                    'title' => 'New Profile Recommendation',
                    'message' => "{$assessment->specialist->name} suggests updating your {$data['label']} to {$data['observed']}",
                    'data' => [
                        'recommendation_id' => $recommendation->id,
                        'field' => $field,
                        'current_value' => $data['current'],
                        'recommended_value' => $data['observed'],
                        'specialist_name' => $assessment->specialist->name,
                    ],
                    'read_at' => null,
                ]);

                $recommendations[] = $recommendation;
            }
        }

        return $recommendations;
    }

    /**
     * Create a specialist note
     */
    public function createNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'specialist_id' => 'required|uuid|exists:specialists,id',
            'salon_id' => 'required|uuid|exists:salons,id',
            'booking_id' => 'nullable|uuid|exists:bookings,id',
            'note' => 'required|string',
            'note_type' => 'nullable|in:general,preference,observation,recommendation',
            'tags' => 'nullable|array',
            'is_private' => 'boolean',
            'is_important' => 'boolean',
            'customer_visible' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $note = SpecialistNote::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'customer_id' => $validated['customer_id'],
            'specialist_id' => $validated['specialist_id'],
            'salon_id' => $validated['salon_id'],
            'booking_id' => $validated['booking_id'] ?? null,
            'note' => $validated['note'],
            'note_type' => $validated['note_type'] ?? 'general',
            'tags' => $validated['tags'] ?? null,
            'is_private' => $validated['is_private'] ?? false,
            'is_important' => $validated['is_important'] ?? false,
            'customer_visible' => $validated['customer_visible'] ?? true,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        return response()->json([
            'message' => 'Note created successfully',
            'note' => $note->load(['specialist']),
        ], 201);
    }

    /**
     * Customer reviews and responds to an assessment
     */
    public function reviewAssessment(Request $request, string $assessmentId): JsonResponse
    {
        $validated = $request->validate([
            'accepted' => 'required|boolean',
            'feedback' => 'nullable|string',
        ]);

        $assessment = ProfessionalAssessment::find($assessmentId);
        if (!$assessment) {
            return response()->json(['message' => 'Assessment not found'], 404);
        }

        $assessment->markAsReviewed($validated['accepted'], $validated['feedback'] ?? null);

        // If accepted, optionally update customer profile
        if ($validated['accepted']) {
            $customer = $assessment->customer;
            $groomingProfile = $customer->groomingProfile;

            if ($groomingProfile) {
                $updates = [];
                
                if ($assessment->observed_hair_type) {
                    $updates['hair_type'] = $assessment->observed_hair_type;
                }
                if ($assessment->observed_beard_style) {
                    $updates['beard_style'] = $assessment->observed_beard_style;
                }
                if ($assessment->observed_skin_type) {
                    $updates['skin_type'] = $assessment->observed_skin_type;
                }

                if (!empty($updates)) {
                    $groomingProfile->update($updates);
                }
            }
        }

        return response()->json([
            'message' => 'Assessment reviewed successfully',
            'assessment' => $assessment->fresh(),
        ]);
    }

    /**
     * Get pending assessments for a customer
     */
    public function getPendingAssessments(string $customerId): JsonResponse
    {
        $assessments = ProfessionalAssessment::byCustomer($customerId)
            ->pendingReview()
            ->with(['specialist', 'salon'])
            ->recent()
            ->get();

        return response()->json([
            'assessments' => $assessments,
        ]);
    }

    /**
     * Mark a note as viewed by customer
     */
    public function markNoteViewed(string $noteId): JsonResponse
    {
        $note = SpecialistNote::find($noteId);
        if (!$note) {
            return response()->json(['message' => 'Note not found'], 404);
        }

        $note->markAsViewedByCustomer();

        return response()->json([
            'message' => 'Note marked as viewed',
            'note' => $note->fresh(),
        ]);
    }

    /**
     * Get customer timeline (assessments and notes combined)
     */
    public function getCustomerTimeline(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        // Get assessments
        $assessments = ProfessionalAssessment::byCustomer($customer->id)
            ->with(['specialist', 'salon'])
            ->recent()
            ->get()
            ->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'type' => 'assessment',
                    'created_at' => $assessment->created_at,
                    'specialist' => $assessment->specialist,
                    'salon' => $assessment->salon,
                    'data' => [
                        'observed_hair_type' => $assessment->observed_hair_type,
                        'hair_density' => $assessment->hair_density,
                        'scalp_condition' => $assessment->scalp_condition,
                        'hairline' => $assessment->hairline,
                        'observed_beard_style' => $assessment->observed_beard_style,
                        'beard_growth_pattern' => $assessment->beard_growth_pattern,
                        'observed_skin_type' => $assessment->observed_skin_type,
                        'skin_conditions' => $assessment->skin_conditions,
                        'confidence_level' => $assessment->confidence_level,
                        'notes' => $assessment->notes,
                    ],
                    'customer_reviewed' => $assessment->customer_reviewed,
                    'customer_accepted' => $assessment->customer_accepted,
                    'customer_feedback' => $assessment->customer_feedback,
                ];
            });

        // Get notes
        $notes = SpecialistNote::byCustomer($customer->id)
            ->with(['specialist'])
            ->customerVisible()
            ->recent()
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'type' => 'note',
                    'created_at' => $note->created_at,
                    'specialist' => $note->specialist,
                    'data' => [
                        'note' => $note->note,
                        'note_type' => $note->note_type,
                        'tags' => $note->tags,
                        'is_important' => $note->is_important,
                    ],
                    'customer_viewed_at' => $note->customer_viewed_at,
                ];
            });

        // Combine and sort by date
        $timeline = collect([...$assessments, ...$notes])
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'customer' => $customer,
            'timeline' => $timeline,
            'summary' => [
                'total_assessments' => $assessments->count(),
                'total_notes' => $notes->count(),
                'pending_assessments' => $assessments->where('customer_reviewed', false)->count(),
                'unread_notes' => $notes->where('customer_viewed_at', null)->count(),
            ],
        ]);
    }

    /**
     * Get pending recommendations for customer
     */
    public function getPendingRecommendations(Request $request): JsonResponse
    {
        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        $recommendations = ProfileRecommendation::byCustomer($customer->id)
            ->active()
            ->with(['specialist', 'assessment'])
            ->recent()
            ->get();

        return response()->json([
            'recommendations' => $recommendations,
            'count' => $recommendations->count(),
        ]);
    }

    /**
     * Customer responds to a recommendation
     */
    public function respondToRecommendation(Request $request, string $recommendationId): JsonResponse
    {
        $validated = $request->validate([
            'accepted' => 'required|boolean',
            'feedback' => 'nullable|string',
        ]);

        $portalAccount = auth('portal')->user();
        $customer = $portalAccount->customer;

        $recommendation = ProfileRecommendation::find($recommendationId);
        if (!$recommendation) {
            return response()->json(['message' => 'Recommendation not found'], 404);
        }

        if ($recommendation->customer_id !== $customer->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($recommendation->status !== 'pending') {
            return response()->json(['message' => 'Recommendation already responded to'], 400);
        }

        if ($validated['accepted']) {
            $recommendation->markAsAccepted($validated['feedback'] ?? null);

            // Update customer profile
            $groomingProfile = $customer->groomingProfile;
            if ($groomingProfile) {
                $groomingProfile->update([
                    $recommendation->field => $recommendation->recommended_value,
                ]);
            }
        } else {
            $recommendation->markAsRejected($validated['feedback'] ?? null);
        }

        return response()->json([
            'message' => 'Recommendation responded successfully',
            'recommendation' => $recommendation->fresh(),
        ]);
    }
}
