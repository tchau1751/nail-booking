<?php
// ============================================================
//  Idempotent schema migrator. Safe to run over and over —
//  it inspects information_schema before touching anything.
//  Called from pos/install.php.
// ============================================================
require_once __DIR__ . '/pos.php';

function dbName(): string {
    static $n = null;
    if ($n === null) $n = fetchOne('SELECT DATABASE() d')['d'];
    return $n;
}
function tableExists(string $t): bool {
    return (bool)fetchOne('SELECT 1 x FROM information_schema.TABLES
                           WHERE TABLE_SCHEMA=? AND TABLE_NAME=?', [dbName(), $t]);
}
function columnExists(string $t, string $c): bool {
    return (bool)fetchOne('SELECT 1 x FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?', [dbName(), $t, $c]);
}
function addColumn(string $t, string $c, string $ddl, array &$log): void {
    if (!tableExists($t) || columnExists($t, $c)) return;
    db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $ddl");
    $log[] = "added $t.$c";
}
function runSqlFile(string $path, array &$log): void {
    $sql = file_get_contents($path);
    if ($sql === false) throw new RuntimeException(basename($path) . ' is missing.');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql)), 'strlen') as $stmt) {
        db()->exec($stmt);
    }
    $log[] = 'ran ' . basename($path);
}

