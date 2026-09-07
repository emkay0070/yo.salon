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
        Schema::table('staff', function (Blueprint $table) {
            $table->text('bio')->nullable();
            $table->json('specialties')->nullable();
            $table->json('languages')->nullable();
            $table->json('qualifications')->nullable();
            $table->json('portfolio')->nullable();
            $table->decimal('rating', 3, 2)->default(0);
            $table->integer('review_count')->default(0);
            $table->string('handle')->nullable()->unique();
            $table->index('handle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex(['handle']);
            $table->dropColumn(['bio', 'specialties', 'languages', 'qualifications', 'portfolio', 'rating', 'review_count', 'handle']);
        });
    }
};
