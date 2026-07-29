<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Salon;
use App\Services\BrandEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingReminder extends Notification implements ShouldQueue
{
    use Queueable;

    protected $booking;
    protected $salon;
    protected $brandEmailService;

    public function __construct(Booking $booking, Salon $salon)
    {
        $this->booking = $booking;
        $this->salon = $salon;
        $this->brandEmailService = new BrandEmailService();
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $styles = $this->brandEmailService->generateEmailStyles($this->salon);
        
        $content = $this->brandEmailService->getCard(
            sprintf(
                '<h2 style="color: %s; margin-top: 0;">Appointment Reminder</h2>' .
                '<p style="color: %s;">This is a friendly reminder about your upcoming appointment at %s.</p>' .
                '<table style="width: 100%%; margin: 20px 0;">' .
                '<tr><td style="padding: 10px; border-bottom: 1px solid %s;"><strong>Service:</strong></td><td style="padding: 10px; border-bottom: 1px solid %s;">%s</td></tr>' .
                '<tr><td style="padding: 10px; border-bottom: 1px solid %s;"><strong>Date:</strong></td><td style="padding: 10px; border-bottom: 1px solid %s;">%s</td></tr>' .
                '<tr><td style="padding: 10px; border-bottom: 1px solid %s;"><strong>Time:</strong></td><td style="padding: 10px; border-bottom: 1px solid %s;">%s</td></tr>' .
                '<tr><td style="padding: 10px;"><strong>Stylist:</strong></td><td style="padding: 10px;">%s</td></tr>' .
                '</table>' .
                '<p style="color: %s;">%s</p>',
                $styles['primary_color'],
                $styles['text_primary'],
                $this->salon->name,
                $styles['border_color'],
                $styles['border_color'],
                $this->booking->service->name,
                $styles['border_color'],
                $styles['border_color'],
                $this->booking->date,
                $styles['border_color'],
                $styles['border_color'],
                $this->booking->time,
                $this->booking->staff->name,
                $styles['text_secondary'],
                'Please arrive 10 minutes before your appointment time. If you need to reschedule, please contact us.'
            ),
            $this->salon
        );

        $button = $this->brandEmailService->getButton(
            'Reschedule Appointment',
            url('/portal/bookings'),
            $this->salon
        );

        $fullContent = $content . '<div style="text-align: center; margin: 20px 0;">' . $button . '</div>';

        $html = $this->brandEmailService->getEmailTemplate($fullContent, $this->salon);

        return (new MailMessage)
            ->subject("Appointment Reminder - {$this->salon->name}")
            ->html($html);
    }
}
