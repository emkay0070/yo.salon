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
            // Drop old status and provider_id
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['status', 'provider_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Re-add status as string with default 'registered'
            $table->string('status')->default('registered')->after('email_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['email_verified', 'onboarding_started', 'onboarding_completed', 'active'])->default('email_verified')->after('email_verified_at');
            $table->uuid('provider_id')->nullable()->after('status');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('set null');
        });
    }
};
