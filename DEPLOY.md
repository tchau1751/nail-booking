# Deploying to Namecheap (cPanel) Hosting

## 1. Create the MySQL database
1. cPanel → **MySQL Databases**.
2. Create a database (e.g. `diamond`) → cPanel will name it `yourcpaneluser_diamond`.
3. Create a database user + strong password → cPanel will name it `yourcpaneluser_dbuser`.
4. Add that user to the database with **All Privileges**.

Leave the database empty — the installer in the next step creates all the tables for you.

## 2. Upload the files
Upload the entire contents of this project into `public_html` (or a subfolder if this is running under a subdomain/addon domain — adjust paths accordingly). Via **File Manager** (upload a zip and extract) or FTP/SFTP with FileZilla.

Final layout on the server should look like:
```
public_html/
  admin/
  api/
  assets/
  config/
  cron/
  includes/
  sql/            <- keep for reference; blocked from web access by .htaccess
  index.php
  install.php
  kiosk.php
  .htaccess
```

## 3. Run the installer
Visit `https://yourdomain.com/install.php` in your browser. Fill in:
- **Database** — the host (usually `localhost`), name, username, and password from step 1
- **Site URL** — your live domain (auto-detected if left blank)
- **Your admin login** — the username/password you'll use to sign into the dashboard (10+ characters)

Click **Install**. It will connect to your database, create every table, load sample services/staff you can edit afterward, write `config/config.php` for you, and create your admin account — all in one step.

**Then delete `install.php` from your server.** It refuses to run a second time once an admin account exists, but removing the file is the safer habit — don't leave a setup script sitting on a live site.

Log in at `https://yourdomain.com/admin/login.php`.

### Alternative: manual setup (skip the installer)
If you'd rather not let a script write `config/config.php`, or your host restricts file writes:
1. cPanel → **phpMyAdmin** → select your database → Import tab → upload `sql/schema.sql`, then `sql/seed.sql`.
2. Edit `config/config.php` directly (File Manager → Edit) and fill in `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SITE_URL`.
3. Visit `https://yourdomain.com/admin/setup.php` once to create your admin login (same 10+ character rule), then delete that file too.

## 4. Turn on email confirmations (Resend)
1. Create a free account at [resend.com](https://resend.com), verify your sending domain (or use their test domain while developing).
2. Copy your API key into `RESEND_API_KEY` in `config/config.php`.
3. Set `RESEND_FROM_EMAIL` to an address on your verified domain.

## 5. Turn on SMS confirmations + reminders (Twilio)
1. Create a Twilio account, buy a phone number capable of SMS.
2. Copy `Account SID`, `Auth Token`, and the phone number into `config/config.php` (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_NUMBER` — E.164 format, e.g. `+18015551234`).

Both of these are optional at launch — the site works fully without them (bookings still save; notifications are just logged as "skipped" in `notification_log`).

## 6. Schedule the reminder cron job
cPanel → **Cron Jobs** → Add New Cron Job:
- **Common Settings:** Once Per Hour
- **Command:**
  ```
  php /home/yourcpaneluser/public_html/cron/send-reminders.php >> /home/yourcpaneluser/cron-reminders.log 2>&1
  ```
  (Adjust the path to match where you uploaded the project. Ask your host if `php` isn't found — some accounts need the full interpreter path, e.g. `/usr/local/bin/php`.)

This checks hourly for appointments ~24 hours out and texts a reminder once, using `bookings.reminder_sent_at` to avoid duplicates.

## 7. Swap in real photography (when ready)
Every image on the public site and the seeded staff profiles currently points to a stable, free-to-use stock photo URL (Pexels) chosen to match the premium brand look — the salon's own gallery images (hosted on Google Sites/Business Profile) return 403 when hotlinked directly and aren't safe to depend on. To swap in real photos later:
- Service photos: **Admin → Services** → edit the Image URL field (no code changes needed).
- Staff photos: **Admin → Staff** → edit the Photo URL field.
- Hero/about/gallery images: update the URLs directly in `index.php` (search for `pexels.com`).

## 8. Lock down the front-desk kiosk (if you're using it)
`kiosk.php` is a public, unauthenticated page by design — it's meant to run full-screen on a tablet in your lobby so clients can check themselves in by phone number. Since it needs no login, physically lock that tablet to just this page (most tablets have a "kiosk mode" / guided access setting) so people can't browse elsewhere on it.

## 9. Test the golden path
1. Visit the homepage — confirm services load, images render, booking widget works end-to-end (select service → date/time → technician → details → confirmation screen).
2. Log into `/admin/` and confirm the booking shows on the Dashboard, Calendar, and All Bookings.
3. Test **Front Desk Check-In**: add a walk-in, check in an upcoming client, mark one completed.
4. Test `/kiosk.php`: check in as a new client, then check in again with the same phone number and confirm it recognizes you as a returning client.
5. Try the Calendar's **+ New Appointment**, click-an-empty-slot, drag-to-move, and Edit/Delete on an existing appointment.
6. Once Resend/Twilio keys are added, place a real test booking and confirm you receive the email + text.
