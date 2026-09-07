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
            // Add over_limit state column
            $table->boolean('is_over_limit')->default(false)->after('status');
            
            // Add grandfathering state column
            $table->boolean('is_grandfathered')->default(false)->after('is_over_limit');
            
            // Add metadata for grandfathering details
            $table->json('grandfathering_metadata')->nullable()->after('is_grandfathered');
            
            // Add indexes
            $table->index('is_over_limit');
            $table->index('is_grandfathered');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['is_over_limit']);
            $table->dropIndex(['is_grandfathered']);
            $table->dropColumn(['is_over_limit', 'is_grandfathered', 'grandfathering_metadata']);
        });
    }
};
