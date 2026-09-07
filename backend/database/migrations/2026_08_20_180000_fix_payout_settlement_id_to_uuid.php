<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally blank. 
        // settlements.id is a BigInt, so payouts.settlement_id correctly 
        // uses unsignedBigInteger. The previous attempt to cast it to UUID 
        // was incorrect because Settlement was only using HasUuids in the Model, 
        // but the database still used integer.
    }

    public function down(): void
    {
        // Intentionally blank.
    }
};
