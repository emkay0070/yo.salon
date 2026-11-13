<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The finance tables whose polymorphic reference_id columns must hold
     * both UUID keys (Booking, GiftCardPurchaseIntent, CompensationPeriod)
     * and bigint keys (transaction payments/refunds stored as strings).
     */
    private const TABLES = [
        'financial_transactions',
        'journal_entries',
        'ledger_entries',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (self::TABLES as $table) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('reference_id', 36)->nullable()->change();
                });
            }

            return;
        }

        // PostgreSQL requires an explicit USING clause to convert uuid to varchar.
        // Existing UUID values are preserved as their canonical string form.
        foreach (self::TABLES as $table) {
            DB::statement(
                "ALTER TABLE {$table} ALTER COLUMN reference_id TYPE VARCHAR(36) USING reference_id::text"
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * Irreversible once a bigint transaction reference has been stored: casting
     * values such as "1" back to uuid would fail or corrupt the reference.
     * Roll back only when every stored reference is still UUID-shaped.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            foreach (self::TABLES as $table) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->uuid('reference_id')->nullable()->change();
                });
            }

            return;
        }

        $uuidPattern = '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$';

        foreach (self::TABLES as $table) {
            $hasNonUuidReference = DB::table($table)
                ->whereNotNull('reference_id')
                ->whereRaw("reference_id !~* '{$uuidPattern}'")
                ->exists();

            if ($hasNonUuidReference) {
                throw new \RuntimeException(
                    "Cannot roll back {$table}.reference_id to UUID: the column contains "
                    . 'non-UUID values (for example bigint transaction IDs stored as strings). '
                    . 'This migration is irreversible while those references exist.'
                );
            }
        }

        foreach (self::TABLES as $table) {
            DB::statement(
                "ALTER TABLE {$table} ALTER COLUMN reference_id TYPE UUID USING reference_id::uuid"
            );
        }
    }
};
