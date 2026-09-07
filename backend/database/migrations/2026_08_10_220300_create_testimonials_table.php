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
        Schema::create('testimonials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('salon_id')->nullable()->constrained('salons')->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->integer('rating')->default(5);
            $table->text('content');
            $table->boolean('visible')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['salon_id', 'visible']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
