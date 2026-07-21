<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['email_verified', 'onboarding_started', 'onboarding_completed', 'active'])->default('email_verified')->after('email_verified_at');
            $table->uuid('salon_id')->nullable()->after('status');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->dropColumn(['status', 'salon_id']);
        });
    }
};
