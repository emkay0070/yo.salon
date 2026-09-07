<?php

namespace App\Services;

use App\Models\ProviderBookingConfig;
use App\Models\SalonBookingConfig;
use App\Models\ServiceBookingConfig;
use App\Models\Provider;
use App\Models\Salon;
use App\Models\Service;

class BookingConfigurationService
{
    /**
     * Get the effective booking configuration for a given context
     * Merges policies in order: Service > Salon > Provider
     */
    public function getConfiguration(string $serviceId, string $salonId): array
    {
        $service = Service::find($serviceId);
        $salon = Salon::find($salonId);

        if (!$service || !$salon) {
            throw new \InvalidArgumentException('Invalid service or salon');
        }

        $providerId = $salon->provider_id;

        // Get all configuration levels
        $providerConfig = ProviderBookingConfig::where('provider_id', $providerId)->first();
        $salonConfig = SalonBookingConfig::where('salon_id', $salonId)->first();
        $serviceConfig = ServiceBookingConfig::where('service_id', $serviceId)->first();

        // Get defaults from model or create empty config objects
        $providerDefaults = $providerConfig ? $providerConfig->getDefaults() : $this->getProviderDefaults();
        $salonOverrides = $salonConfig ? $salonConfig->getOverrides() : [];
        $serviceOverrides = $serviceConfig ? $serviceConfig->getOverrides() : [];
        $serviceRequirements = $serviceConfig ? $serviceConfig->getServiceRequirements() : [];

        // Merge policies with precedence: Service > Salon > Provider
        return [
            'availability_policy' => $this->mergePolicy(
                $providerDefaults['availability_policy'] ?? [],
                $salonOverrides['availability_policy'] ?? [],
                $serviceOverrides['availability_policy'] ?? []
            ),
            'assignment_policy' => $this->mergePolicy(
                $providerDefaults['assignment_policy'] ?? [],
                $salonOverrides['assignment_policy'] ?? [],
                $serviceOverrides['assignment_policy'] ?? []
            ),
            'payment_policy' => $this->mergePolicy(
                $providerDefaults['payment_policy'] ?? [],
                $salonOverrides['payment_policy'] ?? [],
                $serviceOverrides['payment_policy'] ?? []
            ),
            'cancellation_policy' => $this->mergePolicy(
                $providerDefaults['cancellation_policy'] ?? [],
                $salonOverrides['cancellation_policy'] ?? [],
                $serviceOverrides['cancellation_policy'] ?? []
            ),
            'approval_policy' => $this->mergePolicy(
                $providerDefaults['approval_policy'] ?? [],
                $salonOverrides['approval_policy'] ?? [],
                $serviceOverrides['approval_policy'] ?? []
            ),
            'booking_policy' => $this->mergePolicy(
                $providerDefaults['booking_policy'] ?? [],
                $salonOverrides['booking_policy'] ?? [],
                $serviceOverrides['booking_policy'] ?? []
            ),
            'service_requirements' => $serviceRequirements,
        ];
    }

    /**
     * Get provider default policies
     */
    protected function getProviderDefaults(): array
    {
        return [
            'availability_policy' => [
                'max_advance_booking_days' => 30,
                'min_notice_hours' => 2,
                'allow_walk_ins' => false,
                'allow_same_day' => true,
                'buffer_minutes' => 15,
                'max_bookings_per_day' => null,
                'blackout_dates' => [],
            ],
            'assignment_policy' => [
                'strategy' => 'customer_choice',
                'auto_assign_criteria' => 'availability',
                'show_specialist_photos' => true,
                'show_specialist_ratings' => true,
                'allow_specialist_change' => true,
            ],
            'payment_policy' => [
                'strategy' => 'no_payment',
                'deposit' => [
                    'type' => 'percentage',
                    'amount' => 20,
                    'refundable' => true,
                    'refund_deadline_hours' => 24,
                    'transferable' => false,
                ],
                'membership_included' => false,
                'consultation_deposit' => [
                    'enabled' => false,
                    'amount' => 10000,
                    'deductible' => true,
                ],
            ],
            'cancellation_policy' => [
                'free_cancellation_hours' => 24,
                'late_cancellation_fee' => 'deposit',
                'late_cancellation_amount' => null,
                'no_show_policy' => [
                    'first_offense' => 'warning',
                    'second_offense' => 'deposit_required',
                    'third_offense' => 'full_payment_required',
                ],
            ],
            'approval_policy' => [
                'require_approval' => false,
                'approval_type' => 'instant',
                'consultation_required' => false,
                'consultation_duration_minutes' => 15,
                'auto_approve_after_hours' => null,
            ],
            'booking_policy' => [
                'max_bookings_per_customer_per_day' => 5,
                'allow_repeat_bookings' => true,
                'allow_double_booking' => false,
                'waiting_list_enabled' => false,
                'require_customer_profile' => false,
                'require_phone_number' => true,
            ],
        ];
    }

