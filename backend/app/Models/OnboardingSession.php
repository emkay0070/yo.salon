<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingSession extends Model
{
    protected $fillable = [
        'user_id',
        'current_step',
        'draft_data',
        'completed',
    ];

    protected $casts = [
        'draft_data' => 'array',
        'completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateDraft(string $step, array $data): void
    {
        $currentData = $this->draft_data ?? [];
        $currentData[$step] = $data;
        
        $this->update([
            'draft_data' => $currentData,
            'current_step' => $step,
        ]);
    }
}
