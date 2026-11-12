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
        Schema::create('gift_card_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gift_card_id');
            $table->enum('type', ['purchase', 'issue', 'redeem', 'adjustment', 'cancellation']);
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->bigInteger('performed_by')->nullable();

            $table->foreign('gift_card_id')->references('id')->on('gift_cards')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['gift_card_id', 'type']);
            $table->index('created_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
    }
};
