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
        Schema::create('specialist_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('specialist_id');
            $table->uuid('salon_id');
            $table->uuid('booking_id')->nullable();
            
            // Note Content
            $table->text('note');
            $table->string('note_type')->default('general'); // general, preference, observation, recommendation
            $table->jsonb('tags')->nullable(); // Array of tags for categorization
            
            // Visibility
            $table->boolean('is_private')->default(false); // If true, only visible to this specialist
            $table->boolean('is_important')->default(false); // Highlight important notes
            
            // Customer Interaction
            $table->boolean('customer_visible')->default(true); // Whether customer can see this note
            $table->timestamp('customer_viewed_at')->nullable();
            
            // Metadata
            $table->jsonb('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('specialist_id')->references('id')->on('staff')->onDelete('cascade');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            
            // Indexes
            $table->index(['customer_id', 'created_at']);
            $table->index(['specialist_id', 'created_at']);
            $table->index(['salon_id', 'created_at']);
            $table->index(['booking_id']);
            $table->index(['note_type']);
            $table->index(['is_important']);
            $table->index(['customer_visible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_notes');
    }
};
