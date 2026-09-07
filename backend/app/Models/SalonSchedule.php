<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalonSchedule extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected $fillable = [
        'salon_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
        'status',
        'published_at',
    ];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    public function salon()
    {
        return $this->belongsTo(Salon::class);
    }
}
