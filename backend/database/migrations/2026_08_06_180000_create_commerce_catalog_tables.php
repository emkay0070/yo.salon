<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create brands table
        Schema::create('brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('is_active');
            $table->index('country');
        });

        // Create categories table (hierarchical)
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        // Create products table with polymorphic owner
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type'); // Platform, Provider (Supplier), Salon
            $table->uuid('owner_id');
            $table->uuid('brand_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('status')->default('draft'); // draft, active, inactive, discontinued
            $table->string('visibility')->default('private'); // public, private, hidden
            $table->boolean('is_trackable')->default(false);
            $table->boolean('is_service_product')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_expiry_tracking')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['owner_type', 'owner_id']);
            $table->index('brand_id');
            $table->index('category_id');
            $table->index('slug');
            $table->index('status');
            $table->index('visibility');
            $table->unique(['owner_type', 'owner_id', 'slug']);
        });

        // Create product_variants table (SKUs)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('sku_code')->unique();
            $table->string('barcode')->nullable();
            $table->string('name')->nullable();
            $table->json('variant_attributes')->nullable(); // size, color, etc.
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('weight', 8, 4)->nullable();
            $table->string('weight_unit')->nullable();
            $table->string('status')->default('active'); // active, inactive, out_of_stock
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id');
            $table->index('sku_code');
            $table->index('barcode');
            $table->index('status');
        });

        // Create product_images table (polymorphic)
        Schema::create('product_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('imageable_type'); // Product or ProductVariant
            $table->uuid('imageable_id');
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['imageable_type', 'imageable_id']);
            $table->index('is_primary');
            $table->index('sort_order');
        });

        // Create product_attributes table
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id');
            $table->string('attribute_name'); // e.g., "Hair Type"
            $table->string('attribute_type'); // text, number, select, multiselect, boolean, date
            $table->json('value'); // attribute value
            $table->string('display_value')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_filterable')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index('product_id');
            $table->index('attribute_name');
            $table->index('is_filterable');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('brands');
    }
};
