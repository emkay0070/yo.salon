<?php

namespace App\Domain\Platform;

/**
 * PlatformRole defines the approved platform governance roles in TYBUEL.
 *
 * These roles are independent of Organization memberships and ProviderUser operational roles:
 * - Platform Owner: Complete platform ownership and financial/infrastructure control.
 * - Platform Super Admin: Executive platform administration.
 * - Developer: Engineering, diagnostics, and debugging (read-only for sensitive data, non-production tools).
 * - Support: Tenant assistance, invoice lookup, operational troubleshooting.
 */
class PlatformRole
{
    public const PLATFORM_OWNER = 'platform_owner';
    public const PLATFORM_SUPER_ADMIN = 'platform_super_admin';
    public const DEVELOPER = 'developer';
    public const SUPPORT = 'support';

    /**
     * All approved platform governance roles.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return [
            self::PLATFORM_OWNER,
            self::PLATFORM_SUPER_ADMIN,
            self::DEVELOPER,
            self::SUPPORT,
        ];
    }

    /**
     * Determine if a given string is a valid platform governance role.
     */
    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
