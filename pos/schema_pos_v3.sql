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

-- Ticket and refund numbers. Reading the last number and adding one is a race
-- two tills can lose: both read the same number, both try to write it, and the
-- unique index turns the second sale into an error at the counter. One row per
-- day per series, bumped atomically, so the number is handed out rather than
-- guessed.
CREATE TABLE IF NOT EXISTS pos_counters (
  name VARCHAR(40) NOT NULL PRIMARY KEY,
  seq  INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- What the salon paints with, and what it can paint. Both are set up once and
-- then referenced at the chair: the brand list narrows the colour conversation,
-- the design photos are the book a guest flips through while they decide.
CREATE TABLE IF NOT EXISTS pos_polish_brands (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80) NOT NULL UNIQUE,
  is_active     TINYINT(1)  NOT NULL DEFAULT 0,
  display_order INT         NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO pos_polish_brands (name, is_active, display_order) VALUES
 ('OPI',0,1),('DND',0,2),('BND',0,3),('Gelish',0,4),('Kiara Sky',0,5),
 ('LDS',0,6),('SNS',0,7),('Essie',0,8),('China Glaze',0,9),('CND Shellac',0,10);

CREATE TABLE IF NOT EXISTS pos_nail_designs (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  image_url     VARCHAR(400) NOT NULL,
  caption       VARCHAR(160) NOT NULL DEFAULT '',
  display_order INT NOT NULL DEFAULT 0,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
