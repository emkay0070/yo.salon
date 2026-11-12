<?php

namespace App\Relations;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class BookingServiceBelongsToMany extends BelongsToMany
{
    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $ids
     * @param  array  $attributes
     * @param  bool  $touch
     * @return void
     */
    public function attach($ids, array $attributes = [], $touch = true)
    {
        // Auto-generate UUID for booking_service.id if not provided
        $ids = $this->parseIds($ids);
        
        foreach ((array) $ids as $id) {
            if (!isset($attributes['id'])) {
                $attributes['id'] = (string) Str::uuid();
            }
        }
        
        return parent::attach($ids, $attributes, $touch);
    }
}
