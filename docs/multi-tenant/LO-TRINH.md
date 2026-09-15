# Lộ trình: đưa Diamond Nail POS lên mô hình nhiều salon (Multi-Tenant)

Tài liệu gốc: thư mục **laptrinh hoa hinh** —
*POS WebApp_App Mutiple client*, *Blue Print POSNail26*, *Pos Nail26*, *Giatien*, *Start_v1,2,3,4*.

Làm **từng bước**, mỗi bước là một commit riêng trên nhánh `claude/muon-pos-multi-user-model-*`.
Sau mỗi bước ứng dụng vẫn chạy được và salon hiện tại vẫn bán hàng bình thường.

| Bước | Commit | Nội dung |
|---|---|---|
| 0 | *Track the helpers the till already calls…* | Chuẩn bị nhánh, sửa lỗi migrate nhầm database |
| 1 | *Map the project for AI helpers…* | `CLAUDE.md` + tài liệu này |
| 2 | *Give every salon its own rows…* | Bảng `tenants`, `plans`, cột `tenant_id` khắp nơi |
| 3 | *Keep every salon's data to itself* | Mọi câu SQL lọc theo salon + chốt chặn + test |
| 4 | *Give the salon five roles…* | Owner → Manager → Front Desk → Cashier → Technician |
| 5 | *Register each till and tablet…* | Thiết bị / trạm POS |
| 6 | *Run many salons from one platform page* | Super Admin, gói Basic/Pro/Enterprise, giới hạn |
| 7 | *Bring in the day calendar…* + *Keep the day calendar and birthday texts to one salon* | Gộp 3 commit từ GitHub; lịch kéo-thả, đổi lịch hẹn, tin nhắn sinh nhật chỉ trong salon |

---

## 1. Hiện trạng (trước khi làm)

```
1 máy XAMPP ── 1 database nail_booking ── 1 salon
                    │
      POS tablet · Kiosk · Queue · Booking web
```

- Không có `tenant_id`: mọi bảng là của **một** salon.
- Cài đặt là 1 dòng cố định (`WHERE id=1`); số hoá đơn, số điện thoại khách, mã vạch
  là **duy nhất toàn hệ thống**.
- Lỗ hổng sẽ lộ ra khi có salon thứ hai: PIN duyệt giảm giá dò **mọi** manager;
  "Reset everything" xoá dữ liệu của **mọi** salon; vé đang mở theo phiên trình duyệt
  sang salon khác.

## 2. Mô hình đích (đã xây)

```
                    NỀN TẢNG  /platform/  (Super Admin)
                 gói Basic / Professional / Enterprise
                           │
        ┌──────────────────┼──────────────────┐
     Salon A            Salon B            Salon C      ← tenants
        │                  │                  │
  POS#1 POS#2 FrontDesk  POS#1 Kiosk        POS#1        ← pos_devices
        │
  Owner → Manager → Front Desk → Cashier → Technician    ← ROLES
        │
  PHP ── tenantId() từ SESSION / thiết bị ── MySQL: WHERE tenant_id = ?
```

Nguyên tắc cốt lõi:

1. **Mọi** bảng nghiệp vụ có `tenant_id`; **mọi** truy vấn lọc `WHERE tenant_id = ?`.
2. **Không bao giờ tin `tenant_id` do trình duyệt gửi lên.** Salon được xác định theo thứ tự:
   trang công khai tự chọn (`tenantUse`) → phiên đăng nhập → cookie thiết bị đã đăng ký →
   salon duy nhất trên máy chủ.
3. Id từ trình duyệt (dịch vụ, thợ, khách, hoá đơn…) phải được kiểm tra thuộc salon
   (`tenantOwns()` / `ownedId()`) trước khi dùng.
4. Truy vấn cố ý đi qua nhiều salon phải nằm trong `unscoped(...)` để dễ thấy khi review.

---

## 3. Các bước

### Bước 0 — Chuẩn bị nhánh ✅
- Đưa vào git các hàm `hasRole`, `requireRole`, `loginByPin`, `e()`… đang nằm ngoài git ở máy chính.
- `config/config.local.php` (không commit) cho phép trỏ sang database thử nghiệm.
- Sửa lỗi: bộ migrate không còn làm theo `USE nail_booking;` trong file `.sql`.

