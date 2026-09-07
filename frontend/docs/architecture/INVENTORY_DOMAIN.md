# Inventory Domain Architecture

## Overview

The Inventory Domain is responsible for stock tracking and movement. It knows quantities, locations, and movements but never knows product details. This separation allows Inventory to focus on stock management while Catalog handles product information.

## Core Principle

> **Inventory knows stock. Catalog knows products. Procurement knows purchasing.**

Inventory is a pure stock management system. It doesn't care what a product is, only how many exist and where.

## Domain Boundaries

```
Catalog (separate domain)
    │
    ├── Products
    ├── Brands
    └── Categories

Inventory
    │
    ├── Stock Quantities
    ├── Locations
    ├── Expiry Tracking
    ├── Batch Tracking
    └── Movements

Procurement (separate domain)
    │
    ├── Suppliers
    ├── Purchase Orders
    └── Invoices
```

## Why Inventory Matters

Inventory touches:
- **Procurement** → Receives stock
- **Bookings** → Consumes products
- **Finance** → Tracks inventory value
- **Analytics** → Measures stock turnover
- **AI** → Recommends based on availability
- **Salons** → Manage stock levels

Without a dedicated Inventory domain, stock logic would be scattered across Procurement, Bookings, and Finance, creating inconsistency and coupling.

## Inventory Domain Components

### 1. Stock Item

Represents stock quantity for a specific SKU at a specific location.

**Fields:**
- `sku_id` - SKU reference (from Catalog)
- `location_type` / `location_id` - Where this stock is (Salon, Warehouse, etc.)
- `quantity_on_hand` - Current quantity
- `quantity_reserved` - Reserved for orders
- `quantity_available` - On hand minus reserved
- `reorder_level` - Minimum quantity before reorder
- `reorder_quantity` - Quantity to reorder
- `last_counted_at` - Last physical inventory count
- `metadata` - Additional data

**Polymorphic Locations:**
- Salon (owner_type: App\Models\Salon)
- Warehouse (owner_type: App\Models\Warehouse)
- Supplier (owner_type: App\Models\Supplier)

### 2. Stock Movement

Tracks every stock movement for audit trail.

**Fields:**
- `stock_item_id` - Stock item reference
- `movement_type` - receipt, issue, adjustment, transfer, return
- `quantity` - Movement quantity (positive or negative)
- `reference_type` / `reference_id` - What caused this movement (Purchase Order, Booking, etc.)
- `user_id` - Who performed the movement
- `notes` - Movement notes
- `metadata` - Additional data

**Movement Types:**
- `receipt` - Stock received (from Procurement)
- `issue` - Stock issued (to Booking)
- `adjustment` - Manual adjustment (physical count correction)
- `transfer` - Stock transferred between locations
- `return` - Stock returned to supplier
- `damage` - Stock damaged/lost

**Rule:** Never update stock directly. Always create a movement.

### 3. Batch

Tracks batch information for products with expiry or lot tracking.

**Fields:**
- `sku_id` - SKU reference
- `batch_number` - Manufacturer batch number
- `expiry_date` - Expiry date
- `manufacture_date` - Manufacture date
- `quantity` - Batch quantity
- `location_type` / `location_id` - Batch location
- `status` - active/expired/recalled
- `metadata` - Additional data

**Purpose:** FIFO (First-In-First-Out) stock allocation, expiry tracking, recall management.

### 4. Stock Reservation

Reserves stock for specific orders before actual consumption.

**Fields:**
- `stock_item_id` - Stock item reference
- `reference_type` / `reference_id` - What this reservation is for (Booking, etc.)
- `quantity` - Reserved quantity
- `expires_at` - When reservation expires
- `status` - active/consumed/expired
- `metadata` - Additional data

**Purpose:** Prevent overselling. Reserve stock when booking is created, consume when booking is completed.

### 5. Stock Adjustment

Records manual stock corrections (physical inventory counts).

**Fields:**
- `stock_item_id` - Stock item reference
- `expected_quantity` - What system thought was in stock
- `actual_quantity` - What was actually counted
- `difference` - Difference (actual - expected)
- `reason` - Reason for adjustment (theft, damage, error, etc.)
- `performed_by` - Who performed the count
- `approved_by` - Who approved the adjustment
- `metadata` - Additional data

