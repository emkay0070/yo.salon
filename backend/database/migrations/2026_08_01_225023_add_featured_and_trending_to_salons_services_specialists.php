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
        Schema::table('salons', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('city');
            $table->decimal('trending_score', 10, 2)->default(0)->after('is_featured');
            $table->integer('view_count')->default(0)->after('trending_score');
            $table->index('is_featured');
            $table->index('trending_score');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('active');
            $table->decimal('trending_score', 10, 2)->default(0)->after('is_featured');
            $table->integer('view_count')->default(0)->after('trending_score');
            $table->index('is_featured');
            $table->index('trending_score');
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('active');
            $table->decimal('trending_score', 10, 2)->default(0)->after('is_featured');
            $table->integer('view_count')->default(0)->after('trending_score');
            $table->index('is_featured');
            $table->index('trending_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['trending_score']);
            $table->dropColumn(['is_featured', 'trending_score', 'view_count']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['trending_score']);
            $table->dropColumn(['is_featured', 'trending_score', 'view_count']);
        });

        Schema::table('specialists', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['trending_score']);
            $table->dropColumn(['is_featured', 'trending_score', 'view_count']);
        });
    }
};
