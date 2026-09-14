<?php
// ============================================================
//  What a salon's plan lets it have.
//
//  Basic, Professional and Enterprise differ in how many devices,
//  technicians and staff logins a salon may keep switched on. The
//  limit is checked at the moment something would go over it —
//  registering a device, adding a technician, creating a login —
//  and never by switching off what the salon already has, so moving
//  to a smaller plan cannot lock anyone out mid-shift.
// ============================================================
require_once __DIR__ . '/auth.php';

const PLAN_LIMITS = [
    'devices'   => ['max_devices',   'device',      'SELECT COUNT(*) n FROM pos_devices WHERE tenant_id=? AND is_active=1'],
    'employees' => ['max_employees', 'technician',  'SELECT COUNT(*) n FROM technicians WHERE tenant_id=? AND is_active=1'],
    'users'     => ['max_users',     'staff login', 'SELECT COUNT(*) n FROM admin_users WHERE tenant_id=? AND is_active=1'],
];

/** Null while this salon's plan has room for one more, otherwise the reason there is not. */
function planRoomFor(string $what): ?string {
    [$column, $noun, $sql] = PLAN_LIMITS[$what];
    $tenant = currentTenant();
    if ($tenant[$column] === null) return null;
    $max  = (int)$tenant[$column];
    $used = (int)fetchOne($sql, [tenantId()])['n'];
    if ($used < $max) return null;
    return 'The ' . ($tenant['plan_name'] ?? 'current') . ' plan covers ' . $max . ' ' . $noun . ($max === 1 ? '' : 's')
         . ', and ' . $used . ' ' . ($used === 1 ? 'is' : 'are') . ' switched on. '
         . 'Switch one off first, or move to a bigger plan.';
}
