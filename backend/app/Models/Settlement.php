<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToSalon;

class Settlement extends Model
{
    use HasFactory, BelongsToSalon;

    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
