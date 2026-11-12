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
        Schema::create('feature_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('feature_key')->index();
            $table->enum('policy_type', ['subscription', 'customer_count', 'booking_count', 'payments_enabled', 'customer_history_exists']);
            $table->json('rule_value');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['feature_key', 'policy_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_policies');
    }
};
