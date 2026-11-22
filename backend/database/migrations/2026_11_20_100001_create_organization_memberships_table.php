<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates organization_memberships table representing a person's membership
     * and authority within an Organization (commercial governance layer).
     *
     * Dual-principal membership supports both User and SpecialistAccount
     * with full database-level referential integrity and mutual exclusivity.
     */
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->onDelete('cascade');

            // Dual principal foreign keys
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->uuid('specialist_account_id')->nullable();
            $table->foreign('specialist_account_id')
                ->references('id')
                ->on('specialist_accounts')
                ->onDelete('cascade');

            $table->enum('role', ['org_owner', 'org_admin', 'org_member'])->default('org_member');
            $table->enum('status', ['active', 'invited', 'suspended'])->default('active');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Uniqueness per principal
            $table->unique(['organization_id', 'user_id'], 'uq_org_membership_user');
            $table->unique(['organization_id', 'specialist_account_id'], 'uq_org_membership_specialist');
            $table->index('role');
            $table->index('status');
        });

        // Enforce exactly one principal at database level
        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT chk_membership_principal CHECK (
            (user_id IS NOT NULL AND specialist_account_id IS NULL)
            OR
            (user_id IS NULL AND specialist_account_id IS NOT NULL)
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
