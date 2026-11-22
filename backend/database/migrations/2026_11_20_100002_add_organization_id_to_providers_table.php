<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds mandatory organization_id foreign key to providers table.
     * Every operational Provider must belong to exactly one commercial Organization.
     * ON DELETE RESTRICT prevents silently orphaning operational service providers.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->uuid('organization_id')->after('id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('restrict');
            $table->index('organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
