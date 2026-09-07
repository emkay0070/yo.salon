<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each row is the price of a platform_product within a specific price_book.
     * The billing engine looks up: "What does SMS_500 cost in uganda_default?"
     *
     * A product can have different prices in different price books.
     * This is the single source of truth for all pricing.
     */
    public function up(): void
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->uuid('price_book_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('UGX');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('platform_products')
                ->onDelete('cascade');

            $table->foreign('price_book_id')
                ->references('id')
                ->on('price_books')
                ->onDelete('cascade');

            // A product can only have one price per price book
            $table->unique(['product_id', 'price_book_id'], 'product_price_book_unique');
            $table->index('product_id');
            $table->index('price_book_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prices');
    }
};
