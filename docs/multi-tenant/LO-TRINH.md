# Lộ trình: đưa Diamond Nail POS lên mô hình nhiều salon (Multi-Tenant)

Tài liệu gốc: thư mục **laptrinh hoa hinh** —
*POS WebApp_App Mutiple client*, *Blue Print POSNail26*, *Pos Nail26*, *Giatien*, *Start_v1,2,3,4*.

Làm **từng bước**, mỗi bước là một (vài) commit riêng. Sau mỗi bước ứng dụng vẫn
chạy được, salon hiện tại vẫn bán hàng bình thường.

---

## 1. Hiện trạng (trước khi làm)

```
1 máy XAMPP ── 1 database nail_booking ── 1 salon
                    │
      POS tablet · Kiosk · Queue · Booking web
```

- Không có `tenant_id`: mọi bảng là của **một** salon. Cài dữ liệu salon thứ hai
  vào là thấy lẫn khách, doanh thu, nhân viên của nhau.
- Cài đặt là 1 dòng cố định: `pos_settings WHERE id=1`, `business_settings WHERE id=1`.
- Số hoá đơn `260914-0001`, số điện thoại khách, mã vạch… là **duy nhất toàn hệ thống**
  → salon thứ hai sẽ đụng số của salon thứ nhất.
- Đã có: nhiều vé mở cùng lúc trên một máy, số hoá đơn cấp phát nguyên tử (2 máy POS
  không trùng số), CSRF, khoá PIN sai nhiều lần, quyền owner/manager/staff.
- Lỗ hổng sẽ lộ ra khi có nhiều salon: `managerByPin()` dò PIN của **mọi** manager;
  "Reset everything" xoá `DELETE FROM pos_sales` của **mọi** salon.

## 2. Mô hình đích

```
                    NỀN TẢNG (Super Admin)
                 gói Basic / Pro / Enterprise
                           │
        ┌──────────────────┼──────────────────┐
     Salon A            Salon B            Salon C      ← tenants
        │                  │                  │
  POS#1 POS#2 FrontDesk  POS#1 Kiosk        POS#1        ← thiết bị (devices)
        │
  Owner → Manager → Front Desk → Cashier → Technician    ← phân quyền
        │
  PHP ── xác định tenant từ SESSION ── MySQL: WHERE tenant_id = ?
```

Nguyên tắc cốt lõi (lấy từ Blue Print):

1. **Mọi** bảng nghiệp vụ có `tenant_id`; **mọi** truy vấn lọc `WHERE tenant_id = ?`.
2. **Không bao giờ tin `tenant_id` do trình duyệt gửi lên** — backend lấy tenant từ
   phiên đăng nhập (session), từ thiết bị đã đăng ký, hoặc từ đường dẫn công khai của salon.
3. Salon A **tuyệt đối không** thấy khách, nhân viên, doanh thu, lịch hẹn của Salon B.
4. Nhiều máy của cùng một salon thao tác **đồng thời** trên cùng database.

---

## 3. Các bước

### Bước 0 — Chuẩn bị nhánh làm việc ✅
- Nhánh `claude/muon-pos-multi-user-model-*` (git worktree riêng).
- Đưa vào nhánh các hàm trợ giúp đang nằm ngoài git ở máy chính
  (`hasRole`, `requireRole`, `loginByPin`, `e()`…) — thiếu chúng thì POS không chạy.
- `config/config.local.php` (không commit): trỏ nhánh sang database **thử nghiệm**
  `nail_booking_mt`, không đụng dữ liệu thật.
- Sửa lỗi an toàn: bộ migrate bỏ qua lệnh `USE nail_booking;` trong file `.sql`
  (nếu không sẽ migrate nhầm database thật).

### Bước 1 — Init: `CLAUDE.md` + lộ trình này ✅
- `CLAUDE.md`: bản đồ dự án + các luật dễ phạm (SQL, CSRF, quyền, tiền, multi-tenant).
- `docs/multi-tenant/LO-TRINH.md`: tài liệu này (bản sao để trong thư mục *laptrinh hoa hinh*).

### Bước 2 — Nền tảng tenant ⬜
**Database**
- Bảng mới `plans` (Basic / Professional / Enterprise, giới hạn máy, nhân viên, user)
  và `tenants` (tên, slug, gói, trạng thái: trial / active / past_due / suspended / cancelled).
- Thêm `tenant_id` + khoá ngoại tới `tenants` cho **tất cả** bảng nghiệp vụ;
  dữ liệu cũ gán cho salon số 1.
- Đổi khoá duy nhất thành theo salon: `(tenant_id, sale_no)`, `(tenant_id, phone)`,
  `(tenant_id, barcode)`, `(tenant_id, refund_no)`, bộ đếm số hoá đơn `(tenant_id, name)`…
