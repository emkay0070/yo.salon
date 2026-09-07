<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Get reviews for a specific subject (service, specialist, salon, provider)
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'subject_type' => 'required|in:service,specialist,salon,provider',
            'subject_id' => 'required|string',
        ]);

        $subjectType = $request->input('subject_type');
        $subjectId = $request->input('subject_id');

        $query = Review::with(['customer', 'booking']);

        // Use the appropriate scope based on subject type
        switch ($subjectType) {
            case 'service':
                $query->forService($subjectId);
                break;
            case 'specialist':
                $query->forSpecialist($subjectId);
                break;
            case 'salon':
                $query->forSalon($subjectId);
                break;
            case 'provider':
                $query->forProvider($subjectId);
                break;
        }

        $reviews = $query->latest()->paginate(20);

        // Calculate average rating and total count
        $allReviews = Review::query();
        switch ($subjectType) {
            case 'service':
                $allReviews->forService($subjectId);
                break;
            case 'specialist':
                $allReviews->forSpecialist($subjectId);
                break;
            case 'salon':
                $allReviews->forSalon($subjectId);
                break;
            case 'provider':
                $allReviews->forProvider($subjectId);
                break;
        }

        $averageRating = $allReviews->avg('rating') ?: 0;
        $totalReviews = $allReviews->count();

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
            'stats' => [
                'average_rating' => round($averageRating, 2),
                'total_reviews' => $totalReviews,
            ],
        ]);
    }

    /**
     * Store a new review
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|string|exists:bookings,id',
            'subject_type' => 'required|in:service,specialist,salon,provider',
            'subject_id' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $review = Review::create([
            'booking_id' => $request->input('booking_id'),
            'subject_type' => $request->input('subject_type'),
            'subject_id' => $request->input('subject_id'),
            'rating' => $request->input('rating'),
            'comment' => $request->input('comment'),
            'customer_id' => auth()->id(),
        ]);

        return response()->json($review, 201);
    }

    /**
     * Get a specific review
     */
    public function show(string $id): JsonResponse
    {
        $review = Review::with(['customer', 'booking'])->findOrFail($id);

        return response()->json($review);
    }
}
