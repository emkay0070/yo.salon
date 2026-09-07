<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Defines every atomic unit that the platform can meter and bill.
     * Nothing in the billing engine should reference resource names directly —
     * only resource_code from this table.
     */
    public function up(): void
    {
        Schema::create('billing_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // e.g. 'SMS', 'AI_REQUEST', 'STORAGE_GB'
            $table->string('name');           // e.g. 'SMS Message'
            $table->text('description')->nullable();
            $table->string('unit_label')->default('unit'); // e.g. 'message', 'request', 'GB'
            $table->boolean('is_metered')->default(true);  // false = feature flag (on/off), true = quantity
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_resources');
    }
};
