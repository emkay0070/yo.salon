<?php

namespace App\Services;

use App\Domain\Platform\PlatformRole;
use App\Models\PortalAccount;
use App\Models\SpecialistAccount;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * PlatformAuthorizationService manages authorization for platform-level governance.
 *
 * Guarantees that:
 * 1. Only legitimate User identities can hold platform governance roles.
 * 2. SpecialistAccount and PortalAccount identities are strictly rejected.
 * 3. Organization and Provider roles never imply platform privileges.
 * 4. Dangerous seeder operations are constrained by environment and role.
 */
class PlatformAuthorizationService
{
    /**
     * Resolve the platform governance role for an authenticatable actor.
     */
    public function resolvePlatformRole(?Authenticatable $actor): ?string
    {
        if (!$actor instanceof User) {
            // SpecialistAccount, PortalAccount, or guest
            return null;
        }

        // 1. Explicit platform_role property on model (in-memory or DB attribute)
        if (!empty($actor->platform_role) && PlatformRole::isValid($actor->platform_role)) {
            return $actor->platform_role;
        }

        // 2. Check configured email lists in config/platform.php
        // Security requirement (SEC-FIND-01): Email-based platform role resolution MUST require a verified email address.
        if (!$actor->hasVerifiedEmail()) {
            return null;
        }

        $email = strtolower(trim($actor->email ?? ''));
        if (!empty($email)) {
            $configuredRoles = config('platform.roles', []);
            foreach ($configuredRoles as $role => $emails) {
                if (is_array($emails)) {
                    $normalized = array_map(fn ($e) => strtolower(trim($e)), $emails);
                    if (in_array($email, $normalized, true)) {
                        return $role;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Check if the actor has one of the specified platform roles.
     *
     * @param Authenticatable|null $actor
     * @param string|array<string> $roles
     */
    public function hasPlatformRole(?Authenticatable $actor, string|array $roles): bool
    {
        $resolvedRole = $this->resolvePlatformRole($actor);
        if (!$resolvedRole) {
            return false;
        }

        $roles = is_array($roles) ? $roles : [$roles];
        return in_array($resolvedRole, $roles, true);
    }

    /**
     * Check if the actor possesses a specific administrative capability.
     */
    public function canPerform(?Authenticatable $actor, string $capability): bool
    {
        $resolvedRole = $this->resolvePlatformRole($actor);
        if (!$resolvedRole) {
            return false;
        }

        $allowedRoles = config("platform.capabilities.{$capability}", []);
        return in_array($resolvedRole, $allowedRoles, true);
    }

    /**
     * Determine if actor can view seeders.
     */
    public function canViewSeeders(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'seeder_view');
    }

    /**
     * Determine if actor can execute or mutate seeders.
     * Subject to production safeguards.
     */
    public function canExecuteSeeders(?Authenticatable $actor): bool
    {
        if ($this->isSeederExecutionDisabledInCurrentEnvironment()) {
            return false;
        }

        return $this->canPerform($actor, 'seeder_execution');
    }

    /**
     * Determine whether seeder execution is disabled in current environment.
     */
    public function isSeederExecutionDisabledInCurrentEnvironment(): bool
    {
        $shouldDisableInProduction = config('platform.disable_seeders_in_production', true);

        return $shouldDisableInProduction && app()->environment('production');
    }

    /**
     * Determine if actor can view platform subscription invoices.
     */
    public function canViewPlatformInvoices(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'invoices_view');
    }

    /**
     * Determine if actor can verify or reject platform subscription invoices.
     */
    public function canVerifyOrRejectInvoices(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'invoices_verify_reject');
    }

    /**
     * Determine if actor can manage platform payment methods.
     */
    public function canManagePaymentMethods(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'payment_methods_manage');
    }

    /**
     * Determine if actor can view decrypted platform payment method details.
     */
    public function canViewPaymentMethodDetails(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'payment_methods_view_details');
    }

    /**
     * Determine if actor can manage schedule migration.
     */
    public function canManageScheduleMigration(?Authenticatable $actor): bool
    {
        return $this->canPerform($actor, 'schedule_migration_admin');
    }
}
