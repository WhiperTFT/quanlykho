# Database Schema: quanlykho

The **quanlykho** database is a relational schema optimized for historical data integrity through JSON snapshots and dynamic inventory calculation via SQL Views.

---

## 🛠️ Core Entities

### 1. User & Access Control
- **`users`**: Stores authentication credentials, encrypted signatures (X/Y position settings), and permission JSON.
- **`permissions`**: (Logical) Permission keys are stored within the `users` table as a JSON array (e.g., `["view_inventory", "create_order"]`).

### 2. Product Master Data
- **`products`**: Central registry of items.
- **`categories`**: Grouping for products.
- **`units`**: Measurement units (e.g., Piece, Box, Kg).

### 3. Business Partners
- **`partners`**: Unified table for **Customers** and **Suppliers**.
    - `type`: `SET('customer', 'supplier', 'company')`.
    - Includes stored generated columns `is_customer` and `is_supplier` for optimized performance indexing.

---

## 📦 Transactional Schema

### Procurement (Mua hàng)
- **`sales_orders`**: (Semantically Purchase Orders). Stores grand totals and status (`draft`, `ordered`, `fully_received`).
- **`sales_order_details`**: Individual line items. Each row maps to a `product_id`.

### Sales (Bán hàng)
- **`sales_quotes`**: Quotes sent to customers.
- **`sales_quote_details`**: Line items for quotes. Inventory is considered "reserved" once a quote status is set to `accepted`.

### Logistics
- **`drivers`**: Registry of delivery personnel.
- **`driver_trips`**: Tracking of individual delivery routes and outcomes.
- **`lalamove_reports`**: Imported cost data from Third-Party Logistics.

---

## 📈 Analytical Views (The Logic Pillar)

The system avoids storing "Stock Quantities" in a static column to prevent synchronization bugs. Instead, it uses specialized Views:

### `v_inventory_in`
Calculates the total items received from all `fully_received` or `partially_received` `sales_orders`.

### `v_inventory_allocated`
Calculates items committed to customers via `accepted` `sales_quotes` that have not yet been physically delivered.

### `v_inventory_atp` (Available to Promise)
The final inventory status:
`Total Stock In` - `Total Allocated` = **Available to Promise**.

---

## ⚙️ Conventions
- **PKeys**: Always `int(10) unsigned AUTO_INCREMENT`.
- **TKeys**: `created_at` and `updated_at` timestamps are present on all primary tables.
- **Pricing**: Store in `DECIMAL(18,2)` to avoid floating-point rounding errors.
