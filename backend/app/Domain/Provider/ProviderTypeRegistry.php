<?php

namespace App\Domain\Provider;

use App\Domain\Provider\Exceptions\UnsupportedProviderTypeError;

class ProviderTypeRegistry
{
    private array $definitions = [];

    public function register(string $type, ProviderTypeDefinition $definition): void
    {
        $this->definitions[$type] = $definition;
    }

    public function for(string $type): ProviderTypeDefinition
    {
        if (!isset($this->definitions[$type])) {
            throw new UnsupportedProviderTypeError($type);
        }

        return $this->definitions[$type];
    }

    public function isSupported(string $type): bool
    {
        return isset($this->definitions[$type]);
    }

    public function all(): array
    {
        return $this->definitions;
    }
}
