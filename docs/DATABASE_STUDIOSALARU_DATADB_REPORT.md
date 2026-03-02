# Database Deep Scan Report: studiosalaru_datadb.sql

**Source:** `studiosalaru_datadb.sql` (MySQL dump – database name in dump: **studiosalaru_lk**)  
**Prefix:** All tables use the `sma_` prefix (Sale Management Application).

---

## 1. ITEMS (Products)

### Main table: `sma_products`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Primary key (AUTO_INCREMENT, up to ~602) |
| `code` | varchar(50) | Unique product code (e.g. AP-N-12X15, ST-PH-10X15) |
| `name` | varchar(255) | Product name |
| `unit` | int(11) | FK → `sma_units.id` (unit of measure) |
| `cost` | decimal(25,4) | Cost price |
| `price` | decimal(25,4) | Selling price |
| `alert_quantity` | decimal(15,4) | Low-stock alert (default 20) |
| `image` | varchar(255) | Image filename (default no_image.png) |
| **`category_id`** | int(11) | **FK → sma_categories.id** (main category) |
| **`subcategory_id`** | int(11) | **FK → sma_categories.id** (subcategory; nullable) |
| `quantity` | decimal(15,4) | Stock quantity |
| `tax_rate` | int(11) | Tax rate id |
| `details` | varchar(1000) | Description |
| `warehouse` | int(11) | Default warehouse |
| `type` | varchar(55) | e.g. 'standard' |
| `brand` | int(11) | FK → sma_brands.id (nullable) |
| `slug` | varchar(55) | URL slug |
| `hide` | tinyint(1) | Hide from lists (0/1) |
| `hide_pos` | tinyint(1) | Hide from POS (0/1) |
| … | … | Plus: cf1–cf6, suppliers, promo fields, sale_unit, purchase_unit, etc. |

**Relationship:** Each product belongs to one **category** (`category_id`) and optionally one **subcategory** (`subcategory_id`). Both point to `sma_categories`.

---

### Related item tables

| Table | Purpose |
|-------|---------|
| **sma_product_variants** | Variants per product: `product_id`, `name`, `cost`, `price`, `quantity` |
| **sma_product_prices** | Price groups: `product_id`, `price_group_id`, `price` |
| **sma_product_photos** | Extra product images |
| **sma_combo_items** | Combo products: `product_id`, `item_code`, `quantity`, `unit_price` |
| **sma_warehouses_products** | Stock per warehouse |
| **sma_warehouses_products_variants** | Variant stock per warehouse |

---

## 2. CATEGORIES AND SUB-CATEGORIES

### Table: `sma_categories`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Primary key (AUTO_INCREMENT, up to ~41) |
| `code` | varchar(55) | Category code (e.g. PH-FR, ST-PH, M-PR) |
| `name` | varchar(55) | Display name |
| `image` | varchar(55) | Image filename (nullable) |
| **`parent_id`** | int(11) | **Parent category: 0 = top-level, non-zero = subcategory of that id** |
| `slug` | varchar(55) | URL slug |
| `description` | varchar(255) | Description |

**Hierarchy:**
- **Top-level categories:** `parent_id = 0` (e.g. FRAME, STUDIO PHOTO, SUBLIMATION PRINT, LAMINATING, DIGITAL PHOTO, CRYSTAL FRAME, MEDIA PRINT, PHOTO COLLAGE, etc.)
- **Sub-categories:** `parent_id = <id of parent>`. Example: category id 9 is "MEDIA PRINT"; id 11 is "EPSON MEDIA PRINT" with `parent_id = 9`.

**How items link to categories:**
- `sma_products.category_id` → main category (usually a top-level or the category you show in POS)
- `sma_products.subcategory_id` → optional subcategory (also in `sma_categories`)

So: **categories and sub-categories are the same table**; sub-categories are rows where `parent_id` is the parent category’s `id`.

---

## 3. POS SALES DETAILS

### Sales header: `sma_sales`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Sale id (AUTO_INCREMENT, ~56k+) |
| `date` | timestamp | Sale date/time |
| **`reference_no`** | varchar(55) | **Sale ref (e.g. SALE/POS2024/12/34352)** |
| `customer_id` | int(11) | FK to customer |
| `customer` | varchar(55) | Customer name (e.g. Walk-in Customer) |
| `biller_id` | int(11) | Biller user id |
| `biller` | varchar(55) | Biller name (e.g. Studio Salaru) |
| `warehouse_id` | int(11) | Warehouse |
| `total` | decimal(25,4) | Subtotal |
| `product_discount`, `order_discount`, `total_discount` | decimal | Discounts |
| `product_tax`, `order_tax`, `total_tax` | decimal | Tax |
| **`grand_total`** | decimal(25,4) | **Final total** |
| `sale_status` | varchar(20) | e.g. 'completed' |
| `payment_status` | varchar(20) | e.g. 'paid' |
| **`pos`** | tinyint(1) | **1 = POS sale, 0 = non-POS** |
| `paid` | decimal(25,4) | Amount paid |
| `payment_method` | varchar(55) | Payment method |
| `total_items` | smallint(6) | Number of line items |
| … | … | Plus: due_date, return_id, rounding, suspend_note, api, shop, etc. |

