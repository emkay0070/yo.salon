<?php

namespace App\Domain\Availability\DTOs;

final readonly class ResolvedSchedule
{
    public function __construct(
        public bool $isClosed,
        public ?string $openTime = null,
        public ?string $closeTime = null,
        public ?string $breakStart = null,
        public ?string $breakEnd = null,
    ) {}
}
