<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // Make morph columns nullable since we're using provider_id instead
            $table->string('payable_type')->nullable()->change();
            $table->uuid('payable_id')->nullable()->change();
            $table->string('recipient_type')->nullable()->change();
            $table->uuid('recipient_id')->nullable()->change();
            $table->string('reference_type')->nullable()->change();
            $table->uuid('reference_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->string('payable_type')->nullable(false)->change();
            $table->uuid('payable_id')->nullable(false)->change();
            $table->string('recipient_type')->nullable(false)->change();
            $table->uuid('recipient_id')->nullable(false)->change();
            $table->string('reference_type')->nullable(false)->change();
            $table->uuid('reference_id')->nullable(false)->change();
        });
    }
};
