<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('customer_id');
            $table->uuid('subject_id')->nullable()->after('subject_type');
            $table->uuid('salon_id')->nullable()->after('provider_id');
            $table->uuid('service_id')->nullable()->after('salon_id');
            
            $table->index(['subject_type', 'subject_id']);
            $table->index('salon_id');
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex('salon_id');
            $table->dropIndex('service_id');
            $table->dropColumn(['subject_type', 'subject_id', 'salon_id', 'service_id']);
        });
    }
};
