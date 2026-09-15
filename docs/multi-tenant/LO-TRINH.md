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
| 8 | *Put stamp cards, points and gift cards under one Rewards tab* | Tab Rewards; nút Rewards trên Register có thẻ tem của khách |
| 9 | *Put the back office behind an admin password* | Admin password (bắt đầu 1111), mở 15 phút, sai 5 lần khoá 15 phút |
| 10 | *Name the top tabs the way nail salons already know them* | SIGN-IN LIST · CHECKOUT · GIFT-CARD · APPOINTMENT · CUSTOMER · ADMIN; "Hi" + chức vụ |
| 11 | *Split one ticket across card, cash, Zelle and more* | Màn thanh toán nhiều hình thức + bàn phím số; Payment methods trong Settings |
| 12 | *Close the day on one page* | Báo cáo End of day: phiếu theo từng thợ, tiền thợ, tổng theo hình thức, tiền két |

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

### Bước 8 — Rewards: thẻ tem, điểm, gift card một chỗ ✅
- Thanh menu: tab 🎫 Stamps và 🎁 Gift cards gộp thành **🎁 Rewards** (Front Desk trở lên), có tab con
  Stamp cards · Points · Gift cards (Manager) · Settings (Manager). Link cũ `stamps.php`, `giftcards.php` vẫn chạy.
- Trang **Points** mới (`pos/points.php`): tổng điểm khách đang giữ và giá trị bằng tiền, khách nhiều điểm
  nhất, điểm cộng/đổi 30 ngày, lịch sử gần đây.
- Màn **Register**: nút ⭐ Points và 🎁 Gift card gộp thành một nút **🎁 Rewards** (rộng 2 ô), mở hộp 3 tab:
  - 🎫 **Stamp card** — thẻ tem của khách, phần thưởng đang chờ, nút **Hand over reward**
    (Front Desk trở lên; Cashier chỉ xem). Nút Rewards hiện 🎉 khi khách có thẻ đầy.
  - ⭐ **Points** và 🎁 **Gift card** — như trước.
- Dòng khách trên phiếu hiện `⭐ 250 pts · 🎫 3/10`.
- Trao thưởng tem chỉ ghi nhận, **không tự trừ tiền** trên phiếu — giảm giá vẫn cần Manager duyệt như cũ.
- Không đổi database; tem, điểm, gift card đang có giữ nguyên.
- Sửa lỗ hổng: tên khách trong ô tìm khách trên Register được in dạng chữ (tên do khách gõ ở kiosk).

### Bước 9 — Admin password cho khu quản trị ✅
- Tab **☰ More** đổi thành **🔒 Admin**. Các màn quản trị (Services, Products, Staff, Devices, Settings, Sales,
  Refund, Reports, Payroll, Expenses, Marketing, Feedback, Designs, Reset), trang admin đặt lịch cũ và SMS log
  cần thêm **Admin password** — là lớp khoá thêm, không thay phân quyền.
- Mỗi salon một mật khẩu, lưu dạng mã hoá (`pos_settings.admin_pin_hash`). Bắt đầu là **1111**; Settings báo đỏ
  cho tới khi đổi. Mật khẩu mới 4–8 số, phải nhập đúng mật khẩu cũ, không được đặt lại 1111.
- Mở khoá **15 phút** tính từ màn admin cuối cùng; nút **🔒 Lock the admin screens** khoá ngay; đăng nhập lại
  cũng khoá. Mở ở salon này không mở sang salon khác.
- Sai **5 lần** liền → khoá 15 phút, kể cả khi nhập đúng.
- Quên mật khẩu: **Owner** nhập mật khẩu đăng nhập của mình để đưa về 1111, rồi đổi mới ngay.
- Database lên phiên bản tenancy **6** (tự nâng cấp ở request đầu tiên).
- Test cô lập thêm 4 kiểm tra (mật khẩu salon B không mở salon A): 40 kiểm tra.

### Bước 10 — Thanh tab trên cùng quen thuộc với salon ✅
- Tab mới (chữ in hoa, mỗi tab một ô): **SIGN-IN LIST** (hàng chờ) · **CHECKOUT** (Register) · **GIFT-CARD**
  (Manager vào Gift cards, Front Desk vào thẻ tem) · **APPOINTMENT** (lịch theo ngày) · **CUSTOMER** · **🔒 ADMIN**.
- Tab ADMIN gom các màn quản trị; **Sales** và **SMS log** chuyển vào đây.
- Góc phải: **Hi** + chức vụ (Owner, Manager…), **không hiện tên**; nút **Exit** để đăng xuất.
- Màn Checkout giữ nguyên: ảnh dịch vụ, danh mục ngang.
- `pos/appointments.php` đặt lịch theo ngày ngay dưới thanh tab, để lễ tân chuyển qua lại giữa các tab.
- Màn hình hẹp (tablet): giữ tên tab, bỏ icon; màn rất hẹp (điện thoại): chỉ còn icon.

### Bước 11 — Thanh toán chia nhiều hình thức ✅
- Màn **Take payment** mới: TOTAL DUE, danh sách hình thức (Credit card, Cash, Zelle, Venmo, Check, Gift cert),
  bàn phím số bên cạnh (C, ⌫, **Rest of it** = điền phần còn thiếu). Chạm một dòng để nhập tiền cho dòng đó.
- Một phiếu chia được nhiều hình thức, ví dụ Zelle $20 + Cash phần còn lại. Chỉ **Cash** được trả dư (thối tiền);
  thẻ, Zelle… không được vượt số tiền phải trả.
- Ô **Reference** cho từng hình thức (4 số cuối thẻ, mã Zelle…). Hai nút **💾 Save** và **🖨 Save & print**.
- **Settings → 💳 Payment methods**: bật/tắt từng hình thức cho salon (mặc định: Credit card, Cash, Zelle, Venmo, Gift cert).
- Server kiểm tra lại: hình thức đã tắt, số âm, hình thức không phải tiền mặt vượt số phải trả đều bị từ chối.
  Gift card có mã vẫn dùng ở **Rewards → Gift card** (có kiểm tra số dư), không gõ tay ở màn thanh toán.
- Hoá đơn, danh sách Sales và báo cáo hiện tên hình thức (Zelle, Venmo…).
- Database lên phiên bản tenancy **7**: `pos_payments.method` thành chữ, thêm `pos_settings.payment_methods`.

### Bước 12 — Báo cáo End of day ✅
- **ADMIN → 🧮 End of day** (có cả nút trong Reports): chọn ngày, ‹ › sang ngày trước/sau, **In** và **CSV**.
- Thanh tổng: Service sales, Product sales, Gift card sales, Discounts, Tax, Tips, **Total collected**.
- Thanh theo hình thức: Credit card, Cash, Zelle, Venmo… (hiện cả khi $0), Points / Gift card nếu có, Change given,
  Refunds, và **Cash in the drawer** — tiền mặt phải có trong két.
- Mỗi thợ một bảng: Ticket, dịch vụ/sản phẩm, Price, Discount, (Refunded), (Supply fee), Tech earns, Tip (tip tiền mặt
  có ghi "cash"); dòng **payout** = commission − supply fee + wage + card tips, ghi riêng cash tips đã đưa tận tay.
- Tiền thợ tính **đúng công thức Payroll**, tiền két **đúng công thức Reports** — test so khớp từng cent với hai trang đó.
- Báo đỏ nếu tổng các khoản thu trừ tiền thối không bằng tổng các phiếu. Phiếu void không tính (có ghi số lượng).

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