- Cài đặt salon: 1 dòng `pos_settings` / `business_settings` **mỗi salon**.

**Code**
- `includes/tenant.php`: `tenantId()` — lấy tenant từ session; `tenantUse()` cho trang
  công khai / cron; `unscoped()` cho truy vấn cố ý đi qua nhiều salon.
- Đăng nhập lưu `tenant_id` vào session; salon bị *suspended* không đăng nhập mới được.

**Kiểm tra**: chạy migrate 2 lần liên tiếp không lỗi; salon 1 vẫn bán hàng như cũ.

### Bước 3 — Cô lập dữ liệu ⬜
- Sửa **mọi** câu SQL (≈450 chỗ, ~50 file): SELECT/UPDATE/DELETE lọc `tenant_id`,
  INSERT ghi `tenant_id`.
- Id đến từ trình duyệt (dịch vụ, thợ, khách, hoá đơn, thẻ quà tặng…) phải được tra
  **kèm** `tenant_id` trước khi dùng.
- **Chốt chặn** trong `query()`: câu SQL chạm bảng của salon mà không nhắc tới
  `tenant_id` → báo lỗi ngay, không chạy.
- Sửa 2 lỗ hổng: PIN duyệt giảm giá chỉ tìm manager **của salon đó**;
  "Reset everything" chỉ xoá dữ liệu **của salon đó**.
- Trang booking công khai chọn salon bằng `?salon=<slug>`; cron gửi nhắc lịch lặp qua từng salon.

**Kiểm tra**: script `tests/tenant_isolation.php` tạo Salon B, bán hàng ở cả 2 salon và
khẳng định B không đọc/ghi/xoá được gì của A (và ngược lại).

### Bước 4 — Phân quyền 5 cấp ⬜
`Owner → Manager → Front Desk → Cashier → Technician`

| Màn hình | Tối thiểu |
|---|---|
| Bán hàng (Register) | Cashier |
| Hàng chờ, Kiosk, Khách hàng, Tem | Front Desk |
| Hoá đơn, Báo cáo, Lương, Dịch vụ, Sản phẩm, Cài đặt, Nhân viên, Thiết bị | Manager |
| Xoá dữ liệu thử | Owner |
| Technician | xem hàng chờ, chấm công vào/ra |

- Tài khoản `staff` cũ chuyển thành `front_desk` (giữ nguyên quyền đang có).

### Bước 5 — Thiết bị / trạm POS ⬜
- Bảng `pos_devices`: POS #1, POS #2, Front Desk, Kiosk… mỗi máy một token bí mật
  (lưu dạng hash) trong cookie.
- Manager: **Cài đặt → Thiết bị** → Đăng ký máy này / Đổi tên / Vô hiệu hoá.
- Màn PIN biết máy thuộc salon nào nhờ thiết bị đã đăng ký; máy bị vô hiệu hoá thì
  bị đăng xuất và không đăng nhập PIN được nữa.
- Hoá đơn ghi lại **máy nào** bán → báo cáo theo trạm.
- Số máy tối đa theo gói (Basic 1, Pro 3, Enterprise không giới hạn).

### Bước 6 — Trang Nền tảng (Super Admin) ⬜
- `platform/`: đăng nhập riêng (không phải tài khoản salon).
- Danh sách salon: gói, trạng thái, số máy / nhân viên đang dùng so với giới hạn.
- **Tạo salon mới** kèm tài khoản Owner, cài đặt mặc định, giờ mở cửa, mẫu cam kết.
- Đổi gói, tạm ngưng / kích hoạt lại salon.
- Tạo Super Admin đầu tiên bằng dòng lệnh (không mở qua web).

---

## 4. Để sau (chưa làm trong nhánh này)

| Hạng mục | Ghi chú |
|---|---|
| Nhiều chi nhánh (locations) | thêm `location_id` dưới tenant — gói Enterprise |
| License key + kích hoạt | `licenses`, `license_activations`, kiểm tra ở backend |
| Thu phí subscription | cổng thanh toán, grace period, cảnh báo quá hạn |
| SMS queue 2 chiều | `sms_jobs` + worker + retry, trả lời "C" để xác nhận |
| Offline POS | chỉ tiền mặt, đồng bộ lại khi có mạng |
| Customer Display | màn hình khách xem hàng chờ / khuyến mãi |
| Audit log nâng cao, MFA, backup, HTTPS, deploy production | không chạy production trên XAMPP máy cá nhân |
| Khoá ngoại tổng hợp `(tenant_id, id)` | chặn tham chiếu chéo salon ngay trong database |
