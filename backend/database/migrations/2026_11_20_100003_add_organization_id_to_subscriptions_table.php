<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links subscriptions authoritatively to organizations (commercial owner).
     * Removes the unique constraint on provider_id so multiple Providers
     * can belong to one commercially subscribed Organization.
     * Retains restrictive deletion semantics to preserve billing history.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->uuid('organization_id')->after('id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('restrict');
            $table->index('organization_id');

            // Drop unique provider subscription constraint
            $table->dropUnique('subscriptions_provider_id_unique');

            // Make provider_id nullable
            $table->uuid('provider_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique('provider_id', 'subscriptions_provider_id_unique');
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
