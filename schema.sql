-- ============================================================
--  DIAMOND NAIL & SPA (DBA) — Complete Booking System Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS nail_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nail_booking;

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','manager','staff') DEFAULT 'staff',
  is_active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- Default: admin@diamondnail.com / Admin@1234
INSERT IGNORE INTO admin_users (id,name,email,password_hash,role)
VALUES (1,'Studio Owner','admin@diamondnail.com','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','owner');

CREATE TABLE IF NOT EXISTS business_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_name VARCHAR(160) DEFAULT 'Diamond Nail & Spa',
  business_phone VARCHAR(30) DEFAULT '',
  business_email VARCHAR(180) DEFAULT '',
  business_address TEXT DEFAULT '',
  slot_interval_minutes INT DEFAULT 30,
  booking_notice_hours INT DEFAULT 2,
  sms_sender VARCHAR(30) DEFAULT 'DiamondNail',
  timezone VARCHAR(60) DEFAULT 'America/New_York',
  reminder_hours_before INT DEFAULT 24,
  twilio_account_sid VARCHAR(120) DEFAULT '',
  twilio_auth_token VARCHAR(120) DEFAULT '',
  twilio_from_number VARCHAR(30) DEFAULT '',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO business_settings (id, business_name, sms_sender) VALUES (1, 'Diamond Nail & Spa', 'DiamondNail');

CREATE TABLE IF NOT EXISTS technicians (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) DEFAULT '',
  email VARCHAR(180) DEFAULT '',
  bio TEXT DEFAULT '',
  photo_url VARCHAR(400) DEFAULT '',
  specialties VARCHAR(400) DEFAULT '',
  is_active TINYINT(1) DEFAULT 1,
  display_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO technicians (id,name,bio,specialties,display_order) VALUES
(1,'Sophie Laurent','Lead nail artist at Diamond Nail & Spa with 8+ years expertise in gel, nail art and spa treatments.','Gel Manicure,Nail Art,Acrylic',1),
(2,'Maya Reyes','Specialises in luxury pedicure spa treatments and classic manicures at Diamond Nail & Spa.','Classic Manicure,Classic Pedicure,Gel Pedicure',2),
(3,'Ava Thornton','Expert in acrylic full sets and intricate nail art, crafting diamond-level designs at every visit.','Acrylic Full Set,Nail Art,Gel Manicure',3);

CREATE TABLE IF NOT EXISTS services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  description TEXT DEFAULT '',
  duration_minutes INT NOT NULL DEFAULT 45,
  price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  category VARCHAR(80) DEFAULT 'Manicure',
  image_url VARCHAR(400) DEFAULT '',
  is_active TINYINT(1) DEFAULT 1,
  display_order INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT IGNORE INTO services (id,name,description,duration_minutes,price,category,display_order) VALUES
(1,'Classic Manicure','Shape, buff, cuticle care and your choice of polish colour.',45,35.00,'Manicure',1),
(2,'Gel Manicure','Long-lasting gel colour with a high-shine finish — up to 3 weeks.',60,55.00,'Manicure',2),
(3,'Classic Pedicure','Full foot soak, exfoliation, shape and polish for refreshed feet.',60,45.00,'Pedicure',3),
(4,'Gel Pedicure','Gel polish on your toes for a chip-free, polished look.',75,65.00,'Pedicure',4),
(5,'Nail Art Design','Hand-painted custom nail art — from minimalist to elaborate.',90,80.00,'Art',5),
(6,'Acrylic Full Set','Full set of sculpted acrylic nails in your preferred shape and length.',90,95.00,'Acrylic',6);

CREATE TABLE IF NOT EXISTS technician_services (
  technician_id INT NOT NULL,
  service_id INT NOT NULL,
  PRIMARY KEY (technician_id,service_id),
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO technician_services VALUES (1,2),(1,5),(1,6),(2,1),(2,3),(2,4),(3,6),(3,5),(3,2);

CREATE TABLE IF NOT EXISTS business_hours (
  id INT AUTO_INCREMENT PRIMARY KEY,
  weekday TINYINT NOT NULL,
  is_open TINYINT(1) DEFAULT 1,
  start_time TIME DEFAULT '09:00:00',
  end_time TIME DEFAULT '18:00:00'
) ENGINE=InnoDB;
INSERT IGNORE INTO business_hours (id,weekday,is_open,start_time,end_time) VALUES
(1,0,0,'10:00:00','16:00:00'),(2,1,1,'09:00:00','18:00:00'),
(3,2,1,'09:00:00','18:00:00'),(4,3,1,'09:00:00','18:00:00'),
(5,4,1,'09:00:00','19:00:00'),(6,5,1,'09:00:00','19:00:00'),
(7,6,1,'10:00:00','17:00:00');

CREATE TABLE IF NOT EXISTS blocked_dates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  blocked_date DATE NOT NULL UNIQUE,
  reason VARCHAR(255) DEFAULT '',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(180) NOT NULL,
  email VARCHAR(180) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  service_id INT NOT NULL,
  technician_id INT DEFAULT NULL,
  appointment_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  notes TEXT DEFAULT '',
  sms_confirmation_sent TINYINT(1) DEFAULT 0,
  sms_reminder_sent TINYINT(1) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (technician_id) REFERENCES technicians(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT DEFAULT NULL,
  to_number VARCHAR(30),
  message TEXT,
  type ENUM('confirmation','reminder','cancellation','custom') DEFAULT 'custom',
  status VARCHAR(30) DEFAULT 'sent',
  provider_id VARCHAR(160) DEFAULT '',
  sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB;
