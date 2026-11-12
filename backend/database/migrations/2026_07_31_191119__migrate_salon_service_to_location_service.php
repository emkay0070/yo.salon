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
        // Create location_service table with same structure as salon_service
        Schema::create('location_service', function (Blueprint $table) {
            // Keys
            $table->uuid('location_id');
            $table->uuid('service_id');
            $table->primary(['location_id', 'service_id']);

            // Availability (null = inherit from provider)
            $table->boolean('is_available')->nullable()->default(null);
            $table->boolean('online_booking_enabled')->nullable()->default(null);

            // Branch-level overrides (null = use provider-level default)
            $table->decimal('price_override', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_before_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_after_override')->nullable(); // minutes

            // Booking policy overrides
            $table->unsignedSmallInteger('min_booking_notice_override')->nullable(); // hours
            $table->unsignedSmallInteger('cancellation_cutoff_override')->nullable(); // hours
            $table->boolean('deposit_required_override')->nullable(); // null = inherit

            // Notes
            $table->text('notes')->nullable(); // e.g. "Only available on weekdays at this branch"

            $table->timestamps();

            // Foreign Keys
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            // Indexes
            $table->index(['location_id', 'is_available']);
            $table->index(['service_id', 'is_available']);
        });

        // Migrate existing salon_service data to location_service
        // Map salon_id → location_id via salons.location_id
        // Skip if salons table doesn't exist (fresh database)
        if (Schema::hasTable('salons')) {
            DB::statement("
                INSERT INTO location_service (
                    location_id, service_id, is_available, online_booking_enabled,
                    price_override, duration_override, buffer_before_override, buffer_after_override,
                    min_booking_notice_override, cancellation_cutoff_override, deposit_required_override,
                    notes, created_at, updated_at
                )
                SELECT
                    s.location_id,
                    ss.service_id,
                    ss.is_available,
                    ss.online_booking_enabled,
                    ss.price_override,
                    ss.duration_override,
                    ss.buffer_before_override,
                    ss.buffer_after_override,
                    ss.min_booking_notice_override,
                    ss.cancellation_cutoff_override,
                    ss.deposit_required_override,
                    ss.notes,
                    ss.created_at,
                    ss.updated_at
                FROM salon_service ss
                INNER JOIN salons s ON ss.salon_id = s.id
                WHERE s.location_id IS NOT NULL
            ");

            // Drop the old salon_service table
            Schema::dropIfExists('salon_service');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate salon_service table
        Schema::create('salon_service', function (Blueprint $table) {
            // Keys
            $table->uuid('salon_id');
            $table->uuid('service_id');
            $table->primary(['salon_id', 'service_id']);

            // Availability (null = inherit from provider)
            $table->boolean('is_available')->nullable()->default(null);
            $table->boolean('online_booking_enabled')->nullable()->default(null);

            // Branch-level overrides (null = use provider-level default)
            $table->decimal('price_override', 12, 2)->nullable();
            $table->unsignedSmallInteger('duration_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_before_override')->nullable(); // minutes
            $table->unsignedSmallInteger('buffer_after_override')->nullable(); // minutes

            // Booking policy overrides
            $table->unsignedSmallInteger('min_booking_notice_override')->nullable(); // hours
            $table->unsignedSmallInteger('cancellation_cutoff_override')->nullable(); // hours
            $table->boolean('deposit_required_override')->nullable(); // null = inherit

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Foreign Keys
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            // Indexes
            $table->index(['salon_id', 'is_available']);
            $table->index(['service_id', 'is_available']);
        });

        // Migrate location_service data back to salon_service
        // Map location_id → salon_id via salons.location_id
        DB::statement("
            INSERT INTO salon_service (
                salon_id, service_id, is_available, online_booking_enabled,
                price_override, duration_override, buffer_before_override, buffer_after_override,
                min_booking_notice_override, cancellation_cutoff_override, deposit_required_override,
                notes, created_at, updated_at
            )
            SELECT
                s.id as salon_id,
                ls.service_id,
                ls.is_available,
                ls.online_booking_enabled,
                ls.price_override,
                ls.duration_override,
                ls.buffer_before_override,
                ls.buffer_after_override,
                ls.min_booking_notice_override,
                ls.cancellation_cutoff_override,
                ls.deposit_required_override,
                ls.notes,
                ls.created_at,
                ls.updated_at
            FROM location_service ls
            INNER JOIN salons s ON ls.location_id = s.id
        ");

        // Drop the location_service table
        Schema::dropIfExists('location_service');
    }
};
