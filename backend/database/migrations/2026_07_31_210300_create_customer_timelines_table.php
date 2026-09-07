<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_timelines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('salon_id')->nullable();
            $table->uuid('booking_id')->nullable();
            $table->string('service_name');
            $table->string('category')->nullable(); // Hair, Beard, Spa, Other
            $table->string('specialist_name')->nullable();
            $table->uuid('specialist_id')->nullable();
            $table->string('provider_name')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->string('before_photo')->nullable();
            $table->string('after_photo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('service_date');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('set null');
            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('set null');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('set null');
            
            $table->index(['customer_id', 'service_date']);
            $table->index(['customer_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_timelines');
    }
};
