# Commerce/Catalog Domain Architecture

## Overview

The Commerce/Catalog Domain is the canonical product database for the platform. It knows product details but never knows stock levels. This separation allows AI, Inventory, Procurement, and other domains to reference products without coupling to inventory logic.

## Core Principle

> **Catalog knows products. Inventory knows stock. Procurement knows purchasing.**

Catalog is a pure product information system. It doesn't care about quantities, locations, or purchase orders.

## Domain Structure

```
Commerce/
└── Catalog/
    ├── Brand.php
    ├── Category.php
    ├── Product.php
    ├── ProductVariant.php
    ├── ProductImage.php
    └── ProductAttribute.php
```

## Catalog Domain Components

### 1. Brand

Represents a product brand. **A brand is NOT a supplier.**

**Example:**
- Brand: American Crew
- Supplier: Beauty Wholesale Uganda

**Purpose:** Brands are independent entities that can be distributed by multiple suppliers.

**Fields:**
- `name` - Brand name
- `slug` - URL-friendly identifier
- `logo` - Brand logo
- `website` - Brand website
- `description` - Brand description
- `country` - Country of origin
- `is_active` - Active status
- `metadata` - Additional data

**Relationships:**
- `products` - Products in this brand

### 2. Category

Hierarchical product categorization.

**Example Hierarchy:**
```
Hair Care
├── Shampoo
│   ├── Dry Hair
│   └── Oily Hair
├── Conditioner
└── Styling
    └── Gel
```

**Fields:**
- `parent_id` - Parent category (for hierarchy)
- `name` - Category name
- `slug` - URL-friendly identifier
- `description` - Category description
- `icon` - Category icon
- `image` - Category image
- `sort_order` - Display order
- `is_active` - Active status
- `metadata` - Additional data

**Relationships:**
- `parent` - Parent category
- `children` - Child categories
- `products` - Products in this category

**Methods:**
- `getFullPathAttribute()` - Returns "Hair Care > Shampoo > Dry Hair"

### 3. Product

Core product entity with polymorphic ownership.

**Fields:**
- `owner_type` / `owner_id` - Who owns this product
- `brand_id` - Brand reference
- `category_id` - Category reference
- `name` - Product name
- `slug` - URL-friendly identifier
- `sku` - Base SKU
- `barcode` - UPC/EAN barcode
- `description` - Full description
- `short_description` - Short description
- `manufacturer` - Manufacturer name
- `status` - draft/active/inactive/discontinued
- `visibility` - public/private/hidden
- `is_trackable` - Can be tracked in inventory
- `is_service_product` - Is a service (not physical)
- `requires_batch_tracking` - Needs batch tracking
- `requires_expiry_tracking` - Needs expiry tracking
- `metadata` - Additional data

**Product Ownership Examples:**

```
Global Catalog
└── Platform (owner_type: App\Models\Platform)

Private Formula
└── Salon (owner_type: App\Models\Salon)

Supplier Product
└── Supplier (owner_type: App\Models\Provider, type: supplier)
```

**Why Ownership Matters:**
- AI can recommend from Platform catalog
- AI can recommend from Salon catalog
- AI can recommend from Supplier catalog
- Access control based on ownership
- Revenue sharing based on ownership

**Relationships:**
- `owner` - Polymorphic owner
- `brand` - Brand
- `category` - Category
- `variants` - Product variants (SKUs)
- `images` - Product images
- `attributes` - Custom attributes

**Methods:**
- `isPlatformOwned()` - Check if owned by platform
- `isSupplierOwned()` - Check if owned by supplier
- `isSalonOwned()` - Check if owned by salon

### 4. ProductVariant (SKU)

Represents a specific variant of a product.

**Fields:**
- `product_id` - Parent product
- `sku_code` - Unique SKU code
- `barcode` - Variant-specific barcode
- `name` - Variant name
- `variant_attributes` - Size, color, etc. (JSON)
- `price` - Variant price
- `cost` - Variant cost
- `compare_at_price` - Original price (for sales)
- `weight` - Weight
- `weight_unit` - kg, g, lb, oz
- `status` - active/inactive/out_of_stock
- `metadata` - Additional data

**Example:**
```
Product: American Crew Fiber
├── SKU: AC-FIBER-85G (85g)
├── SKU: AC-FIBER-100G (100g)
└── SKU: AC-FIBER-150G (150g)
```

**Relationships:**
- `product` - Parent product
- `images` - Variant-specific images

### 5. ProductImage

Polymorphic image for Product or ProductVariant.

**Fields:**
- `imageable_type` / `imageable_id` - Product or ProductVariant
- `url` - Image URL
- `alt_text` - Alt text for accessibility
- `sort_order` - Display order
- `is_primary` - Is primary image
- `metadata` - Additional data

**Relationships:**
- `imageable` - Polymorphic (Product or ProductVariant)

### 6. ProductAttribute

Flexible attribute system for custom product properties.

**Fields:**
- `product_id` - Product reference
- `attribute_name` - Attribute name (e.g., "Hair Type")
- `attribute_type` - text/number/select/multiselect/boolean/date
- `value` - Attribute value (JSON)
- `display_value` - Human-readable value
- `sort_order` - Display order
- `is_filterable` - Can be used for filtering
- `metadata` - Additional data

**Example Attributes:**
- Hair Type: Dry, Oily, Normal
- Size: 85g, 100g, 150g
- Material: Natural, Synthetic
- Scent: Unscented, Mint, Citrus

