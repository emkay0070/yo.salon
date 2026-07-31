<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $booking;
    public $transaction;
    public $customerId;
    public $salonId;
    public $amount;

    public function __construct(Booking $booking, Transaction $transaction, $customerId)
    {
        $this->booking = $booking;
        $this->transaction = $transaction;
        $this->customerId = $customerId;
        $this->salonId = $booking->salon_id;
        $this->amount = $transaction->gross_amount;
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel('salon.' . $this->salonId),
            new PrivateChannel('customer.' . $this->customerId),
        ];
    }

    public function broadcastAs()
    {
        return 'payment.confirmed';
    }

    public function broadcastWith()
    {
        return [
            'booking_id' => $this->booking->id,
            'transaction_id' => $this->transaction->id,
            'amount' => $this->amount,
            'customer_id' => $this->customerId,
            'salon_id' => $this->salonId,
            'paid_at' => $this->transaction->paid_at->toIso8601String(),
        ];
    }

    public function broadcastWhen()
    {
        return config('reverb.apps.0.key') && config('reverb.apps.0.app_id');
    }
}
