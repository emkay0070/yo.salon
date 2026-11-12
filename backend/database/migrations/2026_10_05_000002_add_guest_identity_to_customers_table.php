<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add guest identity columns to customers.
 *
 * Design principles:
 *   1. A guest is a REAL customer row — bookings, sessions, payments all point to it.
 *   2. is_guest = true means the record was created from a walk-in with no prior CRM history.
 *   3. guest_token is a stable, per-visit identifier the receptionist can use to retrieve
 *      the record without a phone number (e.g. scanned from a printed receipt QR code).
 *   4. guest_provider_id scopes the guest to a specific business — the same physical person
 *      walking into two different salons without identifying themselves is TWO guests,
 *      not one merged CRM customer.
 *   5. A guest can be "promoted" to a real customer by adding their phone/email.
 *      This is a deliberate staff action, not an automatic merge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Whether this customer was created as an anonymous walk-in guest
            $table->boolean('is_guest')->default(false)->after('notes');

            // Stable token for receipt QR / receptionist lookup (not a security secret)
            $table->string('guest_token', 32)->nullable()->unique()->after('is_guest');

            // The provider this guest was created under (scopes guest identity)
            $table->uuid('guest_provider_id')->nullable()->after('guest_token');
            $table->foreign('guest_provider_id')
                  ->references('id')
                  ->on('providers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['guest_provider_id']);
            $table->dropColumn(['is_guest', 'guest_token', 'guest_provider_id']);
        });
    }
};
