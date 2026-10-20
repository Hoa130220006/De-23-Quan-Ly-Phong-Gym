<?php
/**
 * Create New Member
 */

require_once __DIR__ . '/../includes/auth_check.php';

$pdo = getDB();
$errors = [];

// Giá trị mặc định
$data = [
    'member_code'       => 'HV' . str_pad((string)rand(100, 999), 3, '0', STR_PAD_LEFT),
    'full_name'         => '',
    'dob'               => '1998-01-01',
    'gender'            => 'male',
    'phone'             => '',
    'email'             => '',
    'address'           => '',
    'registration_date' => date('Y-m-d'),
    'status'            => 'active'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Mã bảo mật CSRF không hợp lệ!';
    } else {
        $data['member_code']       = strtoupper(trim($_POST['member_code'] ?? ''));
        $data['full_name']         = trim($_POST['full_name'] ?? '');
        $data['dob']               = trim($_POST['dob'] ?? '');
        $data['gender']            = trim($_POST['gender'] ?? 'male');
        $data['phone']             = trim($_POST['phone'] ?? '');
        $data['email']             = trim($_POST['email'] ?? '');
        $data['address']           = trim($_POST['address'] ?? '');
        $data['registration_date'] = trim($_POST['registration_date'] ?? date('Y-m-d'));
        $data['status']            = trim($_POST['status'] ?? 'active');

        // Validation
        if (empty($data['member_code'])) {
            $errors[] = 'Mã hội viên không được để trống!';
        }
        if (empty($data['full_name'])) {
            $errors[] = 'Họ và tên hội viên không được để trống!';
        }
        if (empty($data['dob'])) {
            $errors[] = 'Ngày sinh không hợp lệ!';
        }
        if (empty($data['phone']) || !preg_match('/^[0-9]{9,11}$/', $data['phone'])) {
            $errors[] = 'Số điện thoại không hợp lệ (yêu cầu từ 9 - 11 chữ số)!';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Định dạng Email không hợp lệ!';
        }

        // Kiểm tra trùng mã hội viên
        if (empty($errors)) {
            $stmtCheckCode = $pdo->prepare("SELECT COUNT(*) FROM members WHERE member_code = :code");
            $stmtCheckCode->execute([':code' => $data['member_code']]);
            if ($stmtCheckCode->fetchColumn() > 0) {
                $errors[] = "Mã hội viên '{$data['member_code']}' đã tồn tại trong hệ thống!";
            }
        }

        // Kiểm tra trùng email
        if (empty($errors)) {
            $stmtCheckEmail = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = :email");
            $stmtCheckEmail->execute([':email' => $data['email']]);
            if ($stmtCheckEmail->fetchColumn() > 0) {
                $errors[] = "Địa chỉ Email '{$data['email']}' đã được đăng ký bởi hội viên khác!";
            }
        }

        // Tiến hành lưu vào CSDL
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO members (member_code, full_name, dob, gender, phone, email, address, registration_date, status)
                    VALUES (:member_code, :full_name, :dob, :gender, :phone, :email, :address, :registration_date, :status)
                ");
                $stmt->execute([
                    ':member_code'       => $data['member_code'],
                    ':full_name'         => $data['full_name'],
                    ':dob'               => $data['dob'],
                    ':gender'            => $data['gender'],
                    ':phone'             => $data['phone'],
                    ':email'             => $data['email'],
                    ':address'           => $data['address'],
                    ':registration_date' => $data['registration_date'],
                    ':status'            => $data['status']
                ]);

                $newId = $pdo->lastInsertId();
                recordAuditLog($pdo, 'MEMBER_CREATE', "Tạo mới hội viên: {$data['full_name']} ({$data['member_code']})");
                writeAppLog('INFO', "Created member ID {$newId}: {$data['member_code']}");

                setFlash('success', "Thêm hội viên '{$data['full_name']}' thành công!");
                header('Location: /members/index.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Lỗi lưu cơ sở dữ liệu: ' . $e->getMessage();
                writeAppLog('ERROR', 'Error creating member: ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Thêm Hội Viên Mới';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width: 850px; margin: 0 auto;">
    <div class="card-header">
        <h3 class="card-title">📝 Thông Tin Đăng Ký Hội Viên</h3>
        <a href="/members/index.php" class="btn btn-secondary btn-sm">⬅️ Quay Lại Danh Sách</a>
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

        <form method="POST" action="/members/create.php">
            <?= csrf_field() ?>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="member_code">Mã Hội Viên <span class="required">*</span></label>
                    <input type="text" id="member_code" name="member_code" class="form-control" 
                           value="<?= sanitize($data['member_code']) ?>" required>
                    <span class="form-help">Mã định danh duy nhất (VD: HV001, HV002)</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="full_name">Họ và Tên <span class="required">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?= sanitize($data['full_name']) ?>" placeholder="Nguyễn Văn A" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dob">Ngày Sinh <span class="required">*</span></label>
                    <input type="date" id="dob" name="dob" class="form-control" 
                           value="<?= sanitize($data['dob']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gender">Giới Tính <span class="required">*</span></label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="male" <?= $data['gender'] === 'male' ? 'selected' : '' ?>>Nam</option>
                        <option value="female" <?= $data['gender'] === 'female' ? 'selected' : '' ?>>Nữ</option>
                        <option value="other" <?= $data['gender'] === 'other' ? 'selected' : '' ?>>Khác</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Số Điện Thoại <span class="required">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control" 
                           value="<?= sanitize($data['phone']) ?>" placeholder="0912345678" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Địa Chỉ Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?= sanitize($data['email']) ?>" placeholder="member@example.com" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="registration_date">Ngày Đăng Ký <span class="required">*</span></label>
                    <input type="date" id="registration_date" name="registration_date" class="form-control" 
                           value="<?= sanitize($data['registration_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">Trạng Thái Hoạt Động <span class="required">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Đang hoạt động</option>
                        <option value="suspended" <?= $data['status'] === 'suspended' ? 'selected' : '' ?>>Tạm hoãn</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Ngưng hoạt động</option>
                    </select>
                </div>

                <div class="form-group full-width">
                    <label class="form-label" for="address">Địa Chỉ Thường Trú</label>
                    <textarea id="address" name="address" class="form-control" placeholder="Số nhà, tên đường, quận/huyện..."><?= sanitize($data['address']) ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">💾 Lưu Hội Viên</button>
                <a href="/members/index.php" class="btn btn-secondary">Hủy Bỏ</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
