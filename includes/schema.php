<?php
// ============================================================
//  Schema helpers, and the migration that turns one salon's
//  database into one that many salons share.
//
//  Everything here looks in information_schema before it changes
//  anything, so it is safe on every upgrade, twice in a row, or
//  after a run that stopped half-way.
// ============================================================
require_once __DIR__ . '/db.php';

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
function indexExists(string $t, string $index): bool {
    return (bool)fetchOne('SELECT 1 x FROM information_schema.STATISTICS
                           WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?', [dbName(), $t, $index]);
}
function addColumn(string $t, string $c, string $ddl, array &$log): void {
    if (!tableExists($t) || columnExists($t, $c)) return;
    db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $ddl");
    $log[] = "added $t.$c";
}

/** Unique indexes made of exactly this one column — the ones a per-salon key replaces. */
function singleColumnUniques(string $t, string $col): array {
    return array_column(fetchAll(
        "SELECT INDEX_NAME FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND NON_UNIQUE=0 AND INDEX_NAME<>'PRIMARY'
          GROUP BY INDEX_NAME
         HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = ?", [dbName(), $t, $col]), 'INDEX_NAME');
}

/** True when some salon already has two rows where a key would allow one. */
function hasDuplicateRows(string $t, string $cols): bool {
    return (bool)fetchOne("SELECT 1 x FROM `$t` GROUP BY $cols HAVING COUNT(*) > 1 LIMIT 1");
}

/**
 * Bring the database up to TENANCY_VERSION. Serialised with a named lock, so
 * two tablets opening the till at the same moment after an upgrade take turns
 * instead of both trying to add the same column.
 */
function migrateTenancy(array &$log = []): array {
    db()->query("SELECT GET_LOCK('nail_booking_tenancy', 60)")->fetchColumn();
    try {
        unscoped(function () use (&$log) { migrateTenancyLocked($log); });
    } finally {
        db()->query("SELECT RELEASE_LOCK('nail_booking_tenancy')")->fetchColumn();
    }
    return $log;
}

function migrateTenancyLocked(array &$log): void {
    db()->exec("CREATE TABLE IF NOT EXISTS app_meta (
        k VARCHAR(40)  NOT NULL PRIMARY KEY,
        v VARCHAR(255) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB");

    // The packages from the pricing sheet. NULL is "no limit".
    db()->exec("CREATE TABLE IF NOT EXISTS plans (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        code          VARCHAR(30)  NOT NULL UNIQUE,
        name          VARCHAR(60)  NOT NULL,
        price_month   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        max_locations INT DEFAULT NULL,
        max_devices   INT DEFAULT NULL,
        max_employees INT DEFAULT NULL,
        max_users     INT DEFAULT NULL,
        display_order INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");
    foreach ([
        ['basic',        'Basic',         79, 1,    1,    5,    3,    1],
        ['professional', 'Professional', 149, 3,    3,    20,   20,   2],
        ['enterprise',   'Enterprise',   299, null, null, null, null, 3],
    ] as $plan) {
        query('INSERT IGNORE INTO plans (code, name, price_month, max_locations, max_devices,
                                         max_employees, max_users, display_order)
               VALUES (?,?,?,?,?,?,?,?)', $plan);
    }

    db()->exec("CREATE TABLE IF NOT EXISTS tenants (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        slug          VARCHAR(60)  NOT NULL,
        name          VARCHAR(160) NOT NULL,
        plan_id       INT DEFAULT NULL,
        status        ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
        trial_ends_on DATE DEFAULT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_slug (slug),
        CONSTRAINT fk_tenants_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // The salon that was here before there were tenants becomes salon 1 — the
    // id every existing row is about to be stamped with.
    if (!fetchOne('SELECT 1 x FROM tenants LIMIT 1')) {
        $name = 'My Salon';
        if (tableExists('business_settings')) {
            $name = trim((string)(fetchOne('SELECT business_name FROM business_settings ORDER BY id LIMIT 1')['business_name'] ?? '')) ?: $name;
        }
        query("INSERT INTO tenants (id, slug, name, plan_id, status)
               VALUES (1, ?, ?, (SELECT id FROM plans WHERE code = 'professional'), 'active')",
              [tenantSlugify($name), $name]);
        $log[] = 'created salon 1: ' . $name;
    }

    foreach (TENANT_TABLES as $t) {
        if (!tableExists($t) || columnExists($t, 'tenant_id')) continue;
        $after = columnExists($t, 'id') ? 'AFTER id' : 'FIRST';
        // Every row that exists today belongs to the salon that was here first.
        db()->exec("ALTER TABLE `$t`
                      ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 $after,
                      ADD KEY idx_tenant (tenant_id),
                      ADD CONSTRAINT `fk_{$t}_tenant` FOREIGN KEY (tenant_id) REFERENCES tenants(id)");
        $log[] = "added $t.tenant_id";
    }

    // Every query now names its salon, so a row that arrives without one is a
    // bug. With no default the insert fails on the spot instead of quietly
    // landing in salon 1.
    foreach (TENANT_TABLES as $t) {
        if (!tableExists($t) || !columnExists($t, 'tenant_id')) continue;
        $col = fetchOne("SELECT COLUMN_DEFAULT d FROM information_schema.COLUMNS
                          WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME='tenant_id'", [dbName(), $t]);
        if ($col && $col['d'] !== null && strtoupper((string)$col['d']) !== 'NULL') {
            db()->exec("ALTER TABLE `$t` ALTER COLUMN tenant_id DROP DEFAULT");
            $log[] = "$t.tenant_id must now be given";
        }
    }

    // Numbers and codes that were unique across the whole database only need to
    // be unique inside one salon: two salons can both ring ticket 260914-0001,
    // and the same guest can be a client of both.
    foreach ([
        ['pos_sales',             'sale_no',      'uq_tenant_sale_no'],
        ['pos_refunds',           'refund_no',    'uq_tenant_refund_no'],
        ['pos_clients',           'phone',        'uq_tenant_phone'],
        ['pos_products',          'barcode',      'uq_tenant_barcode'],
        ['pos_consent_templates', 'form_key',     'uq_tenant_form_key'],
        ['pos_polish_brands',     'name',         'uq_tenant_name'],
        ['blocked_dates',         'blocked_date', 'uq_tenant_date'],
    ] as [$t, $col, $key]) {
        if (!tableExists($t) || !columnExists($t, 'tenant_id')) continue;
        if (!indexExists($t, $key)) {
            db()->exec("ALTER TABLE `$t` ADD UNIQUE KEY `$key` (tenant_id, `$col`)");
            $log[] = "$t: $col unique per salon";
        }
        foreach (singleColumnUniques($t, $col) as $old) {
            db()->exec("ALTER TABLE `$t` DROP INDEX `$old`");
            $log[] = "$t: dropped database-wide unique $old";
        }
    }

    // Ticket counters are per salon per day.
    if (tableExists('pos_counters') && columnExists('pos_counters', 'tenant_id')
        && !fetchOne("SELECT 1 x FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA=? AND TABLE_NAME='pos_counters'
                         AND INDEX_NAME='PRIMARY' AND COLUMN_NAME='tenant_id'", [dbName()])) {
        db()->exec('ALTER TABLE pos_counters DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, name)');
        $log[] = 'pos_counters keyed per salon';
    }

    // One row of settings per salon, instead of "the row with id 1". A database
    // that somehow holds two rows for one salon is left without the key and
    // reads the lowest id, exactly as before — nothing is deleted to make room.
    if (tableExists('pos_settings') && columnExists('pos_settings', 'tenant_id')) {
        if (!fetchOne("SELECT 1 x FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA=? AND TABLE_NAME='pos_settings' AND COLUMN_NAME='id'
                          AND EXTRA LIKE '%auto_increment%'", [dbName()])) {
            db()->exec('ALTER TABLE pos_settings MODIFY id INT NOT NULL AUTO_INCREMENT');
            $log[] = 'pos_settings.id numbered automatically';
        }
    }
    foreach ([['pos_settings', 'uq_tenant', 'tenant_id'],
              ['business_settings', 'uq_tenant', 'tenant_id'],
              ['business_hours', 'uq_tenant_weekday', 'tenant_id, weekday']] as [$t, $key, $cols]) {
        if (!tableExists($t) || !columnExists($t, 'tenant_id') || indexExists($t, $key)) continue;
        if (hasDuplicateRows($t, $cols)) {
            $log[] = "$t: left without $key — a salon has more than one row";
            continue;
        }
        db()->exec("ALTER TABLE `$t` ADD UNIQUE KEY `$key` ($cols)");
        $log[] = "$t: one row per salon";
    }

    foreach (fetchAll('SELECT id, name FROM tenants') as $tenant) {
        ensureTenantDefaults((int)$tenant['id'], (string)$tenant['name'], $log);
    }

    // Five roles instead of three. A "staff" account could run the register,
    // the queue and the kiosk — the front desk's job — so that is what it becomes.
    if (tableExists('admin_users')) {
        $roleType = function (): string {
            return (string)(fetchOne("SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
                                      WHERE TABLE_SCHEMA=? AND TABLE_NAME='admin_users' AND COLUMN_NAME='role'",
                                     [dbName()])['t'] ?? '');
        };
        if (strpos($roleType(), 'front_desk') === false) {
            db()->exec("ALTER TABLE admin_users MODIFY role
                        ENUM('owner','manager','front_desk','cashier','technician','staff') DEFAULT 'cashier'");
        }
        if (fetchOne("SELECT 1 x FROM admin_users WHERE role='staff' LIMIT 1")) {
            query("UPDATE admin_users SET role='front_desk' WHERE role='staff'");
            $log[] = 'staff accounts are now front desk';
        }
        if (strpos($roleType(), "'staff'") !== false) {
            db()->exec("ALTER TABLE admin_users MODIFY role
                        ENUM('owner','manager','front_desk','cashier','technician') DEFAULT 'cashier'");
            $log[] = 'admin_users: owner, manager, front desk, cashier, technician';
        }
        // A technician's login points at their row on the turns board, so they
        // can clock themselves in without being able to clock anyone else.
        addColumn('admin_users', 'technician_id', 'INT DEFAULT NULL', $log);
    }

    // The tills, tablets and screens a salon has registered. The token that
    // proves a browser is one of them lives only in that browser's cookie;
    // the database keeps a hash of it.
    db()->exec("CREATE TABLE IF NOT EXISTS pos_devices (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id      INT NOT NULL,
        name           VARCHAR(60) NOT NULL,
        kind           ENUM('pos','front_desk','kiosk','display','manager') NOT NULL DEFAULT 'pos',
        token_hash     CHAR(64) NOT NULL,
        is_active      TINYINT(1) NOT NULL DEFAULT 1,
        last_seen_at   DATETIME DEFAULT NULL,
        last_user_id   INT DEFAULT NULL,
        created_by     INT DEFAULT NULL,
        created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
        deactivated_at DATETIME DEFAULT NULL,
        UNIQUE KEY uq_token (token_hash),
        KEY idx_tenant (tenant_id, is_active),
        CONSTRAINT fk_pos_devices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
    ) ENGINE=InnoDB");
    // Which station rang the sale up — POS #1 or POS #2 — for the reports.
    addColumn('pos_sales', 'device_id', 'INT DEFAULT NULL', $log);

    // The platform: the people who run the service itself, kept apart from
    // every salon's staff, and a log of everything they change.
    db()->exec("CREATE TABLE IF NOT EXISTS platform_admins (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(120) NOT NULL,
        email         VARCHAR(180) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        is_active     TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME DEFAULT NULL,
        created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS platform_audit (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        admin_id   INT DEFAULT NULL,
        tenant_id  INT DEFAULT NULL,
        action     VARCHAR(40)  NOT NULL,
        detail     VARCHAR(255) NOT NULL DEFAULT '',
        ip         VARCHAR(45)  NOT NULL DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY idx_when (created_at),
        KEY idx_ip_action (ip, action, created_at)
    ) ENGINE=InnoDB");

    // Only call it done once sign-in itself can work. On a database whose
    // booking tables have not been imported yet, try again next request.
    if (tableExists('admin_users') && columnExists('admin_users', 'tenant_id')) {
        query("INSERT INTO app_meta (k, v) VALUES ('tenancy', ?)
               ON DUPLICATE KEY UPDATE v = VALUES(v)", [(string)TENANCY_VERSION]);
    }
}

/**
 * The rows every salon needs before its first day: settings, opening hours,
 * the policy forms and the polish list. Only fills what is missing, so it is
 * the same call for a brand-new salon and for one that has been trading for
 * years.
 */
function ensureTenantDefaults(int $tenantId, string $name, array &$log = []): void {
    unscoped(function () use ($tenantId, $name, &$log) {
        if (tableExists('business_settings') && columnExists('business_settings', 'tenant_id')
            && !fetchOne('SELECT 1 x FROM business_settings WHERE tenant_id=?', [$tenantId])) {
            query('INSERT INTO business_settings (tenant_id, business_name, sms_sender) VALUES (?,?,?)',
                  [$tenantId, $name, substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 11) ?: 'Salon']);
            $log[] = "salon $tenantId: business settings";
        }

        if (tableExists('pos_settings') && columnExists('pos_settings', 'tenant_id')
            && !fetchOne('SELECT 1 x FROM pos_settings WHERE tenant_id=?', [$tenantId])) {
            query('INSERT INTO pos_settings (tenant_id, receipt_header) VALUES (?,?)', [$tenantId, $name]);
            $log[] = "salon $tenantId: till settings";
        }

        if (tableExists('business_hours') && columnExists('business_hours', 'tenant_id')) {
            $have = array_map('intval', array_column(
                fetchAll('SELECT weekday FROM business_hours WHERE tenant_id=?', [$tenantId]), 'weekday'));
            // Sunday first, as the table has always stored them.
            $week = [[0, '10:00', '16:00'], [1, '09:00', '18:00'], [1, '09:00', '18:00'], [1, '09:00', '18:00'],
                     [1, '09:00', '19:00'], [1, '09:00', '19:00'], [1, '10:00', '17:00']];
            foreach ($week as $day => [$open, $from, $to]) {
                if (in_array($day, $have, true)) continue;
                query('INSERT INTO business_hours (tenant_id, weekday, is_open, start_time, end_time)
                       VALUES (?,?,?,?,?)', [$tenantId, $day, $open, $from, $to]);
            }
            if (count($have) < 7) $log[] = "salon $tenantId: opening hours";
        }

        if (tableExists('pos_consent_templates') && columnExists('pos_consent_templates', 'tenant_id')
            && !fetchOne('SELECT 1 x FROM pos_consent_templates WHERE tenant_id=?', [$tenantId])) {
            foreach (defaultPolicies() as [$key, $title, $body]) {
                query('INSERT INTO pos_consent_templates (tenant_id, form_key, title, body) VALUES (?,?,?,?)',
                      [$tenantId, $key, $title, $body]);
            }
            $log[] = "salon $tenantId: policy forms";
        }

        if (tableExists('pos_polish_brands') && columnExists('pos_polish_brands', 'tenant_id')
            && !fetchOne('SELECT 1 x FROM pos_polish_brands WHERE tenant_id=?', [$tenantId])) {
            foreach (['OPI', 'DND', 'BND', 'Gelish', 'Kiara Sky', 'LDS', 'SNS', 'Essie', 'China Glaze', 'CND Shellac'] as $i => $brand) {
                query('INSERT INTO pos_polish_brands (tenant_id, name, is_active, display_order) VALUES (?,?,0,?)',
                      [$tenantId, $brand, $i + 1]);
            }
            $log[] = "salon $tenantId: polish brands";
        }
    });
}

/**
 * The four policy forms a new salon starts with — the same wording
 * schema_pos_v2.sql gave the first salon. Each salon edits its own copy.
 */
function defaultPolicies(): array {
    return [
        ['general', 'Service Consent & Waiver',
         "I confirm that I have disclosed any skin conditions, allergies, infections or injuries affecting my hands, feet or nails.\n\n"
       . "I understand that nail services carry a small risk of irritation or infection, and I agree to follow the aftercare advice given to me.\n\n"
       . "I consent to receive the services I have selected today."],
        ['sanitation', 'Sanitation & Certification Statement',
         "This salon and its technicians hold current state cosmetology / nail technician licences, available for inspection at the front desk.\n\n"
       . "Implements are cleaned, disinfected with an EPA-registered hospital-grade disinfectant, and stored sanitised between every guest. Files, buffers and other porous items are single-use.\n\n"
       . "Pedicure basins are drained, scrubbed and disinfected after each guest and receive a full disinfectant cycle at the end of every day. Technicians wash their hands before and after every service.\n\n"
       . "Any guest may ask to see our sanitation log or licence certificates at any time."],
        ['privacy', 'Privacy & Text Message Policy',
         "We collect your name, phone number and visit history only to book your appointments, keep your service notes accurate, and let you know about your visits.\n\n"
       . "We never sell or share your information with third parties.\n\n"
       . "By giving us your mobile number you agree to receive appointment confirmations and reminders. Promotional texts are only sent if you opt in, and you can stop them any time by replying STOP or asking the front desk. Message and data rates may apply.\n\n"
       . "You may ask us to correct or delete your information at any time."],
        ['cancellation', 'Appointment, Cancellation & Refund Policy',
         "Please give us at least 24 hours notice to change or cancel an appointment so we can offer the time to another guest.\n\n"
       . "Guests arriving more than 15 minutes late may need to have their service shortened or rescheduled.\n\n"
       . "Services are non-refundable. If you are not happy with your nails, tell us within 3 days and we will correct the work at no charge.\n\n"
       . "Gift cards are non-refundable and cannot be exchanged for cash except where state law requires it."],
    ];
}
