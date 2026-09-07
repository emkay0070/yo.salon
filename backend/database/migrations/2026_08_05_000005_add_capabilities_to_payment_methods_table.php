<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('supports_online_payment')->default(false)->after('type');
            $table->boolean('supports_in_person_payment')->default(false)->after('supports_online_payment');
            $table->boolean('supports_partial_payment')->default(false)->after('supports_in_person_payment');
            $table->boolean('supports_refunds')->default(false)->after('supports_partial_payment');
            $table->boolean('supports_recurring')->default(false)->after('supports_refunds');
        });

        // Migrate existing data based on provider types
        DB::table('payment_methods')->where('provider', 'cash')->update([
            'supports_in_person_payment' => true,
        ]);
        
        DB::table('payment_methods')->where('provider', '!=', 'cash')->update([
            'supports_online_payment' => true,
            'supports_in_person_payment' => true,
            'supports_partial_payment' => true,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn([
                'supports_online_payment',
                'supports_in_person_payment',
                'supports_partial_payment',
                'supports_refunds',
                'supports_recurring',
            ]);
        });
    }
};
