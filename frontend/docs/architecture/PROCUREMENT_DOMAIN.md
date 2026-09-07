# Procurement Domain Architecture

## Overview

The Procurement Domain is responsible for supplier purchasing and receiving. It manages suppliers, purchase orders, receiving processes, and invoices. It knows purchasing but never knows product details or stock levels.

## Core Principle

> **Procurement knows purchasing. Catalog knows products. Inventory knows stock.**

Procurement is a pure purchasing system. It manages the business of buying from suppliers.

## Domain Boundaries

```
Catalog (separate domain)
    │
    └── Products

Inventory (separate domain)
    │
    └── Stock

Procurement
    │
    ├── Suppliers
    ├── Purchase Orders
    ├── Receiving
    ├── Invoices
    └── Returns

Finance
    │
    └── Payments
```

## Why Procurement Matters

Procurement touches:
- **Catalog** → Supplier uploads products
- **Inventory** → Receives stock
- **Finance** → Pays suppliers
- **Analytics** → Measures supplier performance
- **Salons** → Order supplies

Without a dedicated Procurement domain, purchasing logic would be scattered across Inventory and Finance, creating inconsistency and coupling.

## Procurement Domain Components

### 1. Supplier

Represents a product supplier (a type of Provider).

**Fields:**
- `provider_id` - Reference to Provider (type: supplier)
- `business_name` - Supplier business name
- `contact_person` - Contact person
- `email` - Contact email
- `phone` - Contact phone
- `address` - Supplier address
- `tax_id` - Tax identification number
- `payment_terms` - Payment terms (net 30, net 60, etc.)
- `credit_limit` - Credit limit
- `rating` - Supplier rating
- `status` - active/inactive/suspended
- `metadata` - Additional data

**Note:** Supplier is a `Provider` with `type: supplier`. No new architecture needed.

### 2. Purchase Order

Represents an order to a supplier.

**Fields:**
- `supplier_id` - Supplier reference
- `order_number` - Unique PO number
- `order_date` - Order date
- `expected_delivery_date` - Expected delivery
- `actual_delivery_date` - Actual delivery
- `status` - draft/submitted/confirmed/partial/cancelled
- `subtotal` - Subtotal
- `tax_amount` - Tax amount
- `shipping_cost` - Shipping cost
- `total_amount` - Total amount
- `currency` - Currency (UGX)
- `notes` - Order notes
- `created_by` - Who created the order
- `approved_by` - Who approved the order
- `metadata` - Additional data

**Status Flow:**
```
draft → submitted → confirmed → received → paid
                    ↓
                 cancelled
```

### 3. Purchase Order Line Item

Individual items in a purchase order.

**Fields:**
- `purchase_order_id` - Purchase Order reference
- `sku_id` - SKU reference (from Catalog)
- `quantity_ordered` - Quantity ordered
- `quantity_received` - Quantity received
- `unit_price` - Unit price
- `tax_rate` - Tax rate
- `tax_amount` - Tax amount
- `line_total` - Line total
- `notes` - Line notes
- `metadata` - Additional data

### 4. Receiving

Documents the receipt of goods from a supplier.

**Fields:**
- `purchase_order_id` - Purchase Order reference
- `receiving_number` - Unique receiving number
- `received_date` - Date received
- `received_by` - Who received the goods
- `status` - pending/partial/complete
- `notes` - Receiving notes
- `metadata` - Additional data

### 5. Receiving Line Item

Individual items received.

**Fields:**
- `receiving_id` - Receiving reference
- `purchase_order_line_item_id` - PO line item reference
- `sku_id` - SKU reference
- `quantity_received` - Quantity received
- `batch_number` - Batch number (if applicable)
- `expiry_date` - Expiry date (if applicable)
- `condition` - Condition of goods (good/damaged)
- `notes` - Line notes
- `metadata` - Additional data

**Integration:** Creates Stock Movement in Inventory domain.

### 6. Invoice

Represents a supplier invoice.

**Fields:**
- `purchase_order_id` - Purchase Order reference
- `supplier_id` - Supplier reference
- `invoice_number` - Supplier invoice number
- `invoice_date` - Invoice date
- `due_date` - Due date
- `subtotal` - Subtotal
- `tax_amount` - Tax amount
- `shipping_cost` - Shipping cost
- `total_amount` - Total amount
- `currency` - Currency
- `status` - pending/partial/paid/overdue/disputed
- `notes` - Invoice notes
- `metadata` - Additional data

### 7. Invoice Payment

Records payments to suppliers.

**Fields:**
- `invoice_id` - Invoice reference
- `payment_date` - Payment date
- `amount` - Payment amount
- `payment_method` - Payment method
- `reference` - Payment reference
- `notes` - Payment notes
- `metadata` - Additional data

**Integration:** Creates Payout in Finance domain.

### 8. Return

