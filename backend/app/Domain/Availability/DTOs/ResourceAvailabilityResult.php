<?php

namespace App\Domain\Availability\DTOs;

use Illuminate\Support\Collection;

class ResourceAvailabilityResult
{
    public function __construct(
        public readonly bool $available,
        public readonly Collection $candidates, // Map of requirement_id -> available resources
        public readonly ?string $reason = null,
    ) {}
}
