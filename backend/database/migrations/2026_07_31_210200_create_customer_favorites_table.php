<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->uuid('salon_id')->nullable();
            $table->string('favoritable_type'); // Provider, Specialist, Service
            $table->uuid('favoritable_id');
            $table->uuid('collection_id')->nullable();
            $table->timestamp('added_at');
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('salon_id')->references('id')->on('salons')->onDelete('cascade');
            $table->foreign('collection_id')->references('id')->on('customer_collections')->onDelete('set null');
            
            $table->index(['customer_id', 'favoritable_type', 'favoritable_id']);
            $table->index(['customer_id', 'collection_id']);
            $table->index(['favoritable_type', 'favoritable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_favorites');
    }
};
