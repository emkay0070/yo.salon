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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop foreign key and index for salon_id
            $table->dropForeign(['salon_id']);
            $table->dropIndex(['salon_id']);
            $table->dropUnique(['salon_id']);
            
            // Rename salon_id to provider_id
            $table->renameColumn('salon_id', 'provider_id');
            
            // Add foreign key for provider_id
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->index('provider_id');
            $table->unique('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop foreign key and index for provider_id
            $table->dropForeign(['provider_id']);
            $table->dropIndex(['provider_id']);
            $table->dropUnique(['provider_id']);
            
            // Rename provider_id back to salon_id
            $table->renameColumn('provider_id', 'salon_id');
            
            // Add foreign key for salon_id
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->index('salon_id');
            $table->unique('salon_id');
        });
    }
};
