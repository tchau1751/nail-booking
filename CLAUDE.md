# CLAUDE.md — Diamond Nail POS (`nail-booking`)

Guidance for AI agents working in this repo. The code comments carry the detail;
this is the map and the rules that are easy to break.

## What this is

A nail-salon booking website plus a touchscreen point-of-sale, in plain
**PHP 8 + MySQL/MariaDB on XAMPP**. No framework, no Composer, no build step.
Pages are PHP files that render HTML; JSON endpoints live in `api/` and `pos/api/`.

| Area | Where | Sign-in |
|---|---|---|
| Public booking site | `index.php`, `api/book.php`, `api/slots.php`, `api/services.php`, `api/technicians.php`, `assets/js/public.js` | none |
| Booking admin (older) | `admin/`, `api/admin_*.php`, `api/calendar.php`, `api/appointments.php` | email + password |
| The till (POS) | `pos/` — register `index.php`, queue, clients, sales, reports, payroll, settings… | PIN or email |
| POS logic | `pos/includes/pos.php` (cart, checkout, refund, void), `rewards.php` (gift cards, points, stamps), `salon.php` (clients, queue, turns), `purge.php` (clear test data) |
| Auth & DB helpers | `includes/auth.php` (sessions, roles, PIN, manager approval), `includes/db.php` (`query`, `fetchOne`, `fetchAll`, `e`) |
| Schema | `schema.sql` (booking tables), `pos/schema_pos*.sql`, and `pos/includes/migrate.php` |
| Cron | `cron/send_reminders.php` |

Reference design documents (Vietnamese) for where this is heading live outside the
repo in the owner's *laptrinh hoa hinh* folder; the plan distilled from them is
[docs/multi-tenant/LO-TRINH.md](docs/multi-tenant/LO-TRINH.md).

## Running it locally

- Apache listens on **port 81**: `http://localhost:81/nail-booking/pos/`
- `config/config.php` holds the defaults. Machine-specific values go in the
  git-ignored `config/config.local.php`, which is loaded first — define `DB_NAME`,
  `SUBFOLDER`, etc. there and they win.
- Never develop against the live `nail_booking` database. Copy it:
  ```bash
  mysqldump -u root nail_booking | mysql -u root nail_booking_mt
  ```
- PHP CLI: `D:\xampp\php\php.exe`. MySQL client: `D:\xampp\mysql\bin\mysql.exe -u root`.

## Schema changes

- Add them to `migratePos()` in `pos/includes/migrate.php`. It is idempotent — it
  checks `information_schema` before every change — and runs from
  `pos/install.php` → **Re-run setup**.
- The `.sql` files carry `USE nail_booking;` for people piping them into the mysql
  client. The migrator skips `USE`; keep it that way or it migrates the wrong DB.

## Rules that are easy to break

- **SQL** goes through `query()` / `fetchOne()` / `fetchAll()` with `?`
  placeholders. Never interpolate request data.
- **Output** is escaped with `e()`.
- **Money** is recomputed on the server (`cartTotals()`); the tablet only paints
  what the server returns.
- **CSRF**: every POST to a `pos/` page is checked in `layout_start.php` and the
  token is injected into forms by `layout_end.php`. JSON endpoints call
  `posCsrfValid()` themselves.
- **Roles** are ranked in `roleRank()` (`includes/auth.php`). A page sets
  `$requireRole` *before* including `layout_start.php`; actions inside a page
  re-check with `hasRole()`. Hiding a button is not a permission check.
- **Discounts, custom prices, lower prices** need `managerApproved()` on the server.

## Multi-tenant (in progress on branch `claude/muon-pos-multi-user-model-*`)

The app is being turned from one salon into many salons sharing one database.
Follow [docs/multi-tenant/LO-TRINH.md](docs/multi-tenant/LO-TRINH.md) step by step.
The contract every change must keep:

1. Every business table has `tenant_id`. Every read, update and delete filters on
   it; every insert sets it.
2. The tenant comes from the **signed-in session** (or, for public pages, from a
   public salon slug / a device / a random token) — never from a `tenant_id` the
   browser sends.
3. An id that arrives from the browser (service, technician, client, sale…) is
   looked up *with* `tenant_id` before it is stored or joined on.
4. Anything that deliberately crosses tenants (login by email, token lookups,
   the platform admin, cron looping over salons) is wrapped so it is visible in
   review, not done with a bare query.

## Commits

Short imperative subject in plain salon language, like the history:
"Refund part of a ticket instead of only voiding the whole thing". Body explains
why. One step of the roadmap per commit (or a few commits per step).
