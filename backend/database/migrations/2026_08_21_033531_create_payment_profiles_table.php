<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PaymentProfile answers: "Where do we send this person's money?"
     *
     * This is the PAYOUT DESTINATION — completely separate from PaymentMethod
     * which is the customer COLLECTION RAIL.
     *
     * - owner_type / owner_id: polymorphic (Specialist, Staff)
     * - method: the rail — mtn | airtel | bank | cash
     * - is_verified: manually or API-verified account holder match
     * - is_default: used by AutomatedExecution when no profile is specified
     *
     * No profile on a recipient = automated payout unavailable for that
     * recipient. Manual payout via Payout(execution_mode=manual) always works.
     */
    public function up(): void
    {
        Schema::create('payment_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Who owns this profile
            $table->string('owner_type');
            $table->uuid('owner_id');
            $table->index(['owner_type', 'owner_id']);

            // Which disbursement rail
            $table->string('method'); // mtn | airtel | bank | cash

            // Mobile money fields (MTN / Airtel)
            $table->string('phone_number')->nullable();

            // Bank fields
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank_code')->nullable();   // e.g. "STAN" for Stanbic
            $table->string('bank_name')->nullable();

            // Display / fallback
            $table->string('label')->nullable();       // "My MTN number" / "Salary account"

            // Trust
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();

            // Provider-specific metadata (e.g. name returned from API lookup)
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_profiles');
    }
};
