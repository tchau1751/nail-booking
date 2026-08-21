# Nail Salon POS — XAMPP + tablet

A touchscreen point-of-sale for the salon counter, built on the existing
`nail-booking` PHP/MySQL app. It follows the same idea as the Java
*Simple POS System* (fast ticket entry, barcodes, local data, no
subscription) but runs on XAMPP so a tablet on the shop Wi-Fi can be the
register.

## Install

1. Copy the project into `htdocs` (this repo already lives there).
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Sign in at `http://localhost/nail-booking/admin/login.php`.
4. Open `http://localhost/nail-booking/pos/install.php` and press
   **Create POS tables** — or run it by hand:

```bash
mysql -u root --default-character-set=utf8mb4 nail_booking < pos/schema_pos.sql
```

5. Go to **POS → Settings** and set your tax rate, currency and receipt wording.

## Using it on a tablet

- Find the PC's LAN address (`ipconfig` → IPv4, e.g. `192.168.1.20`).
- On the tablet open `http://192.168.1.20/nail-booking/pos/` and choose
  **Add to Home Screen** — it then runs full-screen with no address bar.
- Layout is built for 1024×768 landscape and up; below 900px wide the
  ticket stacks under the catalog so a phone still works.
- Every button is at least 48px, page zoom is locked, and only the two
  inner panes scroll — no rubber-banding while tapping.

## The register screen

| Area | What it does |
|---|---|
| Category tabs | All / Services / each product category / **Today** (today's bookings) |
| Search box | Filters tiles as you type; a barcode scanner types + presses Enter, which adds the item |
| Custom amount tile | Numeric pad for gift cards, add-ons, anything off-menu |
| Today tab | Tap a booking to load the guest, technician and service onto the ticket |
| Ticket | Per-line ± steppers, discount (amount or %), tip presets, hold/resume |
| Charge | Cash / card / gift / other, quick-cash buttons, change calculated for cash |

Totals are always recomputed on the server — the tablet only paints what
the server returns, so a stale screen can never book the wrong price.
A percentage discount is spread across lines pro-rata before tax, so tax
stays correct on a discounted ticket.

## The salon floor

- **Kiosk** (`pos/kiosk.php`) — guest-facing sign-in for a tablet at the door.
  Name, phone, service, requested technician. Creates or matches the client
  record by phone and drops them in the queue.
- **Queue & Turns** (`pos/queue.php`) — the front-desk board. Waiting list on the
  left, the turns rotation on the right. Whoever is clocked in, free, and has the
  fewest turns is flagged **next up**; ties break on who was assigned least
  recently. It's a hint, never an automatic assignment. Techs clock in and out
  here, which is also what feeds hourly payroll. The board refreshes itself every
  minute unless someone is typing.
- **Turn values** live on each service (`services.turn_value`) — a full set is
  1.00, a quick polish change might be 0.50.

## Clients

`pos/clients.php` and `pos/client.php` — directory and profile. Visit history,
lifetime spend, points ledger, gift cards, pinned notes (allergies, colour
formulas, what to avoid) and signed forms. Clients are created automatically the
first time a guest checks in with a phone number.

## Gift cards and points

- Sell a card **on the register** (🎁 button) so the money lands in the day's
  takings — the code prints on the receipt. `pos/giftcards.php` is for comps,
  reloads, voids and the outstanding-balance liability report.
- Redeem a card or points as tender before cash/card. Points are earned on
  services and retail only — never on tax, tips, or buying a gift card.
- Voiding a sale reverses all of it: stock, points earned and redeemed, gift
  cards issued and spent, and the client's lifetime figures.

## Consent, policy and certification forms

`pos/consent.php` — the guest reads on the tablet and signs with a finger. Four
templates ship with the app (service consent, sanitation & certification,
privacy & SMS, cancellation & refund) and are editable in Settings. The wording
is **snapshotted at signing**, so editing a policy later never changes what
someone already signed.

## Marketing and feedback

- `pos/marketing.php` — segment the client list (lapsed, recent, VIP by spend,
  points balance, birthday month), preview exactly who qualifies, then send over
  your existing Twilio setup. `{name}`, `{points}` and `{salon}` are filled in
  per guest. Opted-out clients are excluded from every segment, always.
- `pos/feedback.php` — a feedback request is created at checkout for any ticket
  with a client attached. Text the review link from here; guests answer on
  `pos/review.php` (public, keyed only by a random token). Report shows average
  rating, response rate, ratings by technician and every comment.

## Other pages

- **Sales** — date range and search, reprint any receipt, void a sale
  (voiding returns the stock).
- **Products** — retail CRUD with SKU, barcode, cost, stock and a low-stock
  threshold; one-tap restock. Retiring a product hides it from the register
  but keeps it on old receipts.
- **Reports** — tickets, gross, tips, average ticket, expected cash in the
  drawer, payment mix, revenue by technician, tips by technician, by day, top
  sellers, low stock, and a **profit & loss**. Print it as a Z-report.
- **Payroll** — commission per technician on their own service revenue (net of
  tax), plus tips passed through in full, hours clocked, turns and tickets. Pay
  rates are set at the bottom of the page; booth-rent and hourly are supported
  too.
- **Expenses** (`pos/expenses.php`) — what you spend to run the salon. Feeds the
  P&L.
- **Settings** — store and owner details, licence number, tax, receipt wording,
  tip presets, points rates, editable policy documents, and the cash drawer log.

## How the P&L counts things

Revenue strips out tax (the state's money) and tips (the tech's money). Selling a
gift card isn't revenue either — it's a liability until the card is spent, and
that spend already shows up as a service or retail line on a later ticket.
Against that it charges retail cost of goods, technician commission, and your
recorded expenses.

## Tax

`tax_services` is off by default because nail services are untaxed in most
US states; retail products are taxed. Flip it in Settings if your state
taxes services.

## Files

```
pos/
  index.php          register (tablet screen)
  sales.php          sale history, void, reprint
  products.php       retail inventory
  reports.php        end-of-day / Z-report
  settings.php       tax, receipt, cash drawer
  receipt.php        80mm printable receipt
  install.php        one-click table setup
  schema_pos.sql     POS tables
  api/cart.php       JSON cart + checkout endpoint
  includes/pos.php   cart, totals, checkout, void
  assets/pos.css     tablet-first styles
  assets/pos.js      register behaviour
```

## Notes

- Payments are recorded, not processed — there is no card gateway. Run the
  card on your existing terminal, then log it here with the last 4 as the
  reference.
- Split payments are stored per sale in `pos_payments`, but the register
  screen currently takes one method per sale.
- Sale numbers are `YYMMDD-0001`, resetting each day.
- Back up by exporting the `nail_booking` database from phpMyAdmin.
