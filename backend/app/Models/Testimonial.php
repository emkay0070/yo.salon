<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Testimonial extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'salon_id',
        'customer_name',
        'customer_email',
        'rating',
        'content',
        'visible',
    ];

    protected $casts = [
        'rating' => 'integer',
        'visible' => 'boolean',
    ];

    public function salon(): BelongsTo
    {
        return $this->belongsTo(Salon::class);
    }
}
