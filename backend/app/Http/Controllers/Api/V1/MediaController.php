<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use App\Services\MediaAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Relations\Relation;

class MediaController extends Controller
{
    protected MediaService $mediaService;
    protected MediaAuthorizationService $authService;

    public function __construct(MediaService $mediaService, MediaAuthorizationService $authService)
    {
        $this->mediaService = $mediaService;
        $this->authService = $authService;
    }

    /**
     * Upload a file
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp,svg|max:10240', // Max 10MB, image types only
            'directory' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:500',
            'attachable_type' => 'nullable|string|max:255',
            'attachable_id' => 'nullable|string|max:255',
            'visibility' => 'nullable|in:public,authenticated,private',
        ]);

        $user = auth()->user();
        
        // Resolve attachable if provided
        $attachable = null;
        if (!empty($validated['attachable_type']) && !empty($validated['attachable_id'])) {
            // Support morphological aliases if registered, or direct class names
            $class = Relation::getMorphedModel($validated['attachable_type']) ?? $validated['attachable_type'];
            if (class_exists($class)) {
                $attachable = $class::find($validated['attachable_id']);
                
                if ($attachable && !$this->authService->canUploadFor($user, $attachable)) {
                    return response()->json(['error' => 'Unauthorized to attach media to this entity'], 403);
                }
            }
        }

        // Salon/Provider context for backward compatibility when uploaded by a salon User
        $salon    = method_exists($user, 'currentSalon') ? $user->currentSalon() : null;
        $provider = $salon?->provider;

        $media = $this->mediaService->upload($request->file('file'), [
            'directory'   => $validated['directory'] ?? 'uploads',
            'provider_id' => $provider?->id,
            'salon_id'    => $salon?->id,
            'uploader'    => $user,
            'attachable'  => $attachable,
            'visibility'  => $validated['visibility'] ?? Media::VISIBILITY_PUBLIC,
            'alt_text'    => $validated['alt_text'] ?? null,
        ]);

        return response()->json($media, 201);
    }

    /**
     * Upload from base64 string
     */
    public function uploadBase64(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'data' => 'required|string|max:10485760', // Max 10MB base64 string
            'directory' => 'nullable|string|max:255',
            'alt_text' => 'nullable|string|max:500',
            'attachable_type' => 'nullable|string|max:255',
            'attachable_id' => 'nullable|string|max:255',
            'visibility' => 'nullable|in:public,authenticated,private',
        ]);

        $user = auth()->user();

        // Resolve attachable if provided
        $attachable = null;
        if (!empty($validated['attachable_type']) && !empty($validated['attachable_id'])) {
            $class = Relation::getMorphedModel($validated['attachable_type']) ?? $validated['attachable_type'];
            if (class_exists($class)) {
                $attachable = $class::find($validated['attachable_id']);
                
                if ($attachable && !$this->authService->canUploadFor($user, $attachable)) {
                    return response()->json(['error' => 'Unauthorized to attach media to this entity'], 403);
                }
            }
        }

        // Salon/Provider context for backward compatibility
        $salon    = method_exists($user, 'currentSalon') ? $user->currentSalon() : null;
        $provider = $salon?->provider;

        $media = $this->mediaService->uploadFromBase64($validated['data'], [
            'directory'   => $validated['directory'] ?? 'uploads',
            'provider_id' => $provider?->id,
            'salon_id'    => $salon?->id,
            'uploader'    => $user,
            'attachable'  => $attachable,
            'visibility'  => $validated['visibility'] ?? Media::VISIBILITY_PUBLIC,
            'alt_text'    => $validated['alt_text'] ?? null,
        ]);

        return response()->json($media, 201);
    }

    /**
     * Get media by ID (with authorization check)
     */
    public function show(Media $media): JsonResponse
    {
        if (!$this->authService->canRead($media, auth()->user())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($media);
    }

    /**
     * Delete media (with authorization check)
     */
    public function destroy(Media $media): JsonResponse
    {
        if (!$this->authService->canDelete($media, auth()->user())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $this->mediaService->delete($media);
        return response()->json(null, 204);
    }
}
