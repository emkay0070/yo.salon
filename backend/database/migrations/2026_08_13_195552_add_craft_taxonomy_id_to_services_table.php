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
        Schema::table('services', function (Blueprint $table) {
            $table->uuid('craft_taxonomy_id')->nullable()->after('category');
            $table->foreign('craft_taxonomy_id')->references('id')->on('craft_taxonomy')->nullOnDelete();
            $table->index('craft_taxonomy_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['craft_taxonomy_id']);
            $table->dropIndex(['craft_taxonomy_id']);
            $table->dropColumn('craft_taxonomy_id');
        });
    }
};
