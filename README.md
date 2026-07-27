# 💎 Diamond Nail & Spa — XAMPP Installation Guide

## ✅ Requirements
- XAMPP (Apache + MySQL + PHP 8.0+)
- mod_rewrite enabled in Apache

---

## 🚀 Step-by-Step Installation (XAMPP)

### STEP 1 — Copy files to XAMPP
Extract the ZIP and copy the `diamond-nail-spa` folder to:

| OS      | Path |
|---------|------|
| Windows | `C:\xampp\htdocs\diamond-nail-spa\` |
| macOS   | `/Applications/XAMPP/htdocs/diamond-nail-spa/` |
| Linux   | `/opt/lampp/htdocs/diamond-nail-spa/` |

### STEP 2 — Enable mod_rewrite (Windows XAMPP)
1. Open **XAMPP Control Panel**
2. Click **Config** next to Apache → **httpd.conf**
3. Find and uncomment: `LoadModule rewrite_module modules/mod_rewrite.so`
4. Find `<Directory "C:/xampp/htdocs">` and change `AllowOverride None` → `AllowOverride All`
5. Save and **Restart Apache**

### STEP 3 — Create the database
1. Open **http://localhost/phpmyadmin**
2. Click **Import** tab
3. Choose the file `diamond-nail-spa/schema.sql`
4. Click **Go**

**OR** via MySQL command line:
```bash
mysql -u root -p < schema.sql
```

### STEP 4 — Configure database (if needed)
Edit `config/config.php`:
```php
define('DB_HOST', 'localhost');   // always localhost on XAMPP
define('DB_NAME', 'nail_booking');
define('DB_USER', 'root');        // XAMPP default
define('DB_PASS', '');            // XAMPP default = no password
```

> If you changed the folder name from `diamond-nail-spa`, also update:
> ```php
> define('SUBFOLDER', 'your-folder-name');
> ```

### STEP 5 — Open in browser
| Page         | URL |
|--------------|-----|
| Public site  | http://localhost/diamond-nail-spa/ |
| Admin login  | http://localhost/diamond-nail-spa/admin/ |

### STEP 6 — Admin login
```
Email:    admin@diamondnailspa.com
Password: Admin@1234
```
⚠️ Change the password immediately after first login via phpMyAdmin.

---

## 💬 Twilio SMS Setup (optional)
1. Sign up at https://twilio.com
2. Get your **Account SID**, **Auth Token** and a **phone number**
3. In Admin Dashboard → **Settings** → enter your Twilio credentials
4. SMS confirmations will be sent automatically on booking
5. SMS reminders run via a PHP cron job (`cron/send_reminders.php`)

### Cron job (Linux/macOS):
```bash
# Sends reminders every hour
0 * * * * php /opt/lampp/htdocs/diamond-nail-spa/cron/send_reminders.php
```

### Windows XAMPP (Task Scheduler):
- Program: `C:\xampp\php\php.exe`
- Arguments: `C:\xampp\htdocs\diamond-nail-spa\cron\send_reminders.php`
- Schedule: every 1 hour

---

## 📁 File Structure
```
diamond-nail-spa/
├── index.php              ← Public booking website
├── schema.sql             ← Full database (import this first)
├── .htaccess              ← Apache rewrite rules
├── config/
│   └── config.php         ← Database & Twilio settings
├── includes/
│   ├── db.php             ← PDO database helper
│   ├── auth.php           ← Session / login
│   ├── sms.php            ← Twilio SMS + message templates
│   └── slots.php          ← Availability logic
├── api/
│   ├── book.php           ← POST booking + auto SMS confirm
│   ├── services.php       ← Public services list
│   ├── technicians.php    ← Technicians by service
│   ├── slots.php          ← Available time slots
│   ├── calendar.php       ← FullCalendar events
│   ├── appointments.php   ← Appointments CRUD + SMS trigger
│   ├── admin_services.php ← Services CRUD
│   ├── admin_technicians.php
│   ├── admin_hours.php
│   ├── admin_blocked.php
│   ├── admin_settings.php
│   └── admin_sms_log.php
├── admin/
│   ├── index.php          ← Full dashboard (calendar, appointments, etc.)
│   ├── login.php          ← Admin login
│   └── logout.php
├── assets/
│   ├── css/admin.css      ← Dashboard styles
│   ├── css/public.css     ← Public website styles
│   ├── js/admin.js        ← Dashboard SPA logic
│   └── js/public.js       ← Booking flow JS
└── cron/
    └── send_reminders.php ← Hourly SMS reminder job
```

---

## 🛠 Troubleshooting

| Problem | Fix |
|---------|-----|
| 404 on any page | Enable mod_rewrite + AllowOverride All in httpd.conf |
| Database error | Check config/config.php credentials, run schema.sql |
| CSS/JS not loading | Confirm SUBFOLDER matches your folder name exactly |
| SMS not sending | Add Twilio credentials in Admin → Settings |
| Session issues | Restart Apache in XAMPP Control Panel |
