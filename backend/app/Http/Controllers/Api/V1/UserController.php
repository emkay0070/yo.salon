<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    protected MediaService $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Get the current authenticated user's profile
     */
    public function me(Request $request): JsonResponse
    {
        \Log::info('UserController::me called');
        
        $user = $request->user();
        // Load providers relationship with logo media, cover image media, and business category
        $user->load(['providers.logoMedia', 'providers.coverImageMedia', 'providers.businessCategory']);
        // Get profile photo manually since profilePhoto() returns a query builder, not a relationship
        $user->profile_photo = $user->profilePhoto()->first();
        
        // Debug logging
        \Log::info('UserController::me response', [
            'user_id' => $user->id,
            'providers_count' => $user->providers->count(),
            'first_provider' => $user->providers->first() ? [
                'id' => $user->providers->first()->id,
                'provider_id' => $user->providers->first()->provider_id,
                'provider' => $user->providers->first()->provider,
                'logo_url' => $user->providers->first()->logo_url,
            ] : null,
            'profile_photo' => $user->profile_photo ? [
                'id' => $user->profile_photo->id,
                'url' => $user->profile_photo->url,
            ] : null,
        ]);
        
        return response()->json($user);
    }

    /**
     * Update the current authenticated user's profile
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'photo_url' => 'sometimes|nullable|string',
        ]);

        $user->update($validated);

        // Load providers relationship with logo media, cover image media, and business category
        $user->load(['providers.logoMedia', 'providers.coverImageMedia', 'providers.businessCategory']);
        // Get profile photo manually since profilePhoto() returns a query builder, not a relationship
        $user->profile_photo = $user->profilePhoto()->first();
        
        return response()->json($user);
    }

    /**
     * Upload profile photo for the current user
     * Uses legacy uploaded_by column since users have integer IDs but media.attachable_id is UUID
     */
    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
        ]);

        // Delete existing profile photo if any (find by uploaded_by and alt_text)
        $existingPhoto = \App\Models\Media::where('uploaded_by', $user->id)
            ->where('alt_text', 'like', '%profile%')
            ->first();
        if ($existingPhoto) {
            $this->mediaService->delete($existingPhoto);
        }

        // Upload the media using legacy uploaded_by column
        $media = $this->mediaService->upload($request->file('file'), [
            'directory' => 'profile-photos',
            'uploaded_by' => $user->id, // Use legacy integer column
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => 'profile photo',
        ]);

        // Load providers relationship with logo media, cover image media, and business category
        $user->load(['providers.logoMedia', 'providers.coverImageMedia', 'providers.businessCategory']);
        // Get profile photo manually since profilePhoto() returns a query builder, not a relationship
        $user->profile_photo = $user->profilePhoto()->first();
        
        return response()->json($user);
    }

    /**
     * Remove profile photo from the current user
     * Uses legacy uploaded_by column since users have integer IDs but media.attachable_id is UUID
     */
    public function removeProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete the profile photo media (find by uploaded_by and alt_text)
        $profilePhoto = \App\Models\Media::where('uploaded_by', $user->id)
            ->where('alt_text', 'like', '%profile%')
            ->first();
        if ($profilePhoto) {
            $this->mediaService->delete($profilePhoto);
        }

        // Load providers relationship with logo media, cover image media, and business category
        $user->load(['providers.logoMedia', 'providers.coverImageMedia', 'providers.businessCategory']);
        // Get profile photo manually since profilePhoto() returns a query builder, not a relationship
        $user->profile_photo = $user->profilePhoto()->first();
        
        return response()->json($user);
    }
}