Documents returns to suppliers.

**Fields:**
- `purchase_order_id` - Purchase Order reference
- `supplier_id` - Supplier reference
- `return_number` - Unique return number
- `return_date` - Return date
- `reason` - Return reason (damage/wrong/expired/etc.)
- `status` - pending/authorized/received/refunded
- `total_amount` - Total return amount
- `notes` - Return notes
- `metadata` - Additional data

**Integration:** Creates Stock Return Movement in Inventory domain.

## Integration Points

### Catalog Integration

Suppliers upload products:
- Supplier adds products to Catalog
- Products have owner_type: Supplier
- Salons can browse supplier catalogs

### Inventory Integration

Procurement receives stock:
- Receiving creates Stock Receipt Movement
- Returns create Stock Return Movement
- Inventory updates quantities

### Finance Integration

Procurement pays suppliers:
- Invoice creates Settlement (Platform owes Supplier)
- Payment creates Payout
- Finance tracks supplier liabilities

### Provider Integration

Supplier is a Provider:
- Uses existing Provider model
- Type: supplier
- No new architecture

## Design Principles

### 1. Separation of Concerns

Procurement knows purchasing. Inventory knows stock. Catalog knows products.

**Procurement never knows:**
- Product details (name, brand, category)
- Stock quantities
- Stock locations
- Product images

### 2. Document-Based Workflow

Every action creates a document:
- Purchase Order
- Receiving
- Invoice
- Return

This provides audit trail and supports reconciliation.

### 3. Status-Based State Machine

Documents flow through defined states:
- Purchase Order: draft → submitted → confirmed → received → paid
- Invoice: pending → partial → paid
- Return: pending → authorized → received → refunded

### 4. Supplier as Provider

Supplier is not a new entity. It's a Provider with type: supplier.

**Benefits:**
- Reuses existing Provider infrastructure
- Consistent identity management
- Unified contact management

### 5. Integration via Events

Procurement publishes events:
- `PurchaseOrderCreated`
- `PurchaseOrderConfirmed`
- `GoodsReceived`
- `InvoiceReceived`
- `PaymentMade`

Other domains listen and react.

## Future Roadmap

### Near-Term

1. **Basic Purchasing**
   - Supplier model (Provider type)
   - Purchase Order model
   - Purchase Order Line Items

2. **Receiving Process**
   - Receiving model
   - Receiving Line Items
   - Integration with Inventory

3. **Invoice Management**
   - Invoice model
   - Invoice Payments
   - Integration with Finance

### Medium-Term

1. **Supplier Portal**
   - Supplier self-service portal
   - Product upload interface
   - Order status tracking

2. **Approval Workflows**
   - PO approval workflow
   - Invoice approval workflow
   - Return authorization workflow

3. **Supplier Performance**
   - On-time delivery tracking
   - Quality tracking
   - Cost analysis

### Long-Term

1. **Automated Reordering**
   - Low stock triggers PO
   - Predictive ordering
   - Bulk ordering optimization

2. **Supplier Collaboration**
   - Supplier inventory visibility
   - Joint demand forecasting
   - Consignment inventory

3. **Multi-Currency Support**
   - International suppliers
   - Currency conversion
   - FX tracking

## Migration Strategy

### Initial Migration

`2026_08_06_170000_create_procurement_domain_tables.php`

Creates:
- `suppliers` table (references providers)
- `purchase_orders` table
- `purchase_order_line_items` table
- `receivings` table
- `receiving_line_items` table
- `invoices` table
- `invoice_payments` table
- `returns` table

**Note:** No migration needed for Supplier entity - uses existing providers table with type column.

## Code Structure

```
backend/app/Domain/Procurement/
├── Models/
│   ├── Supplier.php (extends Provider)
│   ├── PurchaseOrder.php
│   ├── PurchaseOrderLineItem.php
│   ├── Receiving.php
│   ├── ReceivingLineItem.php
│   ├── Invoice.php
│   ├── InvoicePayment.php
│   └── Return.php
├── Services/
│   ├── PurchaseOrderService.php
│   ├── ReceivingService.php
│   └── InvoiceService.php
└── Events/
    ├── PurchaseOrderCreated.php
    ├── PurchaseOrderConfirmed.php
    ├── GoodsReceived.php
    └── InvoiceReceived.php
```

## Testing & Verification

**Verify that:**
- Suppliers are correctly typed as Providers
- Purchase Orders flow through status states correctly
- Receiving creates proper stock movements
- Invoices create proper settlements
- Returns create proper stock return movements
- Events are published for integration

## Conclusion

The Procurement Domain is a pure purchasing system. It manages the business of buying from suppliers without knowing product details or stock levels.

By separating Procurement from Catalog and Inventory, each domain can focus on its core responsibility while communicating via events and references.

**Procurement is the purchasing engine of yo.salon.**
