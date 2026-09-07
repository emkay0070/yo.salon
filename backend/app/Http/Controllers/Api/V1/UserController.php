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
        $user = $request->user();
        return response()->json($user->load('salons', 'profilePhoto'));
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
            'phone' => 'sometimes|nullable|string|max:20',
            'photo_url' => 'sometimes|nullable|string',
        ]);

        $user->update($validated);

        return response()->json($user->load('salons', 'profilePhoto'));
    }

    /**
     * Upload profile photo for the current user
     */
    public function uploadProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
        ]);

        // Upload the media
        $media = $this->mediaService->upload($request->file('file'), [
            'directory' => 'profile-photos',
            'uploader' => $user,
            'attachable' => $user,
            'visibility' => Media::VISIBILITY_PUBLIC,
            'alt_text' => 'profile photo',
        ]);

        // Update user's photo_url
        $user->update(['photo_url' => $media->url]);

        return response()->json($user->load('salons', 'profilePhoto'));
    }

    /**
     * Remove profile photo from the current user
     */
    public function removeProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete the profile photo media
        $profilePhoto = $user->profilePhoto;
        if ($profilePhoto) {
            $this->mediaService->delete($profilePhoto);
        }

        // Clear the photo_url
        $user->update(['photo_url' => null]);

        return response()->json($user->load('salons', 'profilePhoto'));
    }
}
