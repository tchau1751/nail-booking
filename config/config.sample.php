<?php
/**
 * Central config — fill these in with your real Namecheap cPanel values,
 * or simply run install.php once and it will write this file for you.
 *
 * Copy this file to config.php and fill in the real values. config.php is
 * gitignored and must never be committed.
 */

// ---- Database -----------------------------------------------------------
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Site -------------------------------------------------------------
define('SITE_URL', '');
define('SITE_TIMEZONE', 'America/Denver');

// ---- Email (Resend — https://resend.com) -------------------------------
// Leave RESEND_API_KEY empty to skip sending real email (booking still saves,
// notification_log records a "skipped" row instead of "sent"/"failed").
define('RESEND_API_KEY', '');
define('RESEND_FROM_EMAIL', '');

// ---- SMS (Twilio — https://twilio.com) ---------------------------------
define('TWILIO_ACCOUNT_SID', '');
define('TWILIO_AUTH_TOKEN', '');
define('TWILIO_FROM_NUMBER', '');

// ---- Session / security -------------------------------------------------
define('SESSION_NAME', 'diamond_admin_session');

// ---- Cron protection ----------------------------------------------------
// This host's .htaccess "Require all denied" doesn't actually block web
// access to cron/, so the scripts themselves check this shared secret on
// any HTTP-triggered run (e.g. https://.../cron/send-reminders.php?key=...).
// Not required for real CLI cron execution.
define('CRON_SECRET', '');
