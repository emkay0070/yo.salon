<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Price books define a pricing context.
     * This enables regional pricing, promotional pricing, and enterprise pricing
     * without ever changing the product catalog itself.
     *
     * Example price books:
     *   - uganda_default (UGX, active)
     *   - kenya_default  (KES)
     *   - nigeria_default (NGN)
     *   - promo_q1_2027  (UGX, limited time)
     */
    public function up(): void
    {
        Schema::create('price_books', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();           // e.g. 'uganda_default'
            $table->string('name');                     // e.g. 'Uganda — Default Pricing'
            $table->string('currency', 3)->default('UGX');
            $table->string('country_code', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('currency');
            $table->index('is_default');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_books');
    }
};
