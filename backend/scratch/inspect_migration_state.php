<?php
/**
 * Migration State Inspector
 * Run from backend/: php scratch/inspect_migration_state.php
 *
 * Answers:
 * 1. Which payment/transaction migrations have already been applied?
 * 2. What columns does the actual `transactions` table have right now?
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n=== MIGRATION HISTORY (payment / transaction related) ===\n";
$migrations = DB::table('migrations')
    ->where(function ($q) {
        $q->where('migration', 'like', '%transaction%')
          ->orWhere('migration', 'like', '%payment_account%')
          ->orWhere('migration', 'like', '%payment_method%');
    })
    ->orderBy('batch')
    ->orderBy('migration')
    ->get(['migration', 'batch']);

if ($migrations->isEmpty()) {
    echo "  [NONE FOUND]\n";
} else {
    foreach ($migrations as $m) {
        echo "  [Batch {$m->batch}] {$m->migration}\n";
    }
}

echo "\n=== TRANSACTIONS TABLE — ACTUAL COLUMNS ===\n";
if (Schema::hasTable('transactions')) {
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'transactions'
        ORDER BY ordinal_position
    ");
    foreach ($columns as $col) {
        $nullable = $col->is_nullable === 'YES' ? 'nullable' : 'not null';
        echo "  {$col->column_name} ({$col->data_type}, {$nullable})\n";
    }
} else {
    echo "  [TABLE DOES NOT EXIST]\n";
}

echo "\n=== PAYMENT_ACCOUNTS TABLE — ACTUAL COLUMNS ===\n";
if (Schema::hasTable('payment_accounts')) {
    $columns = DB::select("
        SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'payment_accounts'
        ORDER BY ordinal_position
    ");
    foreach ($columns as $col) {
        $nullable = $col->is_nullable === 'YES' ? 'nullable' : 'not null';
        echo "  {$col->column_name} ({$col->data_type}, {$nullable})\n";
    }
} else {
    echo "  [TABLE DOES NOT EXIST]\n";
}

echo "\n=== DIAGNOSIS ===\n";
$hasAmount   = Schema::hasColumn('transactions', 'amount');
$hasGross    = Schema::hasColumn('transactions', 'gross_amount');
$hasGateway  = Schema::hasColumn('transactions', 'gateway_fee');
$hasPlatform = Schema::hasColumn('transactions', 'platform_fee');
$hasTax      = Schema::hasColumn('transactions', 'tax_amount');
$hasNet      = Schema::hasColumn('transactions', 'net_amount');
$hasAcctId   = Schema::hasColumn('transactions', 'payment_account_id');

echo "  transactions.amount            : " . ($hasAmount   ? 'EXISTS (old schema!)' : 'absent') . "\n";
echo "  transactions.gross_amount      : " . ($hasGross    ? 'EXISTS ok'            : 'MISSING') . "\n";
echo "  transactions.gateway_fee       : " . ($hasGateway  ? 'EXISTS ok'            : 'MISSING') . "\n";
echo "  transactions.platform_fee      : " . ($hasPlatform ? 'EXISTS ok'            : 'MISSING') . "\n";
echo "  transactions.tax_amount        : " . ($hasTax      ? 'EXISTS ok'            : 'MISSING') . "\n";
echo "  transactions.net_amount        : " . ($hasNet      ? 'EXISTS ok'            : 'MISSING') . "\n";
echo "  transactions.payment_account_id: " . ($hasAcctId   ? 'EXISTS ok'            : 'MISSING') . "\n";

echo "\n=== RECOMMENDED ACTION ===\n";
if ($hasAmount && !$hasGross) {
    echo "  => OLD schema still active.\n";
    echo "  => CREATE a new migration: alter_transactions_add_economic_fields\n";
    echo "  => Do NOT edit the original migration or run migrate:fresh.\n";
} elseif (!$hasAmount && $hasGross) {
    echo "  => ALREADY normalized! transactions table has correct economic fields.\n";
    echo "  => No migration needed. Proceed directly to writing tests.\n";
} elseif (!$hasAmount && !$hasGross) {
    echo "  => transactions table has NEITHER column.\n";
    echo "  => The migration has NOT been applied yet.\n";
    echo "  => You may safely edit the original migration and migrate:fresh.\n";
} else {
    echo "  => Both old and new columns exist. Manual inspection needed.\n";
}

echo "\n";
