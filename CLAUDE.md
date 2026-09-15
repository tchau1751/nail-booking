# CLAUDE.md — Diamond Nail POS (`nail-booking`)

Guidance for AI agents working in this repo. The code comments carry the detail;
this is the map and the rules that are easy to break.

## What this is

A nail-salon booking website plus a touchscreen point-of-sale, in plain
**PHP 8 + MySQL/MariaDB on XAMPP**. No framework, no Composer, no build step.
Pages are PHP files that render HTML; JSON endpoints live in `api/` and `pos/api/`.
One installation serves **many salons** (tenants) from one database.

| Area | Where | Sign-in |
|---|---|---|
| Public booking site | `index.php?salon=<slug>`, `api/book.php`, `api/slots.php`, `api/services.php`, `api/technicians.php`, `assets/js/public.js` | none |
| Booking admin (older) | `admin/` (and `admin/calendar-standalone.php`, the drag-and-drop day calendar), `api/admin_*.php`, `api/calendar.php`, `api/appointments.php`, `api/reschedule.php`, `api/updateappointment.php`; changing a booking goes through `includes/bookings.php` | email + password |
| The till (POS) | `pos/` — top tabs (in `pos/includes/layout_start.php`): SIGN-IN LIST `queue.php` · CHECKOUT `index.php` · GIFT-CARD `giftcards.php`/`stamps.php`/`points.php` · APPOINTMENT `appointments.php` (frames the day calendar) · CUSTOMER `clients.php` · ADMIN menu (services, staff, settings, sales, reports, payroll…, behind the admin password) | PIN or email |
| POS logic | `pos/includes/pos.php` (cart, checkout, refund, void), `rewards.php` (gift cards, points, stamp cards, birthday texts), `salon.php` (clients, queue, turns), `purge.php` |
| Platform (Super Admin) | `platform/` — every salon, plans, suspension | its own login |
| Tenancy | `includes/tenant.php` (which salon, the guard), `includes/schema.php` (migration), `includes/plans.php`, `includes/devices.php`, `includes/platform.php` |
| Auth & DB helpers | `includes/auth.php` (sessions, roles, PIN), `includes/db.php` (`query`, `fetchOne`, `fetchAll`, `e`) |
| Schema | `schema.sql`, `pos/schema_pos*.sql`, `pos/includes/migrate.php`, `migrateTenancy()` |
| Command line only | `tools/`, `tests/`, `cron/` — the reminder and birthday texts walk every salon (all blocked from the web in `.htaccess`) |

The step-by-step plan behind the multi-salon model, in Vietnamese, with what
each step added: [docs/multi-tenant/LO-TRINH.md](docs/multi-tenant/LO-TRINH.md).

## Running it locally

- Apache listens on **port 81**: `http://localhost:81/nail-booking/pos/`
- `config/config.php` holds the defaults. Machine-specific values go in the
  git-ignored `config/config.local.php`, which is loaded first — define `DB_NAME`,
  `SUBFOLDER`, `TENANT_GUARD`, etc. there and they win.
- Never develop against the live `nail_booking` database. Copy it:
  ```bash
  mysqldump -u root nail_booking | mysql -u root nail_booking_mt
  ```
- PHP CLI: `D:\xampp\php\php.exe`. MySQL client: `D:\xampp\mysql\bin\mysql.exe -u root`.
- First platform admin: `php tools/create-platform-admin.php you@example.com "Name"`.

## Schema changes

- POS tables: add to `migratePos()` in `pos/includes/migrate.php` (runs from
  `pos/install.php`).
- Anything the multi-salon model needs: add to `migrateTenancyLocked()` in
  `includes/schema.php` **and bump `TENANCY_VERSION`** in `includes/tenant.php`.
  The first request after an upgrade runs it under a named lock.
- Both are idempotent — they check `information_schema` first. The `.sql` files
  carry `USE nail_booking;`; the migrator skips it, and skips their seed inserts
  once tenants exist (`ensureTenantDefaults()` hands out starter rows per salon).
- A new table that belongs to a salon: give it `tenant_id INT NOT NULL` with a
  foreign key to `tenants(id)`, and add it to `TENANT_TABLES`.

## The multi-salon contract

1. Every business table has `tenant_id`. Every read, update and delete filters on
   it; every insert sets it.
2. `tenantId()` decides the salon, in this order: an explicit `tenantUse()` (public
   pages, cron, review links) → the signed-in session → a registered device's
   cookie → the only salon on the server. **Never** a `tenant_id` from the browser.
3. An id that arrives from the browser is checked with `tenantOwns($table, $id)` /
   `ownedId()` — or looked up `WHERE id=? AND tenant_id=?` — before it is stored,
   joined on or acted upon.
4. A deliberate cross-salon lookup (sign-in by email, a token lookup) goes inside
   `unscoped(function () { ... })` so it stands out in review.
5. `query()` refuses any statement on a salon table that never mentions
   `tenant_id` (`TENANT_GUARD`, on by default). It catches the query that forgot
   entirely, not one that is scoped wrongly — that is what the tests are for.

Before committing anything that touches SQL:

```bash
php tests/tenant_lint.php                        # static: SQL without tenant_id
php tests/tenant_isolation.php --test-database   # a second salon tries to reach the first (copy DB only)
```

## Rules that are easy to break

- **SQL** goes through `query()` / `fetchOne()` / `fetchAll()` with `?`
  placeholders. Never interpolate request data. Never `db()->exec()` on a salon
  table outside the migration — it bypasses the guard.
- **Output** is escaped with `e()`.
- **Money** is recomputed on the server (`cartTotals()`); the tablet only paints
  what the server returns.
- **CSRF**: every POST to a `pos/` page is checked in `layout_start.php` and the
  token is injected into forms by `layout_end.php`. JSON endpoints call
  `posCsrfValid()` themselves; the platform uses `platformCsrf()`.
- **Roles**, least trusted first: technician, cashier, front_desk, manager, owner
  (`ROLES` in `includes/auth.php`). A page sets `$requireRole` *before* including
  `layout_start.php` — one that forgets is treated as manager-only. JSON endpoints
  use `requireRoleJson()`. Actions inside a page re-check with `hasRole()`.
- **Admin password**: back-office screens (`ADMIN_LOCKED_PAGES` in `includes/auth.php`)
  also need the salon's admin password, which starts as 1111. A new back-office
  page goes into that list; a page outside `pos/` calls `requireAdminUnlock()`, and
  its JSON endpoints `requireAdminUnlockJson()`. It is a second lock, never a
  substitute for the role check.
- **Discounts, custom prices, lower prices** need `managerApproved()` on the server.
- **PINs**: once a salon registers any device, a PIN only works on a registered
  device (`pinBlockedHere()`).
- **Plans**: check `planRoomFor('devices'|'employees'|'users')` before switching
  anything on; never switch off what a salon already has.

## Commits

Short imperative subject in plain salon language, like the history:
"Refund part of a ticket instead of only voiding the whole thing". Body explains
why. One roadmap step per commit.
