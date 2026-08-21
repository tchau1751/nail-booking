-- ============================================================
--  POS v2 - clients, check-in queue, turns, gift cards,
--  loyalty, payroll, expenses, marketing, feedback, consent.
--  ASCII only: the mysql CLI on Windows defaults to cp850 and
--  would double-encode anything else.
--  Run via pos/install.php (preferred) or:
--    mysql -u root --default-character-set=utf8mb4 nail_booking < pos/schema_pos_v2.sql
-- ============================================================
USE nail_booking;

-- The customer record every other feature hangs off.
CREATE TABLE IF NOT EXISTS pos_clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(180) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  email VARCHAR(180) DEFAULT '',
  birthday DATE DEFAULT NULL,
  preferred_tech_id INT DEFAULT NULL,
  points INT NOT NULL DEFAULT 0,
  total_visits INT NOT NULL DEFAULT 0,
  total_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  first_visit DATE DEFAULT NULL,
  last_visit DATE DEFAULT NULL,
  marketing_opt_in TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_phone (phone),
  KEY idx_name (full_name),
  FOREIGN KEY (preferred_tech_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_client_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  note TEXT NOT NULL,
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client (client_id, is_pinned),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE CASCADE,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Signed consent / waiver forms, kept as a drawn signature image.
CREATE TABLE IF NOT EXISTS pos_consents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  form_key VARCHAR(60) NOT NULL DEFAULT 'general',
  form_title VARCHAR(180) NOT NULL,
  form_body MEDIUMTEXT,
  signature LONGTEXT,
  signed_name VARCHAR(180) DEFAULT '',
  signed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client (client_id),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_consent_templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  form_key VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(180) NOT NULL,
  body MEDIUMTEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT IGNORE INTO pos_consent_templates (id, form_key, title, body) VALUES
 (1,'general','Service Consent & Waiver',
  'I confirm that I have disclosed any skin conditions, allergies, infections or injuries affecting my hands, feet or nails.\n\nI understand that nail services carry a small risk of irritation or infection, and I agree to follow the aftercare advice given to me.\n\nI consent to receive the services I have selected today.'),
 (2,'sanitation','Sanitation & Certification Statement',
  'This salon and its technicians hold current state cosmetology / nail technician licences, available for inspection at the front desk.\n\nImplements are cleaned, disinfected with an EPA-registered hospital-grade disinfectant, and stored sanitised between every guest. Files, buffers and other porous items are single-use.\n\nPedicure basins are drained, scrubbed and disinfected after each guest and receive a full disinfectant cycle at the end of every day. Technicians wash their hands before and after every service.\n\nAny guest may ask to see our sanitation log or licence certificates at any time.'),
 (3,'privacy','Privacy & Text Message Policy',
  'We collect your name, phone number and visit history only to book your appointments, keep your service notes accurate, and let you know about your visits.\n\nWe never sell or share your information with third parties.\n\nBy giving us your mobile number you agree to receive appointment confirmations and reminders. Promotional texts are only sent if you opt in, and you can stop them any time by replying STOP or asking the front desk. Message and data rates may apply.\n\nYou may ask us to correct or delete your information at any time.'),
 (4,'cancellation','Appointment, Cancellation & Refund Policy',
  'Please give us at least 24 hours notice to change or cancel an appointment so we can offer the time to another guest.\n\nGuests arriving more than 15 minutes late may need to have their service shortened or rescheduled.\n\nServices are non-refundable. If you are not happy with your nails, tell us within 3 days and we will correct the work at no charge.\n\nGift cards are non-refundable and cannot be exchanged for cash except where state law requires it.');

-- The walk-in queue. One row per guest per visit.
CREATE TABLE IF NOT EXISTS pos_checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT DEFAULT NULL,
  guest_name VARCHAR(180) NOT NULL,
  guest_phone VARCHAR(30) DEFAULT '',
  party_size INT NOT NULL DEFAULT 1,
  service_id INT DEFAULT NULL,
  requested_tech_id INT DEFAULT NULL,
  assigned_tech_id INT DEFAULT NULL,
  appointment_id INT DEFAULT NULL,
  sale_id INT DEFAULT NULL,
  turn_value DECIMAL(4,2) NOT NULL DEFAULT 1.00,
  status ENUM('waiting','in_service','done','no_show') NOT NULL DEFAULT 'waiting',
  note VARCHAR(255) DEFAULT '',
  checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  assigned_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  KEY idx_status (status, checked_in_at),
  KEY idx_day (checked_in_at),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE SET NULL,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
  FOREIGN KEY (requested_tech_id) REFERENCES technicians(id) ON DELETE SET NULL,
  FOREIGN KEY (assigned_tech_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Who is on the floor right now - only clocked-in techs are in rotation.
CREATE TABLE IF NOT EXISTS pos_tech_shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  technician_id INT NOT NULL,
  shift_date DATE NOT NULL,
  clock_in DATETIME NOT NULL,
  clock_out DATETIME DEFAULT NULL,
  KEY idx_tech_day (technician_id, shift_date),
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_gift_cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(24) NOT NULL UNIQUE,
  initial_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  client_id INT DEFAULT NULL,
  recipient VARCHAR(180) DEFAULT '',
  issued_sale_id INT DEFAULT NULL,
  status ENUM('active','used','void') NOT NULL DEFAULT 'active',
  expires_on DATE DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_gift_card_txns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  gift_card_id INT NOT NULL,
  sale_id INT DEFAULT NULL,
  type ENUM('issue','redeem','reload','adjust','void') NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  balance_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_card (gift_card_id),
  FOREIGN KEY (gift_card_id) REFERENCES pos_gift_cards(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_loyalty_txns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  sale_id INT DEFAULT NULL,
  type ENUM('earn','redeem','adjust') NOT NULL,
  points INT NOT NULL DEFAULT 0,
  balance_after INT NOT NULL DEFAULT 0,
  note VARCHAR(180) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client (client_id),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Every stamp given, taken back or redeemed, so a disputed card can be settled.
CREATE TABLE IF NOT EXISTS pos_stamp_txns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  sale_id INT DEFAULT NULL,
  type ENUM('earn','redeem','adjust') NOT NULL,
  stamps INT NOT NULL DEFAULT 0,
  balance_after INT NOT NULL DEFAULT 0,
  note VARCHAR(180) DEFAULT '',
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client (client_id),
  KEY idx_created (created_at),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  expense_date DATE NOT NULL,
  category VARCHAR(80) NOT NULL DEFAULT 'Supplies',
  description VARCHAR(255) DEFAULT '',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_date (expense_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_campaigns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  segment VARCHAR(60) NOT NULL DEFAULT 'all',
  segment_args VARCHAR(120) DEFAULT '',
  recipients INT NOT NULL DEFAULT 0,
  sent_count INT NOT NULL DEFAULT 0,
  failed_count INT NOT NULL DEFAULT 0,
  admin_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pos_feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL UNIQUE,
  client_id INT DEFAULT NULL,
  sale_id INT DEFAULT NULL,
  technician_id INT DEFAULT NULL,
  rating TINYINT DEFAULT NULL,
  comment TEXT,
  requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME DEFAULT NULL,
  KEY idx_responded (responded_at),
  FOREIGN KEY (client_id) REFERENCES pos_clients(id) ON DELETE SET NULL,
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;
