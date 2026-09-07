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
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->nullable();
            $table->uuid('salon_id')->nullable();
            $table->bigInteger('uploaded_by')->nullable(); // user_id (bigint to match users table)
            
            // Storage info
            $table->string('disk')->default('public'); // public, s3, etc.
            $table->string('path'); // e.g., "staff/abc123.jpg"
            $table->string('filename');
            $table->string('mime_type');
            $table->bigInteger('size');
            
            // Image dimensions (if applicable)
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            
            // Metadata
            $table->string('alt_text')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('provider_id');
            $table->index('salon_id');
            $table->index('uploaded_by');
            $table->index('disk');
            
            // Foreign keys
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
