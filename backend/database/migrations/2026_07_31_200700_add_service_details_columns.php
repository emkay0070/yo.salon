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
        Schema::table('services', function (Blueprint $table) {
            $table->text('preparation')->nullable()->after('description');
            $table->text('aftercare')->nullable()->after('preparation');
            $table->text('cancellation_policy')->nullable()->after('cancellation_cutoff_hours');
            $table->json('images')->nullable()->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['preparation', 'aftercare', 'cancellation_policy', 'images']);
        });
    }
};
