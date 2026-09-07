<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerTimeline;
use App\Models\Booking;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TimelineController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function index(Request $request): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        $category = $request->query('category', 'all');
        $year = $request->query('year');
        $month = $request->query('month');
        
        $query = CustomerTimeline::query()->forCustomer($customerId);
        
        if ($category && $category !== 'all') {
            $query->byCategory($category);
        }
        
        if ($year && $month) {
            $query->byMonth($year, $month);
        }
        
        $entries = $query->chronological()->paginate(20);
        
        // Calculate stats
        $stats = [
            'total_visits' => CustomerTimeline::forCustomer($customerId)->count(),
            'total_spent' => CustomerTimeline::forCustomer($customerId)->sum('price') ?? 0,
            'avg_rating' => CustomerTimeline::forCustomer($customerId)->whereNotNull('rating')->avg('rating') ?? 0,
            'total_photos' => CustomerTimeline::forCustomer($customerId)
                ->whereNotNull('before_photo')
                ->orWhereNotNull('after_photo')
                ->count(),
        ];
        
        return response()->json([
            'entries' => $entries,
            'stats' => $stats,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $entry = CustomerTimeline::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        return response()->json([
            'entry' => $entry,
        ]);
    }

    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'photo' => 'required|image|max:10240', // Max 10MB
            'type' => 'required|in:before,after',
        ]);
        
        $customerId = $request->attributes->get('customer_id');
        
        $entry = CustomerTimeline::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        $media = $this->mediaService->upload($request->file('photo'), [
            'directory' => "timeline/{$customerId}",
            'alt_text' => "Timeline photo for entry {$id}",
        ]);
        
        $column = $validated['type'] . '_photo';
        $entry->$column = $media->id;
        $entry->save();
        
        return response()->json([
            'message' => 'Photo uploaded successfully',
            'entry' => $entry,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'nullable|numeric|min:1|max:5',
            'notes' => 'nullable|string',
            'category' => 'nullable|string',
        ]);
        
        $customerId = $request->attributes->get('customer_id');
        
        $entry = CustomerTimeline::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        $entry->update($validated);
        
        return response()->json([
            'message' => 'Timeline entry updated',
            'entry' => $entry,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customerId = $request->attributes->get('customer_id');
        
        $entry = CustomerTimeline::where('id', $id)
            ->where('customer_id', $customerId)
            ->firstOrFail();
        
        // Delete photos if they exist
        if ($entry->before_photo && \Illuminate\Support\Str::isUuid($entry->before_photo)) {
            $media = \App\Models\Media::find($entry->before_photo);
            if ($media) {
                $this->mediaService->delete($media);
            }
        } else if ($entry->before_photo) {
            Storage::disk('public')->delete($entry->before_photo);
        }
        
        if ($entry->after_photo && \Illuminate\Support\Str::isUuid($entry->after_photo)) {
            $media = \App\Models\Media::find($entry->after_photo);
            if ($media) {
                $this->mediaService->delete($media);
            }
        } else if ($entry->after_photo) {
            Storage::disk('public')->delete($entry->after_photo);
        }
        
        $entry->delete();
        
        return response()->json(['message' => 'Timeline entry deleted']);
    }
}
