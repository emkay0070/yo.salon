<?php

namespace App\Domain\Operations\Services;

use App\Models\Provider;
use App\Models\Salon;
use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\ProviderSchedule;
use App\Models\SalonSchedule;
use App\Models\AssignmentSchedule;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\ProviderBookingConfig;
use App\Models\SalonBookingConfig;
use App\Models\Subscription;
use App\Models\WebsiteConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * OperationalHealthService
 *
 * The single source of truth for:
 *   "Is this business healthy enough to operate online today / tomorrow / next year?"
 *
 * Computes a platform-wide health score across 7 pillars:
 *
 *   1. Scheduling   — hours set at every layer (provider / branch / assignment)
 *   2. Payments     — at least one payment method configured and active
 *   3. Staff        — at least one active specialist assigned to this branch
 *   4. Services     — at least one published service offered at this branch
 *   5. Subscription — not expired, within a valid plan period
 *   6. Booking      — booking rules set, online booking flag enabled
 *   7. Website      — basic website config present (name, logo, etc.)
 *
 * Status is INFORMATIONAL only. The engines (BookingEngine, BookabilityEngine)
 * enforce rules independently.
 */
class OperationalHealthService
{
    /**
     * Run a health check pillar, converting any exception into a `false` result
     * so a single failing pillar never takes down the entire health endpoint.
     *
     * @param string   $pillar
     * @param callable $fn  Must return a bool
     */
    private function safeCheck(string $pillar, callable $fn): bool
    {
        try {
            $result = $fn();
            return $result === true;
        } catch (\Throwable $e) {
            Log::warning('[OperationalHealthService] Pillar check failed', [
                'pillar' => $pillar,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Provider-level health (root business).
     */
    public function getProviderHealth(string $providerId): array
    {
        $provider = Provider::find($providerId);
        if (!$provider) {
            return [
                'status'   => 'incomplete',
                'checks'   => [],
                'score'    => 0,
                'provider_id' => null,
                'computed_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        }

        $hasProviderSchedule = $this->safeCheck('scheduling', function () use ($providerId) {
            return ProviderSchedule::where('provider_id', $providerId)->count() >= 1;
        });

        $hasPaymentMethods = $this->safeCheck('payments', function () use ($providerId) {
            return PaymentMethod::where('provider_id', $providerId)
                ->where('active', true)
                ->exists();
        });

        $hasAnySpecialists = $this->safeCheck('staff', function () use ($providerId) {
            return Specialist::where('provider_id', $providerId)
                ->where('active', true)
                ->exists();
        });

        $hasAnyServices = $this->safeCheck('services', function () use ($providerId) {
            return Service::withoutGlobalScopes()
                ->where('provider_id', $providerId)
                ->where('active', true)
                ->exists();
        });

        $hasValidSubscription = $this->safeCheck('subscription', function () use ($providerId) {
            return Subscription::where('provider_id', $providerId)
                ->where(fn($q) => $q
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>', CarbonImmutable::now())
                )
                ->where('status', '!=', 'cancelled')
                ->exists();
        });

        $bookingConfigOk = $this->safeCheck('booking', function () use ($providerId, $provider) {
            $bookingConfig = ProviderBookingConfig::where('provider_id', $providerId)->first();
            $hasConfig = $bookingConfig === null || (
                (is_countable($bookingConfig?->booking_policy) ? count($bookingConfig->booking_policy) > 0 : !empty($bookingConfig?->booking_policy)) ||
                (is_countable($bookingConfig?->availability_policy) ? count($bookingConfig->availability_policy) > 0 : !empty($bookingConfig?->availability_policy))
            );
            $acceptingOnline = (bool) ($provider->settings['accepting_online_bookings'] ?? $provider->active ?? true);
            return $acceptingOnline && $hasConfig;
        });

        $websiteOk = $this->safeCheck('website', function () use ($providerId, $provider) {
            $websiteConfigExists = WebsiteConfiguration::where('provider_id', $providerId)->exists();
            $hasName = (bool) ($provider->display_name ?? $provider->name ?? null);
            return $websiteConfigExists || $hasName;
        });

        $checks = [
            'scheduling'   => $hasProviderSchedule,
            'payments'     => $hasPaymentMethods,
            'staff'        => $hasAnySpecialists,
            'services'     => $hasAnyServices,
            'subscription' => $hasValidSubscription,
            'booking'      => $bookingConfigOk,
            'website'      => $websiteOk,
        ];

        $isConfigured = !in_array(false, array_values($checks), true);

        return [
            'status' => $isConfigured ? 'configured' : 'incomplete',
            'checks' => $checks,
            'score'  => $this->calculateScore($checks),
            'provider_id' => $providerId,
            'computed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Branch-level (salon) health.
     */
    public function getBranchHealth(string $salonId): array
    {
        $salon = Salon::find($salonId);
        if (!$salon) {
            return [
                'status'      => 'incomplete',
                'checks'      => [],
                'score'       => 0,
                'salon_id'    => $salonId,
                'provider_id' => null,
                'computed_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        }

        $providerHealth = $this->getProviderHealth($salon->provider_id);

        $branchSchedulingOk = $this->safeCheck('scheduling', function () use ($salon, $salonId, $providerHealth) {
            if (($salon->schedule_mode ?? 'INHERIT') === 'CUSTOM') {
                return SalonSchedule::where('salon_id', $salonId)->exists();
            }
            return (bool) ($providerHealth['checks']['scheduling'] ?? false);
        });

        $paymentsOk = (bool) ($providerHealth['checks']['payments'] ?? false);

        $staffOk = $this->safeCheck('staff', function () use ($salonId) {
            return SpecialistAssignment::where('salon_id', $salonId)
                ->where('active', true)
                ->exists();
        });

        $servicesOk = $this->safeCheck('services', function () use ($salonId, $providerHealth) {
            // PostgreSQL-safe: is_available is BOOLEAN; compare against true only.
            // NULL = inherit (not overridden). If the salon has ANY override rows with is_available=true,
            // or NO rows at all (fallback to provider catalog), we consider services available.
            $explicitlyAvailable = \DB::table('salon_service')
                ->where('salon_id', $salonId)
                ->where('is_available', true)
                ->count();
            $totalRows = \DB::table('salon_service')
                ->where('salon_id', $salonId)
                ->count();
            if ($totalRows === 0) {
                return (bool) ($providerHealth['checks']['services'] ?? false);
            }
            // If at least one service is explicitly available, branch has a catalog.
            if ($explicitlyAvailable > 0) {
                return true;
            }
            // If all rows are FALSE (all services manually disabled), no services.
            $allExplicitlyDisabled = \DB::table('salon_service')
                ->where('salon_id', $salonId)
                ->where(function ($q) {
                    $q->where('is_available', false);
                })
                ->count() === $totalRows;
            if ($allExplicitlyDisabled) {
                return false;
            }
            // Mix of NULL/inherit rows — fallback to provider check.
            return (bool) ($providerHealth['checks']['services'] ?? false);
        });

        $subscriptionOk = (bool) ($providerHealth['checks']['subscription'] ?? false);

        $bookingOk = $this->safeCheck('booking', function () use ($salon, $salonId, $providerHealth) {
            $providerBookingOk = (bool) ($providerHealth['checks']['booking'] ?? false);
            $branchAccepting = (bool) ($salon->accepting_online_bookings ?? true);
            if (!$branchAccepting) {
                return false;
            }
            $branchConfig = SalonBookingConfig::where('salon_id', $salonId)->first();
            $branchConfigOk = $branchConfig === null || (
                !empty($branchConfig->booking_policy_override) ||
                !empty($branchConfig->availability_policy_override)
            );
            return $providerBookingOk && $branchConfigOk;
        });

        $websiteOk = $this->safeCheck('website', function () use ($salon, $salonId, $providerHealth) {
            $providerWebsiteOk = (bool) ($providerHealth['checks']['website'] ?? false);
            $hasBranchWebsiteConfig = WebsiteConfiguration::where('salon_id', $salonId)->exists();
            $hasBranchName = (bool) ($salon->name ?? null);
            return $providerWebsiteOk || $hasBranchWebsiteConfig || $hasBranchName;
        });

        $checks = [
            'scheduling'   => $branchSchedulingOk,
            'payments'     => $paymentsOk,
            'staff'        => $staffOk,
            'services'     => $servicesOk,
            'subscription' => $subscriptionOk,
            'booking'      => $bookingOk,
            'website'      => $websiteOk,
            // Backward-compat legacy keys for existing UI consumers
            'provider_healthy'  => $providerHealth['status'] === 'configured',
            'branch_scheduling' => $branchSchedulingOk,
        ];

        $scorable = array_intersect_key($checks, array_flip([
            'scheduling', 'payments', 'staff', 'services', 'subscription', 'booking', 'website'
        ]));

        $isConfigured = !in_array(false, array_values($scorable), true);

        return [
            'status'      => $isConfigured ? 'configured' : 'incomplete',
            'checks'      => $checks,
            'score'       => $this->calculateScore($scorable),
            'salon_id'    => $salonId,
            'provider_id' => $salon->provider_id,
            'computed_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Specialist Assignment-level health (finest granularity).
     */
    public function getAssignmentHealth(string $assignmentId): array
    {
        $assignment = SpecialistAssignment::find($assignmentId);
        if (!$assignment) {
            return [
                'status' => 'incomplete',
                'checks' => [],
                'score'  => 0,
            ];
        }

        $branchHealth = $this->getBranchHealth($assignment->salon_id);

        // --- Scheduling (assignment layer) ---
        $assignmentSchedulingOk = true;
        if (($assignment->schedule_mode ?? 'INHERIT') === 'CUSTOM') {
            $assignmentSchedulingOk = AssignmentSchedule::where('assignment_id', $assignmentId)->exists();
        } else {
            $assignmentSchedulingOk = (bool) ($branchHealth['checks']['scheduling'] ?? false);
        }

        // Staff check at assignment level = active assignment
        $assignmentStaffOk = (bool) ($assignment->active ?? false);

        $checks = [
            'branch_healthy'       => $branchHealth['status'] === 'configured',
            'assignment_scheduling' => $assignmentSchedulingOk,
            'assignment_active'     => $assignmentStaffOk,
        ];

        $isConfigured = !in_array(false, array_values($checks), true);

        return [
            'status'       => $isConfigured ? 'configured' : 'incomplete',
            'checks'       => $checks,
            'score'        => $this->calculateScore($checks),
            'assignment_id' => $assignmentId,
            'salon_id'     => $assignment->salon_id,
            'computed_at'  => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * Simple weighted percent score: passed / total.
     */
    private function calculateScore(array $checks): int
    {
        if (empty($checks)) return 100;

        $passed = array_filter($checks, fn($v) => $v === true);
        return (int) round((count($passed) / count($checks)) * 100);
    }
}
