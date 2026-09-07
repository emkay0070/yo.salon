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
        Schema::create('profile_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('assessment_id')->nullable(); // If generated from an assessment
            $table->uuid('specialist_id')->nullable(); // Specialist who made the recommendation
            
            // Recommendation content
            $table->string('field')->nullable(); // Which field to update (hair_type, skin_type, etc.)
            $table->string('current_value')->nullable(); // Current customer value
            $table->string('recommended_value')->nullable(); // Recommended value
            $table->text('reason')->nullable(); // Why this recommendation
            $table->string('confidence_level')->default('Medium'); // Low, Medium, High
            
            // Status
            $table->string('status')->default('pending'); // pending, accepted, rejected, expired
            $table->timestamp('expires_at')->nullable();
            
            // Customer response
            $table->timestamp('responded_at')->nullable();
            $table->text('customer_feedback')->nullable();
            
            // Metadata
            $table->jsonb('metadata')->nullable();
            
            $table->timestamps();
            
            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('assessment_id')->references('id')->on('professional_assessments')->onDelete('set null');
            $table->foreign('specialist_id')->references('id')->on('staff')->onDelete('set null');
            
            // Indexes
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['assessment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_recommendations');
    }
};