**POS identification:** Use `sma_sales.pos = 1` to get only POS sales.

---

### Sales line items: `sma_sale_items`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Line id (AUTO_INCREMENT, ~119k+) |
| **`sale_id`** | int(10) unsigned | **FK → sma_sales.id** |
| **`product_id`** | int(10) unsigned | **FK → sma_products.id** |
| `product_code` | varchar(55) | Product code (denormalized) |
| `product_name` | varchar(255) | Product name (denormalized) |
| `product_type` | varchar(20) | e.g. 'standard' |
| `net_unit_price` | decimal(25,4) | Unit price (after discount/tax logic) |
| `unit_price` | decimal(25,4) | Unit price |
| **`quantity`** | decimal(15,4) | **Quantity sold** |
| `warehouse_id` | int(11) | Warehouse |
| `item_tax`, `tax_rate_id`, `tax` | - | Tax fields |
| `discount`, `item_discount` | - | Discount |
| **`subtotal`** | decimal(25,4) | **Line total** |
| `product_unit_id`, `product_unit_code` | - | Unit |
| `comment` | varchar(255) | Line comment |
| `cgst`, `sgst`, `igst` | decimal | GST fields |

**Relationship:**  
`sma_sales` (1) ←→ (N) `sma_sale_items`  
Each `sma_sale_items` row links to one product via `product_id` and to one sale via `sale_id`. Products link to categories via `sma_products.category_id` and `subcategory_id`.

---

### Payments: `sma_payments`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int(11) | Payment id |
| `date` | timestamp | Payment date |
| **`sale_id`** | int(11) | **FK → sma_sales.id** (nullable for returns/purchases) |
| `reference_no` | varchar(50) | Payment reference (e.g. IPAY2024/12/34352) |
| **`paid_by`** | varchar(20) | **'cash', 'CC', 'other', etc.** |
| `amount` | decimal(25,4) | Payment amount |
| `type` | varchar(20) | e.g. 'received', 'returned' |
| `pos_paid` | decimal(25,4) | POS paid amount |
| `pos_balance` | decimal(25,4) | POS balance |
| `note` | varchar(1000) | Note (e.g. ONLINE TRANSFER NDB BANK) |

Use `sma_payments.sale_id` and `sma_payments.type = 'received'` to get payments for a sale.

---

### POS-specific tables

| Table | Purpose |
|-------|---------|
| **sma_pos_register** | Cash register sessions: `user_id`, `cash_in_hand`, `status` (open/close), `total_cash`, `total_cash_submitted`, `closed_at`, `closed_by` |
| **sma_pos_settings** | POS config: `pos_id`, `default_category`, `default_customer`, `default_biller`, receipt printer, keyboard, rounding, etc. |
| **sma_suspended_bills** | Suspended (held) POS bills |
| **sma_suspended_items** | Line items for suspended bills |

---

## 4. QUICK REFERENCE: HOW THEY CONNECT

```
sma_categories (parent_id = 0 for main, parent_id = id for sub)
       ↑
       │ category_id, subcategory_id
       │
sma_products ←—— sma_sale_items (product_id, sale_id) ——→ sma_sales (pos=1 for POS)
       ↑                                    ↑
       │                                    │
sma_brands, sma_units                    sma_payments (sale_id, paid_by, amount)
```

---

## 5. USEFUL QUERIES (conceptual)

- **Items with category and subcategory names**  
  Join `sma_products` with `sma_categories` twice (as category and subcategory) on `category_id` and `subcategory_id`.

- **POS sales only**  
  `WHERE s.pos = 1` on `sma_sales s`.

- **POS sales with line items and product/category**  
  `sma_sales` → `sma_sale_items` → `sma_products` → `sma_categories` (and again for subcategory).

- **Payments for a sale**  
  `sma_payments` WHERE `sale_id = ?` AND `type = 'received'`.

- **Categories and sub-categories**  
  Top-level: `WHERE parent_id = 0`.  
  Sub-categories: `WHERE parent_id = <parent_id>` or join `sma_categories` to itself on `parent_id`.

---

## 6. SUMMARY

| Area | Tables | Key links |
|------|--------|-----------|
| **Items** | sma_products, sma_product_variants, sma_product_prices, sma_product_photos | products.category_id, subcategory_id → categories |
| **Categories / Sub-categories** | sma_categories | parent_id (0 = main, else subcategory of that id) |
| **POS sales** | sma_sales (pos=1), sma_sale_items, sma_payments | sale_id, product_id → products → categories |
| **POS register/settings** | sma_pos_register, sma_pos_settings | user_id, pos_id |
| **Suspended POS** | sma_suspended_bills, sma_suspended_items | Same idea as sales + items |

All of the above is derived from the structure and sample data in **studiosalaru_datadb.sql** (database **studiosalaru_lk**).
