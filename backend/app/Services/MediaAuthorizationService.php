<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use App\Models\SpecialistAccount;
use App\Models\PortalAccount;
use App\Models\Specialist;
use App\Models\Customer;
use App\Models\CustomerTimeline;

use App\Models\Service;
use App\Models\Provider;

/**
 * MediaAuthorizationService
 *
 * Single responsibility: answer authorization questions about Media.
 * This service knows nothing about storage, file I/O, or business logic.
 * It only answers: "Can this actor perform this action on this Media?"
 *
 * Authorization is driven by the attachable entity (what the media belongs to),
 * not by location membership. Location membership is only relevant for location-owned
 * assets (branding, services, galleries).
 *
 *  Actor → can they modify the attachable? → grant/deny
 *
 * The separation from MediaService is intentional:
 *   MediaService   = storage lifecycle (upload, delete, paths, metadata)
 *   MediaAuthorizationService = access control (read, write, delete, replace)
 */
class MediaAuthorizationService
{
    /**
     * Can this actor read/view this media?
     *
     * Rules (evaluated in order):
     *   PUBLIC visibility     → YES (any authenticated actor)
     *   AUTHENTICATED         → YES (any authenticated actor)
     *   PRIVATE               → only uploader OR actor who controls the attachable
     */
    public function canRead(Media $media, mixed $actor): bool
    {
        // 1. AUTHENTICATED visibility explicitly allows any logged-in actor to read metadata
        if ($media->visibility === Media::VISIBILITY_AUTHENTICATED) {
            return true;
        }

        // 2. PUBLIC visibility applies to the storage file itself, NOT the metadata endpoint.
        // For metadata access (GET /media/{id}), fallback to ownership/attachable rules.
        // 3. PRIVATE visibility also requires ownership/attachable rules.
        
        return $this->isUploader($media, $actor)
            || $this->actorControlsAttachable($media, $actor);
    }

    /**
     * Can this actor delete this media?
     *
     * Stricter than read: actor must be the uploader OR control the attachable.
     * Visibility does not grant delete rights — ownership does.
     */
    public function canDelete(Media $media, mixed $actor): bool
    {
        return $this->isUploader($media, $actor)
            || $this->actorControlsAttachable($media, $actor);
    }

    /**
     * Can this actor replace (overwrite) this media?
     * Same rules as delete — replacing a file is a destructive operation.
     */
    public function canReplace(Media $media, mixed $actor): bool
    {
        return $this->canDelete($media, $actor);
    }

    /**
     * Can this actor upload media and attach it to the given entity?
     * Used as a pre-upload guard when the caller specifies an attachable.
     */
    public function canUploadFor(mixed $actor, mixed $attachable): bool
    {
        return $this->resolveAttachableControl($attachable, $actor);
    }

    // -------------------------------------------------------------------------
    // Internal: uploader identity check
    // -------------------------------------------------------------------------

