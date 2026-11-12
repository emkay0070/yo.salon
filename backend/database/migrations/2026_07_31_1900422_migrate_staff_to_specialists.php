<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate staff to specialists
        $staff = \DB::table('staff')->get();
        
        foreach ($staff as $staffMember) {
            // Create specialist record
            \DB::table('specialists')->insert([
                'id' => $staffMember->id,
                'name' => $staffMember->name,
                'phone' => $staffMember->phone,
                'email' => $staffMember->email,
                'bio' => null,
                'specialties' => $staffMember->specializations,
                'languages' => null,
                'qualifications' => null,
                'portfolio' => null,
                'photo' => $staffMember->photo,
                'rating' => 0,
                'review_count' => 0,
                'active' => $staffMember->active,
                'created_at' => $staffMember->created_at,
                'updated_at' => $staffMember->updated_at,
            ]);

            // NOTE: Specialist assignment/pivot data is seeded separately by
            // DefaultSchedulesSeeder after the specialist_assignments table is created.

        }

        // Update bookings to use specialist_id (if column exists)
        if (Schema::hasColumn('bookings', 'specialist_id')) {
            \DB::statement('UPDATE bookings SET specialist_id = staff_id WHERE specialist_id IS NULL');
        }

        // Update reviews to use specialist_id (if column exists)
        if (Schema::hasColumn('reviews', 'specialist_id')) {
            \DB::statement('UPDATE reviews SET specialist_id = staff_id WHERE specialist_id IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            //
        });
    }
};
