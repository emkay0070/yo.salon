<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Provider;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing salons to providers
        $salons = DB::table('salons')->get();

        foreach ($salons as $salon) {
            // Create provider record
            $providerId = DB::table('providers')->insertGetId([
                'id' => $salon->id, // Use salon ID as provider ID for simplicity
                'type' => 'salon',
                'status' => 'active',
                'display_name' => $salon->name,
                'slug' => $salon->slug,
                'description' => $salon->description,
                'logo' => $salon->logo ?? null,
                'cover_image' => $salon->cover_image ?? null,
                'phone' => $salon->phone ?? null,
                'email' => $salon->email ?? null,
                'website' => $salon->website ?? null,
                'location' => $salon->location ?? null,
                'social_links' => $salon->social_links ?? null,
                'settings' => $salon->settings ?? null,
                'rating' => $salon->rating ?? 0,
                'review_count' => $salon->review_count ?? 0,
                'active' => $salon->active ?? true,
                'created_at' => $salon->created_at,
                'updated_at' => $salon->updated_at,
            ], 'id');

            // Update salon to reference provider
            DB::table('salons')
                ->where('id', $salon->id)
                ->update(['provider_id' => $providerId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove provider_id from salons
        DB::table('salons')->update(['provider_id' => null]);

        // Delete providers created from salons
        DB::table('providers')->where('type', 'salon')->delete();
    }
};
