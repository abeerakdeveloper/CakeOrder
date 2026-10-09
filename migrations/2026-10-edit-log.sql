-- ============================================================
-- BestPOS / Salman Bakers: change log for order edits.
-- Run this AFTER migrations/2026-10-order-types.sql, once.
-- Take a database backup first.
--
-- Every saved change to an order (items, quantities, prices, weights,
-- charges, discount, customer, delivery date and time) adds one row here.
-- Nothing else in the database changes.
-- ============================================================

CREATE TABLE order_change_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bill_no INT NOT NULL,
  changed_by VARCHAR(50) NULL,
  changed_at DATETIME NOT NULL,
  old_total DECIMAL(12,2) NULL,
  new_total DECIMAL(12,2) NULL,
  details TEXT NULL,
  INDEX idx_order_change_bill (bill_no)
);
