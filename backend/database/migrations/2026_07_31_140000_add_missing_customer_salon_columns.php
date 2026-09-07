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
        // Redundant: columns and foreign keys are already added in 
        // 2026_07_20_170756_add_salon_specific_fields_to_customer_salon_table.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Redundant.
    }
};
