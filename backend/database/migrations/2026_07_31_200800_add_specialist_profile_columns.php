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
            if (!Schema::hasColumn('specialists', 'bio')) {
                $table->text('bio')->nullable()->after('role');
            }
            if (!Schema::hasColumn('specialists', 'skills')) {
                $table->json('skills')->nullable()->after('bio');
            }
            if (!Schema::hasColumn('specialists', 'years_experience')) {
                $table->integer('years_experience')->nullable()->after('skills');
            }
            if (!Schema::hasColumn('specialists', 'certifications')) {
                $table->json('certifications')->nullable()->after('years_experience');
            }
            if (!Schema::hasColumn('specialists', 'portfolio')) {
                $table->json('portfolio')->nullable()->after('certifications');
            }
            if (!Schema::hasColumn('specialists', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('portfolio');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('specialists', function (Blueprint $table) {
            $table->dropColumn(['bio', 'skills', 'years_experience', 'certifications', 'portfolio', 'profile_image']);
        });
    }
};