    /**
     * Is this actor the recorded uploader of this media?
     *
     * Checks new morph columns first, then falls back to legacy uploaded_by
     * for pre-migration records uploaded by salon Users.
     */
    protected function isUploader(Media $media, mixed $actor): bool
    {
        // New morph-based identity
        if ($media->uploader_type && $media->uploader_id) {
            return $media->uploader_type === get_class($actor)
                && (string) $media->uploader_id === (string) $actor->getKey();
        }

        // Legacy fallback: uploaded_by is bigint → users.id
        if ($actor instanceof User && $media->uploaded_by) {
            return (int) $media->uploaded_by === (int) $actor->getKey();
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Internal: attachable control
    // -------------------------------------------------------------------------

    /**
     * Can actor control (manage/modify) the entity this media is attached to?
     * Returns false when there is no attachable — absence is not permission.
     */
    protected function actorControlsAttachable(Media $media, mixed $actor): bool
    {
        if (!$media->attachable_type || !$media->attachable_id) {
            return false;
        }

        $attachable = $media->attachable;

        if (!$attachable) {
            return false;
        }

        return $this->resolveAttachableControl($attachable, $actor);
    }

    /**
     * Resolve whether an actor controls a given attachable entity.
     *
     * This is the extensibility point. Add new entity types here as they are
     * introduced into the Media domain. Each branch is a single, auditable rule.
     *
     * The match uses `true` as the subject so each arm can be an expression.
     */
    protected function resolveAttachableControl(mixed $attachable, mixed $actor): bool
    {
        return match (true) {

            // --- Specialist owns their own profile media ---
            $attachable instanceof Specialist
                => $this->specialistActorOwnsSpecialist($actor, $attachable),

            // --- Customer (portal) owns their own profile media ---
            $attachable instanceof Customer
                => $this->portalActorOwnsCustomer($actor, $attachable),

            // --- Customer owns their own timeline entries ---
            $attachable instanceof CustomerTimeline
                => $this->portalActorOwnsTimeline($actor, $attachable),

            // --- Provider admin manages services (scoped via provider) ---
            $attachable instanceof Service
                => $this->providerActorManagesService($actor, $attachable),

            // --- Provider admin manages provider-level assets ---
            $attachable instanceof Provider
                => $this->providerActorManagesProvider($actor, $attachable),

            // --- Provider admin manages location-level assets ---
            $attachable instanceof \App\Models\Location
                => $this->providerActorManagesLocation($actor, $attachable),

            // Unknown entity type — deny by default
            default => false,
        };
    }

    // -------------------------------------------------------------------------
    // Entity-specific authorization rules
    // -------------------------------------------------------------------------

    /**
     * A SpecialistAccount controls a Specialist when the account's specialist_id
     * matches the specialist's primary key.
     *
     * Provider users do NOT control a specialist's personal media via this path
     * (they may manage location-scoped assignments separately, but not personal photos).
     */
    protected function specialistActorOwnsSpecialist(mixed $actor, Specialist $specialist): bool
    {
        if (!$actor instanceof SpecialistAccount) {
            return false;
        }

        return (string) $actor->specialist_id === (string) $specialist->getKey();
    }

    /**
     * A PortalAccount controls a Customer when the account's customer_id
     * matches the customer's primary key.
     */
    protected function portalActorOwnsCustomer(mixed $actor, Customer $customer): bool
    {
        if (!$actor instanceof PortalAccount) {
            return false;
        }

        return (string) $actor->customer_id === (string) $customer->getKey();
    }

    /**
     * A PortalAccount controls a CustomerTimeline entry when the timeline's
     * customer_id matches the actor's customer_id.
     */
    protected function portalActorOwnsTimeline(mixed $actor, CustomerTimeline $timeline): bool
    {
        if (!$actor instanceof PortalAccount) {
            return false;
        }

        return (string) $actor->customer_id === (string) $timeline->customer_id;
    }

    /**
     * A provider User controls a Location when that location's provider is their current active provider.
     */
    protected function providerActorManagesLocation(mixed $actor, \App\Models\Location $location): bool
    {
        if (!$actor instanceof User) {
            return false;
        }

        if (!method_exists($actor, 'currentProvider')) {
            return false;
        }

        $currentProvider = $actor->currentProvider();

        return $currentProvider && (string) $currentProvider->getKey() === (string) $location->provider_id;
    }

    /**
     * A provider User controls a Service when the service's provider matches
     * the user's current provider.
     */
    protected function providerActorManagesService(mixed $actor, Service $service): bool
    {
        if (!$actor instanceof User) {
            return false;
        }

        if (!method_exists($actor, 'currentProvider')) {
            return false;
        }

        $currentProvider = $actor->currentProvider();

        return $currentProvider
            && $service->provider_id
            && (string) $currentProvider->id === (string) $service->provider_id;
    }

    /**
     * A provider User controls a Provider when the provider matches
     * the user's current provider.
     */
    protected function providerActorManagesProvider(mixed $actor, Provider $provider): bool
    {
        if (!$actor instanceof User) {
            return false;
        }

        if (!method_exists($actor, 'currentProvider')) {
            return false;
        }

        $currentProvider = $actor->currentProvider();

        return $currentProvider
            && (string) $currentProvider->id === (string) $provider->getKey();
    }
}
