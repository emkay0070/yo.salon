<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->string('execution_mode')->default('manual')->after('method');
            $table->string('provider')->nullable()->after('execution_mode');
            $table->timestamp('initiated_at')->nullable()->after('provider_reference');
            $table->renameColumn('processed_at', 'completed_at');
            $table->text('failure_reason')->nullable()->after('processed_at'); // Will be after completed_at due to rename execution order nuances
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['execution_mode', 'provider', 'initiated_at', 'failure_reason']);
            $table->renameColumn('completed_at', 'processed_at');
        });
    }
};
