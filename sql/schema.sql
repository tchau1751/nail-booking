-- Diamond Nails & Spa — MySQL schema (Namecheap cPanel / MySQL 5.7+ compatible)
-- Import via cPanel > phpMyAdmin, or: mysql -u USER -p DBNAME < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- business_settings — single-row table holding studio identity/contact info
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS business_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  business_name  VARCHAR(150) NOT NULL DEFAULT 'Diamond Nails & Spa',
  business_email VARCHAR(150) NOT NULL DEFAULT '',
  business_phone VARCHAR(30)  NOT NULL DEFAULT '',
  business_address VARCHAR(255) NOT NULL DEFAULT '',
  hours_note VARCHAR(255) NOT NULL DEFAULT 'Tue–Sat 9:30am–7pm · Sun 11am–4pm · Mon Closed',
  instagram_url VARCHAR(255) NOT NULL DEFAULT '',
  facebook_url VARCHAR(255) NOT NULL DEFAULT '',
  booking_notice TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- services — the public menu; only is_active=1 rows are shown on the website
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  price DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  image_url VARCHAR(500) NOT NULL DEFAULT '',
  category VARCHAR(80) NOT NULL DEFAULT 'Nail Services',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_services_slug (slug),
  KEY idx_services_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- clients — deduplicated by email/phone, built up as bookings come in
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  date_of_birth DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_clients_email (email),
  KEY idx_clients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- staff — nail technicians shown as calendar columns and (optionally) picked
-- by clients when booking
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  title VARCHAR(100) NOT NULL DEFAULT 'Nail Technician',
  photo_url VARCHAR(500) NOT NULL DEFAULT '',
  bio TEXT NULL,
  color_hex VARCHAR(7) NOT NULL DEFAULT '#b8836a',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_staff_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- bookings — one row per client-booked appointment
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NULL,
  service_id INT UNSIGNED NOT NULL,
  staff_id INT UNSIGNED NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL DEFAULT '',
  phone VARCHAR(30) NOT NULL DEFAULT '',
  appointment_date DATE NOT NULL,
  appointment_time TIME NOT NULL,
  status ENUM('pending','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  checked_in_at DATETIME NULL,
  reminder_sent_at DATETIME NULL,
  notes TEXT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'website',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
  KEY idx_bookings_date (appointment_date, appointment_time),
  KEY idx_bookings_status (status),
  KEY idx_bookings_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- notification_log — audit trail of every SMS/email attempt tied to a booking
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notification_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  channel ENUM('email','sms') NOT NULL,
  status ENUM('sent','failed','skipped') NOT NULL,
  message TEXT NULL,
  provider_response TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- admin_users — dashboard login accounts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  email VARCHAR(150) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'manager',
  last_login_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