/** Runs every migration. Returns a log of what actually changed. */
function migratePos(): array {
    $log = [];
    runSqlFile(__DIR__ . '/../schema_pos.sql', $log);
    runSqlFile(__DIR__ . '/../schema_pos_v2.sql', $log);

    // Columns bolted onto tables that already existed before v2.
    addColumn('pos_sales', 'client_id',        'INT DEFAULT NULL', $log);
    addColumn('pos_sales', 'points_earned',    'INT NOT NULL DEFAULT 0', $log);
    addColumn('pos_sales', 'points_redeemed',  'INT NOT NULL DEFAULT 0', $log);
    addColumn('pos_sales', 'checkin_id',       'INT DEFAULT NULL', $log);

    addColumn('pos_settings', 'points_per_dollar',   'DECIMAL(6,2) NOT NULL DEFAULT 1.00', $log);
    addColumn('pos_settings', 'point_value_cents',   'DECIMAL(6,2) NOT NULL DEFAULT 5.00', $log);
    addColumn('pos_settings', 'loyalty_enabled',     'TINYINT(1) NOT NULL DEFAULT 1', $log);
    addColumn('pos_settings', 'points_min_redeem',   'INT NOT NULL DEFAULT 100', $log);
    addColumn('pos_settings', 'default_commission',  'DECIMAL(5,2) NOT NULL DEFAULT 60.00', $log);
    addColumn('pos_settings', 'feedback_enabled',    'TINYINT(1) NOT NULL DEFAULT 1', $log);
    addColumn('pos_settings', 'kiosk_welcome',       "VARCHAR(255) DEFAULT 'Welcome! Please sign in.'", $log);
    // Stamp cards: the paper punch card, kept honestly in the database.
    // A stamp is earned per visit — unlike points, which are per dollar.
    addColumn('pos_clients',  'stamps',            'INT NOT NULL DEFAULT 0', $log);
    addColumn('pos_clients',  'rewards_earned',    'INT NOT NULL DEFAULT 0', $log);
    addColumn('pos_clients',  'rewards_redeemed',  'INT NOT NULL DEFAULT 0', $log);
    addColumn('pos_sales',    'stamp_awarded',     'TINYINT(1) NOT NULL DEFAULT 0', $log);
    addColumn('pos_settings', 'stamps_enabled',    'TINYINT(1) NOT NULL DEFAULT 1', $log);
    addColumn('pos_settings', 'stamps_per_card',   'INT NOT NULL DEFAULT 10', $log);
    addColumn('pos_settings', 'stamp_reward',      "VARCHAR(160) NOT NULL DEFAULT 'Free classic manicure'", $log);

    // Kiosk display: promo panel, live waiting list, menu QR.
    addColumn('pos_settings', 'kiosk_promo_title',  "VARCHAR(120) NOT NULL DEFAULT 'Welcome'", $log);
    addColumn('pos_settings', 'kiosk_promo_text',   "VARCHAR(255) NOT NULL DEFAULT 'New guests enjoy 20% off your first visit'", $log);
    addColumn('pos_settings', 'kiosk_promo_image',  "VARCHAR(400) NOT NULL DEFAULT ''", $log);
    addColumn('pos_settings', 'kiosk_show_wait',    'TINYINT(1) NOT NULL DEFAULT 1', $log);
    addColumn('pos_settings', 'kiosk_menu_url',     "VARCHAR(255) NOT NULL DEFAULT ''", $log);
    addColumn('pos_settings', 'kiosk_terms',        "VARCHAR(500) NOT NULL DEFAULT 'By checking in you agree to receive text messages about your appointments, and occasional birthday or promotional offers from us. Reply STOP at any time to opt out. Message and data rates may apply. We never sell your information.'", $log);

    // Automatic birthday texts.
    addColumn('pos_settings', 'public_show_prices',   'TINYINT(1) NOT NULL DEFAULT 0', $log);
    addColumn('pos_settings', 'public_show_duration', 'TINYINT(1) NOT NULL DEFAULT 0', $log);
    addColumn('pos_settings', 'birthday_sms_enabled', 'TINYINT(1) NOT NULL DEFAULT 0', $log);
    addColumn('pos_settings', 'birthday_sms_text',    "VARCHAR(320) NOT NULL DEFAULT 'Happy birthday {name}! 🎉 Enjoy 20% off any service at {salon} this month — just mention this text. Reply STOP to opt out.'", $log);
    addColumn('pos_clients',  'birthday_sms_year',    'INT DEFAULT NULL', $log);   // last year we texted, so never twice

    addColumn('pos_settings', 'owner_name',          "VARCHAR(160) DEFAULT ''", $log);
    addColumn('pos_settings', 'owner_email',         "VARCHAR(180) DEFAULT ''", $log);
    addColumn('pos_settings', 'owner_phone',         "VARCHAR(40) DEFAULT ''", $log);
    addColumn('pos_settings', 'license_no',          "VARCHAR(80) DEFAULT ''", $log);

    // Till sign-in by PIN: fast switching between people on the one shared
    // tablet. Hashed like a password, never stored in the clear, and locked
    // out after repeated wrong guesses because 4 digits is a small haystack.
    addColumn('admin_users', 'pin_hash',         'VARCHAR(255) DEFAULT NULL', $log);
    addColumn('admin_users', 'pin_fails',        'INT NOT NULL DEFAULT 0', $log);
    addColumn('admin_users', 'pin_locked_until', 'DATETIME DEFAULT NULL', $log);

    addColumn('technicians', 'commission_rate', 'DECIMAL(5,2) NOT NULL DEFAULT 60.00', $log);
    addColumn('technicians', 'pay_type',        "ENUM('commission','booth','hourly') NOT NULL DEFAULT 'commission'", $log);
    addColumn('technicians', 'hourly_rate',     'DECIMAL(8,2) NOT NULL DEFAULT 0.00', $log);

    // Supply fee: the shop's cut for polish, tips-out on product, files and
    // acetone. Charged as a percentage of the ticket the technician worked
    // and taken off their commission — the rate is per person because it is
    // negotiated per person. Off by default; a shop that does not charge one
    // should never see the column.
    addColumn('technicians', 'supply_fee_rate', 'DECIMAL(5,2) NOT NULL DEFAULT 0.00', $log);
    addColumn('pos_settings', 'supply_fee_enabled', 'TINYINT(1) NOT NULL DEFAULT 0', $log);

    // Tips belong to whoever did the work, so they are carried on the line
    // item — not on the ticket, which cannot split between two technicians.
    // How the tip was handed over decides whether payroll owes it: cash tips
    // went straight into the technician's pocket at the chair.
    addColumn('pos_sale_items', 'tip', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00', $log);
    addColumn('pos_sales', 'tip_method', "ENUM('cash','card') NOT NULL DEFAULT 'card'", $log);

    // A turn is the unit of fairness in the rotation: a full set counts
    // as a whole turn, a quick polish change as a half.
    addColumn('services', 'turn_value', 'DECIMAL(4,2) NOT NULL DEFAULT 1.00', $log);

    // Gift cards sold on a ticket need their own line type.
    if (tableExists('pos_sale_items')) {
        $col = fetchOne('SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
                        [dbName(), 'pos_sale_items', 'item_type']);
        if ($col && strpos($col['t'], 'giftcard') === false) {
            db()->exec("ALTER TABLE pos_sale_items MODIFY item_type
                        ENUM('service','product','custom','giftcard') NOT NULL DEFAULT 'product'");
            $log[] = 'extended pos_sale_items.item_type with giftcard';
        }
    }

    // Index the client lookups the queue and directory lean on.
    if (tableExists('pos_clients') && !fetchOne('SELECT 1 x FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?', [dbName(), 'pos_clients', 'idx_lastvisit'])) {
        db()->exec('ALTER TABLE pos_clients ADD INDEX idx_lastvisit (last_visit)');
        $log[] = 'indexed pos_clients.last_visit';
    }
    return $log;
}
