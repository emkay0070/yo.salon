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
        Schema::create('professional_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('specialist_id');
            $table->uuid('salon_id');
            $table->uuid('booking_id')->nullable();
            
            // Hair Assessment
            $table->string('observed_hair_type')->nullable();
            $table->string('hair_density')->nullable(); // Low, Medium, High
            $table->string('scalp_condition')->nullable(); // Healthy, Dry, Oily, Inflamed, Sensitive
            $table->string('hairline')->nullable(); // Normal, Receding, Widow's Peak
            $table->text('hair_observations')->nullable();
            
            // Beard Assessment
            $table->string('observed_beard_style')->nullable();
            $table->string('beard_growth_pattern')->nullable(); // Patchy, Full, Sparse
            $table->text('beard_observations')->nullable();
            
            // Skin Assessment
            $table->string('observed_skin_type')->nullable();
            $table->text('skin_observations')->nullable();
            $table->jsonb('skin_conditions')->nullable(); // Acne, Pigmentation, Sensitive, Scarring
            
            // Assessment Metadata
            $table->string('confidence_level')->default('Medium'); // Low, Medium, High
            $table->text('notes')->nullable();
            $table->jsonb('metadata')->nullable();
            
            // Customer Response
            $table->boolean('customer_reviewed')->default(false);
            $table->boolean('customer_accepted')->nullable();
            $table->timestamp('customer_reviewed_at')->nullable();
            $table->text('customer_feedback')->nullable();
            
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
            $table->index(['customer_reviewed', 'customer_accepted']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('professional_assessments');
    }
};
