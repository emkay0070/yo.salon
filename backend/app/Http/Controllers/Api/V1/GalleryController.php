<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GalleryController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get gallery images for a salon or provider
     */
    public function index(Request $request): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $media = Media::where('salon_id', $salonId)
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($media);
    }

    /**
     * Upload a gallery image
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|image|max:10240', // Max 10MB
            'alt_text' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $salon = \App\Models\Salon::find($salonId);
        if (!$salon) {
            return response()->json(['error' => 'Salon not found'], 404);
        }

        $media = $this->mediaService->upload($request->file('file'), [
            'directory' => 'gallery',
            'provider_id' => $salon->provider_id,
            'salon_id' => $salonId,
            'alt_text' => $validated['alt_text'] ?? 'Gallery image',
            'metadata' => [
                'caption' => $validated['caption'] ?? null,
            ],
        ]);

        return response()->json($media, 201);
    }

    /**
     * Delete a gallery image
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $media = Media::where('id', $id)
            ->where('salon_id', $salonId)
            ->firstOrFail();

        $this->mediaService->delete($media);

        return response()->json(null, 204);
    }

    /**
     * Update gallery image metadata
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'alt_text' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        $salonId = $request->attributes->get('salon_id');
        
        if (!$salonId) {
            return response()->json(['error' => 'Salon context not found'], 400);
        }

        $media = Media::where('id', $id)
            ->where('salon_id', $salonId)
            ->firstOrFail();

        $media->update([
            'alt_text' => $validated['alt_text'] ?? $media->alt_text,
            'metadata' => array_merge($media->metadata ?? [], [
                'caption' => $validated['caption'] ?? null,
            ]),
        ]);

        return response()->json($media);
    }
}
