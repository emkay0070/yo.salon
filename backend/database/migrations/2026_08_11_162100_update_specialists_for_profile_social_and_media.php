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
        Schema::table('specialists', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('languages');
            $table->uuid('photo_media_id')->nullable()->after('photo');
            
            // Optional: Foreign key constraint to media table if it exists and uses UUIDs
            // $table->foreign('photo_media_id')->references('id')->on('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn(['social_links', 'photo_media_id']);
        });
    }
};
