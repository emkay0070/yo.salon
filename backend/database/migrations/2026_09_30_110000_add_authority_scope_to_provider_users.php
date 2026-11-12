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
        Schema::table('provider_users', function (Blueprint $table) {
            $table->enum('authority_scope', ['provider_wide', 'location_scoped'])
                ->default('location_scoped')
                ->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_users', function (Blueprint $table) {
            $table->dropColumn('authority_scope');
        });
    }
};
