<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToSalon;

class Invitation extends Model
{
    use HasUuids, BelongsToSalon;

    protected $fillable = [
        'salon_id',
        'email',
        'role',
        'target_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
