<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if salons table doesn't exist (fresh database)
        if (!Schema::hasTable('salons')) {
            return;
        }

        // Step 1: Add location_id column to salons (if not exists)
        if (!Schema::hasColumn('salons', 'location_id')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->uuid('location_id')->nullable()->after('provider_id');
            });
        }

        // Step 2: Create a location for each existing salon
        $salons = DB::table('salons')->get();
        
        foreach ($salons as $salon) {
            // Skip if salon has no address
            if (empty($salon->address)) {
                continue;
            }

            // Generate a slug for the location (handle null city)
            $cityPart = !empty($salon->city) ? $salon->city : 'location';
            $locationSlug = Str::slug($salon->name . '-' . $cityPart);
            
            // Ensure slug is unique for this provider
            $existingSlugCount = DB::table('locations')
                ->where('provider_id', $salon->provider_id)
                ->where('slug', $locationSlug)
                ->count();
            
            if ($existingSlugCount > 0) {
                $locationSlug = $locationSlug . '-' . time();
            }

            // Create the location
            DB::table('locations')->insert([
                'id' => $salon->id,
                'provider_id' => $salon->provider_id,
                'name' => $salon->name,
                'slug' => $locationSlug,
                'address' => $salon->address,
                'city' => $salon->city,
                'country' => $salon->country ?? null,
                'phone' => $salon->phone ?? null,
                'email' => $salon->email ?? null,
                'website' => $salon->website ?? null,
                'latitude' => $salon->lat ?? null,
                'longitude' => $salon->lng ?? null,
                'timezone' => $salon->timezone ?? null,
                'currency' => $salon->currency ?? null,
                'opening_hours' => $salon->opening_hours ?? null,
                'is_active' => $salon->active ?? true,
                'created_at' => $salon->created_at,
                'updated_at' => $salon->updated_at,
            ]);

            // Update salon to reference the new location
            DB::table('salons')->where('id', $salon->id)->update(['location_id' => $salon->id]);
        }

        // Step 3: Add foreign key constraint (if not exists)
        if (!Schema::hasColumn('salons', 'location_id')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->foreign('location_id')->references('id')->on('locations')->onDelete('set null');
            });
        }

        // Step 4: Mark old location columns as deprecated (if exists and not already renamed)
        if (Schema::hasColumn('salons', 'address') && !Schema::hasColumn('salons', 'address_deprecated')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->renameColumn('address', 'address_deprecated');
            });
        }
        if (Schema::hasColumn('salons', 'city') && !Schema::hasColumn('salons', 'city_deprecated')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->renameColumn('city', 'city_deprecated');
            });
        }
        if (Schema::hasColumn('salons', 'lat') && !Schema::hasColumn('salons', 'lat_deprecated')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->renameColumn('lat', 'lat_deprecated');
            });
        }
        if (Schema::hasColumn('salons', 'lng') && !Schema::hasColumn('salons', 'lng_deprecated')) {
            Schema::table('salons', function (Blueprint $table) {
                $table->renameColumn('lng', 'lng_deprecated');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the renames
        Schema::table('salons', function (Blueprint $table) {
            $table->renameColumn('address_deprecated', 'address');
            $table->renameColumn('city_deprecated', 'city');
            $table->renameColumn('lat_deprecated', 'lat');
            $table->renameColumn('lng_deprecated', 'lng');
        });

        // Drop foreign key and column
        Schema::table('salons', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });

        // Note: We don't delete the created locations in rollback
        // as they may have been modified after migration
    }
};
