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
        if (Schema::hasTable('business_category_service_templates')) {
            return;
        }

        Schema::create('business_category_service_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_category_id');
            $table->uuid('service_template_id');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_recommended')->default(true);
            $table->timestamps();

            $table->foreign('business_category_id')->references('id')->on('business_categories')->onDelete('cascade');
            $table->foreign('service_template_id')->references('id')->on('service_templates')->onDelete('cascade');
            $table->unique(['business_category_id', 'service_template_id']);
            $table->index('business_category_id');
            $table->index('service_template_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_category_service_templates');
    }
};
