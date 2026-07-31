<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BookingActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BookingActivityController extends Controller
{
    public function show(Request $request, string $bookingId): JsonResponse
    {
        $activities = BookingActivity::forBooking($bookingId)
            ->chronological()
            ->get();

        return response()->json([
            'activities' => $activities,
        ]);
    }
}
