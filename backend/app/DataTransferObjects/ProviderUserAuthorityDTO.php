<?php

namespace App\DataTransferObjects;

class ProviderUserAuthorityDTO
{
    public function __construct(
        public readonly string $provider_id,
        public readonly string $user_id,
        public readonly string $role,
        public readonly string $authority_scope,
        public readonly array $permitted_locations,
        public readonly ?array $current_location = null,
        public readonly bool $is_context_valid = true,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            provider_id: $data['provider_id'],
            user_id: $data['user_id'],
            role: $data['role'],
            authority_scope: $data['authority_scope'],
            permitted_locations: $data['permitted_locations'] ?? [],
            current_location: $data['current_location'] ?? null,
            is_context_valid: $data['is_context_valid'] ?? true,
        );
    }

    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider_id,
            'user_id' => $this->user_id,
            'role' => $this->role,
            'authority_scope' => $this->authority_scope,
            'permitted_locations' => $this->permitted_locations,
            'current_location' => $this->current_location,
            'is_context_valid' => $this->is_context_valid,
        ];
    }

    public function isProviderWide(): bool
    {
        return $this->authority_scope === 'provider_wide';
    }

    public function isLocationScoped(): bool
    {
        return $this->authority_scope === 'location_scoped';
    }
}