    /**
     * Merge policies with precedence: service > salon > provider
     * Later policies override earlier ones
     */
    protected function mergePolicy(array $provider, array $salon, array $service): array
    {
        return array_merge($provider, $salon, $service);
    }

    /**
     * Get a specific policy value
     */
    public function getPolicy(string $serviceId, string $salonId, string $policy, string $key = null): mixed
    {
        $config = $this->getConfiguration($serviceId, $salonId);
        
        if ($key) {
            return $config[$policy][$key] ?? null;
        }
        
        return $config[$policy] ?? null;
    }

    /**
     * Check if deposit is required
     */
    public function requiresDeposit(string $serviceId, string $salonId): bool
    {
        $paymentPolicy = $this->getPolicy($serviceId, $salonId, 'payment_policy');
        return $paymentPolicy['strategy'] === 'deposit' || 
               ($paymentPolicy['strategy'] === 'full_payment' && $paymentPolicy['deposit']['amount'] > 0);
    }

    /**
     * Get deposit amount
     */
    public function getDepositAmount(string $serviceId, string $salonId, float $servicePrice): float
    {
        $paymentPolicy = $this->getPolicy($serviceId, $salonId, 'payment_policy');
        $depositConfig = $paymentPolicy['deposit'] ?? [];

        if ($depositConfig['type'] === 'percentage') {
            return $servicePrice * ($depositConfig['amount'] / 100);
        }

        return $depositConfig['amount'] ?? 0;
    }

    /**
     * Check if approval is required
     */
    public function requiresApproval(string $serviceId, string $salonId): bool
    {
        $approvalPolicy = $this->getPolicy($serviceId, $salonId, 'approval_policy');
        return $approvalPolicy['require_approval'] ?? false;
    }

    /**
     * Get specialist assignment strategy
     */
    public function getSpecialistAssignmentStrategy(string $serviceId, string $salonId): string
    {
        $assignmentPolicy = $this->getPolicy($serviceId, $salonId, 'assignment_policy');
        return $assignmentPolicy['strategy'] ?? 'customer_choice';
    }

    /**
     * Check if customer can choose specialist
     */
    public function allowSpecialistChoice(string $serviceId, string $salonId): bool
    {
        $strategy = $this->getSpecialistAssignmentStrategy($serviceId, $salonId);
        return $strategy === 'customer_choice';
    }

    /**
     * Get maximum advance booking days
     */
    public function getMaxAdvanceBookingDays(string $serviceId, string $salonId): int
    {
        return $this->getPolicy($serviceId, $salonId, 'availability_policy', 'max_advance_booking_days') ?? 30;
    }

    /**
     * Get minimum notice hours
     */
    public function getMinNoticeHours(string $serviceId, string $salonId): int
    {
        return $this->getPolicy($serviceId, $salonId, 'availability_policy', 'min_notice_hours') ?? 2;
    }

    /**
     * Get free cancellation hours
     */
    public function getFreeCancellationHours(string $serviceId, string $salonId): int
    {
        return $this->getPolicy($serviceId, $salonId, 'cancellation_policy', 'free_cancellation_hours') ?? 24;
    }

    /**
     * Check if consultation is required
     */
    public function requiresConsultation(string $serviceId, string $salonId): bool
    {
        $serviceRequirements = $this->getPolicy($serviceId, $salonId, 'service_requirements');
        return $serviceRequirements['require_consultation'] ?? false;
    }

    /**
     * Get consultation duration
     */
    public function getConsultationDuration(string $serviceId, string $salonId): int
    {
        $serviceRequirements = $this->getPolicy($serviceId, $salonId, 'service_requirements');
        return $serviceRequirements['consultation_duration_minutes'] ?? 15;
    }

    /**
     * Create or update provider booking configuration
     */
    public function updateProviderConfig(string $providerId, array $policies): ProviderBookingConfig
    {
        $config = ProviderBookingConfig::updateOrCreate(
            ['provider_id' => $providerId],
            $policies
        );

        return $config;
    }

    /**
     * Create or update salon booking configuration
     */
    public function updateSalonConfig(string $salonId, array $overrides): SalonBookingConfig
    {
        $config = SalonBookingConfig::updateOrCreate(
            ['salon_id' => $salonId],
            $overrides
        );

        return $config;
    }

    /**
     * Create or update service booking configuration
     */
    public function updateServiceConfig(string $serviceId, array $overrides, array $requirements = []): ServiceBookingConfig
    {
        $config = ServiceBookingConfig::updateOrCreate(
            ['service_id' => $serviceId],
            array_merge($overrides, ['service_requirements' => $requirements])
        );

        return $config;
    }

    /**
     * Reset salon configuration to provider defaults
     */
    public function resetSalonConfig(string $salonId): void
    {
        SalonBookingConfig::where('salon_id', $salonId)->delete();
    }

    /**
     * Reset service configuration to salon/provider defaults
     */
    public function resetServiceConfig(string $serviceId): void
    {
        ServiceBookingConfig::where('service_id', $serviceId)->delete();
    }
}
