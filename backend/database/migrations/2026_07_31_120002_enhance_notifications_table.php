<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Add category, priority, icon, and action_url
            $table->string('category')->nullable()->after('type');
            $table->string('priority')->default('normal')->after('category'); // low, normal, high, urgent
            $table->string('icon')->nullable()->after('priority');
            $table->string('action_url')->nullable()->after('data');
            
            // Add indexes for better querying
            $table->index(['category', 'read_at']);
            $table->index(['priority', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['category', 'read_at']);
            $table->dropIndex(['priority', 'read_at']);
            $table->dropColumn(['category', 'priority', 'icon', 'action_url']);
        });
    }
};
