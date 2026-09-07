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
        Schema::create('salon_capacities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id'); // Provider-centric (same as subscriptions)
            $table->string('resource_code'); // FK ref to billing_resources.code
            $table->integer('additional_capacity')->default(0); // How much was purchased
            $table->timestamp('expires_at')->nullable(); // For time-limited add-ons
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Source: invoice_id, product_code, etc.
            $table->timestamps();

            $table->foreign('provider_id')
                ->references('id')
                ->on('providers')
                ->onDelete('cascade');

            $table->index('provider_id');
            $table->index('resource_code');
            $table->index('expires_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salon_capacities');
    }
};
