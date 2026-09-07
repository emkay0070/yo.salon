<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Defines every product Yo.Salon can sell to a salon owner.
     * Products are catalog items — they reference a billing resource and
     * define how many units of that resource are being sold.
     */
    public function up(): void
    {
        Schema::create('platform_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();           // e.g. 'SMS_500', 'AI_1000'
            $table->string('name');                     // e.g. '500 SMS Pack'
            $table->text('description')->nullable();
            $table->string('resource_code');            // FK ref to billing_resources.code
            $table->string('type');                     // 'prepaid', 'subscription', 'add_on'
            $table->string('billing_model');            // 'one_time', 'recurring'
            $table->integer('units')->default(1);       // e.g. 500 for SMS_500
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();       // flexible future data
            $table->timestamps();

            $table->index('code');
            $table->index('resource_code');
            $table->index('type');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_products');
    }
};
