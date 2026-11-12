<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Provider;
use App\Models\NotificationJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderService
{
    private NotificationService $notificationService;

    // Default reminder lead time in minutes
    private const DEFAULT_LEAD_MINUTES = 15;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Schedule reminders for a booking based on provider/location policy
     */
    public function scheduleRemindersForBooking(Booking $booking): void
    {
        // Only schedule reminders for confirmed bookings with a customer
        if ($booking->status !== 'confirmed' || !$booking->customer_id) {
            return;
        }

        // Get reminder policy from provider/location config
        $reminderRules = $this->getReminderRules($booking->provider_id, $booking->location_id);

        if (empty($reminderRules)) {
            // Use default 15-minute reminder
            $this->scheduleDefaultReminder($booking);
        } else {
            // Schedule based on configured rules
            foreach ($reminderRules as $rule) {
                if ($rule['enabled'] ?? true) {
                    $this->scheduleReminderFromRule($booking, $rule);
                }
            }
        }
    }

    /**
     * Get reminder rules from provider/location configuration
     */
    private function getReminderRules(string $providerId, ?string $locationId): array
    {
        // Check location-level override first
        if ($locationId) {
            $locationConfig = DB::table('location_booking_config')
                ->where('location_id', $locationId)
                ->first();

            if ($locationConfig && isset($locationConfig->booking_policy_override)) {
                $policy = json_decode($locationConfig->booking_policy_override, true);
                if (isset($policy['reminders']) && is_array($policy['reminders'])) {
                    return $policy['reminders'];
                }
            }
        }

        // Fall back to provider-level policy
        $providerConfig = DB::table('provider_booking_config')
            ->where('provider_id', $providerId)
            ->first();

        if ($providerConfig && isset($providerConfig->booking_policy)) {
            $policy = json_decode($providerConfig->booking_policy, true);
            if (isset($policy['reminders']) && is_array($policy['reminders'])) {
                return $policy['reminders'];
            }
        }

        return [];
    }

    /**
     * Schedule the default 15-minute reminder
     */
    private function scheduleDefaultReminder(Booking $booking): void
    {
        $scheduledFor = $this->calculateScheduledTime($booking, self::DEFAULT_LEAD_MINUTES);

        // Check idempotency - don't create duplicate reminders
        $existing = BookingReminder::forBooking($booking->id)
            ->ofType(BookingReminder::TYPE_APPOINTMENT_REMINDER)
            ->where('scheduled_for', $scheduledFor)
            ->first();

        if ($existing) {
            return; // Already scheduled
        }

        BookingReminder::create([
            'booking_id' => $booking->id,
            'recipient_customer_id' => $booking->customer_id,
            'type' => BookingReminder::TYPE_APPOINTMENT_REMINDER,
            'channel' => $this->determineChannel($booking),
            'scheduled_for' => $scheduledFor,
            'status' => BookingReminder::STATUS_PENDING,
            'data' => [
                'lead_minutes' => self::DEFAULT_LEAD_MINUTES,
                'is_default' => true,
            ],
        ]);

        Log::info('Default reminder scheduled', [
            'booking_id' => $booking->id,
            'scheduled_for' => $scheduledFor,
            'lead_minutes' => self::DEFAULT_LEAD_MINUTES,
        ]);
    }

    /**
     * Schedule reminder from a configured rule
     */
    private function scheduleReminderFromRule(Booking $booking, array $rule): void
    {
        $leadMinutes = $rule['lead_minutes'] ?? self::DEFAULT_LEAD_MINUTES;
        $channel = $rule['channel'] ?? $this->determineChannel($booking);
        $scheduledFor = $this->calculateScheduledTime($booking, $leadMinutes);

        // Check idempotency
        $existing = BookingReminder::forBooking($booking->id)
            ->ofType($rule['type'] ?? BookingReminder::TYPE_APPOINTMENT_REMINDER)
            ->where('scheduled_for', $scheduledFor)
            ->first();

        if ($existing) {
            return; // Already scheduled
        }

        BookingReminder::create([
            'booking_id' => $booking->id,
            'reminder_rule_id' => $rule['id'] ?? null,
            'recipient_customer_id' => $booking->customer_id,
            'type' => $rule['type'] ?? BookingReminder::TYPE_APPOINTMENT_REMINDER,
            'channel' => $channel,
            'scheduled_for' => $scheduledFor,
            'status' => BookingReminder::STATUS_PENDING,
            'data' => [
                'lead_minutes' => $leadMinutes,
                'rule_label' => $rule['label'] ?? null,
            ],
        ]);

        Log::info('Reminder scheduled from rule', [
            'booking_id' => $booking->id,
            'scheduled_for' => $scheduledFor,
            'lead_minutes' => $leadMinutes,
        ]);
    }

    /**
     * Calculate scheduled time using booking's timezone context
     */
    private function calculateScheduledTime(Booking $booking, int $leadMinutes): Carbon
    {
        // Get timezone from location or provider
        $location = $booking->location_id ? Location::find($booking->location_id) : null;
        $provider = Provider::find($booking->provider_id);
        $timezone = $location?->timezone ?? $provider?->timezone ?? 'Africa/Kampala';

        // Normalize time to HH:MM format (strip seconds if present)
        $normalizedTime = substr($booking->time, 0, 5);

        // Extract date portion (strip time if present in date field)
        $dateOnly = substr($booking->date, 0, 10);

        // Parse booking date/time in its timezone
        $bookingTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$dateOnly} {$normalizedTime}",
            $timezone
        );

        // Subtract lead minutes
        return $bookingTime->subMinutes($leadMinutes);
    }

    /**
     * Determine delivery channel based on customer contact info
     */
    private function determineChannel(Booking $booking): string
    {
        $customer = $booking->customer;

        // Prefer SMS if phone is available
        if ($customer && $customer->phone) {
            return BookingReminder::CHANNEL_SMS;
        }

        // Fall back to email
        if ($customer && $customer->email) {
            return BookingReminder::CHANNEL_EMAIL;
        }

        // Default to in-app
        return BookingReminder::CHANNEL_IN_APP;
    }

    /**
     * Cancel all pending reminders for a booking
     */
    public function cancelRemindersForBooking(string $bookingId): void
    {
        BookingReminder::forBooking($bookingId)
            ->where('status', BookingReminder::STATUS_PENDING)
            ->get()
            ->each(function ($reminder) {
                $reminder->markAsCancelled();

                // Also cancel associated notification job if exists
                if ($reminder->notification_job_id) {
                    $job = NotificationJob::find($reminder->notification_job_id);
                    if ($job && $job->status === 'pending') {
                        $job->delete();
                    }
                }
            });

        Log::info('Reminders cancelled for booking', ['booking_id' => $bookingId]);
    }

    /**
     * Process due reminders and create notification jobs
     */
    public function processDueReminders(int $limit = 50): array
    {
        $reminders = BookingReminder::due()
            ->with(['booking.customer', 'booking.location', 'booking.provider'])
            ->limit($limit)
            ->get();

        $results = [
            'scheduled' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($reminders as $reminder) {
            try {
                // Check if booking is still valid (not cancelled)
                if ($reminder->booking->status === 'cancelled') {
                    $reminder->markAsCancelled();
                    $results['skipped']++;
                    continue;
                }

                // Create notification job
                $notificationJob = $this->createNotificationJob($reminder);

                if ($notificationJob) {
                    $reminder->markAsScheduled($notificationJob->id);
                    $results['scheduled']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $reminder->markAsFailed($e->getMessage());
                $results['failed']++;
                Log::error('Failed to process reminder', [
                    'reminder_id' => $reminder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Create notification job for a reminder
     */
    private function createNotificationJob(BookingReminder $reminder): ?NotificationJob
    {
        $booking = $reminder->booking;
        $customer = $booking->customer;

        if (!$customer) {
            return null;
        }

        $message = $this->buildReminderMessage($booking, $customer);

        if ($reminder->channel === BookingReminder::CHANNEL_SMS && $customer->phone) {
            // Note: provider_id not passed to skip credit check for operational reminders
            return $this->notificationService->queueSms(
                $customer->phone,
                $message,
                ['reminder_id' => $reminder->id],
                'booking_reminder',
                $booking->id
            );
        } elseif ($reminder->channel === BookingReminder::CHANNEL_EMAIL && $customer->email) {
            return $this->notificationService->queueEmail(
                $customer->email,
                'Appointment Reminder',
                $message,
                ['reminder_id' => $reminder->id],
                'booking_reminder',
                $booking->id
            );
        }

        return null;
    }

    /**
     * Build reminder message
     */
    private function buildReminderMessage(Booking $booking, Customer $customer): string
    {
        $providerName = $booking->provider?->name ?? 'Your appointment';
        $serviceNames = $booking->services()->pluck('name')->join(', ');
        $date = $booking->date;
        $time = $booking->time;

        return "Hi {$customer->name}, your appointment for {$serviceNames} at {$providerName} is coming up on {$date} at {$time}.";
    }

    /**
     * Handle booking rescheduling - cancel old reminders and schedule new ones
     */
    public function handleBookingRescheduled(string $bookingId): void
    {
        // Cancel pending reminders
        $this->cancelRemindersForBooking($bookingId);

        // Schedule new reminders
        $booking = Booking::find($bookingId);
        if ($booking) {
            $this->scheduleRemindersForBooking($booking);
        }

        Log::info('Reminders rescheduled for booking', ['booking_id' => $bookingId]);
    }
}
