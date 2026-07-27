<?php
// ============================================================
//  DIAMOND NAIL & SPA — Configuration
//  XAMPP: files live in  htdocs/diamond-nail-spa/
//  Access via: http://localhost/diamond-nail-spa/
// ============================================================

// ── Database (XAMPP defaults) ─────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'nail_booking');
define('DB_USER',    'root');
define('DB_PASS',    '');          // XAMPP default = no password
define('DB_CHARSET', 'utf8mb4');

// ── App base path ─────────────────────────────────────────────
// Change 'nail-booking' if you rename the folder in htdocs
define('SUBFOLDER',  'nail-booking');
define('APP_URL',    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . SUBFOLDER);
define('BASE_PATH',  '/' . SUBFOLDER);   // used in HTML href/src

// ── Twilio SMS ────────────────────────────────────────────────
// Fill in after signing up at https://twilio.com/console
// OR enter these in Admin Dashboard → Settings
define('TWILIO_ACCOUNT_SID', '');   // ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
define('TWILIO_AUTH_TOKEN',  '');   // your auth token
define('TWILIO_FROM_NUMBER', '');   // E.164 format: +12025551234

// ── General ──────────────────────────────────────────────────
define('APP_TIMEZONE',   'America/New_York');
define('SESSION_SECRET', 'change-this-to-a-long-random-string');

date_default_timezone_set(APP_TIMEZONE);