### Bước 1 — Init ✅
- `CLAUDE.md`: bản đồ dự án và các luật dễ phạm.
- Tài liệu lộ trình này (bản sao trong thư mục *laptrinh hoa hinh*).

### Bước 2 — Nền tảng tenant ✅
- Bảng `plans` (Basic $79 · Professional $149 · Enterprise $299) và `tenants`
  (slug, gói, trạng thái trial / active / past_due / suspended / cancelled).
- `tenant_id` + khoá ngoại cho 33 bảng; dữ liệu cũ thành **salon số 1**.
- Khoá duy nhất theo salon: số hoá đơn, số hoàn tiền, SĐT khách, mã vạch, mẫu cam kết,
  hãng sơn, ngày nghỉ; bộ đếm số hoá đơn theo `(tenant_id, name)`.
- Mỗi salon một dòng cài đặt; `ensureTenantDefaults()` tạo giờ mở cửa, mẫu cam kết, hãng sơn.
- **Tự migrate** ở request đầu tiên sau khi nâng cấp (có khoá `GET_LOCK`), chạy lại bao nhiêu lần cũng được.
- Phiên đăng nhập cũ (trước nâng cấp) được gán salon của người dùng — không ai bị đăng xuất giữa ca.

### Bước 3 — Cô lập dữ liệu ✅
- Sửa ~450 câu SQL trong POS, trang booking, API admin, cron nhắc lịch.
- **Chốt chặn** `TENANT_GUARD` trong `query()`: câu SQL chạm bảng salon mà không có
  `tenant_id` bị từ chối ngay. `tenant_id` không còn giá trị mặc định → quên là lỗi, không lẫn dữ liệu.
- Đóng 3 lỗ hổng: PIN manager chỉ trong salon; "Reset everything" chỉ salon đang đăng nhập;
  vé đang mở gắn với salon.
- Trang booking công khai: `/?salon=<slug>`; link đánh giá tự biết salon; cron lặp từng salon.
- **Kiểm tra**
  - `tests/tenant_lint.php` — quét mã nguồn tìm SQL chưa lọc salon.
  - `tests/tenant_isolation.php --test-database` — tạo Salon B và thử 28 cách với sang Salon A.

### Bước 4 — Phân quyền 5 cấp ✅

| Màn hình | Tối thiểu |
|---|---|
| Hàng chờ (xem), tự chấm công | Technician |
| Bán hàng, hoá đơn in | Cashier |
| Khách hàng, tem, cam kết, kiosk, lịch hẹn | Front Desk |
| Doanh thu, hoàn tiền, báo cáo, lương, cài đặt, nhân viên, thiết bị, trang admin | Manager |
| Xoá dữ liệu thử, cài đặt database | Owner |

- Tài khoản `staff` cũ → `front_desk` (giữ nguyên quyền đang có).
- Tài khoản technician được liên kết với tên trên bảng lượt; chỉ tự chấm công cho mình.
- Trang nào quên khai báo quyền sẽ mặc định là của **Manager** (khoá nhầm, không mở nhầm).

### Bước 5 — Thiết bị / trạm POS ✅
- **Thiết bị** (Manager): đăng ký trình duyệt đang cầm là "POS #1", "Front desk iPad", "Kiosk"…
  Chỉ lưu **hash** của token; token nằm trong cookie của máy đó.
- Máy đã đăng ký tự biết salon → màn PIN hoạt động kể cả khi máy chủ có nhiều salon.
- Salon đã đăng ký thiết bị → **PIN chỉ dùng được trên máy đã đăng ký** (đăng nhập email vẫn dùng mọi nơi).
- Mỗi hoá đơn ghi **máy nào bán**; Báo cáo có mục *By station*; thanh trên cùng hiện tên máy.
- **Tắt** một máy (bị mất) → người đang dùng bị đăng xuất ở lần chạm kế tiếp.

### Bước 6 — Nền tảng (Super Admin) và gói ✅
- `/platform/`: đăng nhập riêng (bảng `platform_admins`, cookie riêng), khoá 15 phút sau 10 lần sai.
- Danh sách salon: gói, trạng thái, **đang dùng / giới hạn** (máy, thợ, tài khoản), số hoá đơn 30 ngày.
- **Mở salon mới** kèm Owner, cài đặt, giờ mở cửa, mẫu cam kết — tất cả hoặc không gì cả.
- Đổi gói; tạm ngưng / kích hoạt lại (tạm ngưng chặn đăng nhập mới, không xoá gì).
- Giới hạn gói được kiểm tra khi vượt: đăng ký máy, thêm thợ, tạo/bật tài khoản.
- Manager thấy thông báo *past due* / *trial*.
- Nhật ký `platform_audit` cho mọi thay đổi.
- Super Admin đầu tiên chỉ tạo được bằng dòng lệnh.

