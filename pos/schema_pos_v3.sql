-- ============================================================
--  POS v3 — refunds.
--
--  A void cancels a whole ticket. A refund gives back part of one:
--  the guest keeps the pedicure and hands back the polish. Refunds
--  live in their own tables rather than as negative sales, so a
--  ticket's history stays readable and "money in" stays money in.
-- ============================================================

CREATE TABLE IF NOT EXISTS pos_refunds (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  refund_no  VARCHAR(24)  NOT NULL UNIQUE,
  sale_id    INT          NOT NULL,
  amount     DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- goods and services, before tax
  tax        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tip        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- what actually went back to the guest
  method     ENUM('cash','card','gift','other') NOT NULL DEFAULT 'cash',
  reason     VARCHAR(255) NOT NULL DEFAULT '',
  admin_id   INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sale (sale_id),
  INDEX idx_when (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_refund_items (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  refund_id    INT NOT NULL,
  sale_item_id INT NOT NULL,
  qty          INT NOT NULL DEFAULT 1,
  amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- before tax
  tax          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tip          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  INDEX idx_refund (refund_id),
  INDEX idx_item (sale_item_id)
) ENGINE=InnoDB;
