<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->uuid('financial_transaction_id')->nullable()->after('id');
            $table->foreign('financial_transaction_id')
                  ->references('id')
                  ->on('financial_transactions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropForeign(['financial_transaction_id']);
            $table->dropColumn('financial_transaction_id');
        });
    }
};
