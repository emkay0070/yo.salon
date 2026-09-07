<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'reference_id']);
            $table->unique(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropUnique(['reference_type', 'reference_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }
};
