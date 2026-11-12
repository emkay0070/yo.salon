<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Provider;
use App\Services\BrandEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification implements ShouldQueue
{
    use Queueable;

    protected $customer;
    protected $provider;
    protected $brandEmailService;

    public function __construct(Customer $customer, Provider $provider)
    {
        $this->customer = $customer;
        $this->provider = $provider;
        $this->brandEmailService = new BrandEmailService();
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $styles = $this->brandEmailService->generateEmailStyles($this->provider);
        
        $content = $this->brandEmailService->getCard(
            sprintf(
                '<h2 style="color: %s; margin-top: 0;">Welcome to %s!</h2>' .
                '<p style="color: %s;">Thank you for joining us at %s. We\'re excited to have you as part of our community.</p>' .
                '<p style="color: %s;">With your new account, you can:</p>' .
                '<ul style="color: %s; padding-left: 20px;">' .
                '<li style="margin: 10px 0;">Book appointments online</li>' .
                '<li style="margin: 10px 0;">View your booking history</li>' .
                '<li style="margin: 10px 0;">Manage your profile</li>' .
                '<li style="margin: 10px 0;">Earn loyalty rewards</li>' .
                '</ul>' .
                '<p style="color: %s;">Get started by booking your first appointment today!</p>',
                $styles['primary_color'],
                $this->provider->display_name,
                $styles['text_primary'],
                $this->provider->display_name,
                $styles['text_secondary'],
                $styles['text_primary'],
                $styles['text_secondary']
            ),
            $this->provider
        );

        $button = $this->brandEmailService->getButton(
            'Book Your First Appointment',
            url('/portal/bookings/new'),
            $this->provider
        );

        $fullContent = $content . '<div style="text-align: center; margin: 20px 0;">' . $button . '</div>';

        $html = $this->brandEmailService->getEmailTemplate($fullContent, $this->provider);

        return (new MailMessage)
            ->subject("Welcome to {$this->provider->display_name}!")
            ->html($html);
    }
}
