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
        Schema::create('customer_specialist', function (Blueprint $table) {
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->uuid('specialist_id');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');

            // Composite primary key
            $table->primary(['customer_id', 'specialist_id']);

            // Relationship tracking
            $table->uuid('first_booking_id')->nullable();
            $table->uuid('last_booking_id')->nullable();
            $table->integer('total_bookings')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);

            // Customer preference signals
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_following')->default(false);
            $table->decimal('rating_given')->nullable(); // Rating customer gave to specialist
            $table->text('notes')->nullable(); // Customer's private notes about this specialist

            // Relationship strength scoring
            $table->integer('relationship_score')->default(0); // Algorithmic score based on interactions
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();

            // Notification preferences
            $table->boolean('notifications_enabled')->default(true);
            $table->json('notification_preferences')->nullable(); // Specific notification types

            // Metadata
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('customer_id');
            $table->index('specialist_id');
            $table->index('is_favorite');
            $table->index('is_following');
            $table->index('relationship_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_specialist');
    }
};
