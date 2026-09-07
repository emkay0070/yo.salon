<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroomingProfile extends Model
{
    protected $fillable = [
        'customer_id',
        'hair_type',
        'preferred_hair_style',
        'hair_concerns',
        'beard_style',
        'beard_products',
        'skin_type',
        'allergies',
    ];

    protected $casts = [
        'hair_concerns' => 'array',
        'allergies' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'uuid';

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