**Purpose:** Audit trail for physical inventory counts. Explains why stock changed without a normal movement.

## Integration Points

### Catalog Integration

Inventory references SKUs from Catalog:
- Inventory never knows product names, brands, categories
- Inventory only knows SKU IDs
- Catalog provides product details for display

### Procurement Integration

Procurement receives stock:
- Purchase Order → Stock Receipt Movement
- Invoice verification → Stock confirmation
- Returns → Stock Return Movement

### Booking Integration

Bookings consume products:
- Booking created → Stock Reservation
- Booking completed → Stock Issue Movement
- Booking cancelled → Reservation released

### Finance Integration

Finance tracks inventory value:
- Stock receipts increase inventory asset
- Stock issues decrease inventory asset
- Adjustments create loss/gain entries
- Expiry creates write-off entries

### AI Integration

AI checks availability:
- AI queries Inventory for available quantities
- AI recommends products that are in stock
- AI suggests reorder points

## Design Principles

### 1. Separation of Concerns

Inventory knows stock. Catalog knows products.

**Inventory never knows:**
- Product names
- Product brands
- Product categories
- Product images
- Product descriptions

### 2. Movement-Based Updates

Never update stock quantities directly. Always create a movement.

**Wrong:**
```php
$stock->quantity_on_hand = 100;
$stock->save();
```

**Right:**
```php
StockMovement::create([
    'stock_item_id' => $stock->id,
    'movement_type' => 'receipt',
    'quantity' => 50,
    'reference_type' => PurchaseOrder::class,
    'reference_id' => $po->id,
]);
```

### 3. Audit Trail

Every stock change must have:
- Movement record
- Reference to what caused it
- Who performed it
- When it happened

### 4. Polymorphic Locations

Stock can be anywhere:
- Salon
- Warehouse
- Supplier
- Mobile unit
- Future locations

### 5. Reservation System

Reserve stock before consuming:
- Prevents overselling
- Allows accurate availability checks
- Supports backorders

## Future Roadmap

### Near-Term

1. **Basic Stock Tracking**
   - Stock Item model
   - Stock Movement model
   - Basic quantity tracking

2. **Location Support**
   - Polymorphic locations
   - Stock transfer between locations

3. **Reservation System**
   - Stock Reservation model
   - Integration with Bookings

### Medium-Term

1. **Batch Tracking**
   - Batch model
   - Expiry tracking
   - FIFO allocation

2. **Stock Adjustments**
   - Stock Adjustment model
   - Approval workflow
   - Physical count integration

3. **Low Stock Alerts**
   - Reorder level monitoring
   - Automatic notifications
   - Procurement triggers

### Long-Term

1. **Multi-Warehouse Support**
   - Warehouse hierarchy
   - Inter-warehouse transfers
   - Centralized stock allocation

2. **Serial Number Tracking**
   - Individual item tracking
   - Warranty tracking
   - Anti-theft

3. **Inventory Valuation**
   - FIFO/LIFO/Weighted Average costing
   - Inventory value reports
   - Finance integration

## Migration Strategy

### Initial Migration

`2026_08_06_160000_create_inventory_domain_tables.php`

Creates:
- `stock_items` table
- `stock_movements` table
- `batches` table
- `stock_reservations` table
- `stock_adjustments` table

## Code Structure

```
backend/app/Domain/Inventory/
├── Models/
│   ├── StockItem.php
│   ├── StockMovement.php
│   ├── Batch.php
│   ├── StockReservation.php
│   └── StockAdjustment.php
├── Services/
│   ├── StockService.php
│   └── ReservationService.php
└── Events/
    ├── StockReceived.php
    ├── StockIssued.php
    ├── StockAdjusted.php
    └── LowStockAlert.php
```

## Testing & Verification

**Verify that:**
- Stock quantities only change via movements
- Movements have proper references
- Reservations prevent overselling
- Batches correctly track expiry
- Transfers move stock between locations
- Adjustments have proper approval workflow

## Conclusion

The Inventory Domain is a pure stock management system. It doesn't care what products are, only how many exist and where.

By separating Inventory from Catalog and Procurement, each domain can focus on its core responsibility while communicating via events and references.

**Inventory is the stock management engine of yo.salon.**
