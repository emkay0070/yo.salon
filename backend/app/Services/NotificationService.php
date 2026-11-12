<?php

namespace App\Services;

use App\Models\NotificationJob;
use App\Services\SmsProvider;
use App\Services\EmailProvider;
use App\Services\CapabilityResolver;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private SmsProvider $smsProvider;
    private EmailProvider $emailProvider;
    private CapabilityResolver $capabilityResolver;

    public function __construct(SmsProvider $smsProvider, EmailProvider $emailProvider, CapabilityResolver $capabilityResolver)
    {
        $this->smsProvider = $smsProvider;
        $this->emailProvider = $emailProvider;
        $this->capabilityResolver = $capabilityResolver;
    }
    /**
     * Queue an SMS notification
     */
    public function queueSms(string $phone, string $message, array $data = [], ?string $relatedType = null, ?string $relatedId = null, ?string $providerId = null): NotificationJob
    {
        // Check SMS credits if provider ID is provided
        if ($providerId) {
            $availableCredits = $this->capabilityResolver->credits($providerId, 'SMS');
            
            if ($availableCredits === 0) {
                Log::warning('SMS credits exhausted', [
                    'provider_id' => $providerId,
                    'phone' => $phone,
                    'related_type' => $relatedType,
                    'related_id' => $relatedId,
                ]);
                
                // Return a failed job immediately
                return NotificationJob::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'type' => 'sms',
                    'recipient' => $phone,
                    'message' => $message,
                    'data' => $data,
                    'status' => 'failed',
                    'error_message' => 'SMS credits exhausted',
                    'related_type' => $relatedType,
                    'related_id' => $relatedId,
                ]);
            }
        }

        return NotificationJob::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'sms',
            'recipient' => $phone,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    /**
     * Queue an email notification
     */
    public function queueEmail(string $email, string $subject, string $message, array $data = [], ?string $relatedType = null, ?string $relatedId = null): NotificationJob
    {
        return NotificationJob::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'email',
            'recipient' => $email,
            'subject' => $subject,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'related_type' => $relatedType,
            'related_id' => $relatedId,
        ]);
    }

    /**
     * Send a booking confirmation SMS
     */
    public function sendBookingConfirmationSms(string $phone, string $customerName, string $providerName, string $date, string $time): NotificationJob
    {
        $message = "Hi {$customerName}, your appointment with {$providerName} is confirmed for {$date} at {$time}. Reply CANCEL to reschedule.";
        
        return $this->queueSms($phone, $message, [
            'customer_name' => $customerName,
            'provider_name' => $providerName,
            'date' => $date,
            'time' => $time,
        ], 'booking', null);
    }

    /**
     * Send a booking confirmation email
     */
    public function sendBookingConfirmationEmail(string $email, string $customerName, string $providerName, string $date, string $time): NotificationJob
    {
        $subject = "Booking Confirmation - {$providerName}";
        $message = "Dear {$customerName},\n\nYour appointment with {$providerName} is confirmed for {$date} at {$time}.\n\nPlease arrive 10 minutes early.\n\nThank you!";
        
        return $this->queueEmail($email, $subject, $message, [
            'customer_name' => $customerName,
            'provider_name' => $providerName,
            'date' => $date,
            'time' => $time,
        ], 'booking', null);
    }

    /**
     * Send a portal invitation email
     */
    public function sendPortalInvitationEmail(string $email, string $customerName, string $providerName, string $inviteUrl): NotificationJob
    {
        $subject = "You're invited to join {$providerName}'s portal";
        $message = "Dear {$customerName},\n\nYou've been invited to join {$providerName}'s customer portal.\n\nClick here to get started: {$inviteUrl}\n\nThis link expires in 7 days.";
        
        return $this->queueEmail($email, $subject, $message, [
            'customer_name' => $customerName,
            'provider_name' => $providerName,
            'invite_url' => $inviteUrl,
        ], 'invitation', null);
    }

    /**
     * Process pending notification jobs
     */
    public function processPendingJobs(int $limit = 50): array
    {
        $jobs = NotificationJob::pending()
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        $results = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($jobs as $job) {
            try {
                if ($job->type === 'sms') {
                    $this->sendSms($job);
                    $results['sent']++;
                } elseif ($job->type === 'email') {
                    $this->sendEmail($job);
                    $results['sent']++;
                } else {
                    $results['skipped']++;
                    Log::warning("Unknown notification type: {$job->type}");
                }
            } catch (\Exception $e) {
                $job->markAsFailed($e->getMessage());
                $results['failed']++;
                Log::error("Failed to send notification", [
                    'job_id' => $job->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Send SMS
     */
    private function sendSms(NotificationJob $job): void
    {
        // Check SMS credits before sending
        $providerId = $job->data['provider_id'] ?? null;
        if ($providerId) {
            $availableCredits = $this->capabilityResolver->credits($providerId, 'SMS');
            
            if ($availableCredits === 0) {
                $job->markAsFailed('SMS credits exhausted');
                return;
            }
        }

        $phone = $this->smsProvider->formatPhone($job->recipient);
        $sent = $this->smsProvider->send($phone, $job->message);
        
        if ($sent) {
            $job->markAsSent();
            
            // Deduct SMS credit if provider ID is provided
            if ($providerId) {
                // TODO: Implement credit deduction via UsageEvent
                // UsageEvent::record($providerId, 'SMS', 1, ['job_id' => $job->id]);
            }
        } else {
            $job->markAsFailed('SMS provider failed to send');
        }
    }

    /**
     * Send email
     */
    private function sendEmail(NotificationJob $job): void
    {
        $sent = $this->emailProvider->send(
            $job->recipient,
            $job->subject ?? 'Notification',
            $job->message,
            $job->data ?? []
        );
        
        if ($sent) {
            $job->markAsSent();
        } else {
            $job->markAsFailed('Email provider failed to send');
        }
    }

    /**
     * Retry failed jobs
     */
    public function retryFailedJobs(int $limit = 20): array
    {
        $jobs = NotificationJob::failed()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $results = [
            'retried' => 0,
            'failed' => 0,
        ];

        foreach ($jobs as $job) {
            try {
                $job->update(['status' => 'pending', 'error_message' => null]);
                $results['retried']++;
            } catch (\Exception $e) {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Notify provider owner about missing services
     * This is called when customers try to book but the provider has no services configured
     */
    public function notifyProviderMissingServices(string $providerId, string $providerName, ?string $ownerEmail = null, ?string $ownerPhone = null): void
    {
        $subject = "Action Required: Add Services to Start Receiving Bookings";
        $message = "Dear Salon Owner,\n\n" .
            "Customers are trying to book appointments at {$providerName}, but no services have been added yet.\n\n" .
            "To start receiving bookings, please:\n" .
            "1. Log in to your portal\n" .
            "2. Go to Services\n" .
            "3. Add your treatment offerings\n\n" .
            "Once services are added, customers will be able to book appointments online.\n\n" .
            "Thank you,\n" .
            "Yo.Salon Team";

        // Send email if owner email is available
        if ($ownerEmail) {
            $this->queueEmail($ownerEmail, $subject, $message, [
                'provider_name' => $providerName,
                'provider_id' => $providerId,
            ], 'provider', $providerId);
        }

        // Send SMS if owner phone is available
        if ($ownerPhone) {
            $smsMessage = "Yo.Salon: Customers are trying to book at {$providerName} but no services are added. Please add services in your portal to start receiving bookings.";
            $this->queueSms($ownerPhone, $smsMessage, [
                'provider_name' => $providerName,
                'provider_id' => $providerId,
            ], 'provider', $providerId, $providerId);
        }

        Log::info('Provider notified about missing services', [
            'provider_id' => $providerId,
            'provider_name' => $providerName,
            'has_email' => !empty($ownerEmail),
            'has_phone' => !empty($ownerPhone),
        ]);
    }
}
