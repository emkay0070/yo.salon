<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Configuration Publishing Workflow
 *
 * Allows scheduless + booking configs to be edited as "DRAFT" without affecting
 * live availability, then explicitly "PUBLISH"ed to become ACTIVE.
 *
 * BookabilityEngine & AvailabilityWindowGenerator MUST skip rows that are not ACTIVE.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Provider-level schedule status
        if (Schema::hasTable('provider_schedules') && !Schema::hasColumn('provider_schedules', 'status')) {
            Schema::table('provider_schedules', function (Blueprint $table) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('status')->default('ACTIVE');
                } else {
                    $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
                }
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
            });
        }

        // Salon-level schedule status
        if (Schema::hasTable('salon_schedules') && !Schema::hasColumn('salon_schedules', 'status')) {
            Schema::table('salon_schedules', function (Blueprint $table) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('status')->default('ACTIVE');
                } else {
                    $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
                }
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
            });
        }

        // Assignment-level schedule status
        if (Schema::hasTable('assignment_schedules') && !Schema::hasColumn('assignment_schedules', 'status')) {
            Schema::table('assignment_schedules', function (Blueprint $table) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('status')->default('ACTIVE');
                } else {
                    $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
                }
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
            });
        }

        // Provider booking config status
        if (Schema::hasTable('provider_booking_config') && !Schema::hasColumn('provider_booking_config', 'status')) {
            Schema::table('provider_booking_config', function (Blueprint $table) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('status')->default('ACTIVE');
                } else {
                    $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
                }
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
            });
        }

        // Website config status
        if (Schema::hasTable('website_configurations') && !Schema::hasColumn('website_configurations', 'status')) {
            Schema::table('website_configurations', function (Blueprint $table) {
                if (DB::getDriverName() === 'sqlite') {
                    $table->string('status')->default('ACTIVE');
                } else {
                    $table->enum('status', ['DRAFT', 'ACTIVE'])->default('ACTIVE');
                }
                $table->timestamp('published_at')->nullable();
                $table->uuid('published_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('provider_schedules', 'status')) {
            Schema::table('provider_schedules', function (Blueprint $table) {
                $table->dropColumn(['status', 'published_at', 'published_by']);
            });
        }
        if (Schema::hasColumn('salon_schedules', 'status')) {
            Schema::table('salon_schedules', function (Blueprint $table) {
                $table->dropColumn(['status', 'published_at', 'published_by']);
            });
        }
        if (Schema::hasColumn('assignment_schedules', 'status')) {
            Schema::table('assignment_schedules', function (Blueprint $table) {
                $table->dropColumn(['status', 'published_at', 'published_by']);
            });
        }
        if (Schema::hasTable('provider_booking_config') && Schema::hasColumn('provider_booking_config', 'status')) {
            Schema::table('provider_booking_config', function (Blueprint $table) {
                $table->dropColumn(['status', 'published_at', 'published_by']);
            });
        }
        if (Schema::hasTable('website_configurations') && Schema::hasColumn('website_configurations', 'status')) {
            Schema::table('website_configurations', function (Blueprint $table) {
                $table->dropColumn(['status', 'published_at', 'published_by']);
            });
        }
    }
};
