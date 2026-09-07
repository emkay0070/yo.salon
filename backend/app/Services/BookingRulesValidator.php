<?php

namespace App\Services;

use Carbon\Carbon;

class BookingRulesValidator
{
    private BookingConfigurationService $configService;

    public function __construct(BookingConfigurationService $configService)
    {
        $this->configService = $configService;
    }

    /**
     * Validate a booking request against all rules
     */
    public function validateBookingRequest(array $data): array
    {
        $errors = [];
        $warnings = [];

        $serviceId = $data['service_id'];
        $salonId = $data['salon_id'];
        $bookingDate = Carbon::parse($data['date']);
        $bookingTime = $data['time'] ?? null;

        // Check advance booking limit
        $advanceBookingError = $this->validateAdvanceBooking($serviceId, $salonId, $bookingDate);
        if ($advanceBookingError) {
            $errors[] = $advanceBookingError;
        }

        // Check minimum notice period
        $noticeError = $this->validateMinimumNotice($serviceId, $salonId, $bookingDate, $bookingTime);
        if ($noticeError) {
            $errors[] = $noticeError;
        }

        // Check if same-day booking is allowed
        $sameDayWarning = $this->validateSameDayBooking($serviceId, $salonId, $bookingDate);
        if ($sameDayWarning) {
            $warnings[] = $sameDayWarning;
        }

        // Check blackout dates
        $blackoutError = $this->validateBlackoutDates($serviceId, $salonId, $bookingDate);
        if ($blackoutError) {
            $errors[] = $blackoutError;
        }

        // Check customer booking limits
        if (isset($data['customer_id'])) {
            $limitError = $this->validateCustomerBookingLimit($serviceId, $salonId, $data['customer_id'], $bookingDate);
            if ($limitError) {
                $errors[] = $limitError;
            }
        }

        // Check specialist requirements
        if (isset($data['specialist_id'])) {
            $specialistError = $this->validateSpecialistAssignment($serviceId, $salonId, $data['specialist_id']);
            if ($specialistError) {
                $errors[] = $specialistError;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Validate advance booking limit
     */
    protected function validateAdvanceBooking(string $serviceId, string $salonId, Carbon $bookingDate): ?string
    {
        $maxDays = $this->configService->getMaxAdvanceBookingDays($serviceId, $salonId);
        $maxDate = Carbon::now()->addDays($maxDays);

        if ($bookingDate->gt($maxDate)) {
            return "Bookings can only be made up to {$maxDays} days in advance. Please choose a date before {$maxDate->format('F j, Y')}.";
        }

        return null;
    }

    /**
     * Validate minimum notice period
     */
    protected function validateMinimumNotice(string $serviceId, string $salonId, Carbon $bookingDate, ?string $bookingTime): ?string
    {
        $minHours = $this->configService->getMinNoticeHours($serviceId, $salonId);
        
        if (!$bookingTime) {
            return null; // Can't validate without time
        }

        $bookingDateTime = Carbon::parse("{$bookingDate->format('Y-m-d')} {$bookingTime}");
        $minDateTime = Carbon::now()->addHours($minHours);

        if ($bookingDateTime->lt($minDateTime)) {
            return "Bookings require at least {$minHours} hours notice. Please choose a time after {$minDateTime->format('g:i A')}.";
        }

        return null;
    }

    /**
     * Validate same-day booking allowance
     */
    protected function validateSameDayBooking(string $serviceId, string $salonId, Carbon $bookingDate): ?string
    {
        $availabilityPolicy = $this->configService->getPolicy($serviceId, $salonId, 'availability_policy');
        $allowSameDay = $availabilityPolicy['allow_same_day'] ?? true;

        if (!$allowSameDay && $bookingDate->isToday()) {
            return "Same-day bookings are not allowed. Please choose a future date.";
        }

        return null;
    }

    /**
     * Validate blackout dates
     */
    protected function validateBlackoutDates(string $serviceId, string $salonId, Carbon $bookingDate): ?string
    {
        $availabilityPolicy = $this->configService->getPolicy($serviceId, $salonId, 'availability_policy');
        $blackoutDates = $availabilityPolicy['blackout_dates'] ?? [];

        foreach ($blackoutDates as $blackout) {
            $blackoutDate = Carbon::parse($blackout);
            if ($bookingDate->isSameDay($blackoutDate)) {
                return "This date is not available for booking. Please choose another date.";
            }
        }

        return null;
    }

    /**
     * Validate customer booking limit per day
     */
    protected function validateCustomerBookingLimit(string $serviceId, string $salonId, string $customerId, Carbon $bookingDate): ?string
    {
        $bookingPolicy = $this->configService->getPolicy($serviceId, $salonId, 'booking_policy');
        $maxBookingsPerDay = $bookingPolicy['max_bookings_per_customer_per_day'] ?? 5;

        // Get provider_id from salon
        $salon = \App\Models\Salon::find($salonId);
        $providerId = $salon ? $salon->provider_id : null;

        if (!$providerId) {
            return null;
        }

        $existingBookings = \App\Models\Booking::where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->whereDate('date', $bookingDate)
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();

        if ($existingBookings >= $maxBookingsPerDay) {
            return "You have reached the maximum number of bookings ({$maxBookingsPerDay}) for this date.";
        }

        return null;
    }

    /**
     * Validate specialist assignment
     */
    protected function validateSpecialistAssignment(string $serviceId, string $salonId, string $specialistId): ?string
    {
        $serviceRequirements = $this->configService->getPolicy($serviceId, $salonId, 'service_requirements');
        $allowedRoles = $serviceRequirements['allowed_specialist_roles'] ?? null;

        if ($allowedRoles) {
            $specialist = \App\Models\Specialist::find($specialistId);
            if (!$specialist) {
                return "Specialist not found.";
            }

            // Check if specialist works at this salon
            $salonSpecialist = \DB::table('salon_specialist')
                ->where('specialist_id', $specialistId)
                ->where('salon_id', $salonId)
                ->where('active', true)
                ->first();

            if (!$salonSpecialist) {
                return "This specialist is not available at this salon.";
            }

            // Check if specialist has required role
            $specialistRoles = $salonSpecialist->roles ?? ['staff'];
            $hasRequiredRole = !empty(array_intersect($specialistRoles, $allowedRoles));

            if (!$hasRequiredRole) {
                return "This specialist does not have the required role for this service.";
            }
        }

        return null;
    }

    /**
     * Check if approval is required for this booking
     */
    public function requiresApproval(string $serviceId, string $salonId): bool
    {
        return $this->configService->requiresApproval($serviceId, $salonId);
    }

    /**
     * Check if consultation is required
     */
    public function requiresConsultation(string $serviceId, string $salonId): bool
    {
        return $this->configService->requiresConsultation($serviceId, $salonId);
    }

    /**
     * Check if customer profile is required
     */
    public function requiresCustomerProfile(string $serviceId, string $salonId): bool
    {
        $bookingPolicy = $this->configService->getPolicy($serviceId, $salonId, 'booking_policy');
        return $bookingPolicy['require_customer_profile'] ?? false;
    }

    /**
     * Check if phone number is required
     */
    public function requiresPhoneNumber(string $serviceId, string $salonId): bool
    {
        $bookingPolicy = $this->configService->getPolicy($serviceId, $salonId, 'booking_policy');
        return $bookingPolicy['require_phone_number'] ?? true;
    }

    /**
     * Check if walk-ins are allowed
     */
    public function allowsWalkIns(string $serviceId, string $salonId): bool
    {
        $availabilityPolicy = $this->configService->getPolicy($serviceId, $salonId, 'availability_policy');
        return $availabilityPolicy['allow_walk_ins'] ?? false;
    }

    /**
     * Get booking buffer time in minutes
     */
    public function getBufferMinutes(string $serviceId, string $salonId): int
    {
        $availabilityPolicy = $this->configService->getPolicy($serviceId, $salonId, 'availability_policy');
        return $availabilityPolicy['buffer_minutes'] ?? 15;
    }

    /**
     * Check if repeat bookings are allowed
     */
    public function allowsRepeatBookings(string $serviceId, string $salonId): bool
    {
        $bookingPolicy = $this->configService->getPolicy($serviceId, $salonId, 'booking_policy');
        return $bookingPolicy['allow_repeat_bookings'] ?? true;
    }

    /**
     * Check if double booking is allowed
     */
    public function allowsDoubleBooking(string $serviceId, string $salonId): bool
    {
        $bookingPolicy = $this->configService->getPolicy($serviceId, $salonId, 'booking_policy');
        return $bookingPolicy['allow_double_booking'] ?? false;
    }

    /**
     * Check customer's no-show status for a salon
     */
    public function checkNoShowStatus(string $customerId, string $salonId): array
    {
        // Get provider_id from salon
        $salon = \App\Models\Salon::find($salonId);
        $providerId = $salon ? $salon->provider_id : null;

        if (!$providerId) {
            return [
                'status' => 'good',
                'message' => null,
            ];
        }

        // Get recent no-show bookings for this customer at this provider
        $noShowBookings = \App\Models\Booking::where('customer_id', $customerId)
            ->where('provider_id', $providerId)
            ->where('status', 'no_show')
            ->where('date', '>=', Carbon::now()->subDays(30))
            ->count();

        // Get booking policy from salon config
        $salonConfig = \App\Models\SalonBookingConfig::where('salon_id', $salonId)->first();
        $bookingPolicy = $salonConfig ? $salonConfig->getOverride('booking_policy') : [];
        $maxNoShows = $bookingPolicy['max_no_shows_per_month'] ?? 2;

        if ($noShowBookings >= $maxNoShows) {
            return [
                'status' => 'blocked',
                'message' => "You have {$noShowBookings} no-shows in the past 30 days. Please contact the salon to book.",
                'no_show_count' => $noShowBookings,
            ];
        }

        if ($noShowBookings > 0) {
            return [
                'status' => 'warning',
                'message' => "You have {$noShowBookings} no-show(s) in the past 30 days. Please arrive on time to avoid restrictions.",
                'no_show_count' => $noShowBookings,
            ];
        }

        return [
            'status' => 'good',
            'message' => null,
            'no_show_count' => 0,
        ];
    }
}
