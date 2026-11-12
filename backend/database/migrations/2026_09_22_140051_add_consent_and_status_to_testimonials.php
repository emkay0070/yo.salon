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
        Schema::table('testimonials', function (Blueprint $table) {
            // Add customer relationship
            $table->uuid('customer_id')->nullable()->after('provider_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
            $table->index('customer_id');

            // Add location scoping
            $table->uuid('location_id')->nullable()->after('customer_id');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->index('location_id');

            // Add consent tracking
            $table->boolean('consent_given')->default(false)->after('content');
            $table->timestamp('consent_date')->nullable()->after('consent_given');

            // Add status field for moderation workflow
            $table->enum('status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending')->after('visible');
            $table->index('status');

            // Update index to include status
            $table->dropIndex(['provider_id', 'visible']);
            $table->index(['provider_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            // Restore original index
            $table->dropIndex(['provider_id', 'status']);
            $table->index(['provider_id', 'visible']);

            // Remove status field
            $table->dropColumn('status');

            // Remove consent fields
            $table->dropColumn('consent_date');
            $table->dropColumn('consent_given');

            // Remove location scoping
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');

            // Remove customer relationship
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