### Bước 7 — Gộp 3 commit từ GitHub, giữ trong salon ✅
- Gộp `origin/master`: lịch theo ngày kéo-thả, `api/reschedule.php`, sửa lịch hẹn, `technician_id` trên lịch.
  Bỏ file rỗng `studio` (trùng tên với thư mục `studio/`).
- Lịch theo ngày chuyển vào `admin/calendar-standalone.php` (đường dẫn `../includes/` mới đúng).
  Chỉ Front Desk trở lên; chỉ thợ, dịch vụ, lịch hẹn của salon đang đăng nhập.
- `api/update-appointment.php` → `api/updateappointment.php`: đúng tên trang lịch gọi và tên file trên máy chủ.
- Đổi lịch dùng chung `includes/bookings.php`: lịch hẹn và thợ phải thuộc salon. Trùng giờ chỉ tính
  cùng thợ (hoặc lịch chưa gán thợ) như trang đặt lịch — không chặn cả salon vì một khách khác cùng giờ.
- Sửa 2 lỗ hổng trong trang tải lên: tên khách (gõ từ trang đặt lịch) được in dạng chữ, không còn chạy
  được mã HTML/JS; `?date=` được kiểm tra trước khi in ra trang.
- Tin nhắn sinh nhật `cron/send_birthday_sms.php` (trước chỉ nằm ở máy chính, chưa có trong git):
  chạy lần lượt từng salon, chỉ từ dòng lệnh (`--dry-run` để thử). Manager xem trước danh sách hôm nay
  trong **Settings → Automatic birthday texts** — không gửi gì.
- Test cô lập thêm 8 kiểm tra (đổi lịch, thợ, trùng giờ, sinh nhật): 36 kiểm tra.

---

## 4. Chạy và kiểm tra

```bash
# Database thử nghiệm (không bao giờ thử trên nail_booking thật)
mysqldump -u root nail_booking | mysql -u root nail_booking_mt

# config/config.local.php
#   define('DB_NAME', 'nail_booking_mt');

php tests/tenant_lint.php
php tests/tenant_isolation.php --test-database
php tools/create-platform-admin.php you@example.com "Your Name"
```

- Trang booking của từng salon: `http://<máy>/nail-booking/?salon=<slug>`
- Nền tảng: `http://<máy>/nail-booking/platform/`

## 5. Nâng cấp máy thật (posbooking.online / máy salon)

1. **Sao lưu database** (phpMyAdmin → Export) — bắt buộc.
2. Chép mã mới lên máy chủ.
3. Mở bất kỳ trang nào: database tự nâng cấp lên phiên bản tenancy mới nhất
   (ghi lại trong PHP error log). Salon hiện có trở thành salon số 1.
4. Đăng nhập Owner → **Devices**: đăng ký từng máy POS / iPad / kiosk.
5. Trên máy chủ: `php tools/create-platform-admin.php …` để tạo Super Admin.
6. Đổi Twilio auth token (token cũ đang nằm trong lịch sử git).

---

## 6. Để sau (chưa làm trong nhánh này)

| Hạng mục | Ghi chú |
|---|---|
| Nhiều chi nhánh (locations) | thêm `location_id` dưới tenant — gói Enterprise |
| License key + kích hoạt | `licenses`, `license_activations`, kiểm tra ở backend |
| Thu phí subscription tự động | cổng thanh toán, grace period, tự chuyển past_due → suspended |
| SMS queue 2 chiều | `sms_jobs` + worker + retry, trả lời "C" để xác nhận |
| Offline POS | chỉ tiền mặt, đồng bộ lại khi có mạng |
| Customer Display | màn hình khách xem hàng chờ / khuyến mãi |
| Nhật ký hoạt động trong từng salon, MFA | audit log cấp salon, xác thực 2 lớp cho Owner |
| Khoá ngoại tổng hợp `(tenant_id, id)` | chặn tham chiếu chéo salon ngay trong database |
| Production | HTTPS, backup tự động, giám sát — không chạy production trên XAMPP máy cá nhân |
