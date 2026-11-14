<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProviderCapacity records purchased add-on capacity (extra staff seats,
 * branches, storage, SMS/AI credits) on top of a plan's entitlements.
 *
 * App\Models\ProviderCapacity, CapabilityResolver, InvoiceService and
 * AddOnPurchaseController already depend on this table; the schema was
 * missing from the migration history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_capacities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->string('resource_code');
            $table->integer('additional_capacity')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['provider_id', 'resource_code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_capacities');
    }
};
