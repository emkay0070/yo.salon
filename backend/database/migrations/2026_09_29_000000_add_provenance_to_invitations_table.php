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
        Schema::table('invitations', function (Blueprint $table) {
            // Location context (nullable for Provider-wide invitations)
            $table->uuid('location_id')->nullable()->after('provider_id')->index();
            
            // Who created the invitation (inviter provenance) - always a User
            $table->foreignId('created_by')->nullable()->after('location_id')->index()->constrained('users')->onDelete('set null');
            
            // Who accepted the invitation (account provenance)
            // Can be User ID (bigint), PortalAccount ID (UUID), or SpecialistAccount ID (UUID)
            // Stored as string to handle mixed types
            $table->string('accepted_user_id')->nullable()->after('accepted_at')->index();

            // Foreign key for location
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['location_id']);
            $table->dropForeign(['accepted_user_id']);
            $table->dropColumn(['created_by', 'location_id', 'accepted_user_id']);
        });
    }
};
