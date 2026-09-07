<?php

namespace App\Domain\Journey\Services;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;

class JourneyScopeResolver
{
    /**
     * Resolve scope based on assignment status
     * Only active assignments represent current workplace context
     */
    public function resolve(Specialist $specialist, ?SpecialistAssignment $assignment): string
    {
        if ($assignment && $assignment->status === 'active') {
            return 'WORKPLACE';
        }
        return 'SPECIALIST';
    }

    /**
     * Validate access to workplace journey
     * Ensures specialist has active assignment to provider
     */
    public function validateAccess(Specialist $specialist, string $providerSlug): ?SpecialistAssignment
    {
        return $specialist->assignments()
            ->where('status', 'active')
            ->whereHas('provider', fn($q) => $q->where('slug', $providerSlug))
            ->first();
    }

    /**
     * Check if assignment is current (active)
     * Prevents treating old/ended assignments as current workplace
     */
    public function isCurrentAssignment(?SpecialistAssignment $assignment): bool
    {
        return $assignment && $assignment->status === 'active';
    }
}
