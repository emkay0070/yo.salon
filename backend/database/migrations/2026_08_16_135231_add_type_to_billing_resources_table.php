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
        Schema::table('billing_resources', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->string('type')->default('quota')->after('is_metered');
            } else {
                $table->enum('type', ['quota', 'credit', 'feature', 'unlimited'])->default('quota')->after('is_metered');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_resources', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