**Relationships:**
- `product` - Parent product

## Integration Points

### AI Integration

AI recommends products based on:
- Customer preferences
- Specialist expertise
- Booking context
- Product availability (from Inventory)

**AI queries Catalog, not Inventory.**

### Inventory Integration

Inventory stores quantities for SKUs:
- Inventory never knows product details
- Inventory only knows SKU IDs
- Catalog provides product details for display

### Procurement Integration

Suppliers upload products:
- Supplier adds products to Catalog
- Products have owner_type: Provider (type: supplier)
- Salons can browse supplier catalogs

### Provider Integration

Supplier is a Provider with type: supplier.

**Provider Types:**
- `TYPE_SALON` - Salon
- `TYPE_SPECIALIST` - Specialist
- `TYPE_SUPPLIER` - Supplier (NEW)

## Design Principles

### 1. Separation of Concerns

Catalog knows product information. Inventory knows stock levels. Procurement knows purchasing.

**Catalog never knows:**
- How many items in stock
- Where items are located
- When items expire
- Purchase order details

### 2. Polymorphic Ownership

Products can be owned by any entity:
- Platform (global catalog)
- Salon (private formulas)
- Supplier (supplier products)

This enables:
- Multi-tenant product catalogs
- Revenue sharing
- Access control
- AI recommendations across catalogs

### 3. Flexible Attributes

Not all products have the same attributes. Hair products need "hair type." Tools need "material." Services don't need physical attributes.

**Solution:** JSON-based attribute system with typed attributes.

### 4. SKU Variant Support

One product can have multiple variants (size, color, etc.). Each variant has its own SKU, price, and inventory tracking.

### 5. Future-Proofing

Fields like `is_trackable`, `requires_batch_tracking`, `requires_expiry_tracking` establish the language for future Inventory domain integration without implementing the behavior today.

## Database Schema

### Core Tables

```
brands
├── id (UUID)
├── name
├── slug (unique)
├── logo
├── website
├── description
├── country
├── is_active
└── metadata

categories
├── id (UUID)
├── parent_id (FK to categories)
├── name
├── slug (unique)
├── description
├── icon
├── image
├── sort_order
├── is_active
└── metadata

products
├── id (UUID)
├── owner_type / owner_id (polymorphic)
├── brand_id (FK)
├── category_id (FK)
├── name
├── slug
├── sku
├── barcode
├── description
├── short_description
├── manufacturer
├── status (draft/active/inactive/discontinued)
├── visibility (public/private/hidden)
├── is_trackable
├── is_service_product
├── requires_batch_tracking
├── requires_expiry_tracking
└── metadata

product_variants
├── id (UUID)
├── product_id (FK)
├── sku_code (unique)
├── barcode
├── name
├── variant_attributes (JSON)
├── price
├── cost
├── compare_at_price
├── weight
├── weight_unit
├── status (active/inactive/out_of_stock)
└── metadata

product_images
├── id (UUID)
├── imageable_type / imageable_id (polymorphic)
├── url
├── alt_text
├── sort_order
├── is_primary
└── metadata

product_attributes
├── id (UUID)
├── product_id (FK)
├── attribute_name
├── attribute_type
├── value (JSON)
├── display_value
├── sort_order
├── is_filterable
└── metadata
```

## Future Roadmap

### Near-Term (Foundation Only)

**Current Scope - Complete:**
- Core models (Brand, Category, Product, Variant, Image, Attribute)
- Polymorphic ownership
- Hierarchical categories
- Flexible attributes

**No:**
- Stock tracking (Inventory domain)
- Purchase orders (Procurement domain)
- Supplier workflows
- Inventory counts
- Warehouses

### Medium-Term (When Business Needs It)

1. **Product Relationships**
   - Related products (upsell/cross-sell)
   - Product bundles
   - Product substitutions

2. **Product Reviews**
   - Customer reviews
   - Specialist reviews
   - Rating aggregation

3. **Product Media**
   - Video support
   - Document attachments
   - 360° images

### Long-Term

1. **Product Lifecycle**
   - Versioning
   - Discontinuation tracking
   - Seasonal products

2. **Product Analytics**
   - View tracking
   - Conversion tracking
   - Recommendation performance

3. **Multi-language Support**
   - Product names in multiple languages
   - Localized descriptions

## Migration Strategy

### Initial Migration

`2026_08_06_180000_create_commerce_catalog_tables.php`

Creates:
- `brands` table
- `categories` table (hierarchical)
- `products` table (polymorphic owner)
- `product_variants` table
- `product_images` table (polymorphic)
- `product_attributes` table

## Code Structure

```
backend/app/Domain/Commerce/Catalog/
├── Brand.php
├── Category.php
├── Product.php
├── ProductVariant.php
├── ProductImage.php
└── ProductAttribute.php
```

## Testing Strategy

### Unit Tests

- Brand model relationships
- Category hierarchy (parent/child)
- Product polymorphic ownership
- Product variant creation
- Attribute flexibility
- Image polymorphic relationships

### Integration Tests

- Product creation with variants
- Category hierarchy navigation
- Polymorphic ownership queries
- Attribute filtering

## Conclusion

The Commerce/Catalog Domain is the product vocabulary of yo.salon. By establishing Catalog now, future domains (AI, Inventory, Procurement, Analytics) can reference products without creating their own product definitions.

**Catalog defines the language. Inventory defines the stock. Procurement defines the purchasing.**

This separation prevents duplication and ensures consistency across the platform.
