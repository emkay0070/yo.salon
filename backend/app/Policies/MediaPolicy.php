<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use App\Models\SpecialistAccount;
use App\Models\PortalAccount;
use App\Services\MediaAuthorizationService;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    protected MediaAuthorizationService $authService;

    public function __construct(MediaAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Determine if the user can view the media.
     */
    public function view(mixed $user, Media $media): bool
    {
        return $this->authService->canRead($media, $user);
    }

    /**
     * Determine if the user can create media.
     */
    public function create(mixed $user): bool
    {
        // Any authenticated user can upload media
        // The attachable relationship is validated during upload
        return $user instanceof User 
            || $user instanceof SpecialistAccount 
            || $user instanceof PortalAccount;
    }

    /**
     * Determine if the user can update the media metadata.
     */
    public function update(mixed $user, Media $media): bool
    {
        return $this->authService->canDelete($media, $user);
    }

    /**
     * Determine if the user can delete the media.
     */
    public function delete(mixed $user, Media $media): bool
    {
        return $this->authService->canDelete($media, $user);
    }

    /**
     * Determine if the user can upload media for a specific attachable.
     */
    public function uploadFor(mixed $user, mixed $attachable): bool
    {
        return $this->authService->canUploadFor($user, $attachable);
    }

    /**
     * Scope: Only return media belonging to the user's provider.
     * Used for provider-scoped queries.
     */
    public function scopeForProvider(mixed $user, $query)
    {
        if ($user instanceof User && method_exists($user, 'currentProvider')) {
            $provider = $user->currentProvider();
            if ($provider) {
                return $query->where('provider_id', $provider->id);
            }
        }

        // For specialists, scope to their provider
        if ($user instanceof SpecialistAccount && $user->specialist) {
            return $query->where('provider_id', $user->specialist->provider_id);
        }

        // For portal customers, return only public media
        if ($user instanceof PortalAccount) {
            return $query->where('visibility', Media::VISIBILITY_PUBLIC);
        }

        return $query;
    }
}
