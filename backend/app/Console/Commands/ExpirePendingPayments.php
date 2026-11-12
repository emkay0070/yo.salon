<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\PaymentRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire pending payment requests that have passed their expiry time and release associated booking holds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find payment requests that are expired but not yet processed
        // Include both 'pending' and 'sent' statuses as they represent unpaid payment attempts
        $expiredRequests = PaymentRequest::whereIn('status', ['pending', 'sent'])
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredRequests->isEmpty()) {
            $this->info('No pending payment requests to expire.');
            return Command::SUCCESS;
        }

        $expiredCount = 0;
        $bookingCancelledCount = 0;
        $slotReleasedCount = 0;

        foreach ($expiredRequests as $paymentRequest) {
            DB::transaction(function () use ($paymentRequest, &$expiredCount, &$bookingCancelledCount, &$slotReleasedCount) {
                // Mark payment request as expired
                $paymentRequest->update([
                    'status' => 'expired',
                    'completed_at' => now(),
                ]);
                $expiredCount++;

                // Handle associated booking if exists
                if ($paymentRequest->booking_id) {
                    $booking = Booking::find($paymentRequest->booking_id);

                    if ($booking) {
                        // Only process bookings that are still in payment-pending states
                        // Do not touch bookings that are already confirmed, paid, or cancelled
                        if (in_array($booking->status, ['pending_payment', 'reserved'])) {
                            // Check if payment was already successful (protection against race conditions)
                            if ($booking->payment_status === 'paid') {
                                Log::warning('Skipping booking transition for expired payment - booking already paid', [
                                    'payment_request_id' => $paymentRequest->id,
                                    'booking_id' => $booking->id,
                                ]);
                                return;
                            }

                            // Check if this was a reserved booking with a slot hold before updating status
                            $wasReserved = $booking->status === 'reserved';
                            $hasSlotHold = $booking->slot_locked_until !== null;

                            // Cancel the booking
                            $booking->update([
                                'status' => 'cancelled',
                                'payment_status' => 'expired',
                            ]);
                            $bookingCancelledCount++;

                            // Release slot hold if this was a reserved booking
                            if ($wasReserved && $hasSlotHold) {
                                $booking->update(['slot_locked_until' => null]);
                                $slotReleasedCount++;
                            }
                        }
                    }
                }
            });
        }

        Log::info('Expired payment requests and processed bookings', [
            'payment_requests_expired' => $expiredCount,
            'bookings_cancelled' => $bookingCancelledCount,
            'slot_holds_released' => $slotReleasedCount,
        ]);

        $this->info("Expired {$expiredCount} payment requests, cancelled {$bookingCancelledCount} bookings, released {$slotReleasedCount} slot holds.");

        return Command::SUCCESS;
    }
}
