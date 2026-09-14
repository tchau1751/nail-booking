<?php
// ============================================================
//  DIAMOND NAIL & SPA — Configuration
//  XAMPP: files live in  htdocs/diamond-nail-spa/
//  Access via: http://localhost/diamond-nail-spa/
// ============================================================

// ── Machine-local overrides ───────────────────────────────────
// config.local.php is git-ignored. Anything it defines wins over the
// defaults below, so a test copy or a server can differ without an edit here.
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

// ── Database (XAMPP defaults) ─────────────────────────────────
defined('DB_HOST')    || define('DB_HOST',    'localhost');
defined('DB_NAME')    || define('DB_NAME',    'nail_booking');
defined('DB_USER')    || define('DB_USER',    'root');
defined('DB_PASS')    || define('DB_PASS',    '');          // XAMPP default = no password
defined('DB_CHARSET') || define('DB_CHARSET', 'utf8mb4');

// ── App base path ─────────────────────────────────────────────
// Change 'nail-booking' if you rename the folder in htdocs
defined('SUBFOLDER')  || define('SUBFOLDER',  'nail-booking');
define('APP_URL',    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . SUBFOLDER);
define('BASE_PATH',  '/' . SUBFOLDER);   // used in HTML href/src

// ── Twilio SMS ────────────────────────────────────────────────
// Fill in after signing up at https://twilio.com/console
// OR enter these in Admin Dashboard → Settings
define('TWILIO_ACCOUNT_SID', 'AC3c74634420b61c96ed710cf81a98bc13');
define('TWILIO_AUTH_TOKEN',  'a55bb1e51c386caf4be796a2be2d210a');
define('TWILIO_FROM_NUMBER', '+18559381372');   // E.164 format: +12025551234

// ── General ──────────────────────────────────────────────────
define('APP_TIMEZONE',   'America/New_York');
define('SESSION_SECRET', 'change-this-to-a-long-random-string');

date_default_timezone_set(APP_TIMEZONE);
