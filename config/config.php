<?php
// ============================================================
//  DIAMOND NAIL & SPA — Configuration
//  XAMPP: files live in  htdocs/diamond-nail-spa/
//  Access via: http://localhost/diamond-nail-spa/
//
//  This file is in git, so it only holds defaults. Anything secret or
//  particular to one machine goes in config.local.php beside it, which
//  git ignores. That file is loaded first and whatever it defines wins,
//  e.g.   define('TWILIO_AUTH_TOKEN', '...');
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
defined('APP_URL')    || define('APP_URL',    'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . SUBFOLDER);
defined('BASE_PATH')  || define('BASE_PATH',  '/' . SUBFOLDER);   // used in HTML href/src

// ── Twilio SMS ────────────────────────────────────────────────
// Enter these in Settings. What is saved there is used first; these are
// only the fallback for a field left empty there, and they stay blank in
// this file — real values go in config.local.php.
defined('TWILIO_ACCOUNT_SID') || define('TWILIO_ACCOUNT_SID', '');
defined('TWILIO_AUTH_TOKEN')  || define('TWILIO_AUTH_TOKEN',  '');
defined('TWILIO_FROM_NUMBER') || define('TWILIO_FROM_NUMBER', '');   // E.164 format: +12025551234

// ── General ──────────────────────────────────────────────────
defined('APP_TIMEZONE')   || define('APP_TIMEZONE',   'America/New_York');
defined('SESSION_SECRET') || define('SESSION_SECRET', 'change-this-to-a-long-random-string');

date_default_timezone_set(APP_TIMEZONE);
