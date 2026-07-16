# Warehouse System Flow

## 1. **INBOUND PROCESS (Stock Masuk Gudang)**

### A. Purchase Order to Supplier
- [x] **Admin Gudang** creates `Pembelian` (PO) to `Supplier`
- [x] Status: `is_published = false` (draft)
- [x] Stock goes to `StockPembelian` (warehouse staging)

### B. Receiving Goods
- Verify physical goods vs PO
- [x] Click **Publish** on `Pembelian`
- [x] Triggers:
  - Move from `StockPembelian` → `Stock` (global pool)
  - Calculate HPP (Average/FIFO)
  - Update `stock_value`
  - Create `StockMovement` (type: 'in')
  - Deduct from `Kas`

**Result:** Stock now in **Global Warehouse Pool** (no owner yet)

---

## 2. **OUTLET REQUESTS STOCK**

### A. Create Request Order
- [x] **Owner/Outlet** creates `RequestOrder`
- [x] Select products + qty needed
- [x] Status: `pending`

### B. Warehouse Verifies
- [x] **Admin Gudang** opens verification
- [x] Checks `qty_available` for each product
- [x] Decides:
  - **Approved** - full qty given
  - **Partial** - only some qty given
  - **Rejected** - no stock
- [x] System **RESERVES** stock (`qty_reserved` increases)
- [x] Status: `approved`/`partial`/`rejected`

---

## 3. **PICKING & PACKING**

### A. Generate Picking List
- [x] System creates `PickingList` from approved `RequestOrder`
- [x] Shows which stock to take (by batch/expired/location)
- Prioritizes: **FIFO** (oldest first)

### B. Picker Process
- **Petugas Picking** scans items
- [x] Updates `qty_picked` per item
- [x] Status: `in_progress` → `completed`

---

## 4. **OUTBOUND (Kirim ke Outlet)**

### A. Generate Delivery Order
- [x] System creates `DeliveryOrder` from completed picking
- [x] Code: DO######

### B. Send to Outlet
- [x] Click **Send**
- [x] Triggers:
  - `Stock.allocate()` - reduces global warehouse stock
  - Creates/updates `OwnerStock` - outlet now owns it
  - Create `StockMovement` (type: 'out')
  - Unreserves remaining

### C. Outlet Receives
- [x] **Owner** confirms received
- Upload photo proof (optional)
- Status: `delivered`

**Result:** Stock transferred from **Global Pool** → **Owner's Stock**

---

## 5. **STOCK VISIBILITY**

### Global Warehouse (`Stock` table)
```
Total Stock = qty (physical)
Available = qty - qty_reserved
Reserved = booked for approved requests
```

### Owner Stock (`OwnerStock` table)
- Per outlet
- Used for POS transactions
- Can integrate with existing `Penjualan` system

---

## KEY DIFFERENCES FROM OLD SYSTEM

| Old (POS) | New (Warehouse) |
|-----------|-----------------|
| Cart-based | Request-based |
| Direct stock deduction | Reserve → Pick → Allocate |
| Stock per outlet from start | Global pool → allocate when needed |
| `Stock` has `outlet_id` | `Stock` is global, `OwnerStock` is per outlet |

---

## YOUR QUESTION CLARIFIED

> "outlet create po asking gudang and then goes to supplier if run out of stock right?"

**Not quite. Correct flow:**

1. **Warehouse** creates PO → **Supplier** (when warehouse needs stock)
2. **Outlet** creates `RequestOrder` → **Warehouse** (when outlet needs stock)
3. **Warehouse** verifies:
   - If stock available → approve & send
   - If stock low → partial approval OR reject
4. **Warehouse** (separately) monitors `min_stock` → create new PO to supplier

**Outlets don't directly create PO to suppliers.** They only request from warehouse.

---
