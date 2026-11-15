<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PaymentRequestController and the PaymentRequest model already use
 * idempotency_key for duplicate-submission protection, but the column was
 * never added to payment_requests. Every create() therefore failed.
 *
 * The unique key is scoped to the provider because the lookup is always
 * provider-scoped; two providers may legitimately use the same client key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable()->after('provider_reference');
            $table->unique(['provider_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropUnique(['provider_id', 'idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
