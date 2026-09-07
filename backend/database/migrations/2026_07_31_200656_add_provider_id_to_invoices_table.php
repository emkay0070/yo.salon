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
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('provider_id')->nullable()->after('id');
            $table->foreign('provider_id')->references('id')->on('providers')->onDelete('cascade');
            $table->index('provider_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropIndex(['provider_id']);
            $table->dropColumn('provider_id');
        });
    }
};
