<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ServiceCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'name',
        'description',
        'image',
        'icon',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category', 'name');
    }

    /**
     * Get categories ordered by sort_order
     */
    public static function getOrdered(string $salonId)
    {
        return self::where('salon_id', $salonId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
