# Catalog Domain Architecture

## Overview

The Catalog Domain is responsible for product information and taxonomy. It knows product details but never knows stock levels. This separation allows multiple domains (AI, Inventory, Procurement, Analytics) to reference products without coupling to inventory logic.

## Core Principle

> **Catalog knows products. Inventory knows stock. Procurement knows purchasing.**

These are three different businesses with different responsibilities.

## Domain Boundaries

```
Catalog
    │
    ├── Products
    ├── Brands
    ├── Categories
    ├── SKUs
    └── Attributes

Inventory (separate domain)
    │
    ├── Quantities
    ├── Locations
    ├── Expiry
    └── Movements

Procurement (separate domain)
    │
    ├── Suppliers
    ├── Purchase Orders
    ├── Receiving
    └── Invoices
```

## Why Catalog Matters

Products touch almost everything:

- **Bookings** → Uses products
- **Supplier** → Supplies products
- **Inventory** → Stores products
- **Finance** → Purchases products
- **Analytics** → Measures product performance
- **AI** → Recommends products
- **Customer** → Receives product recommendations
- **Specialist** → Uses products

Without a Catalog domain, each of these would need to define "product" differently, creating inconsistency and coupling.

## Catalog Domain Components

### 1. Product

Core product entity with polymorphic ownership.

**Fields:**
- `owner_type` / `owner_id` - Who owns this product
- `name` - Product name
- `description` - Product description
- `sku` - Stock Keeping Unit
- `barcode` - UPC/EAN barcode
- `brand_id` - Brand reference
- `category_id` - Category reference
- `manufacturer` - Manufacturer name
- `attributes` - JSON for custom attributes
- `images` - Product images
- `status` - active/inactive/discontinued
- `metadata` - Additional data

**Product Ownership Examples:**

```
Global Catalog
└── Platform (owner_type: App\Models\Platform)

Private Formula
└── Salon (owner_type: App\Models\Salon)

Supplier Product
└── Supplier (owner_type: App\Models\Supplier)
```

**Why Ownership Matters:**
- AI can recommend from Platform catalog
- AI can recommend from Salon catalog
- AI can recommend from Supplier catalog
- Access control based on ownership
- Revenue sharing based on ownership

### 2. Brand

Product brand information.

**Fields:**
- `name` - Brand name
- `logo` - Brand logo
- `website` - Brand website
- `description` - Brand description
- `country` - Country of origin
- `metadata` - Additional data

### 3. Category

Product categorization (hierarchical).

**Fields:**
- `name` - Category name
- `parent_id` - Parent category (for hierarchy)
- `slug` - URL-friendly identifier
- `description` - Category description
- `icon` - Category icon
- `metadata` - Additional data

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

### 4. SKU (Stock Keeping Unit)

Unique identifier for product variants.

**Fields:**
- `product_id` - Parent product
- `sku_code` - Unique SKU code
- `variant_attributes` - Size, color, etc.
- `barcode` - Variant-specific barcode
- `price` - Variant price
- `cost` - Variant cost
- `status` - active/inactive

**Example:**
```
Product: American Crew Fiber
├── SKU: AC-FIBER-85G (85g)
├── SKU: AC-FIBER-100G (100g)
└── SKU: AC-FIBER-150G (150g)
```

### 5. Product Attribute

Flexible attribute system for custom product properties.

**Fields:**
- `name` - Attribute name (e.g., "Hair Type")
- `type` - Attribute type (text, number, select, etc.)
- `options` - Available options for select types
- `is_required` - Whether attribute is required
- `is_filterable` - Whether attribute can be used for filtering

**Product Attribute Values:**
- `product_id` - Product reference
- `attribute_id` - Attribute reference
- `value` - Attribute value

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
- Inventory only knows SKU IDs and quantities
- Catalog provides product details for display

### Procurement Integration

Procurement purchases from suppliers:
- Supplier uploads products → Catalog
- Salon decides what to stock → Inventory
- Purchase orders reference SKUs

### Finance Integration

Finance tracks product purchases:
- Product cost in Finance
- Revenue from product sales
- Supplier payments via Procurement

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

## Future Roadmap

### Near-Term

1. **Basic Product Model**
   - Product with polymorphic owner
   - Brand and Category models
   - Basic SKU support

2. **Attribute System**
   - Flexible attribute definitions
   - Product attribute values

3. **API Endpoints**
   - CRUD operations for products
   - Filtering and search
   - Category browsing

### Medium-Term

1. **Product Relationships**
   - Related products (upsell/cross-sell)
   - Product bundles
   - Product substitutions

2. **Product Reviews**
   - Customer reviews
   - Specialist reviews
   - Rating aggregation

3. **Product Media**
   - Image gallery
   - Video support
   - Document attachments

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

`2026_08_06_150000_create_catalog_domain_tables.php`

Creates:
- `products` table with polymorphic owner
- `brands` table
- `categories` table (hierarchical)
- `skus` table
- `product_attributes` table define
- `product_attribute_values` table

## Code Structure

```
backend/app/Domain/Catalog/
├── Models/
│   ├── Product.php
│   ├── Brand.php
│   ├── Category.php
│   ├── SKU.php
│   ├── ProductAttribute.php
│   └── ProductAttributeValue.php
├── Services/
│   └── ProductService.php
└── Events/
    ├── ProductCreated.php
    ├── ProductUpdated.php
    └── ProductDeleted.php
```

## Testing & Verification

**Verify that:**
- Products can be owned by different entity types
- Categories support hierarchical relationships
- SKUs correctly represent product variants
- Attributes are flexible and type-safe
- Inventory domain can reference SKUs without knowing product details

## Conclusion

The Catalog Domain is not about inventory management. It's about defining the language of products for the entire platform.

By establishing Catalog now, future domains (AI, Inventory, Procurement, Analytics) can reference products without creating their own product definitions. This prevents duplication and ensures consistency across the platform.

**Catalog is the product vocabulary of yo.salon.**
