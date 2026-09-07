<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            // In SQLite, dropping a foreign key requires recreating the table 
            // under the hood, but Laravel handles this in modern versions.
            // Wrap in try-catch in case the DB driver doesn't enforce the named FK 
            // during local dev or the name doesn't match perfectly.
            try {
                $table->dropForeign(['payment_method_id']);
            } catch (\Exception $e) {
                // Ignore if it doesn't exist
            }

            $table->dropColumn('payment_method_id');
            $table->json('metadata')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn('metadata');
        });
    }
};
