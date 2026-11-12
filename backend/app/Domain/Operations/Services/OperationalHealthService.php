<?php

namespace App\Domain\Operations\Services;

use App\Models\Provider;

use App\Models\Specialist;
use App\Models\SpecialistAssignment;
use App\Models\ProviderSchedule;
use App\Models\LocationSchedule;
use App\Models\AssignmentSchedule;
use App\Models\Service;
use App\Models\PaymentMethod;
use App\Models\ProviderBookingConfig;
use App\Models\LocationBookingConfig;
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
                ->where('is_active', true)
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
            // WebsiteConfiguration uses polymorphic configurable_id/configurable_type
            $websiteConfigExists = WebsiteConfiguration::where('configurable_id', $providerId)
                ->where('configurable_type', Provider::class)
                ->exists();
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
     * Branch-level (Location) health.
     */
    public function getBranchHealth(string $locationId): array
    {
        $location = \App\Models\Location::find($locationId);
        if (!$location) {
            return [
                'status'      => 'incomplete',
                'checks'      => [],
                'score'       => 0,
                'location_id' => $locationId,
                'provider_id' => null,
                'computed_at' => CarbonImmutable::now()->toIso8601String(),
            ];
        }

        $providerHealth = $this->getProviderHealth($location->provider_id);

        $branchSchedulingOk = $this->safeCheck('scheduling', function () use ($location, $providerHealth) {
            if (($location->schedule_mode ?? 'INHERIT') === 'CUSTOM') {
                return LocationSchedule::where('location_id', $location->id)->exists();
            }
            return (bool) ($providerHealth['checks']['scheduling'] ?? false);
        });

        $paymentsOk = (bool) ($providerHealth['checks']['payments'] ?? false);

        $staffOk = $this->safeCheck('staff', function () use ($location) {
            return SpecialistAssignment::where('provider_id', $location->provider_id)
                ->where('location_id', $location->id)
                ->where('status', 'ACTIVE')
                ->exists();
        });

        $servicesOk = $this->safeCheck('services', function () use ($locationId, $providerHealth) {
            $explicitlyAvailable = \DB::table('location_service')
                ->where('location_id', $locationId)
                ->where('is_available', true)
                ->count();
            $totalRows = \DB::table('location_service')
                ->where('location_id', $locationId)
                ->count();
            if ($totalRows === 0) {
                return (bool) ($providerHealth['checks']['services'] ?? false);
            }
            if ($explicitlyAvailable > 0) {
                return true;
            }
            $allExplicitlyDisabled = \DB::table('location_service')
                ->where('location_id', $locationId)
                ->where('is_available', false)
                ->count() === $totalRows;
            if ($allExplicitlyDisabled) {
                return false;
            }
            return (bool) ($providerHealth['checks']['services'] ?? false);
        });

        $subscriptionOk = (bool) ($providerHealth['checks']['subscription'] ?? false);

        $bookingOk = $this->safeCheck('booking', function () use ($location, $locationId, $providerHealth) {
            $providerBookingOk = (bool) ($providerHealth['checks']['booking'] ?? false);
            $branchAccepting   = (bool) ($location->accepting_online_bookings ?? true);
            if (!$branchAccepting) return false;
            $branchConfig = LocationBookingConfig::where('location_id', $location->id)->first();
            $branchConfigOk = $branchConfig === null || (
                !empty($branchConfig->booking_policy_override) ||
                !empty($branchConfig->availability_policy_override)
            );
            return $providerBookingOk && $branchConfigOk;
        });

        $websiteOk = $this->safeCheck('website', function () use ($location, $locationId, $providerHealth) {
            $providerWebsiteOk = (bool) ($providerHealth['checks']['website'] ?? false);
            $hasBranchWebsiteConfig = WebsiteConfiguration::where('configurable_id', $locationId)
                ->where('configurable_type', \App\Models\Location::class)
                ->exists();
            $hasBranchName = (bool) ($location->name ?? null);
            return $providerWebsiteOk || $hasBranchWebsiteConfig || $hasBranchName;
        });

        $checks = [
            'scheduling'        => $branchSchedulingOk,
            'payments'          => $paymentsOk,
            'staff'             => $staffOk,
            'services'          => $servicesOk,
            'subscription'      => $subscriptionOk,
            'booking'           => $bookingOk,
            'website'           => $websiteOk,
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
            'location_id' => $locationId,
            'provider_id' => $location->provider_id,
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

        $locationId  = $assignment->location_id;
        $branchHealth = $locationId ? $this->getBranchHealth($locationId) : ['status' => 'incomplete', 'checks' => []];

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
            'branch_healthy'        => $branchHealth['status'] === 'configured',
            'assignment_scheduling' => $assignmentSchedulingOk,
            'assignment_active'     => $assignmentStaffOk,
        ];

        $isConfigured = !in_array(false, array_values($checks), true);

        return [
            'status'        => $isConfigured ? 'configured' : 'incomplete',
            'checks'        => $checks,
            'score'         => $this->calculateScore($checks),
            'assignment_id' => $assignmentId,
            'location_id'   => $locationId,
            'computed_at'   => CarbonImmutable::now()->toIso8601String(),
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
