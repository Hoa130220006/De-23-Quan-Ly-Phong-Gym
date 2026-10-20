# KỊCH BẢN PHÁT HIỆN VÀ TRUY VẾT LỖI BẰNG LOG TRONG GRAFANA & LOKI

Tài liệu này mô tả kịch bản thực tế khi quản trị viên hệ thống (DevOps / SysAdmin) sử dụng bộ công cụ giám sát để phát hiện sự cố, truy vết nguyên nhân gốc rễ (Root Cause Analysis - RCA) và đưa ra biện pháp khắc phục.

---

## 1. Tình Huống Sự Cố (Incident Scenario)

- **Mô tả tình huống:** Vào lúc 10:15 sáng, hệ thống ghi nhận có dấu hiệu bất thường: Người dùng báo cáo một số trang bị tải chậm hoặc thông báo lỗi, đồng thời xuất hiện nhiều cảnh báo đăng nhập thất bại liên tiếp trên hệ thống.
- **Mục tiêu của Quản trị viên:**
  1. Xác định dịch vụ nào đang gặp vấn đề (Nginx, PHP App hay MySQL).
  2. Truy vết các request gây lỗi bằng LogQL trong Grafana.
  3. Tìm ra nguyên nhân gốc rễ và xác thực thời gian, địa chỉ IP, người dùng liên quan.
  4. Thực hiện khắc phục và xác nhận hệ thống phục hồi bình thường.

---

## 2. Quy Trình 4 Bước Truy Vết & Xử Lý Sự Cố

### Bước 1: Quan sát Tổng quan trên Grafana Dashboard
1. Truy cập Grafana: `http://localhost:3000` -> Mở Dashboard **Titan Fitness - System & Services Overview**.
2. Quan sát hàng **Trạng Thái Dịch Vụ**:
   - Nếu Panel `MySQL 8.0 Database` hiển thị màu đỏ `DOWN`, nguyên nhân là container MySQL bị dừng hoặc treo kết nối.
   - Nếu panel `Nginx Reverse Proxy` và `MySQL` đều `UP`, chuyển sang quan sát bảng **Nginx Request Rate** và **Unified Logs Stream**.
3. Tại bảng nhật ký stream, nhận thấy xuất hiện nhiều dòng log màu vàng có nhãn `level="WARNING"` từ dịch vụ `app`.

---

### Bước 2: Sử dụng Grafana Explore & LogQL để Khoanh Vùng

1. Vào menu **Explore** trong Grafana, chọn Data source **Loki**.
2. **Truy vấn 1: Tìm kiếm các dòng log có mức độ cảnh báo (WARNING / ERROR):**
   ```logql
   {job="app"} | json | level =~ "WARNING|ERROR|CRITICAL"
   ```
   **Kết quả phát hiện:**
   ```json
   {
     "timestamp": "2026-10-20T10:15:32+07:00",
     "level": "WARNING",
     "message": "Failed login attempt for username 'admin'",
     "ip": "172.28.0.1",
     "user_agent": "Mozilla/5.0 ... python-requests"
   }
   ```
   Hệ thống ghi nhận hơn 20 lần thử mật khẩu không chính xác liên tục đối với tài khoản `admin` từ một IP nội bộ.

3. **Truy vấn 2: Truy vết tất cả các yêu cầu HTTP từ địa chỉ IP nghi vấn trên Nginx:**
   ```logql
   {job="nginx", logtype="access"} | json | remote_addr = "172.28.0.1"
   ```
   **Kết quả phân tích:**
   Phát hiện các request POST liên tục tới `/login.php` với tần suất 2 request/giây, chứng tỏ đang có hành vi dò quét mật khẩu tự động (Brute-force attack).

4. **Truy vấn 3: Kiểm tra xem có request nào phát sinh lỗi 5xx không:**
   ```logql
   {job="nginx", logtype="access"} | json | status >= 500
   ```
   Kết quả trả về rỗng (0 logs), chứng tỏ máy chủ Nginx và PHP vẫn đứng vững, cơ chế bắt lỗi và kiểm tra Prepared Statements của backend không bị crash.

---

### Bước 3: Xác Định Nguyên Nhân Gốc Rễ (Root Cause)
- Có đối tượng thực hiện gửi request tự động dò mật khẩu quản trị viên `admin` qua endpoint `/login.php`.
- Nhờ cơ chế băm mật khẩu `password_hash()` với độ phức tạp BCRYPT và mã token chống CSRF, đối tượng không thể đăng nhập thành công.
- Tuy nhiên việc gửi request liên tục làm tiêu tốn tài nguyên xử lý CPU của PHP-FPM.

---

### Bước 4: Biện Pháp Khắc Phục & Tăng Cường (Remediation)
1. **Chặn IP vi phạm:** Thêm cấu hình chặn IP tại Nginx (`docker/nginx/conf.d/default.conf`):
   ```nginx
   deny 172.28.0.1;
   ```
2. **Giới hạn tần suất request (Rate Limiting) trên Nginx:**
   Thêm cấu hình `limit_req_zone` trong `nginx.conf`:
   ```nginx
   limit_req_zone $binary_remote_addr zone=login_limit:10m rate=5r/m;
   ```
   Và áp dụng cho location đăng nhập:
   ```nginx
   location = /login.php {
       limit_req zone=login_limit burst=3 nodelay;
       # ...
   }
   ```
3. **Kiểm tra phục hồi:**
   - Chạy lại truy vấn LogQL:
     ```logql
     {job="app"} |= "LOGIN_SUCCESS"
     ```
   - Nhận thấy quản trị viên đăng nhập lại hợp lệ từ máy chủ, luồng log trở lại trạng thái xanh bình thường.
   - Bảng điều khiển Grafana trở lại trạng thái ổn định.
