<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProviderUserLocation extends Model
{
    use HasUuids;

    protected $fillable = [
        'provider_user_id',
        'location_id',
    ];

    public function providerUser()
    {
        return $this->belongsTo(ProviderUser::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
