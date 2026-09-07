<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->json('achievements')->nullable()->after('certifications');
            $table->json('career_timeline')->nullable()->after('achievements');
            $table->integer('follower_count')->default(0)->after('review_count');
            $table->integer('favorite_count')->default(0)->after('follower_count');
        });
    }

    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn(['achievements', 'career_timeline', 'follower_count', 'favorite_count']);
        });
    }
};
