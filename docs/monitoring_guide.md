# HƯỚNG DẪN GIÁM SÁT HỆ THỐNG (PROMETHEUS, GRAFANA, LOKI)

Tài liệu hướng dẫn chi tiết cách thức vận hành, kiểm tra chỉ số hiệu năng (Metrics) và truy vết nhật ký tập trung (Logs) trong Hệ thống Quản lý Phòng Gym Titan Fitness.

---

## 1. Kiến trúc Giám sát Đầy Đủ (Observability Stack)

Hệ thống triển khai mô hình quan sát toàn diện theo tiêu chuẩn DevOps:
- **Metrics Scraping:** `Prometheus` tự động kéo số liệu từ các container và Exporter chuyên dụng (`nginx-exporter:9113`, `mysql-exporter:9104`).
- **Dashboard Visualization:** `Grafana` kết nối sẵn 2 data sources là Prometheus và Loki, nạp tự động các bảng điều khiển trực quan khi khởi động.
- **Log Aggregation:** `Promtail` đọc file log Nginx và ứng dụng PHP theo thời gian thực, bóc tách JSON và đẩy về máy chủ `Loki`.

---

## 2. Hướng dẫn Truy Cập và Sử Dụng

### 2.1. Truy cập Prometheus Web UI
- **Địa chỉ:** `http://localhost:9090`
- **Kiểm tra trạng thái các Target giám sát:**
  Truy cập menu **Status -> Targets**.
  - `prometheus (1/1 up)`: Giám sát chính máy chủ Prometheus.
  - `nginx (1/1 up)`: Giám sát Nginx Reverse Proxy qua cổng 9113.
  - `mysql (1/1 up)`: Giám sát Cơ sở dữ liệu MySQL qua cổng 9104.
- **Các câu lệnh PromQL mẫu kiểm tra nhanh:**
  - Kiểm tra trạng thái hoạt động của toàn bộ dịch vụ:
    ```promql
    up
    ```
  - Tần suất truy vấn MySQL trên giây:
    ```promql
    rate(mysql_global_status_queries[1m])
    ```
  - Lưu lượng request tới Nginx theo giây:
    ```promql
    rate(nginx_http_requests_total[1m])
    ```
  - Số lượng kết nối client đang mở tới MySQL:
    ```promql
    mysql_global_status_threads_connected
    ```

---

### 2.2. Truy cập Grafana Dashboards
- **Địa chỉ:** `http://localhost:3000`
- **Tài khoản đăng nhập mặc định:**
  - **Username:** `admin`
  - **Password:** `admin` (hoặc cấu hình trong file `.env`)
- **Vào xem Dashboards tự động nạp (Auto-provisioned):**
  1. Vào menu bên trái chọn **Dashboards**.
  2. Mở thư mục **Titan Fitness Monitoring**, hệ thống đã tạo sẵn 2 Dashboard hoàn chỉnh:
     - **Titan Fitness - System & Services Overview:** Trạng thái UP/DOWN của Nginx, MySQL, Prometheus; biểu đồ lượng request/s Nginx; biểu đồ kết nối active; và bảng xem log stream thời gian thực từ Loki.
     - **Titan Fitness - MySQL Performance Overview:** Thời gian chạy (Uptime), tổng số câu truy vấn, biểu đồ QPS (Queries Per Second), số threads connected/running.

---

### 2.3. Hướng dẫn Khai thác Nhật ký với Loki và Promtail

- Trong Grafana, click vào biểu tượng **Explore** (hình chiếc la bàn ở thanh menu bên trái).
- Tại góc trên bên trái màn hình Explore, chọn Data source là **Loki**.
- Sử dụng ngôn ngữ **LogQL** để truy vấn các dòng nhật ký.

#### 3 Câu truy vấn LogQL bắt buộc theo đề bài:

1. **Truy vấn log truy cập Nginx (Access Logs):**
   ```logql
   {job="nginx", logtype="access"}
   ```
   *Giải thích:* Lọc toàn bộ các yêu cầu HTTP/HTTPS gửi tới Nginx kèm thông tin IP, phương thức GET/POST, URL và thời gian phản hồi.

2. **Truy vấn log lỗi HTTP 5xx (Server Error):**
   ```logql
   {job="nginx", logtype="access"} | json | status >= 500
   ```
   *Hoặc truy vấn theo mẫu regex:*
   ```logql
   {job="nginx"} |~ "HTTP/1\\.[01]\" 5[0-9]{2}"
   ```
   *Giải thích:* Bóc tách trường `status` từ JSON log của Nginx và chỉ giữ lại các phản hồi có mã lỗi máy chủ từ 500 trở lên.

3. **Truy vấn log theo từ khóa hoặc mức độ nghiêm trọng (Severity / Keyword):**
   - Lọc các sự kiện có mức độ `WARNING` hoặc `ERROR` hoặc `CRITICAL`:
     ```logql
     {job="app"} | json | level =~ "WARNING|ERROR|CRITICAL"
     ```
   - Lọc vết hoạt động đăng nhập hệ thống:
     ```logql
     {job="app"} |= "LOGIN_SUCCESS"
     ```
   - Lọc các cảnh báo xung đột lịch hoặc lỗi kết nối:
     ```logql
     {job="app"} |= "conflict"
     ```
