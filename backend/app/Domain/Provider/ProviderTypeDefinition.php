<?php

namespace App\Domain\Provider;

readonly class ProviderTypeDefinition
{
    public function __construct(
        public string $type,
        public string $displayName,
        public bool $isIndependent,
        public bool $isOwnerManaged,
    ) {}
}
