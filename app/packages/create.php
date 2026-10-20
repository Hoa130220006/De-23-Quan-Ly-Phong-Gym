<?php
/**
 * Create New Package
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$errors = [];

$data = [
    'package_code'  => 'GOI' . str_pad((string)rand(10, 99), 2, '0', STR_PAD_LEFT),
    'package_name'  => '',
    'duration_days' => 30,
    'price'         => '',
    'description'   => '',
    'status'        => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['package_code']  = strtoupper(trim($_POST['package_code'] ?? ''));
        $data['package_name']  = trim($_POST['package_name'] ?? '');
        $data['duration_days'] = (int)($_POST['duration_days'] ?? 0);
        $data['price']         = (float)($_POST['price'] ?? 0);
        $data['description']   = trim($_POST['description'] ?? '');
        $data['status']        = trim($_POST['status'] ?? 'active');

        // Validation
        if (empty($data['package_code'])) {
            $errors[] = 'Mã gói tập không được để trống!';
        }
        if (empty($data['package_name'])) {
            $errors[] = 'Tên gói tập không được để trống!';
        }
        if ($data['duration_days'] <= 0) {
            $errors[] = 'Thời hạn gói tập phải lớn hơn 0 ngày!';
        }
        if ($data['price'] < 0) {
            $errors[] = 'Giá tiền gói tập không được âm!';
        }

        // Kiểm tra trùng mã gói
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE package_code = :code");
            $stmt->execute([':code' => $data['package_code']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Mã gói tập '{$data['package_code']}' đã tồn tại trong hệ thống!";
            }
        }

        if (empty($errors)) {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO packages (package_code, package_name, duration_days, price, description, status)
                    VALUES (:package_code, :package_name, :duration_days, :price, :description, :status)
                ");
                $stmtInsert->execute([
                    ':package_code'  => $data['package_code'],
                    ':package_name'  => $data['package_name'],
                    ':duration_days' => $data['duration_days'],
                    ':price'         => $data['price'],
                    ':description'   => $data['description'],
                    ':status'        => $data['status']
                ]);

                recordAuditLog($pdo, 'PACKAGE_CREATE', "Thêm mới gói tập: {$data['package_name']} ({$data['package_code']})");
                writeAppLog('INFO', "Created package: {$data['package_code']}");

                setFlash('success', "Thêm gói tập '{$data['package_name']}' thành công!");
                header('Location: /packages/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', 'Error creating package: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Thêm Gói Tập Mới';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">🏷️ Thông Tin Gói Tập</h3>
        <a href="/packages/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại</a>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-alert error" style="margin-bottom: 20px;">
                <div>
                    <strong>Vui lòng khắc phục các lỗi sau:</strong>
                    <ul style="margin-top: 6px; padding-left: 20px;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= sanitize($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" action="/packages/create.php">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="package_code">Mã Gói Tập <span class="required">*</span></label>
                    <input type="text" id="package_code" name="package_code" class="form-control" 
                           value="<?= sanitize($data['package_code']) ?>" required>
                    <span class="form-help">VD: GOI01, GOI02, VIP12</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="package_name">Tên Gói Tập <span class="required">*</span></label>
                    <input type="text" id="package_name" name="package_name" class="form-control" 
                           value="<?= sanitize($data['package_name']) ?>" placeholder="Gói Tiêu Chuẩn 3 Tháng..." required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="duration_days">Thời Hạn Sử Dụng (Số ngày) <span class="required">*</span></label>
                    <input type="number" id="duration_days" name="duration_days" class="form-control" 
                           value="<?= (int)$data['duration_days'] ?>" min="1" required>
                    <span class="form-help">VD: 30 (1 tháng), 90 (3 tháng), 365 (1 năm)</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="price">Giá Gói (VNĐ) <span class="required">*</span></label>
                    <input type="number" id="price" name="price" class="form-control" 
                           value="<?= $data['price'] ?>" min="0" step="10000" placeholder="1200000" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Áp Dụng <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Đang áp dụng</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Tạm ngưng</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="description">Mô Tả & Quyền Lợi</label>
                    <textarea id="description" name="description" class="form-control" placeholder="Mô tả các tiện ích đi kèm: tủ đồ, khăn tắm, PT kèm..."><?= sanitize($data['description']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Lưu Gói Tập</button>
                <a href="/packages/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
