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
        Schema::table('business_categories', function (Blueprint $table) {
            $table->enum('provider_type', ['salon', 'independent_specialist', 'spa', 'beauty_school', 'mobile_team'])->nullable()->after('id');
            $table->index('provider_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_categories', function (Blueprint $table) {
            $table->dropIndex(['provider_type']);
            $table->dropColumn('provider_type');
        });
    }
};
