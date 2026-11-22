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
     * Creates the organizations table representing the commercial and
     * governance owner in the TYBUEL hierarchy (Platform -> Organization -> Provider -> Location).
     *
     * Dual-principal designated ownership supports both User and SpecialistAccount
     * with strict database-enforced foreign keys and mutual exclusivity.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['business', 'personal'])->default('business');
            $table->enum('status', ['active', 'suspended', 'inactive'])->default('active');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            // Dual-principal designated ownership with restrictive deletion
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('restrict');

            $table->uuid('owner_specialist_account_id')->nullable();
            $table->foreign('owner_specialist_account_id')
                ->references('id')
                ->on('specialist_accounts')
                ->onDelete('restrict');

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('type');
            $table->index('status');
            $table->index('owner_user_id');
            $table->index('owner_specialist_account_id');
        });

        // Enforce exactly one designated owner at database level
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT chk_organization_designated_owner CHECK (
            (owner_user_id IS NOT NULL AND owner_specialist_account_id IS NULL)
            OR
            (owner_user_id IS NULL AND owner_specialist_account_id IS NOT NULL)
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
