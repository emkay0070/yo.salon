<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('provider_id');
            $table->integer('visits')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->unique(['customer_id', 'provider_id']);
            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_providers');
    }
};
