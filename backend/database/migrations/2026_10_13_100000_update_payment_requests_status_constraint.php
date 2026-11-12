<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update the check constraint to include 'sent' and 'paid' statuses
        // These are actively used in BookingPaymentService and PaymentRequestController
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payment_requests DROP CONSTRAINT check_payment_requests_status");
            DB::statement("ALTER TABLE payment_requests ADD CONSTRAINT check_payment_requests_status CHECK (status IN ('pending', 'sent', 'processing', 'successful', 'paid', 'failed', 'cancelled', 'expired'))");
        }
    }

    public function down(): void
    {
        // Revert to original constraint
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE payment_requests DROP CONSTRAINT check_payment_requests_status");
            DB::statement("ALTER TABLE payment_requests ADD CONSTRAINT check_payment_requests_status CHECK (status IN ('pending', 'processing', 'successful', 'failed', 'cancelled', 'expired'))");
        }
    }
};
