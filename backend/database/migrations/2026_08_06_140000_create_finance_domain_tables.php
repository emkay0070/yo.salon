<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create financial_transactions table
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_number')->unique();
            $table->string('type'); // payment, refund, adjustment, etc.
            $table->string('description');
            $table->string('reference_type')->nullable(); // Booking, Invoice, etc.
            $table->uuid('reference_id')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency')->default('UGX');
            $table->string('status')->default('posted'); // draft, posted, voided
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('status');
            $table->index('posted_at');
        });

        // Create revenue_distribution_policies table
        Schema::create('revenue_distribution_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('context_type')->nullable(); // Salon, Assignment, Service, etc.
            $table->uuid('context_id')->nullable();
            $table->string('revenue_type'); // booking, product, training, marketplace, etc.
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamp('effective_from')->default(now());
            $table->timestamp('effective_until')->nullable();
            $table->json('rules'); // Distribution rules from DB
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index('revenue_type');
            $table->index('is_active');
            $table->index('priority');
            $table->index('effective_from');
            $table->index('effective_until');
        });

        // Create ledger_accounts table
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type'); // Polymorphic - any entity
            $table->uuid('owner_id');
            $table->string('account_type')->default('default'); // revenue, receivable, payable, etc.
            $table->string('currency')->default('UGX');
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('active'); // active, frozen, closed
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('account_type');
            $table->index('status');
            $table->unique(['owner_type', 'owner_id', 'account_type', 'currency']);
        });

        // Create journal_entries table
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('journal_number')->unique();
            $table->string('type'); // payment, commission, settlement, refund, etc.
            $table->string('description');
            $table->string('reference_type')->nullable(); // Booking, Settlement, Payout, etc.
            $table->uuid('reference_id')->nullable();
            $table->decimal('total_debit', 12, 2)->default(0);
            $table->decimal('total_credit', 12, 2)->default(0);
            $table->string('status')->default('posted'); // draft, posted
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('status');
            $table->index('posted_at');
        });

        // Create ledger_entries table
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('ledger_account_id');
            $table->string('type'); // credit, debit
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('description');
            $table->string('reference_type')->nullable(); // Booking, Settlement, Payout, etc.
            $table->uuid('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('ledger_account_id')->references('id')->on('ledger_accounts')->onDelete('cascade');
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
            $table->index('created_at');
        });

        // Update settlements table to use polymorphic relationships
        Schema::table('settlements', function (Blueprint $table) {
            // Drop existing foreign keys
            $table->dropForeign(['salon_id']);
            
            // Add polymorphic columns for payable (who owes)
            $table->string('payable_type')->after('id');
            $table->uuid('payable_id')->after('payable_type');
            
            // Add polymorphic columns for recipient (who receives)
            $table->string('recipient_type')->after('payable_id');
            $table->uuid('recipient_id')->after('recipient_type');
            
            // Add polymorphic columns for reference (what caused this)
            $table->string('reference_type')->nullable()->after('recipient_id');
            $table->uuid('reference_id')->nullable()->after('reference_type');
            
            // Drop old salon_id column after migration
            $table->dropColumn('salon_id');
        });

        // Create payouts table
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('settlement_id')->nullable();
            $table->string('recipient_type'); // Polymorphic - any entity
            $table->uuid('recipient_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('UGX');
            $table->string('method'); // cash, bank_transfer, mobile_money, etc.
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('reference')->nullable();
            $table->string('provider_reference')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable(); // User who processed manual payout
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('settlement_id')->references('id')->on('settlements')->nullOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('status');
            $table->index('method');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        
        Schema::table('settlements', function (Blueprint $table) {
            // Revert settlements table
            $table->dropColumn(['payable_type', 'payable_id', 'recipient_type', 'recipient_id', 'reference_type', 'reference_id']);
            $table->uuid('salon_id')->after('id');
        });
        
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('revenue_distribution_policies');
    }
};
