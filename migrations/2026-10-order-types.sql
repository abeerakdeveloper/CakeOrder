-- ============================================================
-- BestPOS / Salman Bakers: order types, box groups, extra charges,
-- weighed sweet boxes, invoice fields and the cake product list.
--
-- HOW TO RUN (once, on the live database):
--   1. Take a database backup first (phpMyAdmin > Export).
--   2. Run this whole file in phpMyAdmin (SQL tab) or with the mysql client.
--   3. Run:  SHOW CREATE TABLE cake_order;  and check the new columns exist.
--
-- What it does:
--   * Adds columns to cake_order. Existing rows are not changed; the new
--     columns are NULL for old orders. The old column order_type still stores
--     the order SOURCE (walk-in, phone, ...). The new column sale_type stores
--     the order TYPE.
--   * Makes tiers (the weight) a decimal, so 1.5 pounds can be saved.
--   * Adds cake_product: the products that appear in the New Order cake picker.
--
-- Do not run it twice: the ADD COLUMN lines would fail on the second run.
-- ============================================================

ALTER TABLE cake_order
  ADD COLUMN sale_type VARCHAR(12) NULL,       -- cake | lunch | sweet | eatable | other | charge | weighed
  ADD COLUMN box_group INT NULL,               -- box group number inside an order (1, 2, ...)
  ADD COLUMN box_qty INT NULL,                 -- number of boxes in that group
  ADD COLUMN kitchen_note TEXT NULL,           -- note for the kitchen on this line
  ADD COLUMN material VARCHAR(100) NULL,       -- cake material / covering (optional)
  ADD COLUMN delivery_branch VARCHAR(100) NULL; -- branch the order is delivered from

-- Weight can be decimal (1.5 pounds). Whole numbers already saved stay the same.
ALTER TABLE cake_order MODIFY COLUMN tiers DECIMAL(10,2) NULL DEFAULT 1;

-- Products that are cakes. Filled from the Cake Products page (admin).
-- inv_id must match inventory.inv_id. If your inventory.inv_id is not an
-- integer, tell the developer before running this line.
CREATE TABLE cake_product (
  inv_id INT NOT NULL PRIMARY KEY,
  added_by VARCHAR(50) NULL,
  added_at DATETIME NULL
);
