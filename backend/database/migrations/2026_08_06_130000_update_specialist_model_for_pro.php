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
        // Add new columns to specialists table
        Schema::table('specialists', function (Blueprint $table) {
            // Replace is_pro with subscription relationship (handled separately)
            $table->dropColumn('is_pro');
            
            // Replace is_verified with verification_status enum
            $table->dropColumn('is_verified');
            if (DB::getDriverName() === 'sqlite') {
                $table->string('verification_status')->default('unverified')->after('active');
            } else {
                $table->enum('verification_status', [
                    'unverified',
                    'submitted',
                    'under_review',
                    'approved',
                    'verified',
                    'expired',
                    'revoked',
                    'rejected'
                ])->default('unverified')->after('active');
            }
            
            // Add ranking fields
            $table->decimal('punctuality', 5, 2)->default(0)->after('verified_at');
            $table->decimal('attendance', 5, 2)->default(0)->after('punctuality');
            $table->decimal('response_rate', 5, 2)->default(0)->after('attendance');
            
            // Add location for distance-based ranking
            $table->decimal('latitude', 10, 8)->nullable()->after('response_rate');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
        });

        // Create specialist_verifications table for audit trail
        Schema::create('specialist_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            if (DB::getDriverName() === 'sqlite') {
                $table->string('status')->index();
            } else {
                $table->enum('status', [
                    'unverified',
                    'submitted',
                    'under_review',
                    'approved',
                    'verified',
                    'expired',
                    'revoked',
                    'rejected'
                ])->index();
            }
            if (DB::getDriverName() === 'sqlite') {
                $table->string('previous_status')->nullable();
            } else {
                $table->enum('previous_status', [
                    'unverified',
                    'submitted',
                    'under_review',
                    'approved',
                    'verified',
                    'expired',
                    'revoked',
                    'rejected'
                ])->nullable();
            }
            $table->json('documents')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->index('specialist_id');
        });

        // Create specialist_career_events table for Career Graph
        Schema::create('specialist_career_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('specialist_id');
            if (DB::getDriverName() === 'sqlite') {
                $table->string('type');
            } else {
                $table->enum('type', [
                    'milestone',
                    'employment',
                    'achievement',
                    'award',
                    'certification',
                    'promotion',
                    'salon_change',
                    'skill_mastery'
                ]);
            }
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->uuid('salon_id')->nullable();
            $table->uuid('assignment_id')->nullable();
            $table->json('data')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->foreign('specialist_id')->references('id')->on('specialists')->onDelete('cascade');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('set null');
            $table->foreign('assignment_id')->references('id')->on('specialist_assignments')->onDelete('set null');
            $table->index('specialist_id');
            $table->index('type');
            $table->index('date');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specialist_career_events');
        Schema::dropIfExists('specialist_earnings');
        Schema::dropIfExists('specialist_verifications');

        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'punctuality',
                'attendance',
                'response_rate',
                'latitude',
                'longitude',
            ]);
            
            $table->boolean('is_pro')->default(false)->after('active');
            $table->boolean('is_verified')->default(false)->after('is_pro');
        });
    }
};
