<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use App\Domain\Specialist\SpecialistRankingEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PublicSpecialistController extends Controller
{
    public function show(string $handle): JsonResponse
    {
        $specialist = Specialist::where('handle', $handle)
            ->where('active', true)
            ->firstOrFail();

        $specialist->load(['provider', 'salons' => function ($query) {
            $query->where('active', true);
        }]);

        // Get reviews for this specialist
        $reviews = \DB::table('reviews')
            ->where('reviewable_type', 'App\\Models\\Specialist')
            ->where('reviewable_id', $specialist->id)
            ->where('is_approved', true)
            ->select('id', 'rating', 'comment', 'customer_name', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'author' => $review->customer_name,
                    'date' => $review->created_at,
                ];
            });

        // Get career events for Career Graph
        $careerEvents = $specialist->careerEvents()
            ->public()
            ->chronological()
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'date' => $event->date,
                    'icon' => $event->icon,
                    'salon' => $event->salon?->name,
                ];
            });

        // Get career stats
        $careerStats = [
            'years_experience' => $specialist->years_experience ?? 0,
            'total_clients' => $specialist->follower_count ?? 0,
            'total_bookings' => $specialist->bookings()->count(),
            'punctuality' => $specialist->punctuality ?? 0,
            'attendance' => $specialist->attendance ?? 0,
        ];

        // Get portfolio items
        $portfolio = $specialist->portfolio ?? [];

        return response()->json([
            'id' => $specialist->id,
            'name' => $specialist->name,
            'handle' => $specialist->handle,
            'bio' => $specialist->bio,
            'photo_url' => $specialist->photo_url,
            'rating' => $specialist->rating ?? 0,
            'review_count' => $reviews->count(),
            'specialties' => $specialist->specialties ?? [],
            'verification_status' => $specialist->verification_status?->value ?? 'unverified',
            'is_verified' => $specialist->isVerified(),
            'is_pro' => $specialist->isPro(),
            'location' => $specialist->salons->first()?->city ?? 'Uganda',
            'phone' => $specialist->phone,
            'email' => $specialist->email,
            'salon' => $specialist->salons->first() ? [
                'name' => $specialist->salons->first()->name,
                'slug' => $specialist->salons->first()->slug,
            ] : null,
            'portfolio' => $portfolio,
            'reviews' => $reviews,
            'career_events' => $careerEvents,
            'career_stats' => $careerStats,
        ]);
    }

    public function bookings(Request $request, string $handle): JsonResponse
    {
        $specialist = Specialist::where('handle', $handle)
            ->where('active', true)
            ->firstOrFail();

        // Resolve through assignments - specialist can work at multiple salons
        // This endpoint should return available assignments for booking
        $activeAssignments = $specialist->assignments()
            ->where('active', true)
            ->with('salon')
            ->get();

        return response()->json([
            'specialist_id' => $specialist->id,
            'handle' => $specialist->handle,
            'name' => $specialist->name,
            'assignments' => $activeAssignments->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'salon_id' => $assignment->salon_id,
                    'salon_name' => $assignment->salon?->name,
                    'salon_slug' => $assignment->salon?->slug,
                    'role' => $assignment->role,
                ];
            }),
            'message' => 'Select an assignment to book with this specialist',
        ]);
    }

    public function verificationStatus(Request $request): JsonResponse
    {
        $specialist = $request->user()->specialist;

        $latestVerification = $specialist->verifications()->latest()->first();

        return response()->json([
            'status' => $specialist->verification_status?->value ?? 'unverified',
            'is_verified' => $specialist->isVerified(),
            'latest_verification' => $latestVerification ? [
                'id' => $latestVerification->id,
                'status' => $latestVerification->status->value,
                'submitted_at' => $latestVerification->created_at,
                'reviewed_at' => $latestVerification->reviewed_at,
                'reason' => $latestVerification->reason,
                'expires_at' => $latestVerification->expires_at,
            ] : null,
        ]);
    }

    public function submitVerification(Request $request): JsonResponse
    {
        $specialist = $request->user()->specialist;

        $validated = $request->validate([
            'documents' => 'required|array',
            'portfolio_link' => 'nullable|url',
            'social_links' => 'nullable|string',
        ]);

        // Create verification record
        $verification = $specialist->verifications()->create([
            'status' => \App\Domain\Specialist\VerificationStatus::SUBMITTED,
            'previous_status' => $specialist->verification_status,
            'documents' => $validated['documents'],
            'metadata' => [
                'portfolio_link' => $validated['portfolio_link'] ?? null,
                'social_links' => $validated['social_links'] ?? null,
            ],
        ]);

        // Update specialist status
        $specialist->update([
            'verification_status' => \App\Domain\Specialist\VerificationStatus::SUBMITTED,
        ]);

        return response()->json([
            'success' => true,
            'verification_id' => $verification->id,
            'status' => 'submitted',
        ]);
    }

    public function careerEvents(Request $request): JsonResponse
    {
        $specialist = $request->user()->specialist;

        $events = $specialist->careerEvents()
            ->chronological()
            ->get();

        return response()->json([
            'events' => $events,
        ]);
    }

    public function createCareerEvent(Request $request): JsonResponse
    {
        $specialist = $request->user()->specialist;

        $validated = $request->validate([
            'type' => 'required|in:milestone,employment,achievement,award,certification,promotion,salon_change,skill_mastery',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'salon_id' => 'nullable|uuid',
            'assignment_id' => 'nullable|uuid',
            'icon' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $event = $specialist->careerEvents()->create($validated);

        return response()->json([
            'success' => true,
            'event' => $event,
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $specialist = $request->user()->specialist;

        $period = $request->input('period', 'month');
        $startDate = match($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        // Get or create ledger account for specialist
        $ledgerAccount = \App\Domain\Finance\Ledger\LedgerAccount::getOrCreateFor(
            \App\Models\Specialist::class,
            $specialist->id,
            'receivable',
            'UGX'
        );

        // Get credit entries (money in) for the period
        $entries = $ledgerAccount->entries()
            ->credits()
            ->where('created_at', '>=', $startDate)
            ->get();

        $total = $entries->sum('amount');

        // Get settlements for this specialist
        $settlements = $specialist->settlementsAsRecipient()
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalSettled = $settlements->sum('amount');
        $pendingSettlements = $settlements->where('status', 'pending')->sum('amount');
        $paidSettlements = $settlements->where('status', 'paid')->sum('amount');

        // Get payouts
        $payouts = $specialist->payouts()
            ->where('created_at', '>=', $startDate)
            ->completed()
            ->get();

        $totalPaid = $payouts->sum('amount');

        // Group by salon (from settlement metadata)
        $bySalon = $settlements->map(function ($settlement) {
            $salonId = $settlement->payable_id;
            return [
                'salon_id' => $salonId,
                'total' => $settlement->amount,
                'pending' => $settlement->status === 'pending' ? $settlement->amount : 0,
                'paid' => $settlement->status === 'paid' ? $settlement->amount : 0,
            ];
        })->groupBy('salon_id')->map(function ($items) {
            return [
                'salon_id' => $items->first()['salon_id'],
                'total' => $items->sum('total'),
                'pending' => $items->sum('pending'),
                'paid' => $items->sum('paid'),
            ];
        })->values();

        return response()->json([
            'period' => $period,
            'total_earned' => $total,
            'total_settled' => $totalSettled,
            'total_paid' => $totalPaid,
            'pending_settlements' => $pendingSettlements,
            'paid_settlements' => $paidSettlements,
            'outstanding_balance' => $ledgerAccount->balance,
            'by_salon' => $bySalon,
        ]);
    }
}
