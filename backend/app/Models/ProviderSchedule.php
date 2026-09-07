<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderSchedule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'provider_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
        'effective_date',
        'expires_date',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
        'effective_date' => 'date',
        'expires_date' => 'date',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}
