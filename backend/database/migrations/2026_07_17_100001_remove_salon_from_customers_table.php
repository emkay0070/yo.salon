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
        Schema::table('customers', function (Blueprint $table) {
            // Remove salon_id foreign key and column
            if (Schema::hasColumn('customers', 'salon_id')) {
                $table->dropForeign(['salon_id']);
                $table->dropColumn('salon_id');
            }
            // Remove visits column (will be in pivot table)
            if (Schema::hasColumn('customers', 'visits')) {
                $table->dropColumn('visits');
            }
            // Remove notes column (will be in pivot table)
            if (Schema::hasColumn('customers', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('salon_id')->nullable()->after('id');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->integer('visits')->default(0);
            $table->text('notes')->nullable();
        });
    }
};
