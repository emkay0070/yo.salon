<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Profile extends Model
{
    use HasApiTokens, HasUuids;

    protected $fillable = [
        'email',
        'name',
        'password',
        'role',
        'location_id',
    ];

    protected $hidden = [
        'password',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
