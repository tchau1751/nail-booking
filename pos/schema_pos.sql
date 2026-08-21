-- ============================================================
--  DIAMOND NAIL & SPA - POS module schema
--  Run once:  mysql -u root nail_booking < pos/schema_pos.sql
--  Or open:   http://localhost/nail-booking/pos/install.php
-- ============================================================
USE nail_booking;

-- Retail products (polish, files, gift cards, add-ons ...)
CREATE TABLE IF NOT EXISTS pos_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  sku VARCHAR(60) DEFAULT '',
  barcode VARCHAR(60) DEFAULT '',
  category VARCHAR(80) DEFAULT 'Retail',
  price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  cost DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  stock_qty INT NOT NULL DEFAULT 0,
  low_stock_at INT NOT NULL DEFAULT 3,
  is_taxable TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  display_order INT NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_barcode (barcode),
  KEY idx_active (is_active, category)
) ENGINE=InnoDB;

-- One row per completed / voided transaction
CREATE TABLE IF NOT EXISTS pos_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_no VARCHAR(24) NOT NULL,
  appointment_id INT DEFAULT NULL,
  customer_name VARCHAR(180) DEFAULT '',
  customer_phone VARCHAR(30) DEFAULT '',
  technician_id INT DEFAULT NULL,
  cashier_id INT DEFAULT NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tip_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  change_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('completed','voided','refunded') NOT NULL DEFAULT 'completed',
  note VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_sale_no (sale_no),
  KEY idx_created (created_at),
  KEY idx_status (status),
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  FOREIGN KEY (technician_id)  REFERENCES technicians(id)  ON DELETE SET NULL,
  FOREIGN KEY (cashier_id)     REFERENCES admin_users(id)  ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_sale_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  item_type ENUM('service','product','custom') NOT NULL DEFAULT 'product',
  ref_id INT DEFAULT NULL,           -- services.id or pos_products.id
  name VARCHAR(180) NOT NULL,
  unit_price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  qty INT NOT NULL DEFAULT 1,
  discount DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  tax DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  technician_id INT DEFAULT NULL,
  KEY idx_sale (sale_id),
  FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sale_id INT NOT NULL,
  method ENUM('cash','card','gift','other') NOT NULL DEFAULT 'cash',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  reference VARCHAR(80) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sale (sale_id),
  FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cash drawer opens / pay-ins / pay-outs
CREATE TABLE IF NOT EXISTS pos_cash_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('open','pay_in','pay_out','close') NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  reason VARCHAR(255) DEFAULT '',
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_settings (
  id INT PRIMARY KEY DEFAULT 1,
  currency_symbol VARCHAR(6) NOT NULL DEFAULT '$',
  tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000,   -- percent, e.g. 8.875
  tax_label VARCHAR(30) NOT NULL DEFAULT 'Sales Tax',
  tax_services TINYINT(1) NOT NULL DEFAULT 0,     -- most states do not tax nail services
  receipt_header VARCHAR(255) DEFAULT 'Diamond Nail & Spa',
  receipt_footer VARCHAR(255) DEFAULT 'Thank you for visiting - see you again soon!',
  tip_presets VARCHAR(60) NOT NULL DEFAULT '15,18,20,25',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO pos_settings (id) VALUES (1);

INSERT IGNORE INTO pos_products (id,name,sku,barcode,category,price,cost,stock_qty,display_order) VALUES
 (1,'Gel Polish - Ruby Red','GP-RED','1000000001','Polish',18.00,7.50,12,1),
 (2,'Gel Polish - Nude Blush','GP-NUDE','1000000002','Polish',18.00,7.50,10,2),
 (3,'Cuticle Oil Pen','CUT-OIL','1000000003','Care',12.00,4.00,20,3),
 (4,'Nail File 4-Way','FILE-4W','1000000004','Care',5.00,1.20,40,4),
 (5,'Hand Cream 50ml','HC-50','1000000005','Care',15.00,5.00,15,5),
 (6,'Gift Card $50','GC-50','1000000006','Gift Card',50.00,0.00,999,6);
