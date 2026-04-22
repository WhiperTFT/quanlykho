# Inventory Calculation Logic: Available to Promise (ATP)

This document explains the mathematical and technical logic used to determine stock availability in the **quanlykho** system.

---

## 🧐 The Philosophy
In standard warehouse software, stock is often a static number (e.g., `quantity_in_hand`). In this Mini ERP, we use a **Dynamic Movement Model**. This approach ensures that stock is never "lost" due to database sync errors and always provides an audit trail.

---

## 📐 The ATP Formula

The system derives **Available to Promise (ATP)** stock using three distinct types of events:

$$ATP = \text{Total Purchases (Received)} - \text{Total Sales (Committed)}$$

### 1. Supply: Total Purchases
- **Source**: `sales_order_details` (which act as Purchase Orders).
- **Condition**: Only items from orders with `status IN ('ordered', 'partially_received', 'fully_received')` are counted.
- **SQL View**: `v_inventory_in`

### 2. Demand: Total Sales Reservations
- **Source**: `sales_quote_details`.
- **Condition**: Only quotes with `status = 'accepted'` are counted.
- **Allocation**: When a quote is accepted, it "reserves" the items immediately, even if they haven't shipped yet.
- **SQL View**: `v_inventory_out` (or `v_inventory_allocated`)

### 3. Result: Available to Promise
The system subtracts the **Demand** from the **Supply**.
- **Negative ATP**: If the result is negative, it indicates "Over-selling" or "Back-ordering".
- **Visual Alert**: The UI highlights items with low or negative ATP in Red to alert procurement staff.

---

## 💻 Technical Implementation Example

The core logic is implemented in `process/inventory_serverside.php` using a `UNION ALL` query:

```sql
SELECT 
    product_id, 
    SUM(qty_in) as total_in, 
    SUM(qty_out) as total_out,
    (SUM(qty_in) - SUM(qty_out)) as available_qty
FROM (
    -- Goods coming in from suppliers
    SELECT product_id, quantity as qty_in, 0 as qty_out FROM sales_order_details ...
    UNION ALL
    -- Goods reserved for customers
    SELECT product_id, 0 as qty_in, quantity as qty_out FROM sales_quote_details ...
) as movement_history
GROUP BY product_id;
```

---

## ⚠️ Critical Notes for ERP Upgrade
- **Physical vs. Available**: Physical stock (items actually in the box) is only reduced when a **PXK (Phiếu Xuất Kho)** is generated. ATP is reduced much earlier (at the Quote Acceptance stage) to prevent double-booking items to two different customers.
- **Data Types**: All calculations must use `DECIMAL` precision to avoid float rounding errors in multi-item orders.
