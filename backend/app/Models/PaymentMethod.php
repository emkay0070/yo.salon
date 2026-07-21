<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\BelongsToSalon;

class PaymentMethod extends Model
{
    use HasFactory, BelongsToSalon;

    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }
}
