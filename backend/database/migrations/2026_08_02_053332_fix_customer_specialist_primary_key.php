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
        // Redundant: customer_specialist table is already created with 
        // the correct composite primary key in 2026_08_01_000000_create_customer_specialist_table.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Redundant
    }
};
