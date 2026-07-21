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
        Schema::create('usage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id');
            $table->string('metric'); // staff, branches, storage, sms_credits, bookings, etc.
            $table->integer('current_value')->default(0);
            $table->integer('limit')->default(0);
            $table->string('period')->default('current'); // current, previous
            $table->date('period_start');
            $table->date('period_end');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
            $table->index('subscription_id');
            $table->index('metric');
            $table->index('period');
            $table->unique(['subscription_id', 'metric', 'period', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage');
    }
};
