<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'attempt_count')) {
                $table->unsignedInteger('attempt_count')->default(0)->after('status');
            }
            if (!Schema::hasColumn('payouts', 'last_error_at')) {
                $table->timestamp('last_error_at')->nullable()->after('attempt_count');
            }
            if (!Schema::hasColumn('payouts', 'last_error_message')) {
                $table->text('last_error_message')->nullable()->after('last_error_at');
            }
            if (!Schema::hasColumn('payouts', 'disbursement_idempotency_key')) {
                $table->string('disbursement_idempotency_key')->nullable()->unique()
                    ->after('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (Schema::hasColumn('payouts', 'disbursement_idempotency_key')) {
                $table->dropColumn('disbursement_idempotency_key');
            }
            if (Schema::hasColumn('payouts', 'last_error_message')) {
                $table->dropColumn('last_error_message');
            }
            if (Schema::hasColumn('payouts', 'last_error_at')) {
                $table->dropColumn('last_error_at');
            }
            if (Schema::hasColumn('payouts', 'attempt_count')) {
                $table->dropColumn('attempt_count');
            }
        });
    }
};
