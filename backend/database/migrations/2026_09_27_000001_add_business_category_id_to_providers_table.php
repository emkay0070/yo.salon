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
        Schema::table('providers', function (Blueprint $table) {
            $table->uuid('business_category_id')->nullable()->after('type');
            $table->foreign('business_category_id')->references('id')->on('business_categories')->onDelete('set null');
            $table->index('business_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['business_category_id']);
            $table->dropIndex(['business_category_id']);
            $table->dropColumn('business_category_id');
        });
    }
};
