<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    // --- Visibility constants ---
    /** Served publicly — profile photos, service images, branding, portfolios */
    public const VISIBILITY_PUBLIC = 'public';

    /** Readable by any authenticated actor — shared non-sensitive assets */
    public const VISIBILITY_AUTHENTICATED = 'authenticated';

    /** Readable only by owner/uploader — IDs, verification docs, private photos */
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        // Storage
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'metadata',

        // Provider context (canonical business owner)
        'provider_id',
        
        // Optional location context (for physical-location scoped media)
        'location_id',

        // Legacy uploader (bigint → users table). Kept for backward compatibility.
        // New code should use uploader_type/uploader_id instead.
        'uploaded_by',

        // Who uploaded this? (polymorphic — User, SpecialistAccount, PortalAccount)
        'uploader_type',
        'uploader_id',

        // What entity does this media belong to? (polymorphic — Specialist, Location, Service, Customer, etc.)
        'attachable_type',
        'attachable_id',

        // Visibility level
        'visibility',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size'     => 'integer',
        'width'    => 'integer',
        'height'   => 'integer',
    ];

    protected $appends = ['url'];

    // --- Attribute accessors ---

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    // --- Relations ---

    /**
     * The authenticated actor that uploaded this file.
     * Polymorphic: resolves to User, SpecialistAccount, or PortalAccount.
     */
    public function uploader(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'uploader_type', 'uploader_id');
    }

    /**
     * The domain entity this media is attached to / belongs to.
     * Polymorphic: Specialist, Location, Service, Customer, CustomerTimeline, Provider, etc.
     *
     * This is the authoritative ownership anchor used by MediaAuthorizationService.
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'attachable_type', 'attachable_id');
    }

    /**
     * Legacy: direct relation to User (salon/admin) who uploaded.
     * Only populated for media uploaded before the morph columns were added.
     *
     * @deprecated Use uploader() instead.
     */
    public function legacyUploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    // --- Convenience helpers ---

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * Determine if the given actor is the morph-tracked uploader of this media.
     * Falls back to the legacy uploaded_by for old User-uploaded records.
     */
    public function isUploadedBy(mixed $actor): bool
    {
        // New morph-based check
        if ($this->uploader_type && $this->uploader_id) {
            return $this->uploader_type === get_class($actor)
                && (string) $this->uploader_id === (string) $actor->getKey();
        }

        // Legacy fallback: uploaded_by is a bigint → users.id
        if ($actor instanceof User && $this->uploaded_by) {
            return (int) $this->uploaded_by === (int) $actor->getKey();
        }

        return false;
    }

    // --- Scopes ---

    public function scopeForProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeForLocation($query, $locationId)
    {
        return $query->where('location_id', $locationId);
    }

    public function scopeProviderLevel($query)
    {
        return $query->whereNull('location_id');
    }

    public function scopeLocationScoped($query)
    {
        return $query->whereNotNull('location_id');
    }

    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopePublic($query)
    {
        return $query->where('visibility', self::VISIBILITY_PUBLIC);
    }
}
